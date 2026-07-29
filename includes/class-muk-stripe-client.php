<?php
/**
 * Stripe HTTP client.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Thin Stripe API client using wp_remote_post.
 */
class MUK_Stripe_Client {

	/**
	 * Stripe API base URL.
	 */
	const API_BASE = 'https://api.stripe.com/v1';

	/**
	 * Active Stripe secret key.
	 *
	 * @var string
	 */
	private $secret_key;

	/**
	 * Constructor.
	 *
	 * @param string $secret_key Stripe secret key.
	 */
	public function __construct( $secret_key ) {
		$this->secret_key = (string) $secret_key;
	}

	/**
	 * Whether a secret key is configured.
	 *
	 * @return bool
	 */
	public function has_secret_key() {
		return '' !== $this->secret_key;
	}

	/**
	 * Perform a POST request to the Stripe API.
	 *
	 * @param string $path Endpoint path (e.g. /checkout/sessions).
	 * @param array  $body Form-encoded body params.
	 * @return array|WP_Error Decoded response or error.
	 */
	public function post( $path, array $body ) {
		if ( ! $this->has_secret_key() ) {
			return new WP_Error(
				'muk_missing_secret',
				__( 'Stripe secret key is not configured for the active mode.', 'massuk-configuration' ),
				array( 'status' => 500 )
			);
		}

		$url = self::API_BASE . $path;

		$response = wp_remote_post(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->secret_key,
					'Content-Type'  => 'application/x-www-form-urlencoded',
				),
				'body'    => $body,
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'muk_http_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'muk_invalid_response',
				__( 'Invalid response from Stripe.', 'massuk-configuration' ),
				array( 'status' => 502 )
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error']['message'] )
				? $data['error']['message']
				: __( 'Stripe API request failed.', 'massuk-configuration' );

			return new WP_Error(
				'muk_stripe_error',
				$message,
				array(
					'status' => $code >= 400 && $code < 600 ? $code : 502,
					'stripe' => $data['error'] ?? null,
				)
			);
		}

		return $data;
	}

	/**
	 * Perform a GET request to the Stripe API.
	 *
	 * @param string $path Endpoint path.
	 * @return array|WP_Error
	 */
	public function get( $path ) {
		if ( ! $this->has_secret_key() ) {
			return new WP_Error(
				'muk_missing_secret',
				__( 'Stripe secret key is not configured for the active mode.', 'massuk-configuration' ),
				array( 'status' => 500 )
			);
		}

		$url = self::API_BASE . $path;

		$response = wp_remote_get(
			$url,
			array(
				'timeout' => 30,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->secret_key,
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			return new WP_Error(
				'muk_http_error',
				$response->get_error_message(),
				array( 'status' => 502 )
			);
		}

		$code = (int) wp_remote_retrieve_response_code( $response );
		$raw  = wp_remote_retrieve_body( $response );
		$data = json_decode( $raw, true );

		if ( ! is_array( $data ) ) {
			return new WP_Error(
				'muk_invalid_response',
				__( 'Invalid response from Stripe.', 'massuk-configuration' ),
				array( 'status' => 502 )
			);
		}

		if ( $code < 200 || $code >= 300 ) {
			$message = isset( $data['error']['message'] )
				? $data['error']['message']
				: __( 'Stripe API request failed.', 'massuk-configuration' );

			return new WP_Error(
				'muk_stripe_error',
				$message,
				array(
					'status' => $code >= 400 && $code < 600 ? $code : 502,
					'stripe' => $data['error'] ?? null,
				)
			);
		}

		return $data;
	}

	/**
	 * Retrieve a Checkout Session by id.
	 *
	 * @param string $session_id Session id.
	 * @return array|WP_Error
	 */
	public function get_checkout_session( $session_id ) {
		$session_id = rawurlencode( (string) $session_id );
		return $this->get( '/checkout/sessions/' . $session_id );
	}

	/**
	 * Create a Stripe Checkout Session.
	 *
	 * @param array $args {
	 *     @type int    $amount         Amount in smallest currency unit.
	 *     @type string $currency       Three-letter currency code.
	 *     @type string $product_name   Line item name.
	 *     @type int    $quantity       Line item quantity.
	 *     @type string $success_url    Redirect after success.
	 *     @type string $cancel_url     Redirect after cancel.
	 *     @type string $customer_email Optional email prefill.
	 *     @type array  $metadata       Optional metadata map.
	 * }
	 * @return array|WP_Error Session data or error.
	 */
	public function create_checkout_session( array $args ) {
		$body = array(
			'mode'                                   => 'payment',
			'success_url'                            => $args['success_url'],
			'cancel_url'                             => $args['cancel_url'],
			'line_items[0][quantity]'                => (int) $args['quantity'],
			'line_items[0][price_data][currency]'    => strtolower( $args['currency'] ),
			'line_items[0][price_data][unit_amount]' => (int) $args['amount'],
			'line_items[0][price_data][product_data][name]' => $args['product_name'],
		);

		if ( ! empty( $args['customer_email'] ) ) {
			$body['customer_email'] = $args['customer_email'];
		}
		if ( ! empty( $args['metadata']['order_reference'] ) ) {
			$body['client_reference_id'] = (string) $args['metadata']['order_reference'];
		}

		if ( ! empty( $args['metadata'] ) && is_array( $args['metadata'] ) ) {
			foreach ( $args['metadata'] as $key => $value ) {
				$body[ 'metadata[' . $key . ']' ] = (string) $value;
			}
		}

		return $this->post( '/checkout/sessions', $body );
	}
}
