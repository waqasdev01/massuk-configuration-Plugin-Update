<?php
/**
 * Admin settings.
 *
 * @package Stripe_API_Generator
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles plugin settings storage and admin UI.
 */
class MUK_Settings {

	/**
	 * Option key for all plugin settings.
	 */
	const OPTION_KEY = 'muk_settings';

	/**
	 * Register settings with WordPress.
	 */
	public static function register() {
		register_setting(
			'muk_settings_group',
			self::OPTION_KEY,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize' ),
				'default'           => self::defaults(),
			)
		);
	}

	/**
	 * Default settings values.
	 *
	 * @return array
	 */
	public static function defaults() {
		return array(
			'mode'                => 'test',
			'test_secret'         => '',
			'live_secret'         => '',
			'api_key'             => '',
			'success_url'         => '',
			'cancel_url'          => '',
			'promo_codes'         => array(),
			'symposium_capacity'  => 220,
			'camp_capacity'       => 120,
			'symposium_sold'      => 0,
			'camp_sold'           => 0,
			'symposium_product_name' => 'MASSUK Symposium Registration',
			'camp_product_name'   => 'MASSUK Karate Training Camp Registration',
			'intro_text'          => 'The Symposium and Karate Training & Development Camp are booked separately.',
			'show_symposium_promo' => 1,
			'fixed_price_label'     => 'Fixed price',
			'seats_label_symposium' => '{remaining} of {capacity} symposium places remaining',
			'seats_label_camp'      => '{remaining} of {capacity} training camp places remaining',
			'stripe_note'           => 'After you submit, you will be redirected to Stripe to pay securely. Your card details never touch this page.',
			'pricing_schedule'    => array(
				array( 'end_date' => '2026-08-31', 'amount' => 3500, 'label' => 'Super early bird until 31 August' ),
				array( 'end_date' => '2026-09-30', 'amount' => 4500, 'label' => 'Early bird until 30 September' ),
				array( 'end_date' => '2026-10-17', 'amount' => 6500, 'label' => 'Standard rate until 17 October' ),
			),
			'symposium_default_price' => 0,
			'camp_default_price'      => 0,
			'test_webhook_secret' => '',
			'live_webhook_secret' => '',
			'email_from'          => '',
			'mailerlite_api_token'=> '',
			'mailerlite_group_id' => '',
			'mailerlite_require_consent' => 1,
			'email_subject_symposium_paid'      => 'Your MASSUK Symposium registration is confirmed',
			'email_body_symposium_paid'         => "<p>Hello {name},</p><p>Thank you for registering for the MASSUK Symposium — your payment has been received and your place is confirmed.</p><p>Amount: {amount}<br />Reference: {reference}</p><p>We look forward to seeing you.</p>",
			'email_subject_symposium_failed'    => 'Your MASSUK Symposium payment did not go through',
			'email_body_symposium_failed'       => "<p>Hello {name},</p><p>Your payment for the MASSUK Symposium could not be completed, so your registration has not been confirmed.</p><p>Amount: {amount}<br />Reference: {reference}</p><p>Please try again, or contact us if you need help.</p>",
			'email_subject_symposium_cancelled' => 'Your MASSUK Symposium registration was cancelled',
			'email_body_symposium_cancelled'    => "<p>Hello {name},</p><p>Your Symposium checkout was cancelled and no payment was taken, so your registration has not been confirmed.</p><p>Reference: {reference}</p><p>You're welcome to register again at any time.</p>",
			'email_subject_camp_paid'      => 'Your MASSUK Training Camp registration is confirmed',
			'email_body_camp_paid'         => "<p>Hello {name},</p><p>Thank you for registering for the MASSUK Karate Training &amp; Development Camp — your payment has been received and your place is confirmed.</p><p>Amount: {amount}<br />Reference: {reference}</p><p>We look forward to seeing you.</p>",
			'email_subject_camp_failed'    => 'Your MASSUK Training Camp payment did not go through',
			'email_body_camp_failed'       => "<p>Hello {name},</p><p>Your payment for the MASSUK Training Camp could not be completed, so your registration has not been confirmed.</p><p>Amount: {amount}<br />Reference: {reference}</p><p>Please try again, or contact us if you need help.</p>",
			'email_subject_camp_cancelled' => 'Your MASSUK Training Camp registration was cancelled',
			'email_body_camp_cancelled'    => "<p>Hello {name},</p><p>Your Training Camp checkout was cancelled and no payment was taken, so your registration has not been confirmed.</p><p>Reference: {reference}</p><p>You're welcome to register again at any time.</p>",
		);
	}

	/**
	 * Get all settings merged with defaults.
	 *
	 * @return array
	 */
	public static function get_all() {
		$stored = get_option( self::OPTION_KEY, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		$all = wp_parse_args( $stored, self::defaults() );
		if ( ! isset( $all['promo_codes'] ) || ! is_array( $all['promo_codes'] ) ) {
			$all['promo_codes'] = array();
		}
		if ( ! isset( $all['pricing_schedule'] ) || ! is_array( $all['pricing_schedule'] ) ) $all['pricing_schedule'] = self::defaults()['pricing_schedule'];

		return $all;
	}

	/**
	 * Get a single setting value.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Fallback.
	 * @return mixed
	 */
	public static function get( $key, $default = '' ) {
		$all = self::get_all();
		return isset( $all[ $key ] ) ? $all[ $key ] : $default;
	}

	/**
	 * Normalize a URL for storage (adds https:// when missing).
	 *
	 * @param string $url Raw URL.
	 * @return string
	 */
	public static function normalize_url( $url ) {
		$url = trim( (string) $url );
		if ( '' === $url ) {
			return '';
		}

		if ( ! preg_match( '#^https?://#i', $url ) ) {
			$url = 'https://' . $url;
		}

		return esc_url_raw( $url );
	}

	/**
	 * Whether a value looks like a valid Stripe secret/restricted key.
	 *
	 * @param string $key Key value.
	 * @param string $mode test|live|any.
	 * @return bool
	 */
	public static function is_valid_stripe_secret( $key, $mode = 'any' ) {
		$key = trim( (string) $key );
		if ( '' === $key ) {
			return true;
		}

		if ( 'test' === $mode ) {
			return (bool) preg_match( '/^(sk|rk)_test_/', $key );
		}

		if ( 'live' === $mode ) {
			return (bool) preg_match( '/^(sk|rk)_live_/', $key );
		}

		return (bool) preg_match( '/^(sk|rk)_(test|live)_/', $key );
	}

	/**
	 * Sanitize promo codes from admin form.
	 *
	 * @param mixed $raw Raw promo rows.
	 * @return array
	 */
	public static function sanitize_promo_codes( $raw ) {
		if ( ! is_array( $raw ) ) {
			return array();
		}

		$current_uses = array();
		foreach ( self::get( 'promo_codes', array() ) as $existing ) {
			if ( ! is_array( $existing ) || empty( $existing['code'] ) ) {
				continue;
			}
			$current_uses[ strtoupper( (string) $existing['code'] ) ] = isset( $existing['uses'] ) ? (int) $existing['uses'] : 0;
		}

		$clean = array();
		$seen  = array();

		foreach ( $raw as $row ) {
			if ( ! is_array( $row ) ) {
				continue;
			}

			$code = isset( $row['code'] ) ? strtoupper( sanitize_text_field( wp_unslash( $row['code'] ) ) ) : '';
			$code = preg_replace( '/\s+/', '', $code );
			if ( '' === $code ) {
				continue;
			}

			$type = isset( $row['type'] ) ? sanitize_text_field( wp_unslash( $row['type'] ) ) : 'percent';
			if ( ! in_array( $type, array( 'percent', 'fixed' ), true ) ) {
				$type = 'percent';
			}

			$value = isset( $row['value'] ) ? (float) $row['value'] : 0;
			if ( $value <= 0 ) {
				continue;
			}

			if ( 'percent' === $type ) {
				$value = min( 100, max( 0.01, $value ) );
			} else {
				$value = (int) round( $value * 100 );
				if ( $value < 1 ) {
					continue;
				}
			}

			$max_uses = isset( $row['max_uses'] ) ? (int) $row['max_uses'] : 0;
			if ( $max_uses < 0 ) {
				$max_uses = 0;
			}

			$uses = isset( $current_uses[ $code ] ) ? (int) $current_uses[ $code ] : 0;
			if ( isset( $row['uses'] ) && '' !== $row['uses'] && is_numeric( $row['uses'] ) ) {
				// Allow admin override of used count.
				$uses = max( 0, (int) $row['uses'] );
			}

			$lookup = strtolower( $code );
			if ( isset( $seen[ $lookup ] ) ) {
				continue;
			}
			$seen[ $lookup ] = true;

			$clean[] = array(
				'code'     => $code,
				'type'     => $type,
				'value'    => $value,
				'max_uses' => $max_uses,
				'uses'     => $uses,
			);
		}

		return $clean;
	}

	/**
	 * Increment promo use count after a paid registration.
	 *
	 * @param array  $promos Promo rows.
	 * @param string $code   Promo code.
	 * @return array
	 */
	public static function increment_promo_use( $promos, $code ) {
		if ( ! is_array( $promos ) ) {
			return array();
		}

		$code = strtoupper( preg_replace( '/\s+/', '', (string) $code ) );
		foreach ( $promos as $i => $row ) {
			if ( ! is_array( $row ) || empty( $row['code'] ) ) {
				continue;
			}
			if ( strtoupper( (string) $row['code'] ) !== $code ) {
				continue;
			}
			$promos[ $i ]['uses'] = max( 0, (int) ( isset( $row['uses'] ) ? $row['uses'] : 0 ) ) + 1;
			break;
		}

		return $promos;
	}

	/**
	 * Find a promo code definition (case-insensitive).
	 *
	 * @param string $code Promo code.
	 * @return array|null
	 */
	public static function find_promo_code( $code ) {
		$code = strtoupper( preg_replace( '/\s+/', '', (string) $code ) );
		if ( '' === $code ) {
			return null;
		}

		foreach ( self::get( 'promo_codes', array() ) as $row ) {
			if ( ! is_array( $row ) || empty( $row['code'] ) ) {
				continue;
			}
			if ( strtoupper( (string) $row['code'] ) === $code ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * Apply a promo code to an amount in minor units.
	 *
	 * @param int    $amount Amount in minor units.
	 * @param string $code   Promo code.
	 * @return array|WP_Error { amount, promo_code, discount_type, discount_value, discount_amount }
	 */
	public static function apply_promo_code( $amount, $code ) {
		$amount = (int) $amount;
		$code   = trim( (string) $code );

		if ( '' === $code ) {
			return array(
				'amount'          => $amount,
				'promo_code'      => '',
				'discount_type'   => '',
				'discount_value'  => 0,
				'discount_amount' => 0,
			);
		}

		$promo = self::find_promo_code( $code );
		if ( ! $promo ) {
			return new WP_Error(
				'muk_invalid_promo',
				__( 'Invalid promo code.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$max_uses = isset( $promo['max_uses'] ) ? (int) $promo['max_uses'] : 0;
		$uses     = isset( $promo['uses'] ) ? (int) $promo['uses'] : 0;
		if ( $max_uses > 0 && $uses >= $max_uses ) {
			return new WP_Error(
				'muk_promo_exhausted',
				__( 'This promo code has reached its usage limit.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		$type  = $promo['type'];
		$value = $promo['value'];

		if ( 'percent' === $type ) {
			$discount = (int) floor( $amount * ( (float) $value / 100 ) );
		} else {
			$discount = (int) $value;
		}

		$discount = max( 0, min( $amount, $discount ) );
		$final    = $amount - $discount;

		if ( $final < 1 ) {
			return new WP_Error(
				'muk_promo_zero_amount',
				__( 'This promo code would reduce the total to zero. Stripe requires a positive amount.', 'massuk-configuration' ),
				array( 'status' => 400 )
			);
		}

		return array(
			'amount'          => $final,
			'promo_code'      => (string) $promo['code'],
			'discount_type'   => $type,
			'discount_value'  => $value,
			'discount_amount' => $discount,
		);
	}

	/**
	 * Sanitize settings on save.
	 *
	 * @param array $input Raw input.
	 * @return array
	 */
	public static function sanitize( $input ) {
		if ( ! is_array( $input ) ) {
			return self::get_all();
		}

		$current = self::get_all();
		$output  = $current;

		if ( isset( $input['mode'] ) && in_array( $input['mode'], array( 'test', 'live' ), true ) ) {
			$output['mode'] = $input['mode'];
		}

		if ( array_key_exists( 'test_secret', $input ) ) {
			$test = trim( sanitize_text_field( wp_unslash( $input['test_secret'] ) ) );
			if ( '' === $test ) {
				// Keep existing key when the field is left blank.
				$output['test_secret'] = $current['test_secret'];
			} elseif ( self::is_valid_stripe_secret( $test, 'test' ) ) {
				$output['test_secret'] = $test;
			} else {
				add_settings_error(
					'muk_settings',
					'muk_bad_test_secret',
					__( 'Test Secret Key was not saved. It must start with sk_test_ or rk_test_.', 'massuk-configuration' ),
					'error'
				);
			}
		}

		if ( array_key_exists( 'live_secret', $input ) ) {
			$live = trim( sanitize_text_field( wp_unslash( $input['live_secret'] ) ) );
			if ( '' === $live ) {
				// Keep existing key when the field is left blank.
				$output['live_secret'] = $current['live_secret'];
			} elseif ( self::is_valid_stripe_secret( $live, 'live' ) ) {
				$output['live_secret'] = $live;
			} else {
				add_settings_error(
					'muk_settings',
					'muk_bad_live_secret',
					__( 'Live Secret Key was not saved. It must start with sk_live_ or rk_live_.', 'massuk-configuration' ),
					'error'
				);
			}
		}

		foreach ( array( 'test_webhook_secret', 'live_webhook_secret', 'mailerlite_api_token' ) as $secret_key ) {
			if ( array_key_exists( $secret_key, $input ) ) {
				$value = trim( sanitize_text_field( wp_unslash( $input[ $secret_key ] ) ) );
				$output[ $secret_key ] = '' === $value ? $current[ $secret_key ] : $value;
			}
		}
		if ( isset( $input['email_from'] ) ) $output['email_from'] = sanitize_email( wp_unslash( $input['email_from'] ) );
		foreach ( array( 'symposium_product_name', 'camp_product_name' ) as $name_key ) {
			if ( isset( $input[ $name_key ] ) ) $output[ $name_key ] = sanitize_text_field( wp_unslash( $input[ $name_key ] ) );
		}
		if ( isset( $input['mailerlite_group_id'] ) ) $output['mailerlite_group_id'] = sanitize_text_field( wp_unslash( $input['mailerlite_group_id'] ) );

		$tab = isset( $input['__tab'] ) ? sanitize_key( $input['__tab'] ) : '';

		// Checkbox fields are only present in $input when their own tab is
		// submitted. Guard each one by its owning tab so saving one tab
		// never silently clears a checkbox that lives on another tab.
		if ( 'email' === $tab ) {
			$output['mailerlite_require_consent'] = empty( $input['mailerlite_require_consent'] ) ? 0 : 1;
		}
		if ( 'settings' === $tab ) {
			$output['show_symposium_promo'] = empty( $input['show_symposium_promo'] ) ? 0 : 1;
		}

		if ( isset( $input['intro_text'] ) ) {
			$intro = sanitize_text_field( wp_unslash( $input['intro_text'] ) );
			$output['intro_text'] = '' === $intro ? self::defaults()['intro_text'] : $intro;
		}

		foreach ( array( 'fixed_price_label', 'seats_label_symposium', 'seats_label_camp' ) as $text_key ) {
			if ( isset( $input[ $text_key ] ) ) {
				$output[ $text_key ] = sanitize_text_field( wp_unslash( $input[ $text_key ] ) );
			}
		}
		if ( isset( $input['stripe_note'] ) ) {
			$output['stripe_note'] = sanitize_textarea_field( wp_unslash( $input['stripe_note'] ) );
		}

		foreach ( array( 'symposium_default_price', 'camp_default_price' ) as $default_key ) {
			if ( isset( $input[ $default_key ] ) ) {
				$raw = trim( (string) $input[ $default_key ] );
				$output[ $default_key ] = ( '' === $raw ) ? 0 : max( 0, (int) round( ( (float) $raw ) * 100 ) );
			}
		}

		$email_subject_keys = array(
			'email_subject_symposium_paid', 'email_subject_symposium_failed', 'email_subject_symposium_cancelled',
			'email_subject_camp_paid', 'email_subject_camp_failed', 'email_subject_camp_cancelled',
		);
		foreach ( $email_subject_keys as $subject_key ) {
			if ( isset( $input[ $subject_key ] ) ) {
				$output[ $subject_key ] = sanitize_text_field( wp_unslash( $input[ $subject_key ] ) );
			}
		}
		$email_body_keys = array(
			'email_body_symposium_paid', 'email_body_symposium_failed', 'email_body_symposium_cancelled',
			'email_body_camp_paid', 'email_body_camp_failed', 'email_body_camp_cancelled',
		);
		foreach ( $email_body_keys as $body_key ) {
			if ( isset( $input[ $body_key ] ) ) {
				$output[ $body_key ] = wp_kses_post( wp_unslash( $input[ $body_key ] ) );
			}
		}

		if ( isset( $input['api_key'] ) ) {
			$output['api_key'] = sanitize_text_field( wp_unslash( $input['api_key'] ) );
		}

		if ( isset( $input['success_url'] ) ) {
			$success = self::normalize_url( wp_unslash( $input['success_url'] ) );
			$raw     = trim( (string) wp_unslash( $input['success_url'] ) );
			if ( '' !== $raw && '' === $success ) {
				add_settings_error(
					'muk_settings',
					'muk_bad_success_url',
					__( 'Default Success URL looks invalid and was not saved.', 'massuk-configuration' ),
					'error'
				);
			} else {
				$output['success_url'] = $success;
			}
		}

		if ( isset( $input['cancel_url'] ) ) {
			$cancel = self::normalize_url( wp_unslash( $input['cancel_url'] ) );
			$raw    = trim( (string) wp_unslash( $input['cancel_url'] ) );
			if ( '' !== $raw && '' === $cancel ) {
				add_settings_error(
					'muk_settings',
					'muk_bad_cancel_url',
					__( 'Default Cancel URL looks invalid and was not saved.', 'massuk-configuration' ),
					'error'
				);
			} else {
				$output['cancel_url'] = $cancel;
			}
		}

		if ( isset( $input['promo_codes'] ) ) {
			$output['promo_codes'] = self::sanitize_promo_codes( $input['promo_codes'] );
		}

		foreach ( array( 'symposium_capacity', 'camp_capacity', 'symposium_sold', 'camp_sold' ) as $cap_key ) {
			if ( isset( $input[ $cap_key ] ) ) {
				$output[ $cap_key ] = max( 0, (int) $input[ $cap_key ] );
			}
		}

		if ( isset( $input['pricing_schedule'] ) && is_array( $input['pricing_schedule'] ) ) {
			$schedule = array();
			foreach ( $input['pricing_schedule'] as $row ) {
				if ( ! is_array( $row ) ) continue;
				$date   = isset( $row['end_date'] ) ? sanitize_text_field( wp_unslash( $row['end_date'] ) ) : '';
				$amount = isset( $row['amount'] ) && '' !== trim( (string) $row['amount'] ) ? (int) round( ( (float) $row['amount'] ) * 100 ) : 0;
				$label  = isset( $row['label'] ) ? sanitize_text_field( wp_unslash( $row['label'] ) ) : '';
				if ( preg_match( '/^\d{4}-\d{2}-\d{2}$/', $date ) && $amount > 0 ) $schedule[] = array( 'end_date' => $date, 'amount' => $amount, 'label' => $label );
			}
			if ( $schedule ) {
				usort( $schedule, function( $a, $b ) { return strcmp( $a['end_date'], $b['end_date'] ); } );
				$output['pricing_schedule'] = $schedule;
			}
		}

		return $output;
	}

	/**
	 * Add Settings submenu page.
	 */
	public static function add_menu() {
		add_options_page(
			__( 'Massuk Configuration', 'massuk-configuration' ),
			__( 'Massuk Configuration', 'massuk-configuration' ),
			'manage_options',
			'muk-settings',
			array( __CLASS__, 'render_page' )
		);
	}

	/**
	 * Return the active price for a registration type.
	 *
	 * If the admin has set an explicit default/override price for this
	 * type, it always wins — that is the whole point of "default". If it's
	 * left blank ("None"), the price automatically follows whichever
	 * month's tier in the pricing schedule covers today's date.
	 *
	 * @param string $type symposium|camp (optional — omit for the shared ladder only).
	 */
	public static function current_price( $type = '' ) {
		$default_key = '';
		if ( 'symposium' === $type ) {
			$default_key = 'symposium_default_price';
		} elseif ( 'camp' === $type ) {
			$default_key = 'camp_default_price';
		}
		if ( $default_key ) {
			$default_amount = (int) self::get( $default_key, 0 );
			if ( $default_amount > 0 ) {
				return array(
					'amount' => $default_amount,
					'label'  => sprintf( '£%0.2f', $default_amount / 100 ),
					'tier'   => self::get( 'fixed_price_label', 'Fixed price' ),
					'open'   => true,
				);
			}
		}

		// No override set ("None") — automatically use whichever month's
		// tier in the schedule covers today's date.
		$today = current_time( 'Y-m-d' );
		foreach ( self::get( 'pricing_schedule', array() ) as $row ) {
			if ( ! empty( $row['end_date'] ) && $today <= $row['end_date'] && ! empty( $row['amount'] ) ) {
				return array( 'amount' => (int) $row['amount'], 'label' => sprintf( '£%0.2f', (int) $row['amount'] / 100 ), 'tier' => isset( $row['label'] ) ? $row['label'] : '', 'open' => true );
			}
		}

		return array( 'amount' => 0, 'label' => __( 'Closed', 'massuk-configuration' ), 'tier' => __( 'Registration is closed.', 'massuk-configuration' ), 'open' => false );
	}

	/**
	 * Format a stored promo value for the admin input.
	 *
	 * @param array $row Promo row.
	 * @return string
	 */
	private static function promo_value_for_input( $row ) {
		if ( empty( $row['type'] ) || empty( $row['value'] ) ) {
			return '';
		}

		if ( 'fixed' === $row['type'] ) {
			return number_format( ( (int) $row['value'] ) / 100, 2, '.', '' );
		}

		return (string) $row['value'];
	}

	/** Tabs shown in the admin UI, in display order. */
	private static function tabs() {
		return array(
			'stripe'        => __( 'Stripe', 'massuk-configuration' ),
			'discounts'     => __( 'Discounts', 'massuk-configuration' ),
			'registrations' => __( 'Registrations', 'massuk-configuration' ),
			'settings'      => __( 'Settings', 'massuk-configuration' ),
			'email'         => __( 'Email', 'massuk-configuration' ),
			'edit'          => __( 'Edit', 'massuk-configuration' ),
		);
	}

	/**
	 * Render admin settings page shell with tabbed navigation.
	 */
	public static function render_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings = self::get_all();
		$tabs     = self::tabs();
		$active   = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'stripe'; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $tabs[ $active ] ) ) {
			$active = 'stripe';
		}

		$mode_label = ( 'live' === $settings['mode'] )
			? __( 'Live', 'massuk-configuration' )
			: __( 'Test', 'massuk-configuration' );
		$endpoint   = rest_url( 'muk/v1/checkout' );
		?>
		<div class="wrap muk-settings">
			<div class="muk-header">
				<h1><?php echo esc_html__( 'Massuk Configuration', 'massuk-configuration' ); ?></h1>
				<span class="muk-mode-pill muk-mode-<?php echo esc_attr( $settings['mode'] ); ?>">
					<?php echo esc_html( $mode_label ); ?>
				</span>
			</div>
			<?php settings_errors(); ?>
			<div class="notice notice-info inline muk-endpoint-notice">
				<p>
					<strong><?php echo esc_html__( 'Checkout endpoint:', 'massuk-configuration' ); ?></strong>
					<code><?php echo esc_html( $endpoint ); ?></code>
					&nbsp;|&nbsp;
					<strong><?php echo esc_html__( 'Shortcode:', 'massuk-configuration' ); ?></strong>
					<code>[muk_registration_form]</code>
				</p>
			</div>

			<nav class="muk-tabs" aria-label="<?php echo esc_attr__( 'Settings sections', 'massuk-configuration' ); ?>">
				<?php foreach ( $tabs as $slug => $label ) : ?>
					<a
						href="<?php echo esc_url( add_query_arg( array( 'page' => 'muk-settings', 'tab' => $slug ), admin_url( 'options-general.php' ) ) ); ?>"
						class="muk-tab <?php echo $active === $slug ? 'is-active' : ''; ?>"
					><?php echo esc_html( $label ); ?></a>
				<?php endforeach; ?>
			</nav>

			<div class="muk-tab-panel">
				<?php
				switch ( $active ) {
					case 'discounts':
						self::render_tab_discounts( $settings );
						break;
					case 'registrations':
						self::render_tab_registrations();
						break;
					case 'settings':
						self::render_tab_settings( $settings );
						break;
					case 'email':
						self::render_tab_email( $settings );
						break;
					case 'edit':
						self::render_tab_edit();
						break;
					default:
						self::render_tab_stripe( $settings );
						break;
				}
				?>
			</div>
		</div>
		<?php
	}

	/** Stripe tab: mode toggle, keys, webhook secrets, API key. */
	private static function render_tab_stripe( $settings ) {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'muk_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[__tab]" value="stripe" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Mode', 'massuk-configuration' ); ?></th>
						<td>
							<fieldset id="muk-mode-fieldset">
								<label>
									<input type="radio" class="muk-mode-radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mode]" value="test" <?php checked( $settings['mode'], 'test' ); ?> />
									<?php echo esc_html__( 'Test', 'massuk-configuration' ); ?>
								</label>
								&nbsp;&nbsp;
								<label>
									<input type="radio" class="muk-mode-radio" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mode]" value="live" <?php checked( $settings['mode'], 'live' ); ?> />
									<?php echo esc_html__( 'Live', 'massuk-configuration' ); ?>
								</label>
								<p class="description"><?php echo esc_html__( 'Only the fields for the selected mode are shown below — the other mode\'s keys stay safely saved but hidden.', 'massuk-configuration' ); ?></p>
							</fieldset>
						</td>
					</tr>
				</tbody>
			</table>

			<div class="muk-mode-section" data-mode="test">
				<h2><?php echo esc_html__( 'Test mode', 'massuk-configuration' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="muk_test_secret"><?php echo esc_html__( 'Test Secret Key', 'massuk-configuration' ); ?></label></th>
							<td>
								<input type="text" class="regular-text code" id="muk_test_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[test_secret]" value="<?php echo esc_attr( $settings['test_secret'] ); ?>" autocomplete="off" spellcheck="false" />
								<p class="description"><?php echo esc_html__( 'Must start with sk_test_ or rk_test_. Leave blank to keep the current key.', 'massuk-configuration' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="muk_test_webhook_secret"><?php echo esc_html__( 'Test Webhook Secret', 'massuk-configuration' ); ?></label></th>
							<td>
								<input type="password" class="regular-text code" id="muk_test_webhook_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[test_webhook_secret]" value="" autocomplete="new-password" />
								<p class="description"><?php echo esc_html__( 'Paste the Stripe signing secret (whsec_…) for your test webhook. Leave blank to keep the current value.', 'massuk-configuration' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<div class="muk-mode-section" data-mode="live">
				<h2><?php echo esc_html__( 'Live mode', 'massuk-configuration' ); ?></h2>
				<table class="form-table" role="presentation">
					<tbody>
						<tr>
							<th scope="row"><label for="muk_live_secret"><?php echo esc_html__( 'Live Secret Key', 'massuk-configuration' ); ?></label></th>
							<td>
								<input type="text" class="regular-text code" id="muk_live_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[live_secret]" value="<?php echo esc_attr( $settings['live_secret'] ); ?>" autocomplete="off" spellcheck="false" />
								<p class="description"><?php echo esc_html__( 'Must start with sk_live_ or rk_live_. Leave blank to keep the current key.', 'massuk-configuration' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="muk_live_webhook_secret"><?php echo esc_html__( 'Live Webhook Secret', 'massuk-configuration' ); ?></label></th>
							<td>
								<input type="password" class="regular-text code" id="muk_live_webhook_secret" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[live_webhook_secret]" value="" autocomplete="new-password" />
								<p class="description"><?php echo esc_html__( 'Paste the Stripe signing secret (whsec_…) for your live webhook. Leave blank to keep the current value.', 'massuk-configuration' ); ?></p>
							</td>
						</tr>
					</tbody>
				</table>
			</div>

			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="muk_api_key"><?php echo esc_html__( 'Plugin API Key', 'massuk-configuration' ); ?></label></th>
						<td>
							<input type="text" class="regular-text code" id="muk_api_key" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[api_key]" value="<?php echo esc_attr( $settings['api_key'] ); ?>" autocomplete="off" />
							<button type="button" class="button" id="muk-generate-api-key"><?php echo esc_html__( 'Generate', 'massuk-configuration' ); ?></button>
							<p class="description"><?php echo esc_html__( 'Sent as the X-MUK-API-Key header. The [muk_registration_form] shortcode fills this in automatically.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_success_url"><?php echo esc_html__( 'Default Success URL', 'massuk-configuration' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="muk_success_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[success_url]" value="<?php echo esc_attr( $settings['success_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/?payment=success' ) ); ?>" />
							<p class="description"><?php echo esc_html__( 'Used when the request does not include success_url.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_cancel_url"><?php echo esc_html__( 'Default Cancel URL', 'massuk-configuration' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="muk_cancel_url" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[cancel_url]" value="<?php echo esc_attr( $settings['cancel_url'] ); ?>" placeholder="<?php echo esc_attr( home_url( '/?payment=cancelled' ) ); ?>" />
							<p class="description"><?php echo esc_html__( 'Used when the request does not include cancel_url.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Save Stripe Settings', 'massuk-configuration' ) ); ?>
		</form>
		<script>
		(function () {
			var btn = document.getElementById('muk-generate-api-key');
			var input = document.getElementById('muk_api_key');
			if (btn && input) {
				btn.addEventListener('click', function () {
					var bytes = new Uint8Array(24);
					window.crypto.getRandomValues(bytes);
					input.value = Array.from(bytes, function (b) {
						return ('0' + b.toString(16)).slice(-2);
					}).join('');
				});
			}

			function applyModeVisibility() {
				var checked = document.querySelector('.muk-mode-radio:checked');
				var mode = checked ? checked.value : 'test';
				document.querySelectorAll('.muk-mode-section').forEach(function (section) {
					section.style.display = section.getAttribute('data-mode') === mode ? '' : 'none';
				});
			}
			document.querySelectorAll('.muk-mode-radio').forEach(function (radio) {
				radio.addEventListener('change', applyModeVisibility);
			});
			applyModeVisibility();
		})();
		</script>
		<?php
	}

	/** Discounts tab: promo code table. */
	private static function render_tab_discounts( $settings ) {
		$promos = is_array( $settings['promo_codes'] ) ? $settings['promo_codes'] : array();
		if ( empty( $promos ) ) {
			$promos = array(
				array( 'code' => 'MASSUK10', 'type' => 'percent', 'value' => 10, 'max_uses' => 10, 'uses' => 0 ),
				array( 'code' => 'MASSUK25', 'type' => 'percent', 'value' => 25, 'max_uses' => 10, 'uses' => 0 ),
				array( 'code' => 'MASSUK40', 'type' => 'percent', 'value' => 40, 'max_uses' => 5, 'uses' => 0 ),
			);
		}
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'muk_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[__tab]" value="discounts" />
			<h2><?php echo esc_html__( 'Promo codes', 'massuk-configuration' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Optional codes for selected delegates. Percent = % off. Max uses = 0 means unlimited. These are managed in this plugin — not in Stripe.', 'massuk-configuration' ); ?></p>
			<table class="widefat striped muk-promo-table" id="muk-promo-table">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Code', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Discount type', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Value', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Max uses', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Used', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Remove', 'massuk-configuration' ); ?></th>
					</tr>
				</thead>
				<tbody id="muk-promo-rows">
					<?php foreach ( $promos as $index => $row ) : ?>
						<tr class="muk-promo-row">
							<td>
								<input type="text" class="regular-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_codes][<?php echo esc_attr( (string) $index ); ?>][code]" value="<?php echo esc_attr( isset( $row['code'] ) ? $row['code'] : '' ); ?>" placeholder="MASSUK10" />
							</td>
							<td>
								<select name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_codes][<?php echo esc_attr( (string) $index ); ?>][type]">
									<option value="percent" <?php selected( isset( $row['type'] ) ? $row['type'] : '', 'percent' ); ?>><?php echo esc_html__( 'Percent (%)', 'massuk-configuration' ); ?></option>
									<option value="fixed" <?php selected( isset( $row['type'] ) ? $row['type'] : '', 'fixed' ); ?>><?php echo esc_html__( 'Fixed amount', 'massuk-configuration' ); ?></option>
								</select>
							</td>
							<td>
								<input type="number" step="0.01" min="0" class="small-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_codes][<?php echo esc_attr( (string) $index ); ?>][value]" value="<?php echo esc_attr( self::promo_value_for_input( $row ) ); ?>" placeholder="10" />
							</td>
							<td>
								<input type="number" min="0" class="small-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_codes][<?php echo esc_attr( (string) $index ); ?>][max_uses]" value="<?php echo esc_attr( isset( $row['max_uses'] ) ? (string) (int) $row['max_uses'] : '0' ); ?>" placeholder="10" />
							</td>
							<td>
								<input type="number" min="0" class="small-text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[promo_codes][<?php echo esc_attr( (string) $index ); ?>][uses]" value="<?php echo esc_attr( isset( $row['uses'] ) ? (string) (int) $row['uses'] : '0' ); ?>" />
							</td>
							<td>
								<button type="button" class="button muk-remove-promo"><?php echo esc_html__( 'Remove', 'massuk-configuration' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="muk-add-promo"><?php echo esc_html__( 'Add promo code', 'massuk-configuration' ); ?></button>
			</p>
			<?php submit_button( __( 'Save Discounts', 'massuk-configuration' ) ); ?>
		</form>
		<script>
		(function () {
			var rows = document.getElementById('muk-promo-rows');
			var addBtn = document.getElementById('muk-add-promo');
			var optionKey = <?php echo wp_json_encode( self::OPTION_KEY ); ?>;
			var nextIndex = <?php echo (int) count( $promos ); ?>;

			function bindRemove(button) {
				button.addEventListener('click', function () {
					var tr = button.closest('tr');
					if (!tr || !rows) return;
					if (rows.querySelectorAll('tr').length <= 1) {
						tr.querySelectorAll('input').forEach(function (el) { el.value = ''; });
						return;
					}
					tr.remove();
				});
			}

			if (rows) {
				rows.querySelectorAll('.muk-remove-promo').forEach(bindRemove);
			}

			if (addBtn && rows) {
				addBtn.addEventListener('click', function () {
					var i = nextIndex++;
					var tr = document.createElement('tr');
					tr.className = 'muk-promo-row';
					tr.innerHTML =
						'<td><input type="text" class="regular-text" name="' + optionKey + '[promo_codes][' + i + '][code]" value="" placeholder="MASSUK10" /></td>' +
						'<td><select name="' + optionKey + '[promo_codes][' + i + '][type]">' +
							'<option value="percent">Percent (%)</option>' +
							'<option value="fixed">Fixed amount</option>' +
						'</select></td>' +
						'<td><input type="number" step="0.01" min="0" class="small-text" name="' + optionKey + '[promo_codes][' + i + '][value]" value="" placeholder="10" /></td>' +
						'<td><input type="number" min="0" class="small-text" name="' + optionKey + '[promo_codes][' + i + '][max_uses]" value="10" placeholder="10" /></td>' +
						'<td><input type="number" min="0" class="small-text" name="' + optionKey + '[promo_codes][' + i + '][uses]" value="0" /></td>' +
						'<td><button type="button" class="button muk-remove-promo">Remove</button></td>';
					rows.appendChild(tr);
					bindRemove(tr.querySelector('.muk-remove-promo'));
				});
			}
		})();
		</script>
		<?php
	}

	/** Registrations tab: read-only view of the muk_submissions table. */
	private static function render_tab_registrations() {
		$type   = isset( $_GET['muk_type'] ) ? sanitize_key( wp_unslash( $_GET['muk_type'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$status = isset( $_GET['muk_status'] ) ? sanitize_key( wp_unslash( $_GET['muk_status'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$search = isset( $_GET['muk_search'] ) ? sanitize_text_field( wp_unslash( $_GET['muk_search'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$paged  = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		$result = MUK_Submissions::query( array(
			'registration_type' => $type,
			'payment_status'    => $status,
			'search'            => $search,
			'paged'             => $paged,
			'per_page'          => 20,
		) );
		$items      = $result['items'];
		$total      = $result['total'];
		$total_pages = max( 1, (int) ceil( $total / 20 ) );
		$base_url   = add_query_arg( array( 'page' => 'muk-settings', 'tab' => 'registrations' ), admin_url( 'options-general.php' ) );
		?>
		<h2><?php echo esc_html__( 'Complete form submissions', 'massuk-configuration' ); ?></h2>
		<p class="description"><?php echo esc_html__( 'Every field submitted on the registration forms, stored in its own database table (not just the Stripe payment record).', 'massuk-configuration' ); ?></p>

		<form method="get" class="muk-registrations-filters">
			<input type="hidden" name="page" value="muk-settings" />
			<input type="hidden" name="tab" value="registrations" />
			<select name="muk_type">
				<option value=""><?php echo esc_html__( 'All types', 'massuk-configuration' ); ?></option>
				<option value="symposium" <?php selected( $type, 'symposium' ); ?>><?php echo esc_html__( 'Symposium', 'massuk-configuration' ); ?></option>
				<option value="camp" <?php selected( $type, 'camp' ); ?>><?php echo esc_html__( 'Training Camp', 'massuk-configuration' ); ?></option>
			</select>
			<select name="muk_status">
				<option value=""><?php echo esc_html__( 'All statuses', 'massuk-configuration' ); ?></option>
				<?php foreach ( array( 'pending', 'paid', 'failed', 'cancelled' ) as $s ) : ?>
					<option value="<?php echo esc_attr( $s ); ?>" <?php selected( $status, $s ); ?>><?php echo esc_html( ucfirst( $s ) ); ?></option>
				<?php endforeach; ?>
			</select>
			<input type="search" name="muk_search" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php echo esc_attr__( 'Search name, email, session id…', 'massuk-configuration' ); ?>" />
			<button type="submit" class="button"><?php echo esc_html__( 'Filter', 'massuk-configuration' ); ?></button>
		</form>

		<table class="widefat striped muk-reg-table">
			<thead>
				<tr>
					<th><?php echo esc_html__( 'Date', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Type', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Name', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Email', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Amount', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Promo', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Status', 'massuk-configuration' ); ?></th>
					<th><?php echo esc_html__( 'Details', 'massuk-configuration' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $items ) ) : ?>
					<tr><td colspan="8"><?php echo esc_html__( 'No submissions yet.', 'massuk-configuration' ); ?></td></tr>
				<?php else : ?>
					<?php foreach ( $items as $row ) : ?>
						<tr>
							<td><?php echo esc_html( get_date_from_gmt( $row['created_at'], 'd M Y H:i' ) ); ?></td>
							<td><?php echo esc_html( 'camp' === $row['registration_type'] ? __( 'Training Camp', 'massuk-configuration' ) : __( 'Symposium', 'massuk-configuration' ) ); ?></td>
							<td><?php echo esc_html( trim( $row['full_name'] ) ); ?></td>
							<td><?php echo esc_html( $row['email'] ); ?></td>
							<td><?php echo esc_html( strtoupper( $row['currency'] ) . ' ' . number_format( $row['amount'] / 100, 2 ) ); ?></td>
							<td><?php echo esc_html( $row['promo_code'] ? $row['promo_code'] : '—' ); ?></td>
							<td><span class="muk-status muk-status-<?php echo esc_attr( $row['payment_status'] ); ?>"><?php echo esc_html( ucfirst( $row['payment_status'] ) ); ?></span></td>
							<td>
								<button type="button" class="button button-small muk-view-submission" data-form="<?php echo esc_attr( $row['form_data'] ); ?>"><?php echo esc_html__( 'View', 'massuk-configuration' ); ?></button>
							</td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php if ( $total_pages > 1 ) : ?>
			<p class="muk-pagination">
				<?php for ( $p = 1; $p <= $total_pages; $p++ ) : ?>
					<a class="button <?php echo $p === $paged ? 'button-primary' : ''; ?>" href="<?php echo esc_url( add_query_arg( 'paged', $p, $base_url ) ); ?>"><?php echo esc_html( (string) $p ); ?></a>
				<?php endfor; ?>
			</p>
		<?php endif; ?>

		<div id="muk-submission-modal" class="muk-modal" style="display:none">
			<div class="muk-modal-inner">
				<button type="button" class="button muk-modal-close"><?php echo esc_html__( 'Close', 'massuk-configuration' ); ?></button>
				<pre id="muk-submission-json"></pre>
			</div>
		</div>
		<script>
		(function () {
			var modal = document.getElementById('muk-submission-modal');
			var pre = document.getElementById('muk-submission-json');
			document.querySelectorAll('.muk-view-submission').forEach(function (btn) {
				btn.addEventListener('click', function () {
					try {
						pre.textContent = JSON.stringify(JSON.parse(btn.dataset.form || '{}'), null, 2);
					} catch (e) {
						pre.textContent = btn.dataset.form || '';
					}
					modal.style.display = 'flex';
				});
			});
			var closeBtn = modal.querySelector('.muk-modal-close');
			if (closeBtn) closeBtn.addEventListener('click', function () { modal.style.display = 'none'; });
		})();
		</script>
		<?php
	}

	/** Settings tab: capacities, pricing, product names, page copy. */
	/**
	 * Diagnostic panel: shows exactly what the database holds right now
	 * (price + availability), and lets the admin fetch the live public
	 * REST endpoint from the browser to compare. If the two disagree,
	 * the frontend is serving cached output, not stale settings.
	 */
	private static function render_live_snapshot() {
		$availability = MUK_Registrations::availability();
		$price        = array(
			'symposium' => self::current_price( 'symposium' ),
			'camp'      => self::current_price( 'camp' ),
		);
		$config_url = rest_url( 'muk/v1/config' );
		?>
		<div class="muk-live-snapshot">
			<h2><?php echo esc_html__( 'Live snapshot (right now, from the database)', 'massuk-configuration' ); ?></h2>
			<table class="widefat striped" style="max-width:640px">
				<thead>
					<tr>
						<th><?php echo esc_html__( 'Type', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Price', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Sold', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Capacity', 'massuk-configuration' ); ?></th>
						<th><?php echo esc_html__( 'Remaining', 'massuk-configuration' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<tr>
						<td><?php echo esc_html__( 'Symposium', 'massuk-configuration' ); ?></td>
						<td><?php echo esc_html( $price['symposium']['open'] ? $price['symposium']['label'] : __( 'Closed', 'massuk-configuration' ) ); ?></td>
						<td><?php echo esc_html( (string) $availability['symposium']['sold'] ); ?></td>
						<td><?php echo esc_html( (string) $availability['symposium']['capacity'] ); ?></td>
						<td><?php echo esc_html( (string) $availability['symposium']['remaining'] ); ?></td>
					</tr>
					<tr>
						<td><?php echo esc_html__( 'Training Camp', 'massuk-configuration' ); ?></td>
						<td><?php echo esc_html( $price['camp']['open'] ? $price['camp']['label'] : __( 'Closed', 'massuk-configuration' ) ); ?></td>
						<td><?php echo esc_html( (string) $availability['camp']['sold'] ); ?></td>
						<td><?php echo esc_html( (string) $availability['camp']['capacity'] ); ?></td>
						<td><?php echo esc_html( (string) $availability['camp']['remaining'] ); ?></td>
					</tr>
				</tbody>
			</table>
			<p>
				<button type="button" class="button" id="muk-test-live-endpoint"><?php echo esc_html__( 'Fetch this from the live REST endpoint', 'massuk-configuration' ); ?></button>
				<span class="description"><?php echo esc_html__( 'If this differs from the table above, something between the browser and this endpoint (a caching plugin, CDN, or a cached copy of the registration page) is serving stale data — not this plugin\'s settings.', 'massuk-configuration' ); ?></span>
			</p>
			<pre id="muk-live-endpoint-result" style="display:none;background:#0e1a3d;color:#e6e6f0;padding:14px;border-radius:8px;max-width:640px;overflow:auto;"></pre>
		</div>
		<script>
		(function () {
			var btn = document.getElementById('muk-test-live-endpoint');
			var out = document.getElementById('muk-live-endpoint-result');
			if (!btn) return;
			btn.addEventListener('click', function () {
				out.style.display = 'block';
				out.textContent = <?php echo wp_json_encode( __( 'Loading…', 'massuk-configuration' ) ); ?>;
				fetch(<?php echo wp_json_encode( $config_url ); ?>, {
					method: 'POST',
					headers: { 'X-MUK-API-Key': <?php echo wp_json_encode( self::get( 'api_key' ) ); ?> },
					cache: 'no-store',
				})
					.then(function (r) { return r.json(); })
					.then(function (data) { out.textContent = JSON.stringify(data, null, 2); })
					.catch(function (err) { out.textContent = 'Request failed: ' + err; });
			});
		})();
		</script>
		<hr />
		<?php
	}

	private static function render_tab_settings( $settings ) {
		self::render_live_snapshot();
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'muk_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[__tab]" value="settings" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Registration pricing', 'massuk-configuration' ); ?></th>
						<td>
							<p class="description"><?php echo esc_html__( 'One tab per month. Prices are in pounds and are used by both registrations — the form loads the active price directly from these settings, switching automatically as each month\'s date range takes over.', 'massuk-configuration' ); ?></p>

							<div class="muk-month-tabs" id="muk-month-tabs">
								<?php foreach ( $settings['pricing_schedule'] as $index => $tier ) :
									$month_label = ! empty( $tier['end_date'] ) ? date_i18n( 'F', strtotime( $tier['end_date'] ) ) : sprintf( __( 'Tier %d', 'massuk-configuration' ), $index + 1 );
									?>
									<button type="button" class="button muk-month-tab-btn <?php echo 0 === $index ? 'is-active' : ''; ?>" data-muk-month-index="<?php echo esc_attr( $index ); ?>"><?php echo esc_html( $month_label ); ?></button>
								<?php endforeach; ?>
							</div>

							<?php foreach ( $settings['pricing_schedule'] as $index => $tier ) : ?>
								<div class="muk-month-panel" data-muk-month-index="<?php echo esc_attr( $index ); ?>" style="<?php echo 0 === $index ? '' : 'display:none;'; ?>">
									<p>
										<label><?php echo esc_html__( 'Price applies until', 'massuk-configuration' ); ?> <input type="date" class="muk-month-end-date" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pricing_schedule][<?php echo esc_attr( $index ); ?>][end_date]" value="<?php echo esc_attr( $tier['end_date'] ); ?>" /></label>
									</p>
									<p>
										<label><?php echo esc_html__( 'Price (£)', 'massuk-configuration' ); ?> <input class="small-text" type="number" step="0.01" min="0.01" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pricing_schedule][<?php echo esc_attr( $index ); ?>][amount]" value="<?php echo esc_attr( number_format( $tier['amount'] / 100, 2, '.', '' ) ); ?>" /></label>
									</p>
									<p>
										<label><?php echo esc_html__( 'Label shown to delegates', 'massuk-configuration' ); ?> <input class="regular-text" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[pricing_schedule][<?php echo esc_attr( $index ); ?>][label]" value="<?php echo esc_attr( $tier['label'] ); ?>" /></label>
									</p>
								</div>
							<?php endforeach; ?>
							<p><button type="button" class="button" id="muk-add-month"><?php echo esc_html__( 'Add month', 'massuk-configuration' ); ?></button></p>
							<script>
							(function () {
								var wrap = document.getElementById('muk-month-tabs');
								if (!wrap) return;
								var optionKey = <?php echo wp_json_encode( self::OPTION_KEY ); ?>;
								var nextIndex = <?php echo (int) count( $settings['pricing_schedule'] ); ?>;

								function activate(idx) {
									wrap.querySelectorAll('.muk-month-tab-btn').forEach(function (b) {
										b.classList.toggle('is-active', b.getAttribute('data-muk-month-index') === String(idx));
									});
									document.querySelectorAll('.muk-month-panel').forEach(function (panel) {
										panel.style.display = panel.getAttribute('data-muk-month-index') === String(idx) ? '' : 'none';
									});
								}

								function bindTab(btn) {
									btn.addEventListener('click', function () {
										activate(btn.getAttribute('data-muk-month-index'));
									});
								}

								wrap.querySelectorAll('.muk-month-tab-btn').forEach(bindTab);

								var addBtn = document.getElementById('muk-add-month');
								if (addBtn) {
									addBtn.addEventListener('click', function () {
										var i = nextIndex++;
										var btn = document.createElement('button');
										btn.type = 'button';
										btn.className = 'button muk-month-tab-btn';
										btn.setAttribute('data-muk-month-index', i);
										btn.textContent = 'New month';
										wrap.appendChild(btn);
										bindTab(btn);

										var panel = document.createElement('div');
										panel.className = 'muk-month-panel';
										panel.setAttribute('data-muk-month-index', i);
										panel.style.display = 'none';
										panel.innerHTML =
											'<p><label>Price applies until <input type="date" name="' + optionKey + '[pricing_schedule][' + i + '][end_date]" value="" /></label></p>' +
											'<p><label>Price (£) <input class="small-text" type="number" step="0.01" min="0.01" name="' + optionKey + '[pricing_schedule][' + i + '][amount]" value="" /></label></p>' +
											'<p><label>Label shown to delegates <input class="regular-text" type="text" name="' + optionKey + '[pricing_schedule][' + i + '][label]" value="" /></label></p>';
										addBtn.parentNode.parentNode.insertBefore(panel, addBtn.parentNode);
										activate(i);
									});
								}
							})();
							</script>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Default price (override)', 'massuk-configuration' ); ?></th>
						<td>
							<label for="muk_symposium_default_price"><?php echo esc_html__( 'Symposium (£)', 'massuk-configuration' ); ?></label>
							<input type="number" step="0.01" min="0" class="small-text" id="muk_symposium_default_price" placeholder="<?php echo esc_attr__( 'None', 'massuk-configuration' ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[symposium_default_price]" value="<?php echo esc_attr( $settings['symposium_default_price'] > 0 ? number_format( $settings['symposium_default_price'] / 100, 2, '.', '' ) : '' ); ?>" />
							&nbsp;&nbsp;
							<label for="muk_camp_default_price"><?php echo esc_html__( 'Training Camp (£)', 'massuk-configuration' ); ?></label>
							<input type="number" step="0.01" min="0" class="small-text" id="muk_camp_default_price" placeholder="<?php echo esc_attr__( 'None', 'massuk-configuration' ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[camp_default_price]" value="<?php echo esc_attr( $settings['camp_default_price'] > 0 ? number_format( $settings['camp_default_price'] / 100, 2, '.', '' ) : '' ); ?>" />
							<p class="description"><?php echo esc_html__( 'If set, this price always wins — it overrides the monthly tabs above completely. Leave it blank ("None") to have the price follow the current month automatically instead.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Stripe product names', 'massuk-configuration' ); ?></th>
						<td>
							<p><label for="muk_symposium_product_name"><?php echo esc_html__( 'Symposium', 'massuk-configuration' ); ?> <input class="regular-text" id="muk_symposium_product_name" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[symposium_product_name]" value="<?php echo esc_attr( $settings['symposium_product_name'] ); ?>" /></label></p>
							<p><label for="muk_camp_product_name"><?php echo esc_html__( 'Training Camp', 'massuk-configuration' ); ?> <input class="regular-text" id="muk_camp_product_name" type="text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[camp_product_name]" value="<?php echo esc_attr( $settings['camp_product_name'] ); ?>" /></label></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_intro_text"><?php echo esc_html__( 'Registration page intro text', 'massuk-configuration' ); ?></label></th>
						<td>
							<input type="text" class="large-text" id="muk_intro_text" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[intro_text]" value="<?php echo esc_attr( $settings['intro_text'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'The sentence shown under "Choose your registration" on the registration page.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_fixed_price_label"><?php echo esc_html__( '"Fixed price" label', 'massuk-configuration' ); ?></label></th>
						<td>
							<input type="text" class="regular-text" id="muk_fixed_price_label" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[fixed_price_label]" value="<?php echo esc_attr( $settings['fixed_price_label'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Shown under the price when a Default price (override) is active.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Seats remaining text', 'massuk-configuration' ); ?></th>
						<td>
							<p><label for="muk_seats_label_symposium"><?php echo esc_html__( 'Symposium', 'massuk-configuration' ); ?> <input type="text" class="large-text" id="muk_seats_label_symposium" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[seats_label_symposium]" value="<?php echo esc_attr( $settings['seats_label_symposium'] ); ?>" /></label></p>
							<p><label for="muk_seats_label_camp"><?php echo esc_html__( 'Training Camp', 'massuk-configuration' ); ?> <input type="text" class="large-text" id="muk_seats_label_camp" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[seats_label_camp]" value="<?php echo esc_attr( $settings['seats_label_camp'] ); ?>" /></label></p>
							<p class="description"><?php echo esc_html__( 'Placeholders: {remaining}, {capacity}.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_stripe_note"><?php echo esc_html__( 'Stripe checkout note', 'massuk-configuration' ); ?></label></th>
						<td>
							<textarea class="large-text" rows="3" id="muk_stripe_note" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[stripe_note]"><?php echo esc_textarea( $settings['stripe_note'] ); ?></textarea>
							<p class="description"><?php echo esc_html__( 'Shown on both forms under "Secure Stripe Checkout".', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Symposium promo box', 'massuk-configuration' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[show_symposium_promo]" value="1" <?php checked( ! empty( $settings['show_symposium_promo'] ) ); ?> /> <?php echo esc_html__( 'Show the "Have a promo code?" box on the Symposium form', 'massuk-configuration' ); ?></label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php echo esc_html__( 'Seat limits', 'massuk-configuration' ); ?></th>
						<td>
							<label for="muk_symposium_capacity"><?php echo esc_html__( 'Symposium capacity', 'massuk-configuration' ); ?></label>
							<input type="number" min="0" class="small-text" id="muk_symposium_capacity" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[symposium_capacity]" value="<?php echo esc_attr( (string) $settings['symposium_capacity'] ); ?>" />
							&nbsp;
							<label for="muk_symposium_sold"><?php echo esc_html__( 'Sold', 'massuk-configuration' ); ?></label>
							<input type="number" min="0" class="small-text" id="muk_symposium_sold" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[symposium_sold]" value="<?php echo esc_attr( (string) $settings['symposium_sold'] ); ?>" />
							<br /><br />
							<label for="muk_camp_capacity"><?php echo esc_html__( 'Training camp capacity', 'massuk-configuration' ); ?></label>
							<input type="number" min="0" class="small-text" id="muk_camp_capacity" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[camp_capacity]" value="<?php echo esc_attr( (string) $settings['camp_capacity'] ); ?>" />
							&nbsp;
							<label for="muk_camp_sold"><?php echo esc_html__( 'Sold', 'massuk-configuration' ); ?></label>
							<input type="number" min="0" class="small-text" id="muk_camp_sold" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[camp_sold]" value="<?php echo esc_attr( (string) $settings['camp_sold'] ); ?>" />
							<p class="description"><?php echo esc_html__( 'Sold counts increase automatically after successful Stripe payment confirmation. You can adjust them manually if needed.', 'massuk-configuration' ); ?></p>
						</td>
					</tr>
				</tbody>
			</table>
			<?php submit_button( __( 'Save Settings', 'massuk-configuration' ) ); ?>
		</form>
		<?php
	}

	/** Email tab: sender address, MailerLite, and per-status email templates. */
	private static function render_tab_email( $settings ) {
		?>
		<form method="post" action="options.php">
			<?php settings_fields( 'muk_settings_group' ); ?>
			<input type="hidden" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[__tab]" value="email" />
			<table class="form-table" role="presentation">
				<tbody>
					<tr>
						<th scope="row"><label for="muk_email_from"><?php echo esc_html__( 'Payment Email From', 'massuk-configuration' ); ?></label></th>
						<td><input type="email" class="regular-text" id="muk_email_from" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_from]" value="<?php echo esc_attr( $settings['email_from'] ); ?>" placeholder="payments@example.com" /><p class="description"><?php echo esc_html__( 'Optional sender address for transactional payment emails. Configure SMTP separately for reliable delivery.', 'massuk-configuration' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_mailerlite_api_token"><?php echo esc_html__( 'MailerLite API Token', 'massuk-configuration' ); ?></label></th>
						<td><input type="password" class="regular-text code" id="muk_mailerlite_api_token" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mailerlite_api_token]" value="" autocomplete="new-password" /><p class="description"><?php echo esc_html__( 'API token from MailerLite. Leave blank to keep the current value.', 'massuk-configuration' ); ?></p></td>
					</tr>
					<tr>
						<th scope="row"><label for="muk_mailerlite_group_id"><?php echo esc_html__( 'MailerLite Group ID', 'massuk-configuration' ); ?></label></th>
						<td><input type="text" class="regular-text code" id="muk_mailerlite_group_id" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mailerlite_group_id]" value="<?php echo esc_attr( $settings['mailerlite_group_id'] ); ?>" /><p class="description"><?php echo esc_html__( 'Optional existing group ID; paid customers are added to it.', 'massuk-configuration' ); ?></p>
							<label><input type="checkbox" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[mailerlite_require_consent]" value="1" <?php checked( ! empty( $settings['mailerlite_require_consent'] ) ); ?> /> <?php echo esc_html__( 'Only sync customers who submitted marketing_consent=true.', 'massuk-configuration' ); ?></label>
						</td>
					</tr>
				</tbody>
			</table>

			<h2><?php echo esc_html__( 'Email templates', 'massuk-configuration' ); ?></h2>
			<p class="description"><?php echo esc_html__( 'Body fields use a rich text (WYSIWYG) editor and are sent as HTML email. Placeholders you can use: {name}, {amount}, {reference}. Symposium and Training Camp each have their own separate templates.', 'massuk-configuration' ); ?></p>
			<?php
			$statuses = array(
				'paid'      => __( 'Payment successful', 'massuk-configuration' ),
				'failed'    => __( 'Payment failed', 'massuk-configuration' ),
				'cancelled' => __( 'Payment cancelled', 'massuk-configuration' ),
			);
			$forms = array(
				'symposium' => __( 'Symposium (Form 1) emails', 'massuk-configuration' ),
				'camp'      => __( 'Training Camp (Form 2) emails', 'massuk-configuration' ),
			);
			foreach ( $forms as $form_key => $form_label ) :
				?>
				<h2 style="margin-top:28px;"><?php echo esc_html( $form_label ); ?></h2>
				<?php foreach ( $statuses as $key => $label ) :
					$field_key = $form_key . '_' . $key;
					?>
					<h3><?php echo esc_html( $label ); ?></h3>
					<table class="form-table" role="presentation">
						<tbody>
							<tr>
								<th scope="row"><label for="muk_email_subject_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html__( 'Subject', 'massuk-configuration' ); ?></label></th>
								<td><input type="text" class="large-text" id="muk_email_subject_<?php echo esc_attr( $field_key ); ?>" name="<?php echo esc_attr( self::OPTION_KEY ); ?>[email_subject_<?php echo esc_attr( $field_key ); ?>]" value="<?php echo esc_attr( $settings[ 'email_subject_' . $field_key ] ); ?>" /></td>
							</tr>
							<tr>
								<th scope="row"><label for="muk_email_body_<?php echo esc_attr( $field_key ); ?>"><?php echo esc_html__( 'Body', 'massuk-configuration' ); ?></label></th>
								<td>
									<?php
									wp_editor(
										$settings[ 'email_body_' . $field_key ],
										'muk_email_body_' . $field_key,
										array(
											'textarea_name' => self::OPTION_KEY . '[email_body_' . $field_key . ']',
											'textarea_rows' => 8,
											'media_buttons' => false,
											'teeny'         => true,
										)
									);
									?>
								</td>
							</tr>
						</tbody>
					</table>
				<?php endforeach; ?>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save Email Settings', 'massuk-configuration' ) ); ?>
		</form>
		<?php
	}

	/** Edit tab: raw HTML/CSS/JS editor for test.html. */
	private static function render_tab_edit() {
		$parts = MUK_Editor::read_parts();
		if ( is_wp_error( $parts ) ) {
			echo '<div class="notice notice-error inline"><p>' . esc_html( $parts->get_error_message() ) . '</p></div>';
			return;
		}
		if ( isset( $_GET['muk_saved'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
			if ( 'error' === $_GET['muk_saved'] ) { // phpcs:ignore WordPress.Security.NonceVerification.Recommended
				$error = get_transient( 'muk_editor_error' );
				delete_transient( 'muk_editor_error' );
				echo '<div class="notice notice-error inline"><p>' . esc_html( $error ? $error : __( 'Could not save.', 'massuk-configuration' ) ) . '</p></div>';
			} else {
				echo '<div class="notice notice-success inline"><p>' . esc_html__( 'test.html saved.', 'massuk-configuration' ) . '</p></div>';
			}
		}
		?>
		<p class="description"><?php echo esc_html__( 'Edit the registration page directly. This changes test.html, which powers both the [muk_registration_form] shortcode and direct HTML-widget embeds.', 'massuk-configuration' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<?php wp_nonce_field( 'muk_save_editor' ); ?>
			<input type="hidden" name="action" value="muk_save_editor" />

			<h2><?php echo esc_html__( 'HTML', 'massuk-configuration' ); ?></h2>
			<textarea name="muk_editor_html" class="muk-code-box" spellcheck="false"><?php echo esc_textarea( $parts['html'] ); ?></textarea>

			<h2><?php echo esc_html__( 'CSS', 'massuk-configuration' ); ?></h2>
			<textarea name="muk_editor_css" class="muk-code-box" spellcheck="false"><?php echo esc_textarea( $parts['css'] ); ?></textarea>

			<h2><?php echo esc_html__( 'JS', 'massuk-configuration' ); ?></h2>
			<textarea name="muk_editor_js" class="muk-code-box" spellcheck="false"><?php echo esc_textarea( $parts['js'] ); ?></textarea>

			<?php submit_button( __( 'Save test.html', 'massuk-configuration' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Generate a random plugin API key (server-side helper).
	 *
	 * @return string
	 */
	public static function generate_api_key() {
		try {
			if ( function_exists( 'random_bytes' ) ) {
				return bin2hex( random_bytes( 24 ) );
			}
		} catch ( Exception $e ) { // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
			// Fall through to WordPress helper.
		}

		if ( function_exists( 'wp_generate_password' ) ) {
			return wp_generate_password( 48, false, false );
		}

		return md5( uniqid( (string) mt_rand(), true ) );
	}

	/**
	 * Return the Stripe secret key for the active mode.
	 *
	 * @return string
	 */
	public static function get_active_secret_key() {
		$settings = self::get_all();
		if ( 'live' === $settings['mode'] ) {
			return (string) $settings['live_secret'];
		}

		return (string) $settings['test_secret'];
	}

	/**
	 * Resolve success URL from request override or settings.
	 *
	 * @param string $override Optional per-request URL.
	 * @return string
	 */
	public static function resolve_success_url( $override = '' ) {
		$override = self::normalize_url( $override );
		if ( $override ) {
			return $override;
		}

		$saved = self::normalize_url( (string) self::get( 'success_url' ) );
		return $saved ? $saved : home_url( '/?payment=success' );
	}

	/**
	 * Resolve cancel URL from request override or settings.
	 *
	 * @param string $override Optional per-request URL.
	 * @return string
	 */
	public static function resolve_cancel_url( $override = '' ) {
		$override = self::normalize_url( $override );
		if ( $override ) {
			return $override;
		}

		$saved = self::normalize_url( (string) self::get( 'cancel_url' ) );
		return $saved ? $saved : home_url( '/?payment=cancelled' );
	}
}
