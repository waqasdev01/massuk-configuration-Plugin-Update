<?php
/**
 * Plugin Name:       Massuk Configuration
 * Plugin URI:        https://github.com/moizxox/massuk-configuration
 * Description:       Creates Stripe Checkout Sessions via a simple REST API. Switch between test and live keys in the admin.
 * Version:           1.1.0
 * Requires at least: 5.8
 * Requires PHP:      7.4
 * Author:            Mian Moiz
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       massuk-configuration
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'MUK_VERSION', '1.1.0' );
define( 'MUK_PLUGIN_FILE', __FILE__ );
define( 'MUK_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'MUK_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

/**
 * Load plugin classes.
 */
function muk_load_files() {
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-settings.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-registrations.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-payment-records.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-submissions.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-email-service.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-mailerlite.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-stripe-client.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-rest-api.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-shortcode.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-editor.php';
	require_once MUK_PLUGIN_DIR . 'includes/class-muk-plugin.php';
}

/**
 * Seed defaults on activation (generate API key if missing).
 */
function muk_activate() {
	muk_load_files();
	MUK_Payment_Records::install();
	MUK_Submissions::install();

	try {
		$settings = MUK_Settings::get_all();
		if ( empty( $settings['api_key'] ) ) {
			$settings['api_key'] = MUK_Settings::generate_api_key();
			update_option( MUK_Settings::OPTION_KEY, $settings );
		}
	} catch ( Exception $e ) {
		$settings            = MUK_Settings::defaults();
		$existing            = get_option( MUK_Settings::OPTION_KEY, array() );
		if ( is_array( $existing ) ) {
			$settings = wp_parse_args( $existing, $settings );
		}
		$settings['api_key'] = wp_generate_password( 48, false, false );
		update_option( MUK_Settings::OPTION_KEY, $settings );
	}
}
register_activation_hook( __FILE__, 'muk_activate' );

/**
 * Boot plugin after all plugins are loaded.
 */
function muk_init() {
	muk_load_files();
	MUK_Plugin::instance();
}
add_action( 'plugins_loaded', 'muk_init' );
