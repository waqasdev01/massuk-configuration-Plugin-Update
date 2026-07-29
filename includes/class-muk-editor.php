<?php
/**
 * Powers the "Edit" tab: lets an admin edit test.html's HTML, CSS and JS
 * directly from wp-admin instead of editing the file on disk.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MUK_Editor {

	/** Path to the file the editor reads from and writes to. */
	private static function file_path() {
		return MUK_PLUGIN_DIR . 'test.html';
	}

	/**
	 * Split test.html into its three editable parts.
	 *
	 * @return array|WP_Error { html, css, js }
	 */
	public static function read_parts() {
		$file = self::file_path();
		if ( ! file_exists( $file ) ) {
			return new WP_Error( 'muk_editor_missing_file', __( 'test.html was not found in the plugin folder.', 'massuk-configuration' ) );
		}
		$content = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $content ) {
			return new WP_Error( 'muk_editor_unreadable', __( 'test.html could not be read.', 'massuk-configuration' ) );
		}

		$css  = '';
		$js   = '';
		$html = '';

		if ( preg_match( '#<style[^>]*>(.*?)</style>#is', $content, $m ) ) {
			$css = trim( $m[1] );
		}
		if ( preg_match( '#<script[^>]*>(.*?)</script>#is', $content, $m ) ) {
			$js = trim( $m[1] );
		}
		if ( preg_match( '#<body[^>]*>(.*?)<script#is', $content, $m ) ) {
			$html = trim( $m[1] );
		}

		return array( 'html' => $html, 'css' => $css, 'js' => $js );
	}

	/**
	 * Rebuild test.html from edited parts and save it back to disk.
	 *
	 * @param string $html Body markup (without the <script> block).
	 * @param string $css  Stylesheet contents (without <style> tags).
	 * @param string $js   Script contents (without <script> tags).
	 * @return true|WP_Error
	 */
	public static function write_parts( $html, $css, $js ) {
		$file = self::file_path();
		if ( ! file_exists( $file ) || ! is_writable( $file ) ) { // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_is_writable
			return new WP_Error( 'muk_editor_not_writable', __( 'test.html is not writable by the web server. Check file permissions.', 'massuk-configuration' ) );
		}

		$doc = "<!doctype html>\n<html lang=\"en\">\n  <head>\n    <meta charset=\"UTF-8\" />\n    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\" />\n    <title>MASSUK Registration</title>\n    <style>\n"
			. $css
			. "\n    </style>\n  </head>\n  <body>\n"
			. $html
			. "\n    <script>\n"
			. $js
			. "\n    </script>\n  </body>\n</html>\n";

		$written = file_put_contents( $file, $doc ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
		if ( false === $written ) {
			return new WP_Error( 'muk_editor_write_failed', __( 'Could not save test.html.', 'massuk-configuration' ) );
		}
		return true;
	}

	/**
	 * admin-post.php handler for saving the editor form.
	 */
	public static function handle_save() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to do this.', 'massuk-configuration' ) );
		}
		check_admin_referer( 'muk_save_editor' );

		$html = isset( $_POST['muk_editor_html'] ) ? wp_unslash( $_POST['muk_editor_html'] ) : '';
		$css  = isset( $_POST['muk_editor_css'] ) ? wp_unslash( $_POST['muk_editor_css'] ) : '';
		$js   = isset( $_POST['muk_editor_js'] ) ? wp_unslash( $_POST['muk_editor_js'] ) : '';

		// Intentionally not run through wp_kses: this is trusted admin-only
		// code for the registration page's own markup/script/styles, edited
		// by the same person who could otherwise edit the file directly.
		$result = self::write_parts( $html, $css, $js );

		$redirect = add_query_arg(
			array(
				'page' => 'muk-settings',
				'tab'  => 'edit',
				'muk_saved' => is_wp_error( $result ) ? 'error' : '1',
			),
			admin_url( 'options-general.php' )
		);
		if ( is_wp_error( $result ) ) {
			set_transient( 'muk_editor_error', $result->get_error_message(), 60 );
		}
		wp_safe_redirect( $redirect );
		exit;
	}
}
