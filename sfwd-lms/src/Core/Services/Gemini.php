<?php
/**
 * Google Gemini AI provider service.
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
 * Google Gemini AI provider.
 *
 * Calls the generateContent endpoint:
 * https://generativelanguage.googleapis.com/v1beta/models/{model}:generateContent (API key in the x-goog-api-key header)
 *
 * Key differences from the OpenAI format:
 * - API key is passed in the 'x-goog-api-key' request header.
 * - System prompt is a top-level 'systemInstruction' object.
 * - Messages are wrapped as 'contents' with nested 'parts' arrays.
 * - The AI role is 'model', not 'assistant', and turns must alternate starting with 'user'.
 * - Response text is at candidates[0].content.parts[0].text.
 *
 * @since 5.1.0
 */
class Gemini implements AI_Provider {
	/**
	 * Default model.
	 *
	 * gemini-flash-latest is an evergreen alias that Google hot-swaps to point
	 * at whatever the current production Flash model is. Using the alias means
	 * we don't have to ship a code update every time a new Flash variant ships.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'gemini-flash-latest';

	/**
	 * Gemini API base URL (model appended at request time).
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private const API_BASE_URL = 'https://generativelanguage.googleapis.com/v1beta/models/';

	/**
	 * Context window for the default model.
	 * Current Flash models support 1M tokens; we use a conservative 32k.
	 *
	 * @since 5.1.0
	 *
	 * @var int
	 */
	private const MODEL_MAX_CONTEXT_WINDOW_TOKENS = 32000;

	/**
	 * Gemini API key.
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
	 * Conversation message history.
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
	 * @param string $api_key Gemini API key.
	 * @param string $model   Model identifier. Defaults to gemini-flash-latest.
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
		 * Filters the request data sent to the Gemini API.
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
			'learndash_service_gemini_send_command_data',
			[
				'systemInstruction' => [
					'parts' => [ [ 'text' => $this->get_instruction() ] ],
				],
				'contents'          => $this->map_messages_to_request_format(),
			],
			$command,
			$args
		);

		$response      = $this->request( 'POST', $data );
		$response_data = json_decode( $response, true );

		if (
			! is_array( $response_data )
			|| ! isset( $response_data['candidates'][0]['content']['parts'][0]['text'] )
			|| ! is_string( $response_data['candidates'][0]['content']['parts'][0]['text'] )
		) {
			// Log the detail for admins; the student only sees a generic message.
			error_log( 'LearnDash AI (Gemini): unexpected response shape: ' . substr( $response, 0, 2000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			throw new Exception(
				esc_html__( 'The AI service returned an unexpected response. Please try again.', 'learndash' ),
				502
			);
		}

		return $response_data['candidates'][0]['content']['parts'][0]['text'];
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
	 */
	public function set_messages( array $messages ): void {
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
	 * Returns messages with a leading synthetic system Chat_Message so that
	 * Chat_Session::map_messages() can filter it consistently across providers.
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
			return;
		}

		$this->messages[] = $message;
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_max_context_window_tokens(): int {
		/**
		 * Filters the maximum context window tokens for the Gemini model.
		 *
		 * @since 5.1.0
		 *
		 * @param int $tokens Maximum context window tokens.
		 *
		 * @return int
		 */
		return apply_filters(
			'learndash_service_gemini_model_max_context_window_tokens',
			self::MODEL_MAX_CONTEXT_WINDOW_TOKENS
		);
	}

	/**
	 * Sends an HTTP request to the Gemini generateContent endpoint.
	 *
	 * @since 5.1.0
	 *
	 * @throws Exception On API or HTTP error.
	 *
	 * @param string               $method HTTP method.
	 * @param array<string, mixed> $body   Request body.
	 *
	 * @return string Raw response body.
	 */
	private function request( string $method, array $body ): string {
		// The key is sent as a header rather than a ?key= query parameter so it does not end up in server, proxy or CDN logs.
		$url = self::API_BASE_URL . rawurlencode( $this->model ) . ':generateContent';

		$args = [
			'method'  => $method,
			'body'    => wp_json_encode( $body ),
			'headers' => [
				'Content-Type'   => 'application/json',
				'x-goog-api-key' => $this->api_key,
			],
			'timeout' => 2 * MINUTE_IN_SECONDS,
		];

		$response = wp_remote_request( $url, $args );
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
			error_log( sprintf( 'LearnDash AI (Gemini): HTTP %s: %s', is_int( $code ) ? $code : 'n/a', $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

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
	 * Maps internal Chat_Message objects to the Gemini 'contents' array format.
	 *
	 * Gemini uses role 'model' where we store 'assistant' internally, requires
	 * the conversation to start with a 'user' turn, and requires strict turn alternation.
	 *
	 * @since 5.1.0
	 *
	 * @return array<array{role: string, parts: array<array{text: string}>}>
	 */
	private function map_messages_to_request_format(): array {
		$output = [];

		foreach ( $this->messages as $message ) {
			if ( $message->role === AI_Provider::ROLE_SYSTEM ) {
				continue;
			}

			// Gemini expects 'model' instead of 'assistant'.
			$role = $message->role === AI_Provider::ROLE_ASSISTANT ? 'model' : 'user';

			// Gemini requires the first turn in contents to be 'user'. Skip leading 'model' greeting.
			if ( empty( $output ) && $role !== 'user' ) {
				continue;
			}

			$last_index = count( $output ) - 1;
			if ( $last_index >= 0 && $output[ $last_index ]['role'] === $role ) {
				$output[ $last_index ]['parts'][0]['text'] .= "\n\n" . $message->content;
			} else {
				$output[] = [
					'role'  => $role,
					'parts' => [ [ 'text' => $message->content ] ],
				];
			}
		}

		return $output;
	}

	/**
	 * Whether an API key is set.
	 *
	 * MMA build: see AI_Provider::is_configured().
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_key );
	}
}
