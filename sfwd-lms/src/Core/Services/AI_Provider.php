<?php
/**
 * AI Provider interface.
 *
 * @since 5.1.0
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Services;

use LearnDash\Core\Modules\AI\Chat_Message;

/**
 * Contract that all AI provider implementations must satisfy.
 *
 * Implementations: ChatGPT (OpenAI), Anthropic (Claude), Gemini (Google),
 * OpenAI_Compatible (local / self-hosted models).
 *
 * @since 5.1.0
 */
interface AI_Provider {
	/**
	 * Role constant for system messages (instructions / persona).
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	const ROLE_SYSTEM = 'system';

	/**
	 * Role constant for user messages.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	const ROLE_USER = 'user';

	/**
	 * Role constant for AI assistant messages.
	 *
	 * @since 5.1.0
	 *
	 * @var string
	 */
	const ROLE_ASSISTANT = 'assistant';

	/**
	 * Sends a command to the AI provider and returns the text response.
	 *
	 * @since 5.1.0
	 *
	 * @param string               $command The user message / prompt to send.
	 * @param array<string, mixed> $args    Optional extra arguments (provider-specific).
	 *
	 * @return string The AI response text.
	 */
	public function send_command( string $command, array $args = [] ): string;

	/**
	 * Sets the system instruction (persona / behaviour prompt).
	 *
	 * @since 5.1.0
	 *
	 * @param string $instruction The system instruction text.
	 *
	 * @return void
	 */
	public function set_instruction( string $instruction ): void;

	/**
	 * Returns the current system instruction.
	 *
	 * @since 5.1.0
	 *
	 * @return string
	 */
	public function get_instruction(): string;

	/**
	 * Replaces the entire message history (used when restoring a session).
	 *
	 * Implementations must prepend a system message using get_instruction().
	 *
	 * @since 5.1.0
	 *
	 * @param Chat_Message[] $messages Messages to set.
	 *
	 * @return void
	 */
	public function set_messages( array $messages ): void;

	/**
	 * Returns the current message history including the system message.
	 *
	 * @since 5.1.0
	 *
	 * @return Chat_Message[]
	 */
	public function get_messages(): array;

	/**
	 * Appends a single message to the history.
	 *
	 * @since 5.1.0
	 *
	 * @param Chat_Message $message The message to append.
	 *
	 * @return void
	 */
	public function add_message( Chat_Message $message ): void;

	/**
	 * Returns the maximum context window size in tokens for the active model.
	 *
	 * Used by the summarizer to decide when conversation history needs pruning.
	 *
	 * @since 5.1.0
	 *
	 * @return int
	 */
	public function get_max_context_window_tokens(): int;

	/**
	 * Whether the provider has the settings it needs to make a request
	 * (an API key, or for a self-hosted server, a base URL).
	 *
	 * Self-hosted build: used by the Course Outline and Quiz Creation admin tools.
	 *
	 * @return bool
	 */
	public function is_configured(): bool;
}
