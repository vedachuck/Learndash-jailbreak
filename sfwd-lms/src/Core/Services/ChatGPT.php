<?php
/**
 * ChatGPT service class.
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Services;

use Exception;
use LearnDash\Core\Modules\AI\Chat_Message;
use LearnDash\Core\Modules\AI\ChatGPT_Summarizer;

/**
 * ChatGPT client class.
 *
 * @since 4.6.0
 */
class ChatGPT implements AI_Provider {
	/**
	 * Role key for system.
	 *
	 * @since 4.13.0
	 *
	 * @var string
	 */
	public static $role_system = 'system';

	/**
	 * Role key for assistant.
	 *
	 * @since 4.13.0
	 *
	 * @var string
	 */
	public static $role_assistant = 'assistant';

	/**
	 * Role key for user.
	 *
	 * @since 4.13.0
	 *
	 * @var string
	 */
	public static $role_user = 'user';

	/**
	 * Default OpenAI model.
	 *
	 * gpt-5-mini is the current cost-efficient flagship for chat workloads and
	 * a sensible default. The legacy gpt-3.5-turbo default was retired long ago.
	 * The model can be overridden in
	 * LearnDash → Settings → Advanced → AI Integrations → OpenAI Model.
	 *
	 * @since 4.13.0
	 *
	 * @see https://platform.openai.com/docs/models
	 *
	 * @var string
	 */
	private const DEFAULT_MODEL = 'gpt-5-mini';

	/**
	 * Max context window tokens.
	 *
	 * Conservative default that comfortably covers the GPT-5 family. Used by
	 * ChatGPT_Summarizer to decide when conversation history needs pruning.
	 * Override per-site via the learndash_service_chatgpt_model_max_context_window_tokens filter.
	 *
	 * @since 4.13.0
	 *
	 * @see https://platform.openai.com/docs/models
	 *
	 * @var int
	 */
	private const MODEL_MAX_CONTEXT_WINDOW_TOKENS = 32000;

	/**
	 * Default max tokens.
	 *
	 * The maximum number of tokens that can be generated in the chat completion.
	 *
	 * @since 4.13.0
	 *
	 * @see https://platform.openai.com/docs/models/gpt-3-5-turbo
	 *
	 * @var int
	 */
	private const MAX_TOKENS = 4096;

	/**
	 * Default temperature. The default temperature is 0.9 so that the model will be less repetitive, but not too random which results in response that does not make sense.
	 *
	 * Temperature is a number between 0 and 2 that controls randomness in boltzmann distribution. Lower temperature
	 * results in less random completions. As the temperature approaches zero, the model will become deterministic
	 * and repetitive. Higher temperature results in more random completions.
	 *
	 * @since 4.13.0
	 *
	 * @var float
	 */
	private const TEMPERATURE = 0.9;

	/**
	 * Default top p. The default top p is 0.7 so that the model will be less repetitive.
	 *
	 * Top p is a number between 0 and 1 that controls diversity via nucleus sampling: 0.5 means half of all
	 * likelihood-weighted options are considered.
	 *
	 * @since 4.13.0
	 *
	 * @var float
	 */
	private const TOP_P = 0.7;

	/**
	 * OpenAI API key.
	 *
	 * @since 4.6.0
	 *
	 * @var string
	 */
	private $api_key;

	/**
	 * Model identifier.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	private string $model;

	/**
	 * ChatGPT API url.
	 *
	 * @since 4.6.0
	 *
	 * @var string
	 */
	private $api_url = 'https://api.openai.com/v1/chat/completions';

	/**
	 * A collection of system, user and ChatGPT response messages.
	 *
	 * A collection of chat message objects. ChatGPT needs the history of the
	 * conversation to generate a response that understands the context of a conversation.
	 * This property only accept objects which have roles supported by ChatGPT.
	 *
	 * @since 4.13.0
	 *
	 * @var Chat_Message[]
	 */
	private array $messages = [];

	/**
	 * Instruction for the ChatGPT.
	 *
	 * System message used to set the initial instruction for the ChatGPT. ChatGPT accepts custom system message
	 * or instruction that can direct the way ChatGPT generates response.
	 *
	 * @since 4.13.0
	 *
	 * @var string
	 */
	private string $instruction = '';

	/**
	 * Constructor.
	 *
	 * @since 4.6.0
	 *
	 * @param string $api_key OpenAI API key.
	 * @param string $model   Model identifier. Defaults to self::DEFAULT_MODEL.
	 *
	 * @return void
	 */
	public function __construct( string $api_key, string $model = self::DEFAULT_MODEL ) {
		$this->api_key = $api_key;
		$this->model   = ! empty( $model ) ? $model : self::DEFAULT_MODEL;
	}

	/**
	 * Returns API key.
	 *
	 * @since 4.6.0
	 *
	 * @return string
	 */
	public function get_api_key(): string {
		return $this->api_key;
	}

	/**
	 * Send request to ChatGPT API url.
	 *
	 * @since 4.6.0
	 *
	 * @throws Exception Throw exception with error message and response code if request failed for any reason.
	 *
	 * @param string               $method  Request method.
	 * @param array<string, mixed> $body    Request body.
	 * @param array<string, mixed> $headers Request headers.
	 * @param array<string, mixed> $args    Request args.
	 *
	 * @return string         Response.
	 */
	public function request( $method = 'GET', $body = [], $headers = [], $args = [] ): string {
		$body    = wp_parse_args( $body, [] );
		$api_key = $this->api_key;

		$headers = wp_parse_args(
			$headers,
			[
				'Content-Type'  => 'application/json',
				'Authorization' => 'Bearer ' . $api_key,
			]
		);

		$args = wp_parse_args(
			$args,
			[
				'method'  => $method,
				'body'    => wp_json_encode( $body ),
				'headers' => $headers,
				'timeout' => 2 * MINUTE_IN_SECONDS,
			]
		);

		$response = wp_remote_request( $this->api_url, $args );

		unset( $api_key );
		unset( $headers );
		unset( $args );

		$code = wp_remote_retrieve_response_code( $response );
		$body = wp_remote_retrieve_body( $response );

		if ( is_wp_error( $response ) || $code !== 200 ) {
			if ( is_wp_error( $response ) ) {
				$error_message = $response->get_error_message();

				if ( mb_stristr( $error_message, 'curl error 28' ) ) {
					$error_message .= '. ' . esc_html__( 'The request has timed out. Please try it again with lower number of items. If the issue persists, please check your website server or Open AI service status.', 'learndash' );
				}
			} else {
				$decoded       = json_decode( $body, true );
				$error_message = is_array( $decoded ) && isset( $decoded['error']['message'] ) ? $decoded['error']['message'] : '';

				if ( empty( $error_message ) ) {
					$error_code = is_array( $decoded ) && isset( $decoded['error']['code'] ) ? $decoded['error']['code'] : '';

					switch ( $error_code ) {
						case 'invalid_api_key':
							$error_message = __( 'Invalid OpenAI API key. Please check your API key.', 'learndash' );
							break;

						default:
							$error_message = wp_sprintf(
								// translators: Error code.
								__( 'Code: %s', 'learndash' ),
								$error_code
							);
							break;
					}
				}
			}

			// Log the upstream error details for site administrators.
			error_log( sprintf( 'LearnDash AI (OpenAI): HTTP %s: %s', is_int( $code ) ? $code : 'n/a', $error_message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			$code = is_int( $code ) && $code > 0 ? $code : 500;

			// Preserve detailed error messages in wp-admin for Course Outline / Quiz Creation tools,
			// but show a generic message to students on the frontend Virtual Instructor chatbox.
			if ( is_admin() && current_user_can( LEARNDASH_ADMIN_CAPABILITY_CHECK ) ) {
				throw new Exception(
					sprintf(
						// translators: Error message.
						esc_html__( 'Error: %s', 'learndash' ),
						$error_message
					),
					$code
				);
			}

			throw new Exception(
				esc_html__( 'The AI service is currently unavailable. Please try again later.', 'learndash' ),
				$code
			);
		}

		return $body;
	}

	/**
	 * Send request.
	 *
	 * @since 4.6.0
	 *
	 * @throws Exception Throw exception with error message and response code if request failed for any reason.
	 *
	 * @param string               $command Command for the request.
	 * @param array<string, mixed> $args    Additional arguments if any.
	 *
	 * @return string
	 */
	public function send_command( $command, array $args = [] ): string {
		$this->construct_messages_from_command( $command );

		$args = wp_parse_args(
			$args,
			[
				'role' => self::$role_user,
			]
		);

		// OpenAI's gpt-5 and o-series reasoning models require 'max_completion_tokens'
		// instead of 'max_tokens' and do not support custom 'temperature' / 'top_p' values.
		$is_reasoning_model = (bool) preg_match( '/^(gpt-5|o[134])/i', $this->model );

		$payload = [
			'model'    => $this->model,
			'messages' => $this->map_messages_to_request_format(),
		];

		if ( $is_reasoning_model ) {
			$payload['max_completion_tokens'] = self::MAX_TOKENS;
		} else {
			$payload['max_tokens']  = self::MAX_TOKENS;
			$payload['temperature'] = self::TEMPERATURE;
			$payload['top_p']       = self::TOP_P;
		}

		/**
		 * Filters the data to send to ChatGPT API.
		 *
		 * @since 4.6.0
		 *
		 * @param array<string, mixed> $data    Data to send to ChatGPT API.
		 * @param string               $command Command for the request.
		 * @param array<string, mixed> $args    Additional arguments if any.
		 *
		 * @return array<string, mixed>
		 */
		$data = apply_filters(
			'learndash_service_chatgpt_send_command_data',
			$payload,
			$command,
			$args
		);

		$response = $this->request(
			'POST',
			$data
		);

		$response_data = json_decode( $response, true );

		if (
			! is_array( $response_data )
			|| ! isset( $response_data['choices'][0]['message']['content'] )
			|| ! is_string( $response_data['choices'][0]['message']['content'] )
		) {
			error_log( 'LearnDash AI (OpenAI): unexpected response shape: ' . substr( $response, 0, 2000 ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log

			throw new Exception(
				esc_html__( 'The AI service returned an unexpected response. Please try again.', 'learndash' ),
				502
			);
		}

		return $response_data['choices'][0]['message']['content'];
	}

	/**
	 * Adds a new chat message object into messages property.
	 *
	 * @since 4.13.0
	 *
	 * @param Chat_Message $message Chat message object.
	 *
	 * @return void
	 */
	public function add_message( Chat_Message $message ): void {
		if ( empty( $this->messages ) ) {
			$this->messages = [
				new Chat_Message(
					$this->get_instruction(),
					self::$role_system
				),
			];
		}

		$this->messages = array_merge( $this->get_messages(), [ $message ] );
	}

	/**
	 * Sets messages property.
	 *
	 * It can be used to restore contextual messages from a saved state or a session.
	 *
	 * @since 4.13.0
	 *
	 * @param Chat_Message[] $messages Messages to set.
	 *
	 * @return void
	 */
	public function set_messages( array $messages ): void {
		$system_message = [
			new Chat_Message(
				$this->get_instruction(),
				self::$role_system
			),
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
	 * Returns messages property.
	 *
	 * @since 4.13.0
	 *
	 * @return Chat_Message[]
	 */
	public function get_messages(): array {
		return $this->messages;
	}

	/**
	 * Sets instruction.
	 *
	 * @since 4.13.0
	 *
	 * @param string $instruction Instruction for the ChatGPT.
	 *
	 * @return void
	 */
	public function set_instruction( string $instruction ): void {
		$this->instruction = $instruction;
	}

	/**
	 * Returns ChatGPT instruction.
	 *
	 * It returns default generic instruction if the instruction is not set.
	 *
	 * @since 4.13.0
	 *
	 * @return string
	 */
	public function get_instruction(): string {
		return ! empty( $this->instruction )
			? $this->instruction
			: __( 'You are a helpful assistant.', 'learndash' );
	}

	/**
	 * Returns model max context window tokens.
	 *
	 * @since 4.13.0
	 *
	 * @return int
	 */
	public static function get_model_max_context_window_tokens(): int {
		/**
		 * Filters the maximum context window tokens for ChatGPT model.
		 *
		 * @since 4.13.0
		 *
		 * @param int $max_context_window_tokens Maximum context window tokens for a ChatGPT model.
		 *
		 * @return int Maximum context window tokens for a ChatGPT model.
		 */
		return apply_filters(
			'learndash_service_chatgpt_model_max_context_window_tokens',
			self::MODEL_MAX_CONTEXT_WINDOW_TOKENS
		);
	}

	/**
	 * Returns the maximum context window tokens (AI_Provider interface method).
	 *
	 * Delegates to the static method so both call paths return the same filtered value.
	 *
	 * @since 5.1.0
	 *
	 * @return int
	 */
	public function get_max_context_window_tokens(): int {
		return self::get_model_max_context_window_tokens();
	}

	/**
	 * Maps chat message objects into chatgpt messages array.
	 *
	 * @since 4.13.0
	 *
	 * @return array<array{
	 *     content: string,
	 *     role: string
	 * }> ChatGPT messages array.
	 */
	private function map_messages_to_request_format(): array {
		$chatgpt_messages = [];

		foreach ( $this->messages as $message ) {
			$chatgpt_messages[] = [
				'content' => $message->content,
				'role'    => $message->role,
			];
		}

		return $chatgpt_messages;
	}

	/**
	 * Constructs messages for the request from user command.
	 *
	 * @since 4.13.0
	 *
	 * @param string $command User command.
	 *
	 * @return void
	 */
	private function construct_messages_from_command( string $command ): void {
		$chatgpt_summarizer = new ChatGPT_Summarizer( $this->get_messages() );

		if ( $chatgpt_summarizer->messages_need_summarization() ) {
			$summary = $chatgpt_summarizer->summarize_messages();

			if ( ! empty( $summary ) ) {
				// Re-construct the messages to include the summary.
				$messages = array_merge(
					[ new Chat_Message( $summary, self::$role_user ) ],
					$chatgpt_summarizer->get_preserved_messages(),
				);
				$this->set_messages( $messages );
			}
		}

		$this->add_message( new Chat_Message( $command, self::$role_user ) );
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
