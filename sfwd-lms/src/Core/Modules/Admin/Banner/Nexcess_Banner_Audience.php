<?php
/**
 * Who may see the Nexcess banner, and where.
 *
 * @since 5.2.2
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\Admin\Banner;

use LearnDash\Core\Utilities\Location;
use StellarWP\Learndash\LiquidWeb\Harbor\Admin\Feature_Manager_Page;
use WP_Screen;

/**
 * Decides whether the Nexcess banner may show on the current screen.
 *
 * @since 5.2.2
 */
class Nexcess_Banner_Audience {
	/**
	 * Whether the banner may show on the current screen.
	 *
	 * @since 5.2.2
	 *
	 * @return bool
	 */
	public function should_display(): bool {
		// Stored state only, so showing the banner never rechecks the license with the licensing server.
		return learndash_is_learndash_license_valid( false )
			&& ! $this->is_editor()
			&& ( Location::is_learndash_admin_page() || $this->is_license_manager_page() );
	}

	/**
	 * Whether this screen edits or creates a single post.
	 *
	 * @since 5.2.2
	 *
	 * @return bool
	 */
	private function is_editor(): bool {
		$screen = get_current_screen();

		return $screen instanceof WP_Screen && 'post' === $screen->base;
	}

	/**
	 * Whether this is the Nexcess licensing page.
	 *
	 * @since 5.2.2
	 *
	 * @return bool
	 */
	private function is_license_manager_page(): bool {
		$screen = get_current_screen();

		return $screen instanceof WP_Screen && 'settings_page_' . Feature_Manager_Page::PAGE_SLUG === $screen->id;
	}
}
