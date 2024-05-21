<?php

/**
 * Send Invoice to user if/when changing order status to "success" for Check based payment.
 *
 * @param MemberOrder $morder - Updated order as it's being saved
 */
function pmpropbc_send_invoice_email( $morder ) {
    // Only worry about this if this is a check order that is now in "success" status.
    if ( 'check' !== strtolower( $morder->payment_type ) || 'success' !== $morder->status ) {
		return;
	}

	// If using PMPro v3.0+, update the subscription data.
	if ( method_exists( $morder, 'get_subscription' ) ) {
		$subscription = $morder->get_subscription();
		if ( ! empty( $subscription ) ) {
			$subscription->update();
		}
	}

	// Check order meta to see if an invoice email has already been sent for this order.
	if ( function_exists( 'get_pmpro_membership_order_meta' ) && get_pmpro_membership_order_meta( $morder->id, 'pmpropbc_invoice_email_sent', true ) ) {
		return;
	}

	// Make sure that the user still has the membership level for this order.
	if ( ! pmpro_hasMembershipLevel( $morder->membership_id, $morder->user_id ) ) {
		return;
	}

	$recipient = get_user_by( 'ID', $morder->user_id );

	$invoice_email = new PMProEmail();
	$invoice_email->sendInvoiceEmail( $recipient, $morder );

	// Update order meta to indicate that an invoice email has been sent.
	if ( function_exists( 'update_pmpro_membership_order_meta' ) ) {
		update_pmpro_membership_order_meta( $morder->id, 'pmpropbc_invoice_email_sent', true );
	}
}

add_action( 'pmpro_updated_order', 'pmpropbc_send_invoice_email', 10, 1 );

/**
 * Add the Pay by Check email templates to the Core PMPro Email Templates.
 * 
 * @param array $template - The existing PMPro Email Templates.
 * @return array $template - The updated PMPro Email Templates.
 * @since TBD.
 */
function pmpropbc_email_template_to_pmproet_add_on( $template ) {

	$template['check_pending'] = array(
		'subject'     => 'New Invoice for !!display_name!! at !!sitename!!',
		'description' => 'Pay By Check - Check Pending',
		'body'        => file_get_contents( PMPRO_PAY_BY_CHECK_DIR . '/email/check_pending.html' ), 
	);
	$template['check_pending_reminder'] = array(
		'subject'     => 'Reminder: New Invoice for !!display_name!! at !!sitename!!',
		'description' => 'Pay By Check - Check Pending Reminder',
		'body'        => file_get_contents( PMPRO_PAY_BY_CHECK_DIR . '/email/check_pending_reminder.html' ), 
	);

	return $template;
}
add_filter( 'pmproet_templates', 'pmpropbc_email_template_to_pmproet_add_on' );
