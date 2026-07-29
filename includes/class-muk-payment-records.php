<?php
/**
 * Persistent Stripe payment records.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MUK_Payment_Records {
	const DB_VERSION = '1.0';
	const OPTION_KEY = 'muk_payments_db_version';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'muk_payments';
	}

	public static function maybe_install() {
		if ( self::DB_VERSION !== get_option( self::OPTION_KEY ) ) {
			self::install();
		}
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$table   = self::table_name();
		$charset = $wpdb->get_charset_collate();
		$sql     = "CREATE TABLE $table (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			checkout_session_id varchar(255) NOT NULL DEFAULT '',
			payment_intent_id varchar(255) NOT NULL DEFAULT '',
			transaction_id varchar(255) NOT NULL DEFAULT '',
			customer_email varchar(320) NOT NULL DEFAULT '',
			customer_name varchar(255) NOT NULL DEFAULT '',
			order_reference varchar(255) NOT NULL DEFAULT '',
			amount bigint(20) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			payment_status varchar(40) NOT NULL DEFAULT 'pending',
			gateway_response longtext NULL,
			webhook_response longtext NULL,
			metadata longtext NULL,
			email_status varchar(40) NOT NULL DEFAULT '',
			mailerlite_status varchar(40) NOT NULL DEFAULT '',
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY checkout_session_id (checkout_session_id),
			KEY payment_intent_id (payment_intent_id),
			KEY transaction_id (transaction_id),
			KEY payment_status (payment_status),
			KEY customer_email (customer_email)
		) $charset;";
		dbDelta( $sql );
		update_option( self::OPTION_KEY, self::DB_VERSION );
	}

	public static function get_by_session( $session_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE checkout_session_id = %s', $session_id ), ARRAY_A );
	}

	public static function create_checkout( array $session, array $args ) {
		global $wpdb;
		$now      = current_time( 'mysql', true );
		$record_metadata = ! empty( $args['metadata'] ) ? $args['metadata'] : array();
		if ( ! empty( $args['customer_data'] ) ) $record_metadata['form_data'] = $args['customer_data'];
		$metadata = wp_json_encode( $record_metadata );
		$data     = array(
			'checkout_session_id' => sanitize_text_field( $session['id'] ),
			'customer_email'      => sanitize_email( $args['customer_email'] ),
			'customer_name'       => self::name_from_metadata( $args['metadata'] ),
			'order_reference'     => self::order_reference( $args['metadata'], $session ),
			'amount'              => (int) $args['amount'],
			'currency'            => strtolower( sanitize_text_field( $args['currency'] ) ),
			'payment_status'      => 'pending',
			'gateway_response'    => wp_json_encode( $session ),
			'metadata'            => $metadata,
			'created_at'          => $now,
			'updated_at'          => $now,
		);
		$existing = self::get_by_session( $data['checkout_session_id'] );
		if ( $existing ) {
			$wpdb->update( self::table_name(), $data, array( 'id' => (int) $existing['id'] ) );
			return self::get_by_session( $data['checkout_session_id'] );
		}
		$wpdb->insert( self::table_name(), $data );
		return self::get_by_session( $data['checkout_session_id'] );
	}

	public static function apply_event( array $event ) {
		global $wpdb;
		$object = isset( $event['data']['object'] ) && is_array( $event['data']['object'] ) ? $event['data']['object'] : array();
		$type   = isset( $event['type'] ) ? (string) $event['type'] : '';
		$session_id = isset( $object['id'] ) && 0 === strpos( (string) $object['id'], 'cs_' ) ? (string) $object['id'] : ( isset( $object['metadata']['checkout_session_id'] ) ? (string) $object['metadata']['checkout_session_id'] : '' );
		$intent_id  = isset( $object['payment_intent'] ) ? (string) $object['payment_intent'] : ( isset( $object['id'] ) && 0 === strpos( (string) $object['id'], 'pi_' ) ? (string) $object['id'] : '' );
		$row = $session_id ? self::get_by_session( $session_id ) : null;
		if ( ! $row && $intent_id ) {
			$row = $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE payment_intent_id = %s', $intent_id ), ARRAY_A );
		}
		if ( ! $row ) {
			return new WP_Error( 'muk_payment_not_found', __( 'No payment record was found for this Stripe event.', 'massuk-configuration' ) );
		}

		$status = self::status_for_event( $type, $object, $row['payment_status'] );
		$meta   = json_decode( $row['metadata'], true );
		$meta   = is_array( $meta ) ? $meta : array();
		if ( ! empty( $object['metadata'] ) && is_array( $object['metadata'] ) ) {
			$meta = array_merge( $meta, $object['metadata'] );
		}
		$data = array(
			'payment_intent_id' => $intent_id ? sanitize_text_field( $intent_id ) : $row['payment_intent_id'],
			'transaction_id'    => isset( $object['id'] ) ? sanitize_text_field( $object['id'] ) : $row['transaction_id'],
			'customer_email'    => self::event_email( $object, $row['customer_email'] ),
			'customer_name'     => self::event_name( $object, $row['customer_name'] ),
			'amount'            => isset( $object['amount_total'] ) ? (int) $object['amount_total'] : ( isset( $object['amount_received'] ) ? (int) $object['amount_received'] : (int) $row['amount'] ),
			'currency'          => isset( $object['currency'] ) ? strtolower( sanitize_text_field( $object['currency'] ) ) : $row['currency'],
			'payment_status'    => $status,
			'gateway_response'  => wp_json_encode( $object ),
			'webhook_response'  => wp_json_encode( $event ),
			'metadata'          => wp_json_encode( $meta ),
			'updated_at'        => current_time( 'mysql', true ),
		);
		$wpdb->update( self::table_name(), $data, array( 'id' => (int) $row['id'] ) );
		$current = array_merge( $row, $data );
		$current['previous_status'] = $row['payment_status'];
		return $current;
	}

	public static function mark_delivery( $id, $field, $status ) {
		if ( ! in_array( $field, array( 'email_status', 'mailerlite_status' ), true ) ) {
			return;
		}
		global $wpdb;
		$wpdb->update( self::table_name(), array( $field => sanitize_key( $status ), 'updated_at' => current_time( 'mysql', true ) ), array( 'id' => (int) $id ) );
	}

	private static function status_for_event( $type, array $object, $fallback ) {
		if ( in_array( $type, array( 'checkout.session.completed', 'checkout.session.async_payment_succeeded', 'payment_intent.succeeded' ), true ) ) {
			return ( isset( $object['payment_status'] ) && 'unpaid' === $object['payment_status'] ) ? 'pending' : 'paid';
		}
		if ( in_array( $type, array( 'checkout.session.async_payment_failed', 'payment_intent.payment_failed' ), true ) ) return 'failed';
		if ( in_array( $type, array( 'checkout.session.expired', 'payment_intent.canceled' ), true ) ) return 'cancelled';
		return sanitize_key( $fallback ? $fallback : 'pending' );
	}

	private static function name_from_metadata( array $metadata ) { return trim( ( isset( $metadata['first_name'] ) ? $metadata['first_name'] : '' ) . ' ' . ( isset( $metadata['last_name'] ) ? $metadata['last_name'] : '' ) ); }
	private static function order_reference( array $metadata, array $session ) { return ! empty( $metadata['order_reference'] ) ? sanitize_text_field( $metadata['order_reference'] ) : ( isset( $session['client_reference_id'] ) ? sanitize_text_field( $session['client_reference_id'] ) : '' ); }
	private static function event_email( array $object, $fallback ) { return ! empty( $object['customer_details']['email'] ) ? sanitize_email( $object['customer_details']['email'] ) : ( ! empty( $object['receipt_email'] ) ? sanitize_email( $object['receipt_email'] ) : $fallback ); }
	private static function event_name( array $object, $fallback ) { return ! empty( $object['customer_details']['name'] ) ? sanitize_text_field( $object['customer_details']['name'] ) : $fallback; }
}
