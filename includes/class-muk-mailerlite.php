<?php
/** MailerLite subscriber synchronisation for completed payments. */
if ( ! defined( 'ABSPATH' ) ) exit;

class MUK_MailerLite {
	const API_URL = 'https://connect.mailerlite.com/api/subscribers';
	public static function sync_paid_customer( array $payment ) {
		$settings = MUK_Settings::get_all();
		if ( 'paid' !== $payment['payment_status'] || empty( $settings['mailerlite_api_token'] ) || empty( $payment['customer_email'] ) ) return false;
		$metadata = json_decode( $payment['metadata'], true );
		$metadata = is_array( $metadata ) ? $metadata : array();
		$has_consent = isset( $metadata['marketing_consent'] ) && in_array( strtolower( (string) $metadata['marketing_consent'] ), array( '1', 'true', 'yes', 'on' ), true );
		if ( ! empty( $settings['mailerlite_require_consent'] ) && ! $has_consent ) return false;
		// These are MailerLite's standard fields. Custom fields should be created in
		// MailerLite first and can be added through the muk_mailerlite_fields filter.
		$fields = array_filter( array( 'name' => isset( $metadata['first_name'] ) ? sanitize_text_field( $metadata['first_name'] ) : $payment['customer_name'], 'last_name' => isset( $metadata['last_name'] ) ? sanitize_text_field( $metadata['last_name'] ) : '', 'phone' => isset( $metadata['phone'] ) ? sanitize_text_field( $metadata['phone'] ) : '' ) );
		$fields = apply_filters( 'muk_mailerlite_fields', $fields, $payment, $metadata );
		$body = array( 'email' => sanitize_email( $payment['customer_email'] ), 'fields' => $fields );
		if ( ! empty( $settings['mailerlite_group_id'] ) ) $body['groups'] = array( (string) $settings['mailerlite_group_id'] );
		$response = wp_remote_post( self::API_URL, array( 'timeout' => 20, 'headers' => array( 'Authorization' => 'Bearer ' . $settings['mailerlite_api_token'], 'Content-Type' => 'application/json', 'Accept' => 'application/json' ), 'body' => wp_json_encode( $body ) ) );
		$ok = ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) >= 200 && wp_remote_retrieve_response_code( $response ) < 300;
		MUK_Payment_Records::mark_delivery( $payment['id'], 'mailerlite_status', $ok ? 'synced' : 'failed' );
		return $ok;
	}
}
