<?php
/**
 * WordPress requirements module.
 *
 * @since 4.20.1
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\Support\Requirements;

use StellarWP\Learndash\StellarWP\AdminNotices\AdminNotices;
use WP_Error;
use WP_Upgrader;

/**
 * WordPress requirements module class.
 *
 * @since 4.20.1
 */
class WordPress {
	/**
	 * WordPress version below which LearnDash refuses to download its own plugin updates.
	 *
	 * Keep this in sync with the "Requires at least" header in sfwd_lms.php.
	 *
	 * @since 4.21.5
	 * @since 5.2.1 Renamed from MINIMUM_WORDPRESS_VERSION and raised from 6.6 to 6.7.
	 *
	 * @var string
	 */
	private const MINIMUM_WORDPRESS_VERSION_FOR_UPDATES = '6.7';

	/**
	 * WordPress version a future LearnDash release will require.
	 *
	 * Drives the admin notice only. Plugin updates are not refused below it.
	 *
	 * @since 5.2.1
	 *
	 * @var string
	 */
	private const UPCOMING_MINIMUM_WORDPRESS_VERSION = '6.9';

	/**
	 * URL of the LearnDash technical requirements page.
	 *
	 * @since 5.2.1
	 *
	 * @var string
	 */
	private const TECHNICAL_REQUIREMENTS_URL = 'https://go.learndash.com/tecreq';

	/**
	 * Register admin notices.
	 *
	 * @since 4.20.1
	 * @since 4.21.4 Added the final warning notice for WP < 6.6 and remove the previous one.
	 * @since 4.22.1 Removed the final warning notice for WP < 6.6.
	 * @since 5.2.1 Added the notice for WP < UPCOMING_MINIMUM_WORDPRESS_VERSION.
	 *
	 * @return void
	 */
	public function register_notices(): void {
		if ( ! $this->is_wp_version_below( self::UPCOMING_MINIMUM_WORDPRESS_VERSION ) ) {
			return;
		}

		AdminNotices::show(
			'learndash-support-policy-wp-version-update-' . str_replace( '.', '-', self::UPCOMING_MINIMUM_WORDPRESS_VERSION ),
			$this->get_upcoming_version_notice_message()
		)
			->on(
				// Dashboard page.
				[
					'id' => 'dashboard',
				],
				'plugins.php', // Plugins page.
				'update-core.php', // Updates page.
				'~(edit|admin)\.php\?(post_type|page)=.*?(learndash|lms|groups|ld|lms|sfwd)~i' // LearnDash admin pages.
			)
			->ifUserCan( LEARNDASH_ADMIN_CAPABILITY_CHECK )
			->asWarning()
			->autoParagraph()
			->dismissible();
	}

	/**
	 * Check the required WordPress version.
	 *
	 * @since 4.21.5
	 *
	 * @param bool                 $pre_download Whether to bail without returning the package. Default false.
	 * @param string               $package      The package file name.
	 * @param WP_Upgrader          $wp_upgrader  The WP_Upgrader instance.
	 * @param array<string, mixed> $hook_extra   Extra arguments passed to hooked filters.
	 *
	 * @return bool|WP_Error
	 */
	public function check_required_wp_version( $pre_download, $package, $wp_upgrader, $hook_extra ) {
		if (
			! isset( $hook_extra['plugin'] )
			|| $hook_extra['plugin'] !== LEARNDASH_LMS_PLUGIN_KEY
		) {
			return $pre_download;
		}

		if ( ! $this->is_wp_version_below( self::MINIMUM_WORDPRESS_VERSION_FOR_UPDATES ) ) {
			return $pre_download;
		}

		return new WP_Error(
			'learndash_wp_version_error',
			sprintf(
				// Translators: %1$s: minimum WordPress version, %2$s: opening link tag, %3$s: closing link tag.
				__( 'Please update WordPress to at least version %1$s. %2$sSee all Technical Requirements%3$s.', 'learndash' ),
				self::MINIMUM_WORDPRESS_VERSION_FOR_UPDATES,
				'<a href="' . esc_url( self::TECHNICAL_REQUIREMENTS_URL ) . '" target="_blank" rel="noopener noreferrer">',
				'</a>'
			)
		);
	}

	/**
	 * Returns the message of the notice prompting an update to the upcoming minimum WordPress version.
	 *
	 * The inline "Update WordPress" text links to the technical requirements page. The button is left
	 * off for a user without the update_core capability, since it would send them to a screen they
	 * cannot use. WordPress withholds that capability from a site administrator on Multisite, and
	 * from everyone when file modifications are disabled.
	 *
	 * @since 5.2.1
	 *
	 * @return string
	 */
	private function get_upcoming_version_notice_message(): string {
		$message = sprintf(
			// Translators: %1$s: upcoming minimum WordPress version, %2$s: opening link tag, %3$s: closing link tag.
			__( 'LearnDash updates will soon require WordPress %1$s or higher. %2$sUpdate WordPress%3$s to continue receiving LearnDash updates.', 'learndash' ),
			self::UPCOMING_MINIMUM_WORDPRESS_VERSION,
			'<a href="' . esc_url( self::TECHNICAL_REQUIREMENTS_URL ) . '" target="_blank" rel="noopener noreferrer">',
			'</a>'
		);

		if ( ! current_user_can( 'update_core' ) ) {
			return $message;
		}

		return $message
			. ' <a class="button button-primary" href="' . esc_url( admin_url( 'update-core.php' ) ) . '">'
			. esc_html__( 'Update WordPress', 'learndash' )
			. '</a>';
	}

	/**
	 * Returns whether the site's WordPress version is below the given version.
	 *
	 * @since 5.2.1
	 *
	 * @param string $version The WordPress version to compare against.
	 *
	 * @return bool
	 */
	private function is_wp_version_below( string $version ): bool {
		return version_compare(
			learndash_sanitize_version_string( get_bloginfo( 'version' ) ),
			learndash_sanitize_version_string( $version ),
			'<'
		);
	}
}
