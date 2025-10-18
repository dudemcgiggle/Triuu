<?php
namespace NewfoldLabs\WP\Module\Onboarding;

use NewfoldLabs\WP\Module\Onboarding\Data\Data;

/**
 * Class to intercept redirect calls and filter them.
 *
 * The purpose of this class is to prevent any redirects while the user is on the onboarding page.
 * The only allowed redirect is to the brand plugin page.
 */
class ExternalRedirectInterceptor {
	/**
	 * Constructor.
	 */
	public function __construct() {
		if ( ! isset( $_GET['page'] ) || \sanitize_text_field( $_GET['page'] ) !== WP_Admin::$slug ) {
			return;
		}

		\add_filter( 'wp_redirect', array( $this, 'wp_redirect' ), 10, 1 );
	}

	/**
	 * Intercept wp_redirect calls and filter them.
	 *
	 * @param string $location The location to redirect to.
	 */
	public function wp_redirect( $location ): string {
		$runtime_data     = Data::runtime();
		$brand_plugin_url = '';

		// Get the brand plugin page URL from the runtime data.
		if (
			isset( $runtime_data['currentBrand'], $runtime_data['currentBrand']['pluginDashboardPage'] ) &&
			is_string( $runtime_data['currentBrand']['pluginDashboardPage'] )
			) {
				// Set the brand plugin page URL.
				$brand_plugin_url = $runtime_data['currentBrand']['pluginDashboardPage'];
		}

		// Redirect if the brand plugin page URL is empty.
		if ( empty( $brand_plugin_url ) ) {
			return $location;
		}

		$location_is_brand_plugin_url = strpos( $location, $brand_plugin_url );
		// Intercept if the redirect is going anywhere other than the brand plugin page.
		if ( false === $location_is_brand_plugin_url || 0 !== $location_is_brand_plugin_url ) {
			return '';
		}

		// Allow the redirect if it's going to the brand plugin page.
		return $location;
	}
}
