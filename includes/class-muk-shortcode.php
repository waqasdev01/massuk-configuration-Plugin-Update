<?php
/**
 * Frontend shortcode: renders the registration page markup from test.html
 * so it can be dropped into any page/post with [muk_registration_form]
 * instead of pasting the raw HTML file into a text block.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MUK_Shortcode {

	/**
	 * Register the shortcode.
	 */
	public static function register() {
		add_shortcode( 'muk_registration_form', array( __CLASS__, 'render' ) );
	}

	/**
	 * Output the registration form.
	 *
	 * The form lives in test.html (self-contained style + markup + script).
	 * We pull the <style> and <body> contents out of that file at render
	 * time, so editing test.html is enough to update the shortcode output
	 * too — there is nothing to keep in sync by hand.
	 *
	 * @return string
	 */
	public static function render() {
		$file = MUK_PLUGIN_DIR . 'test.html';

		if ( ! file_exists( $file ) ) {
			return '<p>' . esc_html__( 'Registration form file (test.html) was not found in the plugin folder.', 'massuk-configuration' ) . '</p>';
		}

		$html = file_get_contents( $file ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $html || '' === trim( (string) $html ) ) {
			return '<p>' . esc_html__( 'Registration form file could not be read.', 'massuk-configuration' ) . '</p>';
		}

		$style = '';
		if ( preg_match( '#<style[^>]*>(.*?)</style>#is', $html, $m ) ) {
			$style = $m[1];
		}

		$body = '';
		if ( preg_match( '#<body[^>]*>(.*)</body>#is', $html, $m ) ) {
			$body = $m[1];
		}

		if ( '' === trim( $body ) ) {
			return '<p>' . esc_html__( 'Registration form markup could not be parsed from test.html.', 'massuk-configuration' ) . '</p>';
		}

		// Auto-fill the plugin API key from settings so nobody has to hand-edit
		// test.html's MUK_CONFIG.apiKey placeholder after every change.
		$api_key = (string) MUK_Settings::get( 'api_key' );
		$body    = str_replace( 'REPLACE_WITH_PLUGIN_API_KEY', esc_js( $api_key ), $body );

		// Note: these styles are page-wide (same as pasting test.html's
		// <style> block into an Elementor HTML widget), not scoped to the
		// wrapper below. Class names inside test.html were written for a
		// standalone page, so keep this shortcode to one instance per page.

		return '<div class="muk-registration-form-wrapper">'
			. ( $style ? '<style>' . $style . '</style>' : '' )
			. $body
			. '</div>';
	}
}
