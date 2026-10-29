<?php
/**
 * Nexcess software packages banner.
 *
 * @since 5.2.2
 *
 * @package LearnDash\Core
 */

namespace LearnDash\Core\Modules\Admin\Banner;

use DateTimeImmutable;
use DateTimeZone;
use StellarWP\Learndash\Nexcess\Banners\Banner;
use StellarWP\Learndash\Nexcess\Banners\BannerFields;
use StellarWP\Learndash\Nexcess\Banners\Registry;

/**
 * The Nexcess software packages banner.
 *
 * @since 5.2.2
 */
class Nexcess_Software_Packages_Banner {
	/**
	 * Shared by every Nexcess plugin that shows this banner.
	 *
	 * @since 5.2.2
	 *
	 * @var string
	 */
	private const BANNER_ID = 'nexcess-software-packages-2026-10-14';

	/**
	 * When the banner starts showing, in New York time.
	 *
	 * @since 5.2.2
	 *
	 * @var string
	 */
	private const START = '2026-10-14 12:00:00';

	/**
	 * Who may see the banner, and where.
	 *
	 * @since 5.2.2
	 *
	 * @var Nexcess_Banner_Audience
	 */
	private Nexcess_Banner_Audience $audience;

	/**
	 * Constructor.
	 *
	 * @since 5.2.2
	 *
	 * @param Nexcess_Banner_Audience $audience Who may see the banner, and where.
	 */
	public function __construct( Nexcess_Banner_Audience $audience ) {
		$this->audience = $audience;
	}

	/**
	 * Registers the banner.
	 *
	 * @since 5.2.2
	 *
	 * @return void
	 */
	public function register(): void {
		$fields          = new BannerFields();
		$fields->heading = __( 'Changes are coming to your plan at your next renewal.', 'learndash' );
		$fields->body    = __( 'Watch your inbox for details.', 'learndash' );

		$fields->ctaLabel     = __( 'Learn more', 'learndash' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Named by the stellarwp/banners library.
		$fields->ctaUrl       = 'https://explore.nexcess.com/software-packages'; // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Named by the stellarwp/banners library.
		$fields->dismissLabel = __( 'Dismiss this banner', 'learndash' ); // phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase -- Named by the stellarwp/banners library.

		$package_url = plugins_url( 'vendor-prefixed/stellarwp/banners/', LEARNDASH_LMS_PLUGIN_DIR . 'sfwd_lms.php' );
		$start       = new DateTimeImmutable( self::START, new DateTimeZone( 'America/New_York' ) );

		( new Registry() )->register( new Banner( self::BANNER_ID, '1.0.0', $fields, $package_url, $start, null ) );
	}

	/**
	 * Renders the banner.
	 *
	 * @since 5.2.2
	 *
	 * @return void
	 */
	public function render(): void {
		$registry = new Registry();

		if ( ! $registry->has( self::BANNER_ID ) ) {
			return;
		}

		$registry->get( self::BANNER_ID )->render( [ $this->audience, 'should_display' ] );
	}
}
