<?php
/**
 * Registration capacity and paid-session confirmation.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Tracks seat counts and idempotent payment confirmations.
 */
class MUK_Registrations {

	/**
	 * Option key for confirmed Stripe session IDs.
	 */
	const CONFIRMED_OPTION = 'muk_confirmed_sessions';

	/**
	 * Allowed registration types.
	 *
	 * @return string[]
	 */
	public static function types() {
		return array( 'symposium', 'camp' );
	}

	/**
	 * Capacity / sold snapshot for both events.
	 *
	 * @return array
	 */
	public static function availability() {
		$out = array();
		foreach ( self::types() as $type ) {
			$capacity  = max( 0, (int) MUK_Settings::get( $type . '_capacity', 0 ) );
			$sold      = max( 0, (int) MUK_Settings::get( $type . '_sold', 0 ) );
			$remaining = max( 0, $capacity - $sold );
			$out[ $type ] = array(
				'capacity'  => $capacity,
				'sold'      => $sold,
				'remaining' => $remaining,
				'full'      => ( $capacity > 0 && $remaining < 1 ),
			);
		}
		return $out;
	}

	/**
	 * Whether a registration type still has seats.
	 *
	 * @param string $type symposium|camp.
	 * @return true|WP_Error
	 */
	public static function assert_has_capacity( $type ) {
		$type = sanitize_key( (string) $type );
		if ( ! in_array( $type, self::types(), true ) ) {
			return new WP_Error(
				'muk_invalid_registration_type',
				__( 'registration_type must be symposium or camp.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$info = self::availability();
		if ( ! empty( $info[ $type ]['full'] ) ) {
			$label = ( 'symposium' === $type )
				? __( 'Symposium', 'massuk-configuration' )
				: __( 'Training Camp', 'massuk-configuration' );

			return new WP_Error(
				'muk_sold_out',
				/* translators: %s: event name */
				sprintf( __( '%s registration is currently full.', 'massuk-configuration' ), $label ),
				array(
					'status'       => 409,
					'availability' => $info[ $type ],
				)
			);
		}

		return true;
	}

	/**
	 * Whether a Stripe session was already confirmed.
	 *
	 * @param string $session_id Checkout session id.
	 * @return bool
	 */
	public static function is_session_confirmed( $session_id ) {
		$session_id = (string) $session_id;
		if ( '' === $session_id ) {
			return false;
		}
		$confirmed = get_option( self::CONFIRMED_OPTION, array() );
		return is_array( $confirmed ) && isset( $confirmed[ $session_id ] );
	}

	/**
	 * Mark a paid session as confirmed and bump counters once.
	 *
	 * @param string $session_id Checkout session id.
	 * @param string $type       symposium|camp.
	 * @param string $promo_code Optional promo code from metadata.
	 * @return array|WP_Error Result payload.
	 */
	public static function confirm_paid_session( $session_id, $type, $promo_code = '' ) {
		$session_id = sanitize_text_field( (string) $session_id );
		$type       = sanitize_key( (string) $type );
		$promo_code = sanitize_text_field( (string) $promo_code );

		if ( '' === $session_id ) {
			return new WP_Error(
				'muk_missing_session',
				__( 'session_id is required.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		if ( ! in_array( $type, self::types(), true ) ) {
			return new WP_Error(
				'muk_invalid_registration_type',
				__( 'registration_type must be symposium or camp.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$confirmed = get_option( self::CONFIRMED_OPTION, array() );
		if ( ! is_array( $confirmed ) ) {
			$confirmed = array();
		}

		if ( isset( $confirmed[ $session_id ] ) ) {
			return array(
				'already_confirmed' => true,
				'session_id'        => $session_id,
				'registration_type' => $type,
				'availability'      => self::availability(),
			);
		}

		$settings = MUK_Settings::get_all();
		$sold_key = $type . '_sold';
		$settings[ $sold_key ] = max( 0, (int) $settings[ $sold_key ] ) + 1;

		if ( '' !== $promo_code ) {
			$settings['promo_codes'] = MUK_Settings::increment_promo_use( $settings['promo_codes'], $promo_code );
		}

		update_option( MUK_Settings::OPTION_KEY, $settings );

		$confirmed[ $session_id ] = array(
			'type'       => $type,
			'promo_code' => $promo_code,
			'confirmed'  => gmdate( 'c' ),
		);
		update_option( self::CONFIRMED_OPTION, $confirmed, false );

		return array(
			'already_confirmed' => false,
			'session_id'        => $session_id,
			'registration_type' => $type,
			'promo_code'        => $promo_code,
			'availability'      => self::availability(),
		);
	}
}
