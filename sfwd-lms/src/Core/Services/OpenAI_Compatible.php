<?php
/**
 * OpenAI-compatible AI provider service.
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
 * Generic OpenAI-compatible AI provider.
 *
 * Works with any server that implements the OpenAI chat completions API,
 * including Ollama, LM Studio, vLLM, LocalAI, and other self-hosted models
 * running on a VPS or local machine.
 *
 * Configure by setting the base URL to your server's endpoint, e.g.:
 * - Ollama:     http://your-vps:11434/v1
 * - LM Studio:  http://localhost:1234/v1
 * - vLLM:       http://your-vps:8000/v1
 *
 * The /chat/completions path is appended automatically.
 *
 * @since 5.1.0
 */
class OpenAI_Compatible implements AI_Provider {
	/**
	 * Fallback model name when none is specified.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'default';

	/**
	 * Maximum tokens to generate in a single response.
	 *
	 * @since 5.1.0
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 4096;

	/**
	 * Conservative context window default.
	 *
	 * Administrators can override this via the learndash_service_openai_compatible_model_max_context_window_tokens filter.
	 *
	 * @since 5.1.0
	 *
	 * @var int
	 */
	private const MODEL_MAX_CONTEXT_WINDOW_TOKENS = 16385;

	/**
	 * Temperature — controls response creativity.
	 *
	 * @since 5.1.0
	 *
	 * @var float
	 */
	private const TEMPERATURE = 0.9;

	/**
	 * Top-p nucleus sampling value.
	 *
	 * @since 5.1.0
	 *
	 * @var float
	 */
	private const TOP_P = 0.7;

	/**
	 * Raw base URL configured in settings.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $base_url;

	/**
	 * Full API endpoint URL (base_url + /chat/completions).
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $api_url;

	/**
	 * API key (leave empty for unauthenticated local servers).
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $api_key;

	/**
	 * Model identifier as expected by the server.
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
	 * @param string $base_url Base URL of the OpenAI-compatible server (without trailing slash or path).
	 * @param string $model    Model name as expected by the server.
	 * @param string $api_key  API key (optional — leave empty for local unauthenticated servers).
	 */
	public function __construct( string $base_url, string $model = self::DEFAULT_MODEL, string $api_key = '' ) {
		$this->base_url = trim( $base_url );
		$this->api_url  = ! empty( $this->base_url )
			? trailingslashit( rtrim( $this->base_url, '/' ) ) . 'chat/completions'
			: '';
		$this->model    = ! empty( $model ) ? $model : self::DEFAULT_MODEL;
		$this->api_key  = $api_key;
	}

	/**
	 * {@inheritdoc}
	 */
	public function send_command( string $command, array $args = [] ): string {
		$this->construct_messages_from_command( $command );

		/**
		 * Filters the request data sent to the OpenAI-compatible API.
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
			'learndash_service_openai_compatible_send_command_data',
			[
				'model'       => $this->model,
				'messages'    => $this->map_messages_to_request_format(),
				'max_tokens'  => self::MAX_TOKENS,
				'temperature' => self::TEMPERATURE,
				'top_p'       => self::TOP_P,
			],
			$command,
			$args
		);

		$response      = $this->request( 'POST', $data );
		$response_data = json_decode( $response, true );

		if (
			! is_array( $response_data )
			|| ! isset( $response_data['choices'][0]['message']['content'] )
			|| ! is_string( $response_data['choices'][0]['message']['content'] )
		) {
			// Log the detail for admins; the student only sees a generic message.
			error_log( 'LearnDash AI (OpenAI-compatible): unexpected response shape: ' . substr( $response, 0, 2000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			throw new Exception(
				esc_html__( 'The AI service returned an unexpected response. Please try again.', 'learndash' ),
				502
			);
		}

		return $response_data['choices'][0]['message']['content'];
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
		$system_message = [
			new Chat_Message( $this->get_instruction(), AI_Provider::ROLE_SYSTEM ),
		];

		$this->messages = array_merge(
			$system_message,
			array_filter(
				$messages,
				fn( Chat_Message $m ) => $m->role !== AI_Provider::ROLE_SYSTEM
			)
		);
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_messages(): array {
		return $this->messages;
	}

	/**
	 * {@inheritdoc}
	 */
	public function add_message( Chat_Message $message ): void {
		if ( empty( $this->messages ) ) {
			$this->messages = [
				new Chat_Message( $this->get_instruction(), AI_Provider::ROLE_SYSTEM ),
			];
		}

		$this->messages = array_merge( $this->get_messages(), [ $message ] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function get_max_context_window_tokens(): int {
		/**
		 * Filters the maximum context window tokens for the OpenAI-compatible model.
		 *
		 * Override this if your model has a larger or smaller context window.
		 *
		 * @since 5.1.0
		 *
		 * @param int $tokens Maximum context window tokens.
		 *
		 * @return int
		 */
		return apply_filters(
			'learndash_service_openai_compatible_model_max_context_window_tokens',
			self::MODEL_MAX_CONTEXT_WINDOW_TOKENS
		);
	}

	/**
	 * Sends an HTTP request to the OpenAI-compatible endpoint.
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
		// Self-hosted build: a plain format check, not wp_http_validate_url(), which rejects
		// localhost, private-network addresses and non-standard ports (Ollama 11434,
		// LM Studio 1234, vLLM 8000), the servers this provider exists to support.
		$scheme = wp_parse_url( $this->api_url, PHP_URL_SCHEME );
		$host   = wp_parse_url( $this->api_url, PHP_URL_HOST );

		if ( empty( $this->api_url ) || empty( $host ) || ! in_array( strtolower( (string) $scheme ), [ 'http', 'https' ], true ) ) {
			error_log( 'LearnDash AI (OpenAI-compatible): missing or invalid Server Base URL.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			throw new Exception(
				esc_html__( 'The AI service is not configured properly. Please contact the site administrator.', 'learndash' ),
				500
			);
		}

		$headers = [
			'Content-Type' => 'application/json',
		];

		// Only add auth header if an API key was provided.
		if ( ! empty( $this->api_key ) ) {
			$headers['Authorization'] = 'Bearer ' . $this->api_key;
		}

		$args = [
			'method'  => $method,
			'body'    => wp_json_encode( $body ),
			'headers' => $headers,
			'timeout' => 2 * MINUTE_IN_SECONDS,
		];

		$response = wp_remote_request( $this->api_url, $args );
		$code     = wp_remote_retrieve_response_code( $response );
		$raw_body = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) || $code !== 200 ) {
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();

				if ( mb_stristr( $error_message, 'curl error 28' ) ) {
					$error_message .= '. ' . esc_html__( 'The request timed out. Check your server URL and that the model server is running.', 'learndash' );
				}
			} else {
				$decoded       = json_decode( $raw_body, true );
				$error_message = is_array( $decoded ) && isset( $decoded['error']['message'] )
					? $decoded['error']['message']
					: sprintf( __( 'HTTP %d', 'learndash' ), $code );
			}

			// Log the detail for admins; the student only sees a generic message.
			error_log( sprintf( 'LearnDash AI (OpenAI-compatible): HTTP %s: %s', is_int( $code ) ? $code : 'n/a', $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

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
	 * Maps internal Chat_Message objects to the OpenAI messages array format.
	 *
	 * @since 5.1.0
	 *
	 * @return array<array{role: string, content: string}>
	 */
	private function map_messages_to_request_format(): array {
		$output = [];

		foreach ( $this->messages as $message ) {
			$output[] = [
				'role'    => $message->role,
				'content' => $message->content,
			];
		}

		return $output;
	}

	/**
	 * Whether a base URL is set. The API key is optional for local servers.
	 *
	 * Self-hosted build: see AI_Provider::is_configured().
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->api_url );
	}
}
