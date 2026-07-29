<?php
/**
 * Dedicated database table for complete registration form submissions.
 *
 * This is separate from MUK_Payment_Records (which tracks the Stripe
 * payment/webhook lifecycle). This table stores the full form the
 * delegate actually filled in — every field from test.html — both as
 * queryable columns for the common fields and as a raw JSON copy so
 * nothing submitted is ever lost, even if the form changes later.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class MUK_Submissions {

	const DB_VERSION = '1.0';
	const OPTION_KEY = 'muk_submissions_db_version';

	public static function table_name() {
		global $wpdb;
		return $wpdb->prefix . 'muk_submissions';
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
			registration_type varchar(40) NOT NULL DEFAULT '',
			full_name varchar(255) NOT NULL DEFAULT '',
			email varchar(320) NOT NULL DEFAULT '',
			phone varchar(60) NOT NULL DEFAULT '',
			date_of_birth varchar(20) NOT NULL DEFAULT '',
			guardian_name varchar(255) NOT NULL DEFAULT '',
			guardian_contact varchar(255) NOT NULL DEFAULT '',
			dietary_requirements varchar(255) NOT NULL DEFAULT '',
			medical_details longtext NULL,
			session_selection varchar(120) NOT NULL DEFAULT '',
			sparring_kit_confirmed tinyint(1) NOT NULL DEFAULT 0,
			promo_code varchar(60) NOT NULL DEFAULT '',
			amount bigint(20) NOT NULL DEFAULT 0,
			currency varchar(10) NOT NULL DEFAULT '',
			payment_status varchar(40) NOT NULL DEFAULT 'pending',
			form_data longtext NULL,
			created_at datetime NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			KEY checkout_session_id (checkout_session_id),
			KEY registration_type (registration_type),
			KEY payment_status (payment_status),
			KEY email (email)
		) $charset;";
		dbDelta( $sql );
		update_option( self::OPTION_KEY, self::DB_VERSION );
	}

	public static function get_by_session( $session_id ) {
		global $wpdb;
		return $wpdb->get_row( $wpdb->prepare( 'SELECT * FROM ' . self::table_name() . ' WHERE checkout_session_id = %s', $session_id ), ARRAY_A );
	}

	/**
	 * Record (or update) a submission when a checkout session is created.
	 *
	 * @param array $session Stripe session (id, etc).
	 * @param array $args    Validated checkout args, including customer_data (the full form).
	 */
	public static function create_from_checkout( array $session, array $args ) {
		global $wpdb;
		$now  = current_time( 'mysql', true );
		$form = ! empty( $args['customer_data'] ) && is_array( $args['customer_data'] ) ? $args['customer_data'] : array();

		$data = array(
			'checkout_session_id'    => sanitize_text_field( $session['id'] ),
			'registration_type'      => sanitize_key( isset( $args['registration_type'] ) ? $args['registration_type'] : '' ),
			'full_name'              => self::field( $form, 'first_name' ) . ' ' . self::field( $form, 'last_name' ),
			'email'                  => sanitize_email( self::field( $form, 'email', $args['customer_email'] ) ),
			'phone'                  => self::field( $form, 'phone' ),
			'date_of_birth'          => self::field( $form, 'date_of_birth' ),
			'guardian_name'          => trim( self::field( $form, 'guardian1_email' ) . ' ' . self::field( $form, 'guardian1_phone' ) ),
			'guardian_contact'       => trim( self::field( $form, 'guardian2_email' ) . ' ' . self::field( $form, 'guardian2_phone' ) ),
			'dietary_requirements'   => self::field( $form, 'dietary' ) === 'Other' ? self::field( $form, 'dietary_other' ) : self::field( $form, 'dietary' ),
			'medical_details'        => wp_json_encode(
				array(
					'medical'       => self::field( $form, 'medical' ),
					'medical_details' => self::field( $form, 'medical_details' ),
					'sen'           => self::field( $form, 'sen' ),
					'sen_details'   => self::field( $form, 'sen_details' ),
					'access'        => self::field( $form, 'access' ),
					'access_details' => self::field( $form, 'access_details' ),
					'othersupport'  => self::field( $form, 'othersupport' ),
					'othersupport_details' => self::field( $form, 'othersupport_details' ),
				)
			),
			'session_selection'      => trim( self::field( $form, 'morning' ) . '/' . self::field( $form, 'afternoon' ) ),
			'sparring_kit_confirmed' => 'true' === self::field( $form, 'sparring_kit_ack' ) ? 1 : 0,
			'promo_code'             => sanitize_text_field( isset( $args['promo_code'] ) ? $args['promo_code'] : '' ),
			'amount'                 => (int) $args['amount'],
			'currency'               => strtolower( sanitize_text_field( $args['currency'] ) ),
			'payment_status'         => 'pending',
			'form_data'              => wp_json_encode( $form ),
			'created_at'             => $now,
			'updated_at'             => $now,
		);

		$existing = self::get_by_session( $data['checkout_session_id'] );
		if ( $existing ) {
			$wpdb->update( self::table_name(), $data, array( 'id' => (int) $existing['id'] ) );
			return self::get_by_session( $data['checkout_session_id'] );
		}
		$wpdb->insert( self::table_name(), $data );
		return self::get_by_session( $data['checkout_session_id'] );
	}

	/**
	 * Update payment status for a submission after a Stripe webhook event.
	 *
	 * @param string $session_id Checkout session id.
	 * @param string $status     paid|failed|cancelled|pending.
	 */
	public static function set_status( $session_id, $status ) {
		global $wpdb;
		if ( '' === (string) $session_id ) {
			return;
		}
		$wpdb->update(
			self::table_name(),
			array(
				'payment_status' => sanitize_key( $status ),
				'updated_at'     => current_time( 'mysql', true ),
			),
			array( 'checkout_session_id' => sanitize_text_field( $session_id ) )
		);
	}

	/**
	 * Fetch a page of submissions for the admin list view.
	 *
	 * @param array $args { registration_type, payment_status, search, paged, per_page }
	 * @return array { items, total }
	 */
	public static function query( array $args = array() ) {
		global $wpdb;
		$table    = self::table_name();
		$where    = array( '1=1' );
		$values   = array();

		if ( ! empty( $args['registration_type'] ) ) {
			$where[]  = 'registration_type = %s';
			$values[] = sanitize_key( $args['registration_type'] );
		}
		if ( ! empty( $args['payment_status'] ) ) {
			$where[]  = 'payment_status = %s';
			$values[] = sanitize_key( $args['payment_status'] );
		}
		if ( ! empty( $args['search'] ) ) {
			$where[]  = '(full_name LIKE %s OR email LIKE %s OR checkout_session_id LIKE %s)';
			$like     = '%' . $wpdb->esc_like( $args['search'] ) . '%';
			$values[] = $like;
			$values[] = $like;
			$values[] = $like;
		}

		$per_page = ! empty( $args['per_page'] ) ? (int) $args['per_page'] : 20;
		$paged    = ! empty( $args['paged'] ) ? max( 1, (int) $args['paged'] ) : 1;
		$offset   = ( $paged - 1 ) * $per_page;

		$where_sql = implode( ' AND ', $where );

		$count_sql = "SELECT COUNT(*) FROM $table WHERE $where_sql";
		$total     = (int) ( $values ? $wpdb->get_var( $wpdb->prepare( $count_sql, $values ) ) : $wpdb->get_var( $count_sql ) );

		$list_sql    = "SELECT * FROM $table WHERE $where_sql ORDER BY created_at DESC LIMIT %d OFFSET %d";
		$list_values = array_merge( $values, array( $per_page, $offset ) );
		$items       = $wpdb->get_results( $wpdb->prepare( $list_sql, $list_values ), ARRAY_A );

		return array(
			'items' => $items ? $items : array(),
			'total' => $total,
		);
	}

	private static function field( array $form, $key, $fallback = '' ) {
		return isset( $form[ $key ] ) && '' !== $form[ $key ] ? sanitize_text_field( (string) $form[ $key ] ) : (string) $fallback;
	}
}
