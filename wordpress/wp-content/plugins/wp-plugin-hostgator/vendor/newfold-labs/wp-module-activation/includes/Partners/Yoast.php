<?php
/**
 * Yoast.
 *
 * @package NewfoldLabs\WP\Module\Activation
 */

namespace NewfoldLabs\WP\Module\Activation\Partners;

/**
 * Yoast class.
 */
class Yoast extends Partner {

	/**
	 * Initialize.
	 *
	 * @return void
	 */
	public function init() {
		add_action(
			'plugins_loaded',
			function () {
				if ( class_exists( 'WPSEO_Options' ) ) {
					add_action( 'admin_init', array( $this, 'dismiss_admin_notice' ) );
				}
			}
		);
	}

	/**
	 * Dismiss admin notice.
	 *
	 * @return void
	 */
	public function dismiss_admin_notice() {
		\WPSEO_Options::set( 'dismiss_configuration_workout_notice', true );
	}
}
