<?php
/**
 * AI Provider factory.
 *
 * @since 5.1.0
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Services;

/**
 * Creates the correct AI_Provider implementation based on the saved admin settings.
 *
 * This factory is used by classes that need an AI provider at request-time
 * (e.g. Virtual_Instructor\Chat_Session, ChatGPT_Summarizer) but that are not
 * registered as DI-container singletons, making App::make() resolution of the
 * AI_Provider interface impractical.
 *
 * Supported providers are selected via LearnDash → Advanced Settings → AI Integrations.
 *
 * @since 5.1.0
 */
class AI_Provider_Factory {
	/**
	 * Creates and returns an AI_Provider instance for the currently configured provider.
	 *
	 * Reads the `learndash_ai_integrations` option from the database on every call.
	 * Callers that need a stable instance across multiple operations within the same
	 * request should store the result themselves.
	 *
	 * @since 5.1.0
	 *
	 * @return AI_Provider
	 */
	public static function make(): AI_Provider {
		$options  = get_option( 'learndash_ai_integrations' );
		$options  = is_array( $options ) ? $options : [];
		$provider = $options['ai_provider'] ?? 'openai';

		switch ( $provider ) {
			case 'anthropic':
				$api_key = $options['anthropic_api_key'] ?? '';
				$model   = $options['anthropic_model'] ?? '';
				return new Anthropic( $api_key, $model );

			case 'gemini':
				$api_key = $options['gemini_api_key'] ?? '';
				$model   = $options['gemini_model'] ?? '';
				return new Gemini( $api_key, $model );

			case 'openai_compatible':
				$base_url = $options['openai_compatible_base_url'] ?? '';
				$model    = $options['openai_compatible_model'] ?? '';
				$api_key  = $options['openai_compatible_api_key'] ?? '';
				return new OpenAI_Compatible( $base_url, $model, $api_key );

			case 'openai':
			default:
				$api_key = $options['openai_api_key'] ?? '';
				$model   = $options['openai_model'] ?? '';
				return new ChatGPT( $api_key, $model );
		}
	}
}
