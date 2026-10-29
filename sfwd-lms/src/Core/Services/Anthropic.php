<?php
/**
 * Anthropic (Claude) AI provider service.
 *
 * @since 5.1.0
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Services;

use Exception;
use LearnDash\Core\Modules\AI\Chat_Message;
use LearnDash\Core\Modules\AI\ChatGPT_Summarizer;

/**
 * Anthropic Claude AI provider.
 *
 * Implements the messages API at https://api.anthropic.com/v1/messages.
 *
 * Key differences from the OpenAI format:
 * - The system prompt is a top-level request parameter, not a message with role 'system'.
 * - The API key is passed in the 'x-api-key' header, not 'Authorization: Bearer'.
 * - Response text is at content[0].text, not choices[0].message.content.
 * - First message in the messages array must use role 'user', and roles must strictly alternate.
 *
 * @since 5.1.0
 */
class Anthropic implements AI_Provider {
	/**
	 * Default model.
	 *
	 * Starting with the 4.x generation, Anthropic uses dateless model IDs as
	 * pinned snapshots (the dateless string IS the canonical ID, there are no
	 * separate `-latest` aliases for these). claude-haiku-4-5 is the current
	 * fast/cost-effective Haiku and a sensible default for interactive chat.
	 *
	 * To update the model, change the value in
	 * LearnDash → Settings → Advanced → AI Integrations → Anthropic Model.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'claude-haiku-4-5';

	/**
	 * Anthropic API version header value.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private const API_VERSION = '2023-06-01';

	/**
	 * Maximum tokens to generate in a single response.
	 *
	 * @since 5.1.0
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 4096;

	/**
	 * Context window for the default model. Current Claude models support
	 * 200k+ tokens; we use a conservative 32k to keep summarisation costs low.
	 *
	 * @since 5.1.0
	 *
	 * @var int
	 */
	private const MODEL_MAX_CONTEXT_WINDOW_TOKENS = 32000;

	/**
	 * Anthropic API endpoint.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $api_url = 'https://api.anthropic.com/v1/messages';

	/**
	 * Anthropic API key.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model identifier.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * System instruction / persona prompt.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $instruction = '';

	/**
	 * Conversation message history (excludes system prompt).
	 *
	 * @since 5.1.0
	 *
	 * @var Chat_Message[]
	 */
	private array $messages = [];

	/**
	 * Constructor.
	 *
	 * @since 5.1.0
	 *
	 * @param string $api_key Anthropic API key.
	 * @param string $model   Model identifier. Defaults to claude-haiku-4-5.
	 */
	public function __construct( string $api_key, string $model = self::DEFAULT_MODEL ) {
		$this->api_key = $api_key;
		$this->model   = ! empty( $model ) ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * {@inheritdoc}
	 */
	public function send_command( string $command, array $args = [] ): string {
		$this->construct_messages_from_command( $command );

		/**
		 * Filters the request data sent to the Anthropic API.
		 *
		 * @since 5.1.0
		 *
		 * @param array<string, mixed> $data    Request body.
		 * @param string               $command User command.
		 * @param array<string, mixed> $args    Additional args.
		 *
		 * @return array<string, mixed>
		 */
		$data = apply_filters(
			'learndash_service_anthropic_send_command_data',
			[
				'model'      => $this->model,
				'system'     => $this->get_instruction(),
				'messages'   => $this->map_messages_to_request_format(),
				'max_tokens' => self::MAX_TOKENS,
			],
			$command,
			$args
		);

		$response      = $this->request( 'POST', $data );
		$response_data = json_decode( $response, true );

		if (
			! is_array( $response_data )
			|| ! isset( $response_data['content'][0]['text'] )
			|| ! is_string( $response_data['content'][0]['text'] )
		) {
			// Log the detail for admins; the student only sees a generic message.
			error_log( 'LearnDash AI (Anthropic): unexpected response shape: ' . substr( $response, 0, 2000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			throw new Exception(
				esc_html__( 'The AI service returned an unexpected response. Please try again.', 'learndash' ),
				502
			);
		}

		return $response_data['content'][0]['text'];
	}

	/**
	 * {@inheritdoc}
	 */
	public function set_instruction( string $instruction ): void {
		$this->instruction = $instruction;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_instruction(): string {
		return ! empty( $this->instruction )
			? $this->instruction
			: __( 'You are a helpful assistant.', 'learndash' );
	}

	/**
	 * {@inheritdoc}
	 *
	 * Note: Anthropic does not use a system-role message in the messages array,
	 * so we store messages without the system entry (it is sent separately).
	 */
	public function set_messages( array $messages ): void {
		// Filter out any system messages — the system prompt is sent separately.
		$this->messages = array_values(
			array_filter(
				$messages,
				fn( Chat_Message $m ) => $m->role !== AI_Provider::ROLE_SYSTEM
			)
		);
	}

	/**
	 * {@inheritdoc}
	 *
	 * Returns messages without a leading system message (Anthropic sends it
	 * separately), prefixed with a synthetic system Chat_Message so that
	 * Chat_Session::map_messages() can filter it out consistently.
	 */
	public function get_messages(): array {
		$system = [ new Chat_Message( $this->get_instruction(), AI_Provider::ROLE_SYSTEM ) ];
		return array_merge( $system, $this->messages );
	}

	/**
	 * {@inheritdoc}
	 */
	public function add_message( Chat_Message $message ): void {
		if ( $message->role === AI_Provider::ROLE_SYSTEM ) {
			return; // system is handled separately.
		}

		$this->messages[] = $message;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_max_context_window_tokens(): int {
		/**
		 * Filters the maximum context window tokens for the Anthropic model.
		 *
		 * @since 5.1.0
		 *
		 * @param int $tokens Maximum context window tokens.
		 *
		 * @return int
		 */
		return apply_filters(
			'learndash_service_anthropic_model_max_context_window_tokens',
			self::MODEL_MAX_CONTEXT_WINDOW_TOKENS
		);
	}

	/**
	 * Sends an HTTP request to the Anthropic API.
	 *
	 * @since 5.1.0
	 *
	 * @throws Exception On API or HTTP error.
	 *
	 * @param string               $method  HTTP method.
	 * @param array<string, mixed> $body    Request body.
	 *
	 * @return string Raw response body.
	 */
	private function request( string $method, array $body ): string {
		$args = [
			'method'  => $method,
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type'      => 'application/json',
				'x-api-key'         => $this->api_key,
				'anthropic-version' => self::API_VERSION,
			],
			'timeout' => 2 * MINUTE_IN_SECONDS,
		];

		$response = wp_remote_request( $this->api_url, $args );
		$code     = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) || $code !== 200 ) {
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();
			} else {
				$decoded       = json_decode( $raw_body, true );
				$error_message = is_array( $decoded ) && isset( $decoded['error']['message'] )
					? $decoded['error']['message']
					: sprintf( __( 'HTTP %d', 'learndash' ), $code );
			}

			// Log the detail for admins; the student only sees a generic message.
			error_log( sprintf( 'LearnDash AI (Anthropic): HTTP %s: %s', is_int( $code ) ? $code : 'n/a', $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			throw new Exception(
				esc_html__( 'The AI service is currently unavailable. Please try again later.', 'learndash' ),
				is_int( $code ) && $code > 0 ? $code : 500
			);
		}

		return $raw_body;
	}

	/**
	 * Prunes/summarises messages if needed then appends the new user command.
	 *
	 * @since 5.1.0
	 *
	 * @param string $command User command.
	 *
	 * @return void
	 */
	private function construct_messages_from_command( string $command ): void {
		$summarizer = new ChatGPT_Summarizer( $this->get_messages() );

		if ( $summarizer->messages_need_summarization() ) {
			$summary = $summarizer->summarize_messages();

			if ( ! empty( $summary ) ) {
				$this->set_messages(
					array_merge(
						[ new Chat_Message( $summary, AI_Provider::ROLE_USER ) ],
						$summarizer->get_preserved_messages()
					)
				);
			}
		}

		$this->add_message( new Chat_Message( $command, AI_Provider::ROLE_USER ) );
	}

	/**
	 * Maps internal Chat_Message objects to the Anthropic messages array format.
	 *
	 * System messages are excluded (sent as the top-level 'system' param).
	 * Leading assistant messages (e.g. initial Virtual Instructor greeting) are skipped
	 * because Anthropic requires the first message to use the 'user' role, and consecutive
	 * same-role messages (e.g. after conversation summarisation) are merged.
	 *
	 * @since 5.1.0
	 *
	 * @return array<array{role: string, content: string}>
	 */
	private function map_messages_to_request_format(): array {
		$output = [];

		foreach ( $this->messages as $message ) {
			if ( $message->role === AI_Provider::ROLE_SYSTEM ) {
				continue;
			}

			// Anthropic requires the first message to be 'user'. Skip leading 'assistant' greeting.
			if ( empty( $output ) && $message->role !== AI_Provider::ROLE_USER ) {
				continue;
			}

			$last_index = count( $output ) - 1;
			if ( $last_index >= 0 && $output[ $last_index ]['role'] === $message->role ) {
				$output[ $last_index ]['content'] .= "\n\n" . $message->content;
			} else {
				$output[] = [
					'role'    => $message->role, // 'user' or 'assistant'
					'content' => $message->content,
				];
			}
		}

		return $output;
	}

	/**
	 * Whether an API key is set.
	 *
	 * Self-hosted build: see AI_Provider::is_configured().
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_key );
	}
}
