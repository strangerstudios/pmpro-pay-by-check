<?php

/**
 * Show levels with pending payments on the account page.
 */
function pmpropbc_pmpro_account_bullets_bottom() {
	$user_levels = pmpro_getMembershipLevelsForUser( get_current_user_id() );
	if ( empty( $user_levels ) ) {
		return;
	}

	foreach ( $user_levels as $level ) {
		// Get the last order for this level.
		$order = new MemberOrder();
		$order->getLastMemberOrder( get_current_user_id(), array('success', 'pending', 'cancelled' ), $level->id );

		// If the order is pending and it was a check payment, show a message.
		if ( $order->status == 'pending' && $order->gateway == 'check' ) {
			?>
			<li>
				<?php
				// Check if the user is pending for the level.
				if ( pmpropbc_isMemberPending( $order->user_id, $order->membership_id ) ) {
					printf( esc_html__('%sYour %s membership is pending.%s We are still waiting for payment for %syour latest invoice%s.', 'pmpro-pay-by-check'), '<strong>', esc_html( $level->name ), '</strong>', sprintf( '<a href="%s">', pmpro_url('invoice', '?invoice=' . $order->code) ), '</a>' );
				} else {
					printf( esc_html__('%sImportant Notice:%s We are still waiting for payment on %sthe latest invoice%s for your %s membership.', 'pmpro-pay-by-check'), '<strong>', '</strong>', sprintf( '<a href="%s">', pmpro_url('invoice', '?invoice=' . $order->code ) ), '</a>', esc_html( $level->name ) );
				}
				?>
			</li>
			<?php
		}
	}
}
add_action('pmpro_account_bullets_bottom', 'pmpropbc_pmpro_account_bullets_bottom');

/**
 * If an invoice is pending, show a message on the invoice page.
 */
function pmpropbc_pmpro_invoice_bullets_bottom() {
	if ( empty( $_REQUEST['invoice'] ) ) {
		return;
	}

	// Get the order.
	$order = new MemberOrder( $_REQUEST['invoice'] );

	// Check if it is pending and a check payment.
	if ( $order->status == 'pending' && $order->gateway == 'check' ) {
		?>
		<li>
			<?php
			// Check if the user is pending for the level.
			if ( pmpropbc_isMemberPending( $order->user_id, $order->membership_id ) ) {
				printf( esc_html__('%sMembership pending.%s We are still waiting for payment of this invoice.', 'pmpro-pay-by-check'), '<strong>', '</strong>' );
			} else {
				printf( esc_html__('%sImportant Notice:%s We are still waiting for payment of this invoice.', 'pmpro-pay-by-check'), '<strong>', '</strong>' );
			}
			?>
		</li>
		<?php
	}
}
add_action('pmpro_invoice_bullets_bottom', 'pmpropbc_pmpro_invoice_bullets_bottom');


/**
 * Filter the confirmation message of Paid Memberships Pro when the gateway is check and the payment isn't successful.
 *
 * @param string $confirmation_message The confirmation message before it is altered.
 * @param object $invoice The PMPro MemberOrder object.
 * @return string $confirmation_message The level confirmation message.
 */
function pmpropbc_confirmation_message( $confirmation_message, $invoice ) {

	// Only filter orders that are done by check.
	if ( $invoice->gateway !== 'check' || ( $invoice->gateway == 'check' && $invoice->status == 'success' ) ) {
		return $confirmation_message;
	}

	$user = get_user_by( 'ID', $invoice->user_id );
	
	$confirmation_message = '<p>' . sprintf( __( 'Thank you for your membership to %1$s. Your %2$s membership status is: <b>%3$s</b>.', 'pmpro-pay-by-check' ), get_bloginfo( 'name' ), $invoice->membership_level->name, $invoice->status ) . ' ' . __( 'Once payment is received and processed you will gain access to your membership content.', 'pmpro-pay-by-check' ) . '</p>';

	// Put the level confirmation from level settings into the message.
	$level_obj = pmpro_getLevel( $invoice->membership_id );
	if ( ! empty( $level_obj->confirmation ) ) {
		$confirmation_message .= wpautop( wp_unslash( $level_obj->confirmation ) );
	}

	$confirmation_message .= '<p>' . sprintf( __( 'Below are details about your membership account and a receipt for your initial membership invoice. A welcome email with a copy of your initial membership invoice has been sent to %s.', 'pmpro-pay-by-check' ), $user->user_email ) . '</p>';

	// Put the check instructions into the message.
	$invoice->getMembershipLevel();
	if ( ! empty( $invoice ) && $invoice->gateway == 'check' && ! pmpro_isLevelFree( $invoice->membership_level ) ) {
		$confirmation_message .= '<div class="pmpro_payment_instructions">' . wpautop( wp_unslash( get_option( 'pmpro_instructions' ) ) ) . '</div>';
	}
	
	// Run it through wp_kses_post in case someone translates the strings to have weird code.
	return wp_kses_post( $confirmation_message );

}
add_filter( 'pmpro_confirmation_message', 'pmpropbc_confirmation_message', 10, 2 );