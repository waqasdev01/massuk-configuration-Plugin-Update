<?php
/**
 * Plugin bootstrap class.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Main plugin class — wires admin and REST hooks.
 */
class MUK_Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var MUK_Plugin|null
	 */
	private static $instance = null;

	/**
	 * Get singleton instance.
	 *
	 * @return MUK_Plugin
	 */
	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Register WordPress hooks.
	 */
	private function init_hooks() {
		MUK_Payment_Records::maybe_install();
		MUK_Submissions::maybe_install();
		add_action( 'admin_init', array( 'MUK_Settings', 'register' ) );
		add_action( 'admin_menu', array( 'MUK_Settings', 'add_menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
		add_action( 'rest_api_init', array( 'MUK_REST_API', 'register_routes' ) );
		add_action( 'rest_api_init', array( 'MUK_REST_API', 'register_cors' ) );
		add_action( 'init', array( 'MUK_Shortcode', 'register' ) );
		add_action( 'admin_post_muk_save_editor', array( 'MUK_Editor', 'handle_save' ) );
	}

	/**
	 * Load admin CSS on the settings page only.
	 *
	 * @param string $hook Current admin page hook.
	 */
	public function enqueue_admin_assets( $hook ) {
		if ( 'settings_page_muk-settings' !== $hook ) {
			return;
		}

		wp_enqueue_style(
			'muk-admin',
			MUK_PLUGIN_URL . 'assets/admin.css',
			array(),
			MUK_VERSION
		);
	}
}
