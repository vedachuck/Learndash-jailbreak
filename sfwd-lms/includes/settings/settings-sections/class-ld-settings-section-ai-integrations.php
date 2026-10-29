<?php
/**
 * LearnDash Settings Section for AI Integrations Metabox.
 *
 * @since 4.6.0
 * @package LearnDash\Settings\Sections
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use LearnDash\Core\Utilities\Cast;
use LearnDash\Core\Utilities\Str;

if ( class_exists( 'LearnDash_Settings_Section' ) && ! class_exists( 'LearnDash_Settings_Section_AI_Integrations' ) ) {
	/**
	 * Class LearnDash Settings Section for AI Integrations Metabox.
	 *
	 * @since 4.6.0
	 */
	class LearnDash_Settings_Section_AI_Integrations extends LearnDash_Settings_Section {
		/**
		 * Protected constructor for class
		 *
		 * @since 4.6.0
		 */
		protected function __construct() {
			$this->settings_page_id = 'learndash_lms_advanced';

			// This is the 'option_name' key used in the wp_options table.
			$this->setting_option_key = 'learndash_ai_integrations';

			// This is the HTML form field prefix used.
			$this->setting_field_prefix = 'learndash_ai_integrations';

			// Used within the Settings API to uniquely identify this section.
			$this->settings_section_key = 'settings_ai_integrations';

			// Section label/header.
			$this->settings_section_label = esc_html__( 'AI Integrations', 'learndash' );

			parent::__construct();
		}

		/**
		 * Initialize the metabox settings fields.
		 *
		 * @since 4.6.0
		 *
		 * @return void
		 */
		public function load_settings_fields(): void {
			$selected_provider = $this->setting_option_values['ai_provider'] ?? 'openai';

			// Helper: safely mask a saved API key for display, including short bearer tokens.
			$mask_key = function ( string $key ): string {
				$len = strlen( $key );
				if ( $len === 0 ) {
					return '';
				}
				if ( $len <= 10 ) {
					return str_repeat( '*', $len );
				}
				$masked = Str::mask( $key, '*', 3, $len - 7 );
				return $len > 39
					? substr( $masked, 0, 35 ) . substr( $masked, -4, 4 )
					: $masked;
			};

			$openai_api_key             = $this->setting_option_values['openai_api_key'] ?? '';
			$openai_model               = $this->setting_option_values['openai_model'] ?? 'gpt-5-mini';
			$anthropic_api_key          = $this->setting_option_values['anthropic_api_key'] ?? '';
			$gemini_api_key             = $this->setting_option_values['gemini_api_key'] ?? '';
			$openai_compatible_api_key  = $this->setting_option_values['openai_compatible_api_key'] ?? '';
			$openai_compatible_base_url = $this->setting_option_values['openai_compatible_base_url'] ?? '';
			$openai_compatible_model    = $this->setting_option_values['openai_compatible_model'] ?? '';
			$anthropic_model            = $this->setting_option_values['anthropic_model'] ?? 'claude-haiku-4-5';
			$gemini_model               = $this->setting_option_values['gemini_model'] ?? 'gemini-flash-latest';

			$this->setting_option_fields = [

				// ── Provider selector ──────────────────────────────────────────────
				'ai_provider' => [
					'name'      => 'ai_provider',
					'type'      => 'select',
					'label'     => esc_html__( 'AI Provider', 'learndash' ),
					'help_text' => esc_html__( 'Select which AI service to use for Virtual Instructors, Course Outline generation and Quiz Creation.', 'learndash' ),
					'value'     => $selected_provider,
					'options'   => [
						'openai'            => esc_html__( 'OpenAI (ChatGPT)', 'learndash' ),
						'anthropic'         => esc_html__( 'Anthropic (Claude)', 'learndash' ),
						'gemini'            => esc_html__( 'Google Gemini', 'learndash' ),
						'openai_compatible' => esc_html__( 'OpenAI-compatible / Self-hosted', 'learndash' ),
					],
				],

				// ── OpenAI ─────────────────────────────────────────────────────────
				'openai_api_key' => [
					'name'       => 'openai_api_key',
					'type'       => 'password',
					'label'      => esc_html__( 'OpenAI API Key', 'learndash' ),
					'input_note' => ! empty( $openai_api_key )
						? sprintf( esc_html__( 'Saved key: %1$s', 'learndash' ), $mask_key( Cast::to_string( $openai_api_key ) ) )
						: '',
					'help_text'  => sprintf(
						esc_html__( '%1$sGet your OpenAI API key here%2$s.', 'learndash' ),
						'<a href="https://platform.openai.com/account/api-keys" target="_blank" rel="noreferrer noopener">',
						'</a>'
					),
					'value'      => ! empty( $openai_api_key ) ? $this->get_placeholder_for_keys() : '',
					'class'      => 'regular-text',
				],
				'openai_model' => [
					'name'      => 'openai_model',
					'type'      => 'text',
					'label'     => esc_html__( 'OpenAI Model', 'learndash' ),
					'help_text' => esc_html__( 'Model ID to use. Defaults to gpt-5-mini (fast, cost-efficient). For more capable models try gpt-5 or gpt-5.4-mini. The legacy gpt-3.5-turbo / gpt-4o-mini also still work.', 'learndash' ),
					'value'     => $openai_model,
					'class'     => 'regular-text',
				],

				// ── Anthropic (Claude) ─────────────────────────────────────────────
				'anthropic_api_key' => [
					'name'       => 'anthropic_api_key',
					'type'       => 'password',
					'label'      => esc_html__( 'Anthropic API Key', 'learndash' ),
					'input_note' => ! empty( $anthropic_api_key )
						? sprintf( esc_html__( 'Saved key: %1$s', 'learndash' ), $mask_key( Cast::to_string( $anthropic_api_key ) ) )
						: '',
					'help_text'  => sprintf(
						esc_html__( '%1$sGet your Anthropic API key here%2$s.', 'learndash' ),
						'<a href="https://console.anthropic.com/settings/keys" target="_blank" rel="noreferrer noopener">',
						'</a>'
					),
					'value'      => ! empty( $anthropic_api_key ) ? $this->get_placeholder_for_keys() : '',
					'class'      => 'regular-text',
				],
				'anthropic_model' => [
					'name'      => 'anthropic_model',
					'type'      => 'text',
					'label'     => esc_html__( 'Anthropic Model', 'learndash' ),
					'help_text' => esc_html__( 'Model ID to use. Defaults to claude-haiku-4-5 (fast, cheap). For more capable models try claude-sonnet-4-6 or claude-opus-4-6. New 4.x model IDs use a dateless format and update infrequently — update this field when Anthropic releases newer models.', 'learndash' ),
					'value'     => $anthropic_model,
					'class'     => 'regular-text',
				],

				// ── Google Gemini ──────────────────────────────────────────────────
				'gemini_api_key' => [
					'name'       => 'gemini_api_key',
					'type'       => 'password',
					'label'      => esc_html__( 'Gemini API Key', 'learndash' ),
					'input_note' => ! empty( $gemini_api_key )
						? sprintf( esc_html__( 'Saved key: %1$s', 'learndash' ), $mask_key( Cast::to_string( $gemini_api_key ) ) )
						: '',
					'help_text'  => sprintf(
						esc_html__( '%1$sGet your Gemini API key from Google AI Studio%2$s.', 'learndash' ),
						'<a href="https://aistudio.google.com/app/apikey" target="_blank" rel="noreferrer noopener">',
						'</a>'
					),
					'value'      => ! empty( $gemini_api_key ) ? $this->get_placeholder_for_keys() : '',
					'class'      => 'regular-text',
				],
				'gemini_model' => [
					'name'      => 'gemini_model',
					'type'      => 'text',
					'label'     => esc_html__( 'Gemini Model', 'learndash' ),
					'help_text' => esc_html__( 'Model ID to use. Defaults to gemini-flash-latest (an evergreen alias that always points to the current Flash model). For pinned versions use e.g. gemini-2.5-flash, gemini-2.5-pro, or a snapshot from Google AI Studio.', 'learndash' ),
					'value'     => $gemini_model,
					'class'     => 'regular-text',
				],

				// ── OpenAI-compatible / Self-hosted ────────────────────────────────
				'openai_compatible_base_url' => [
					'name'      => 'openai_compatible_base_url',
					'type'      => 'text',
					'label'     => esc_html__( 'Server Base URL', 'learndash' ),
					'help_text' => esc_html__( 'Base URL of your OpenAI-compatible server, e.g. http://your-vps:11434/v1 (Ollama) or http://localhost:1234/v1 (LM Studio). The /chat/completions path is added automatically.', 'learndash' ),
					'value'     => $openai_compatible_base_url,
					'class'     => 'regular-text',
				],
				'openai_compatible_model' => [
					'name'      => 'openai_compatible_model',
					'type'      => 'text',
					'label'     => esc_html__( 'Model Name', 'learndash' ),
					'help_text' => esc_html__( 'The model identifier as expected by your server, e.g. llama3, mistral, phi3. Check your server documentation.', 'learndash' ),
					'value'     => $openai_compatible_model,
					'class'     => 'regular-text',
				],
				'openai_compatible_api_key' => [
					'name'       => 'openai_compatible_api_key',
					'type'       => 'password',
					'label'      => esc_html__( 'API Key (optional)', 'learndash' ),
					'input_note' => ! empty( $openai_compatible_api_key )
						? sprintf( esc_html__( 'Saved key: %1$s', 'learndash' ), $mask_key( Cast::to_string( $openai_compatible_api_key ) ) )
						: '',
					'help_text'  => esc_html__( 'Leave empty for unauthenticated local servers. Required for cloud-hosted OpenAI-compatible APIs.', 'learndash' ),
					'value'      => ! empty( $openai_compatible_api_key ) ? $this->get_placeholder_for_keys() : '',
					'class'      => 'regular-text',
				],
			];

			/** This filter is documented in includes/settings/settings-metaboxes/class-ld-settings-metabox-course-access-settings.php */
			$this->setting_option_fields = apply_filters( 'learndash_settings_fields', $this->setting_option_fields, $this->settings_section_key );

			parent::load_settings_fields();
		}
	}
}

add_action(
	'learndash_settings_sections_init',
	function() {
		LearnDash_Settings_Section_AI_Integrations::add_section_instance();
	}
);
