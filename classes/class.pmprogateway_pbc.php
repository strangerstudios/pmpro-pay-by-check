<?php
class PMProGateway_pbc extends PMProGateway {
	function __construct() {
		$this->gateway = 'check';
	}

	/**
	 * Check whether or not a gateway supports a specific feature.
	 * 
	 * @since 3.0
	 * 
	 * @return bool|string
	 */
	public static function supports( $feature ) {
		$supports = array(
			'subscription_sync' => true,
		);

		if ( empty( $supports[$feature] ) ) {
			return false;
		}

		return $supports[$feature];
	}

	/**
	 * Process checkout.
	 */
	function process(&$order) {
		//clean up a couple values
		$order->payment_type = 'Check';
		$order->CardType = '';
		$order->cardtype = '';
		if ( empty( $order->code ) ) {
			$order->code = $order->getRandomCode();
		}

		// Set subscription transaction ID if needed.
		$checkout_level = $order->getMembershipLevelAtCheckout();
		if ( pmpro_isLevelRecurring( $checkout_level ) ) {
			$order->subscription_transaction_id = 'CHECK' . $order->code;
		}

		// Save the order so that we can update order meta.
		// Set the order to pending and save the checkout data into the order.
		$order->status = 'pending';
		$order->saveOrder();

		// Save the profile start date to order meta so that we can use it later when we create the subscription.
		update_pmpro_membership_order_meta( $order->id, 'pbc_profile_start_date', pmpro_calculate_profile_start_date( $order, 'Y-m-d H:i:s' ) );

		// If this order was free, then we don't need to wait for the check to be recieved.
		// Set the order to success and complete the checkout.
		if ( empty( $order->total ) ) {
			$order->status = 'success';
			return true;
		}

		// The order is paid, so we need to wait for a check to be recieved.
		// Save the checkout data into the order.
		pmpro_save_checkout_data_to_order( $order );

		// Send the check_pending emails.
		pmpropbc_send_check_pending_email( $order );
		pmpropbc_send_check_pending_admin_email( $order );

		// Redirect to the confirmation page and await checkout completion.
		$confirmation_url = apply_filters( 'pmpro_confirmation_url', add_query_arg( 'pmpro_level', $order->membership_level->id, pmpro_url("confirmation" ) ), $order->user_id, $order->membership_level );
		wp_redirect( $confirmation_url );
		exit;
	}

	/**
	 * Synchronizes a subscription with this payment gateway.
	 *
	 * @since 3.0
	 *
	 * @param PMPro_Subscription $subscription The subscription to synchronize.
	 * @return string|null Error message is returned if update fails.
	 */
	public function update_subscription_info( $subscription ) {
		// Track the fields that need to be updated.
		$update_array = array();

		// Update the start date to the date of the first order for this subscription if it
		// it is earlier than the current start date.
		$oldest_orders = $subscription->get_orders( [
			'limit'   => 1,
			'orderby' => '`timestamp` ASC, `id` ASC',
		] );
		if ( ! empty( $oldest_orders ) ) {
			$oldest_order = current( $oldest_orders );
			if ( empty( $subscription->get_startdate() ) || $oldest_order->getTimestamp( true ) < strtotime( $subscription->get_startdate() ) ) {
				$update_array['startdate'] = date_i18n( 'Y-m-d H:i:s', $oldest_order->getTimestamp( true ) );
			}
		}

		// Update the subscription's next payment date.
		// If there is a pending order for this subscription, the subscription's next payment date should be the timestamp of the oldest pending order.
		$pending_orders = $subscription->get_orders(
			array(
				'status'  => 'pending',
				'orderby' => '`timestamp` ASC, `id` ASC',
				'limit'   => 1,
			)
		);
		if ( ! empty( $pending_orders ) ) {
			// Get the oldest pending order.
			$oldest_pending_order = current( $pending_orders );

			// Set the next payment date to the timestamp of the oldest pending order.
			$update_array['next_payment_date'] = date_i18n( 'Y-m-d H:i:s', $oldest_pending_order->getTimestamp( true ) );
		} else {
			// There are no pending orders. Get the most recent successful order.
			$newest_orders = $subscription->get_orders(
				array(
					'status' => 'success',
					'limit'  => 1
				)
			);
			if ( ! empty( $newest_orders ) ) {
				$newest_order = current( $newest_orders );

				// Check if the most recent order has pbc_profile_start_date meta set.
				$pbc_profile_start_date = get_pmpro_membership_order_meta( $newest_order->id, 'pbc_profile_start_date', true );
				if ( ! empty( $pbc_profile_start_date ) ) {
					// Set the next payment date to the profile start date.
					$update_array['next_payment_date'] = $pbc_profile_start_date;
				} else {
					// Set the next payment date to the timestamp of the newest successful order.
					$update_array['next_payment_date'] = date_i18n( 'Y-m-d H:i:s', strtotime( '+ ' . $subscription->get_cycle_number() . ' ' . $subscription->get_cycle_period(), $newest_order->getTimestamp( true ) ) );
				}
			}
		}

		// Update the subscription.
		$subscription->set( $update_array );
	}
}
