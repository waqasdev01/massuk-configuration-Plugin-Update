<?php
/** Transactional payment email service — separate templates per registration type. */
if ( ! defined( 'ABSPATH' ) ) exit;

class MUK_Email_Service {
	public static function send_for_payment( array $payment ) {
		$status = $payment['payment_status'];
		if ( ! in_array( $status, array( 'paid', 'failed', 'cancelled' ), true ) || empty( $payment['customer_email'] ) ) return false;

		$metadata = isset( $payment['metadata'] ) ? json_decode( $payment['metadata'], true ) : array();
		$metadata = is_array( $metadata ) ? $metadata : array();
		$type     = ! empty( $metadata['registration_type'] ) ? sanitize_key( $metadata['registration_type'] ) : ( ! empty( $payment['registration_type'] ) ? sanitize_key( $payment['registration_type'] ) : 'symposium' );
		if ( ! in_array( $type, array( 'symposium', 'camp' ), true ) ) $type = 'symposium';

		$settings = MUK_Settings::get_all();
		$amount   = number_format_i18n( ( (int) $payment['amount'] ) / 100, 2 );

		$subject_key = 'email_subject_' . $type . '_' . $status;
		$body_key    = 'email_body_' . $type . '_' . $status;
		$subject     = ! empty( $settings[ $subject_key ] ) ? $settings[ $subject_key ] : self::default_subject( $type, $status );
		$body        = ! empty( $settings[ $body_key ] ) ? $settings[ $body_key ] : self::default_body( $type, $status );

		$replacements = array(
			'{name}'      => $payment['customer_name'] ? $payment['customer_name'] : __( 'there', 'massuk-configuration' ),
			'{amount}'    => strtoupper( $payment['currency'] ) . ' ' . $amount,
			'{reference}' => $payment['order_reference'],
		);
		$subject = strtr( $subject, $replacements );
		$body    = strtr( $body, $replacements );

		$headers = array( 'Content-Type: text/html; charset=UTF-8' );
		if ( ! empty( $settings['email_from'] ) && is_email( $settings['email_from'] ) ) $headers[] = 'From: ' . sanitize_email( $settings['email_from'] );

		$sent = wp_mail( $payment['customer_email'], $subject, $body, $headers );
		MUK_Payment_Records::mark_delivery( $payment['id'], 'email_status', $sent ? 'sent' : 'failed' );
		return $sent;
	}

	private static function type_label( $type ) {
		return 'camp' === $type ? __( 'Training Camp', 'massuk-configuration' ) : __( 'Symposium', 'massuk-configuration' );
	}

	private static function default_subject( $type, $status ) {
		$labels = array(
			'paid'      => __( 'Payment successful', 'massuk-configuration' ),
			'failed'    => __( 'Payment failed', 'massuk-configuration' ),
			'cancelled' => __( 'Payment cancelled', 'massuk-configuration' ),
		);
		return sprintf( '%s: %s (%s)', $labels[ $status ], get_bloginfo( 'name' ), self::type_label( $type ) );
	}

	private static function default_body( $type, $status ) {
		$labels = array(
			'paid'      => __( 'Payment successful', 'massuk-configuration' ),
			'failed'    => __( 'Payment failed', 'massuk-configuration' ),
			'cancelled' => __( 'Payment cancelled', 'massuk-configuration' ),
		);
		return sprintf(
			"<p>Hello {name},</p><p>%s — %s.</p><p>Amount: {amount}<br />Reference: {reference}</p><p>%s</p>",
			self::type_label( $type ),
			$labels[ $status ],
			( 'paid' === $status ? __( 'Thank you for your registration.', 'massuk-configuration' ) : __( 'Please try again or contact us if you need help.', 'massuk-configuration' ) )
		);
	}
}
