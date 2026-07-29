<?php
/**
 * REST API.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers and handles Stripe Checkout REST routes.
 */
class MUK_REST_API {

	/**
	 * REST namespace.
	 */
	const REST_NAMESPACE = 'muk/v1';

	/**
	 * Register REST routes.
	 */
	public static function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/checkout',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'create_checkout' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
		register_rest_route(
			self::REST_NAMESPACE,
			'/config',
			array(
				'methods'             => 'GET,POST',
				'callback'            => array( __CLASS__, 'get_config' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/stripe-webhook',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'stripe_webhook' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/availability',
			array(
				'methods'             => 'GET,POST',
				'callback'            => array( __CLASS__, 'get_availability' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/confirm',
			array(
				'methods'             => 'POST',
				'callback'            => array( __CLASS__, 'confirm_payment' ),
				'permission_callback' => array( __CLASS__, 'check_permission' ),
			)
		);
	}

	/**
	 * Allow browser calls from external registration pages.
	 */
	public static function register_cors() {
		add_filter( 'rest_allowed_cors_headers', array( __CLASS__, 'allow_api_key_header' ) );
	}

	/**
	 * Permit the plugin API key header in CORS preflight.
	 *
	 * @param string[] $headers Allowed headers.
	 * @return string[]
	 */
	public static function allow_api_key_header( $headers ) {
		$headers[] = 'X-MUK-API-Key';
		return $headers;
	}

	/**
	 * Require a matching X-MUK-API-Key header.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return bool|WP_Error
	 */
	public static function check_permission( WP_REST_Request $request ) {
		$expected = (string) MUK_Settings::get( 'api_key' );

		if ( '' === $expected ) {
			return new WP_Error(
				'muk_api_key_not_configured',
				__( 'Plugin API key is not configured.', 'massuk-configuration' ),
				array( 'status' => 503 )
			);
		}

		$provided = $request->get_header( 'x-muk-api-key' );
		if ( null === $provided || '' === $provided ) {
			$provided = $request->get_header( 'X-MUK-API-Key' );
		}

		if ( ! is_string( $provided ) || ! hash_equals( $expected, $provided ) ) {
			return new WP_Error(
				'muk_unauthorized',
				__( 'Invalid or missing API key.', 'massuk-configuration' ),
				array( 'status' => 401 )
			);
		}

		return true;
	}

	/**
	 * Ensure success URL includes Stripe session id placeholder.
	 *
	 * @param string $url Success URL.
	 * @return string
	 */
	public static function with_session_id_placeholder( $url ) {
		$url = (string) $url;
		if ( false !== strpos( $url, '{CHECKOUT_SESSION_ID}' ) ) {
			return $url;
		}

		$sep = ( false === strpos( $url, '?' ) ) ? '?' : '&';
		return $url . $sep . 'session_id={CHECKOUT_SESSION_ID}';
	}

	/**
	 * Availability snapshot.
	 *
	 * @return WP_REST_Response
	 */
	public static function get_availability() {
		return self::no_cache_response(
			array(
				'availability' => MUK_Registrations::availability(),
			)
		);
	}

	/** Frontend configuration sourced from plugin settings. */
	public static function get_config() {
		return self::no_cache_response( array(
			'price'        => array(
				'symposium' => MUK_Settings::current_price( 'symposium' ),
				'camp'      => MUK_Settings::current_price( 'camp' ),
			),
			'availability' => MUK_Registrations::availability(),
			'products'     => array(
				'symposium' => array( 'product_name' => MUK_Settings::get( 'symposium_product_name' ) ),
				'camp'      => array( 'product_name' => MUK_Settings::get( 'camp_product_name' ) ),
			),
			'copy'         => array(
				'intro_text'            => (string) MUK_Settings::get( 'intro_text' ),
				'show_symposium_promo'  => (bool) MUK_Settings::get( 'show_symposium_promo', true ),
				'seats_label_symposium' => (string) MUK_Settings::get( 'seats_label_symposium' ),
				'seats_label_camp'      => (string) MUK_Settings::get( 'seats_label_camp' ),
				'stripe_note'           => (string) MUK_Settings::get( 'stripe_note' ),
			),
		) );
	}

	/**
	 * Build a WP_REST_Response with headers that stop it being cached by
	 * page-cache plugins, CDNs, or the browser. Pricing/capacity/copy must
	 * always reflect what was just saved in wp-admin, never a stale copy.
	 *
	 * @param array $data Response body.
	 * @return WP_REST_Response
	 */
	private static function no_cache_response( array $data ) {
		$response = new WP_REST_Response( $data, 200 );
		$response->header( 'Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		return $response;
	}

	/**
	 * Validate and normalize checkout request params.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return array|WP_Error Normalized args or error.
	 */
	public static function validate_request( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$amount = isset( $params['amount'] ) ? (int) $params['amount'] : 0;
		if ( $amount < 1 ) {
			return new WP_Error(
				'muk_invalid_amount',
				__( 'amount must be a positive integer in the smallest currency unit.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$currency = isset( $params['currency'] ) ? strtolower( sanitize_text_field( $params['currency'] ) ) : '';
		if ( ! preg_match( '/^[a-z]{3}$/', $currency ) ) {
			return new WP_Error(
				'muk_invalid_currency',
				__( 'currency must be a 3-letter ISO code (e.g. usd).', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$product_name = isset( $params['product_name'] ) ? sanitize_text_field( $params['product_name'] ) : '';
		if ( '' === $product_name ) {
			return new WP_Error(
				'muk_invalid_product_name',
				__( 'product_name is required.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$quantity = isset( $params['quantity'] ) ? (int) $params['quantity'] : 1;
		if ( $quantity < 1 ) {
			return new WP_Error(
				'muk_invalid_quantity',
				__( 'quantity must be at least 1.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$customer_email = '';
		if ( ! empty( $params['customer_email'] ) ) {
			$customer_email = sanitize_email( $params['customer_email'] );
			if ( ! is_email( $customer_email ) ) {
				return new WP_Error(
					'muk_invalid_email',
					__( 'customer_email must be a valid email address.', 'massuk-configuration' ),
					array( 'status' => 400 )
				);
			}
		}

		$metadata = array();
		if ( isset( $params['metadata'] ) && is_array( $params['metadata'] ) ) {
			foreach ( $params['metadata'] as $key => $value ) {
				$clean_key = sanitize_key( (string) $key );
				if ( '' === $clean_key ) {
					continue;
				}
				$metadata[ $clean_key ] = sanitize_text_field( (string) $value );
			}
		}

		// Keep complete submitted form data in our own database. Stripe metadata is
		// deliberately kept small because Stripe limits both keys and value lengths.
		$customer_data = array();
		if ( isset( $params['customer_data'] ) && is_array( $params['customer_data'] ) ) {
			foreach ( $params['customer_data'] as $key => $value ) {
				$key = sanitize_key( (string) $key );
				if ( '' !== $key && is_scalar( $value ) ) $customer_data[ $key ] = sanitize_textarea_field( (string) $value );
			}
		}

		$registration_type = '';
		if ( ! empty( $metadata['registration_type'] ) ) {
			$registration_type = sanitize_key( $metadata['registration_type'] );
		} elseif ( ! empty( $params['registration_type'] ) ) {
			$registration_type = sanitize_key( (string) $params['registration_type'] );
		}

		if ( '' === $registration_type ) {
			return new WP_Error(
				'muk_missing_registration_type',
				__( 'registration_type is required (symposium or camp).', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}
		$current_price = MUK_Settings::current_price( $registration_type );
		if ( empty( $current_price['open'] ) || $amount !== (int) $current_price['amount'] ) {
			return new WP_Error( 'muk_invalid_price', __( 'The selected registration price is no longer available. Please refresh the page and try again.', 'massuk-configuration' ), array( 'status' => 409 ) );
		}

		$capacity_ok = MUK_Registrations::assert_has_capacity( $registration_type );
		if ( is_wp_error( $capacity_ok ) ) {
			return $capacity_ok;
		}

		$metadata['registration_type'] = $registration_type;

		$success_url = isset( $params['success_url'] ) ? esc_url_raw( $params['success_url'] ) : '';
		$cancel_url  = isset( $params['cancel_url'] ) ? esc_url_raw( $params['cancel_url'] ) : '';

		$promo_code = '';
		if ( ! empty( $params['promo_code'] ) ) {
			$promo_code = sanitize_text_field( (string) $params['promo_code'] );
		}

		$priced = MUK_Settings::apply_promo_code( $amount, $promo_code );
		if ( is_wp_error( $priced ) ) {
			return $priced;
		}

		if ( ! empty( $priced['promo_code'] ) ) {
			$metadata['promo_code']      = $priced['promo_code'];
			$metadata['discount_type']   = (string) $priced['discount_type'];
			$metadata['discount_value']  = (string) $priced['discount_value'];
			$metadata['discount_amount'] = (string) $priced['discount_amount'];
			$metadata['original_amount'] = (string) $amount;
		}

		return array(
			'amount'             => (int) $priced['amount'],
			'original_amount'    => $amount,
			'currency'           => $currency,
			'product_name'       => $product_name,
			'quantity'           => $quantity,
			'customer_email'     => $customer_email,
			'metadata'           => $metadata,
			'customer_data'      => $customer_data,
			'success_url'        => self::with_session_id_placeholder( MUK_Settings::resolve_success_url( $success_url ) ),
			'cancel_url'         => MUK_Settings::resolve_cancel_url( $cancel_url ),
			'promo_code'         => $priced['promo_code'],
			'discount_amount'    => (int) $priced['discount_amount'],
			'registration_type'  => $registration_type,
		);
	}

	/**
	 * Create a Stripe Checkout Session and return id + url.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function create_checkout( WP_REST_Request $request ) {
		$args = self::validate_request( $request );
		if ( is_wp_error( $args ) ) {
			return $args;
		}

		$client  = new MUK_Stripe_Client( MUK_Settings::get_active_secret_key() );
		$session = $client->create_checkout_session( $args );

		if ( is_wp_error( $session ) ) {
			return $session;
		}

		if ( empty( $session['id'] ) || empty( $session['url'] ) ) {
			return new WP_Error(
				'muk_incomplete_session',
				__( 'Stripe did not return a complete Checkout Session.', 'massuk-configuration' ),
				array( 'status' => 502 )
			);
		}

		MUK_Payment_Records::create_checkout( $session, $args );
		MUK_Submissions::create_from_checkout( $session, $args );

		return new WP_REST_Response(
			array(
				'id'                => $session['id'],
				'url'               => $session['url'],
				'amount'            => $args['amount'],
				'original_amount'   => $args['original_amount'],
				'promo_code'        => $args['promo_code'],
				'discount_amount'   => $args['discount_amount'],
				'registration_type' => $args['registration_type'],
				'availability'      => MUK_Registrations::availability(),
			),
			200
		);
	}

	/**
	 * Confirm a paid Checkout Session and update seat / promo counters.
	 *
	 * @param WP_REST_Request $request Request.
	 * @return WP_REST_Response|WP_Error
	 */
	public static function confirm_payment( WP_REST_Request $request ) {
		$params = $request->get_json_params();
		if ( ! is_array( $params ) ) {
			$params = array();
		}

		$session_id = isset( $params['session_id'] ) ? sanitize_text_field( (string) $params['session_id'] ) : '';
		if ( '' === $session_id ) {
			return new WP_Error(
				'muk_missing_session',
				__( 'session_id is required.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$client  = new MUK_Stripe_Client( MUK_Settings::get_active_secret_key() );
		$session = $client->get_checkout_session( $session_id );
		if ( is_wp_error( $session ) ) {
			return $session;
		}

		$payment_status = isset( $session['payment_status'] ) ? (string) $session['payment_status'] : '';
		if ( 'paid' !== $payment_status ) {
			return new WP_Error(
				'muk_payment_not_paid',
				__( 'Payment is not completed yet.', 'massuk-configuration' ),
				array( 'status' => 402 )
			);
		}

		$payment = MUK_Payment_Records::apply_event( array( 'type' => 'checkout.session.completed', 'data' => array( 'object' => $session ) ) );
		if ( is_wp_error( $payment ) ) return $payment;
		self::handle_payment_change( $payment );
		return new WP_REST_Response( array( 'session_id' => $session_id, 'payment_status' => $payment['payment_status'] ), 200 );
	}

	/** Receive Stripe's signed webhook. This route intentionally has no browser API key. */
	public static function stripe_webhook( WP_REST_Request $request ) {
		$payload   = $request->get_body();
		$signature = $request->get_header( 'stripe-signature' );
		if ( ! self::valid_stripe_signature( $payload, $signature ) ) {
			return new WP_Error( 'muk_invalid_webhook_signature', __( 'Invalid Stripe webhook signature.', 'massuk-configuration' ), array( 'status' => 400 ) );
		}
		$event = json_decode( $payload, true );
		if ( ! is_array( $event ) || empty( $event['type'] ) || empty( $event['data']['object'] ) ) {
			return new WP_Error( 'muk_invalid_webhook_payload', __( 'Invalid Stripe webhook payload.', 'massuk-configuration' ), array( 'status' => 400 ) );
		}
		$payment = MUK_Payment_Records::apply_event( $event );
		if ( is_wp_error( $payment ) ) {
			// Acknowledge unrelated Stripe events so Stripe does not retry them forever.
			return new WP_REST_Response( array( 'received' => true, 'ignored' => true ), 200 );
		}
		self::handle_payment_change( $payment );
		return new WP_REST_Response( array( 'received' => true ), 200 );
	}

	private static function valid_stripe_signature( $payload, $header ) {
		$settings = MUK_Settings::get_all();
		$secret = ( 'live' === $settings['mode'] ) ? $settings['live_webhook_secret'] : $settings['test_webhook_secret'];
		if ( '' === $secret || '' === $header || '' === $payload ) return false;
		$timestamp = '';
		$signatures = array();
		foreach ( explode( ',', $header ) as $item ) {
			$pair = explode( '=', trim( $item ), 2 );
			if ( 2 !== count( $pair ) ) continue;
			if ( 't' === $pair[0] ) $timestamp = $pair[1];
			if ( 'v1' === $pair[0] ) $signatures[] = $pair[1];
		}
		if ( ! ctype_digit( $timestamp ) || abs( time() - (int) $timestamp ) > 300 ) return false;
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );
		foreach ( $signatures as $signature ) if ( hash_equals( $expected, $signature ) ) return true;
		return false;
	}

	private static function handle_payment_change( array $payment ) {
		$status_changed = $payment['previous_status'] !== $payment['payment_status'];
		if ( ! empty( $payment['checkout_session_id'] ) ) {
			MUK_Submissions::set_status( $payment['checkout_session_id'], $payment['payment_status'] );
		}
		if ( ! $status_changed ) return;
		if ( 'paid' === $payment['payment_status'] ) {
			$metadata = json_decode( $payment['metadata'], true );
			$metadata = is_array( $metadata ) ? $metadata : array();
			if ( ! empty( $payment['checkout_session_id'] ) && ! empty( $metadata['registration_type'] ) ) {
				MUK_Registrations::confirm_paid_session( $payment['checkout_session_id'], $metadata['registration_type'], isset( $metadata['promo_code'] ) ? $metadata['promo_code'] : '' );
			}
			MUK_MailerLite::sync_paid_customer( $payment );
		}
		MUK_Email_Service::send_for_payment( $payment );
	}
}
