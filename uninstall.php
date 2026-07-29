<?php
/**
 * Cleanup on uninstall.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'muk_settings' );
delete_option( 'muk_confirmed_sessions' );
delete_option( 'muk_payments_db_version' );
delete_option( 'muk_submissions_db_version' );

// Note: the wp_muk_payments and wp_muk_submissions tables are left in place
// on uninstall so payment/registration history is not silently destroyed.
// Remove them manually via phpMyAdmin/WP-CLI if you want a full wipe:
//   DROP TABLE wp_muk_payments, wp_muk_submissions;
