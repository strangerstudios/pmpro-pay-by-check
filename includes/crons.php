<?php

/**
 * Set up crons on plugin activation.
 */
function pmpropbc_activation() {
	wp_schedule_event(current_time('timestamp'), 'daily', 'pmpropbc_cancel_overdue_orders');
	wp_schedule_event(current_time('timestamp')+1, 'daily', 'pmpropbc_recurring_orders');
	wp_schedule_event(current_time('timestamp')+2, 'daily', 'pmpropbc_reminder_emails');

	do_action('pmpropbc_activation');
}
register_activation_hook( PMPRO_PAY_BY_CHECK_BASE_FILE, 'pmpropbc_activation' );

/**
 * Clear crons on plugin deactivation.
 */
function pmpropbc_deactivation() {
	wp_clear_scheduled_hook('pmpropbc_cancel_overdue_orders');
	wp_clear_scheduled_hook('pmpropbc_recurring_orders');
	wp_clear_scheduled_hook('pmpropbc_reminder_emails');

	do_action('pmpropbc_deactivation');
}
register_deactivation_hook( PMPRO_PAY_BY_CHECK_BASE_FILE, 'pmpropbc_deactivation' );

/**
 * Create pending orders for subscriptions.
 */
function pmpropbc_recurring_orders() {
	global $wpdb;

	// If the PMPro_Subscriptions class doesn't exist, use the legacy logic.
	if ( ! class_exists( 'PMPro_Subscription' ) ) {
		pmpropbc_recurring_orders_legacy();
		return;
	}

	// Run for each level.
	$levels = pmpro_getAllLevels(true, true);
	if ( empty( $levels ) || ! is_array( $levels ) ) {
		return;
	}
	foreach ( $levels as $level ) {
		// Get the cutoff day for sending reminder emails. All orders before this date should have reminder emails sent.
		$options = pmpropbc_getOptions($level->id);
		$date = date( "Y-m-d", strtotime( "+ " . $options['renewal_days'] . " days", current_time('timestamp') ) );

		// Get all subscriptions with a next payment date before the cutoff that do not already have a pending order created.
		$subscriptions = $wpdb->get_col(
			$wpdb->prepare(
				"
				SELECT s.id FROM $wpdb->pmpro_subscriptions s
				LEFT JOIN $wpdb->pmpro_membership_orders o ON s.subscription_transaction_id = o.subscription_transaction_id AND o.status = 'pending'
				WHERE s.status = 'active'
				AND s.membership_level_id = %d
				AND s.next_payment_date <= %s
				AND s.gateway = 'check'
				AND o.id IS NULL
				ORDER BY s.next_payment_date ASC;
				",
				$level->id,
				$date
			)
		);
		if ( empty( $subscriptions ) || ! is_array( $subscriptions ) ) {
			continue;
		}

		// Process subscriptions.
		foreach ( $subscriptions as $subscription_id ) {
			// Get the PMPro_Subscription object.
			$subscription = new PMPro_Subscription( $subscription_id );			

			// Create a new order.
			$pending_order = new MemberOrder();
			$pending_order->user_id = $subscription->get_user_id();
			$pending_order->membership_id = $subscription->get_membership_level_id();
			$pending_order->InitialPayment = $subscription->get_billing_amount();
			$pending_order->PaymentAmount = $subscription->get_billing_amount();
			$pending_order->BillingPeriod = $subscription->get_cycle_period();
			$pending_order->BillingFrequency = $subscription->get_cycle_number();
			$pending_order->subscription_transaction_id = $subscription->get_subscription_transaction_id();
			$pending_order->gateway = 'check';
			$pending_order->payment_type = 'Check';
			$pending_order->status = 'pending';
			$pending_order->timestamp = $subscription->get_next_payment_date();
			$pending_order->find_billing_address();

			// Save the order.
			$pending_order->saveOrder();

			// Send a check_pending email.
			pmpropbc_send_check_pending_email( $pending_order );
		}
	}
}
add_action('pmpropbc_recurring_orders', 'pmpropbc_recurring_orders');

/*
	Send reminder emails for pending invoices.
*/
function pmpropbc_reminder_emails() {
	global $wpdb;

	// Run for each level.
	$levels = pmpro_getAllLevels(true, true);
	if ( empty( $levels ) || ! is_array( $levels ) ) {
		return;
	}
	foreach ( $levels as $level ) {
		// Get the cutoff day for sending reminder emails. All orders before this date should have reminder emails sent.
		$options = pmpropbc_getOptions($level->id);
		$date = date( "Y-m-d", strtotime( "- " . $options['reminder_days'] . " days", current_time('timestamp') ) );

		// Get all check orders still pending after X days
		$sqlQuery = "
			SELECT id 
			FROM $wpdb->pmpro_membership_orders 
			WHERE membership_id = $level->id 
				AND gateway = 'check' 
				AND status = 'pending' 
				AND timestamp <= '" . $date . "'
				AND notes NOT LIKE '%Reminder Sent:%' AND notes NOT LIKE '%Reminder Skipped:%'
			ORDER BY id
		";
		if ( defined( 'PMPRO_CRON_LIMIT' ) ) {
			$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;
		}
		$orders = $wpdb->get_col($sqlQuery);
		if ( empty( $orders ) || ! is_array( $orders ) ) {
			continue;
		}

		// Process the orders.
		foreach ( $orders as $order_id ) {
			// Get the order object.
			$order = new MemberOrder($order_id);

			// Note when we send the reminder.
			$new_notes = $order->notes . "Reminder Sent:" . date( 'Y-m-d' ) . "\n";
			$wpdb->query( "UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql( $new_notes ) . "' WHERE id = '" . $order_id . "' LIMIT 1" );

			// Send email.
			pmpropbc_send_check_pending_reminder_email( $order );
		}
	}
}
add_action('pmpropbc_reminder_emails', 'pmpropbc_reminder_emails');

/**
 * Cancel overdue members.
 */
function pmpropbc_cancel_overdue_orders() {
	global $wpdb;

	// Run for each level.
	$levels = pmpro_getAllLevels(true, true);
	if ( empty( $levels ) || ! is_array( $levels ) ) {
		return;
	}
	foreach( $levels as $level ) {
		// Get the cutoff day for cancelling old pending orders. All orders before this date should be cancelled.
		$options = pmpropbc_getOptions($level->id);
		$date = date( "Y-m-d", strtotime( "- " . $options['cancel_days'] . " days", current_time('timestamp') ) );

		//get all check orders still pending after X days
		$sqlQuery = "
			SELECT id 
			FROM $wpdb->pmpro_membership_orders 
			WHERE membership_id = $level->id 
				AND gateway = 'check' 
				AND status = 'pending' 
				AND timestamp <= '" . $date . "'
			ORDER BY id
		";
		if ( defined( 'PMPRO_CRON_LIMIT' ) ) {
			$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;
		}
		$orders = $wpdb->get_col($sqlQuery);
		if ( empty( $orders ) || ! is_array( $orders ) ) {
			continue;
		}

		// Process the orders.
		foreach ( $orders as $order_id ) {
			// Get the order object.
			$order = new MemberOrder($order_id);

			//remove their membership
			$level_removed = pmpro_cancelMembershipLevel( $order->membership_id, $order->user_id, 'cancelled' );

			// Update the order.
			$order->status = 'error';
			$order->notes .= "Check Payment Cancelled:" . date( 'Y-m-d' ) . "\n";
			$order->saveOrder();

			// Send an email to the member.
			$user = get_userdata( $order->user_id );
			if ( ! empty( $user ) && $level_removed ) {
				$email = new PMProEmail();
				$email->sendCancelEmail( $user, $order->membership_id );
			}

		}
	}
}
add_action('pmpropbc_cancel_overdue_orders', 'pmpropbc_cancel_overdue_orders');

/**
 * Legacy logic for creating pending orders for recurring subscriptions.
 * This function should be deprecated once PMPro v3.0 is widespread.
 */
function pmpropbc_recurring_orders_legacy() {
	global $wpdb;

	//make sure we only run once a day
	$now = current_time('timestamp');
	$today = date("Y-m-d", $now);

	//have to run for each level, so get levels
	$levels = pmpro_getAllLevels(true, true);

	if(empty($levels))
		return;

	foreach($levels as $level)
	{
		//get options
		$options = pmpropbc_getOptions($level->id);
		if(!empty($options['renewal_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['renewal_days'] . " days", $now));
		else
			$date = $today;

		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";
		$combos = $wpdb->get_col($sqlQuery);

		if(empty($combos))
			continue;

		foreach($combos as $combo)
		{
			//check if it's been one pay period since the last payment
			/*
				- Check should create an invoice X days before expiration based on a setting on the levels page.
				- Set invoice date based on cycle and the day of the month of the member start date.
				- Send a reminder email Y days after initial invoice is created if it's still pending.
				- Cancel membership after Z days if invoice is not paid. Send email.
// ADDED Extra brackets round OR as sql results were missing some orders (match for same user id was not being used due to the missing brackets) 
			*/
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT o1.id FROM
				    (SELECT mo.id, mo.user_id, mo.timestamp
				    FROM {$wpdb->pmpro_membership_orders} AS mo
				    WHERE mo.membership_id = $level->id
				        AND mo.gateway = 'check'
				        AND mo.status IN('pending', 'success')
				    ) as o1

					LEFT OUTER JOIN 
					
					(SELECT mo1.id, mo1.user_id, mo1.timestamp
				    FROM {$wpdb->pmpro_membership_orders} AS mo1
				    WHERE mo1.membership_id = $level->id
				        AND mo1.status IN('pending', 'success')
				    ) as o2

					ON o1.user_id = o2.user_id
					AND (o1.timestamp < o2.timestamp
					OR (o1.timestamp = o2.timestamp AND o1.id < o2.id))
				WHERE
					o2.id IS NULL
					AND DATE_ADD(o1.timestamp, INTERVAL $combo) <= '" . $date . "'
			";

			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;

			foreach($orders as $order_id)
			{
				$order = new MemberOrder($order_id);

				// If using PMPro v3.0+, only create a pending order if the subscription is still active.
				if ( method_exists( $order, 'get_subscription' ) ) {
					$subscription = $order->get_subscription();
					if ( ! empty( $subscription ) && 'active' !== $subscription->get_status() ) {
						continue;
					}
				}

				$user = get_userdata($order->user_id);
				if ( $user ) {
					$user->membership_level = pmpro_getSpecificMembershipLevelForUser( $order->user_id, $level->id );
				}

				//check that user still has same level?
				if(empty($user->membership_level) || $order->membership_id != $user->membership_level->id)
					continue;

				// If Paid Memberships Pro - Auto-Renewal Checkbox is active there may be mixed recurring and non-recurring users at this level
				if( $user->membership_level->cycle_number == 0 || $user->membership_level->billing_amount == 0)
				  continue;

				// Make sure that the user's billing structure is the same as the billing structure that we are checking for ($combo).
				if ( $user->membership_level->cycle_number . ' ' . $user->membership_level->cycle_period != $combo ) {
					continue;
				}

				//create new pending order
				$morder = new MemberOrder();
				$morder->user_id = $order->user_id;
				$morder->membership_id = $user->membership_level->id;
				$morder->InitialPayment = $user->membership_level->billing_amount;
				$morder->PaymentAmount = $user->membership_level->billing_amount;
				$morder->BillingPeriod = $user->membership_level->cycle_period;
				$morder->BillingFrequency = $user->membership_level->cycle_number;
				$morder->subscription_transaction_id = $order->subscription_transaction_id;
				$morder->gateway = "check";
				$morder->setGateway();
				$morder->payment_type = "Check";
				$morder->status = "pending";

				// Copy billing addres from last order
				$morder->billing = new stdClass();
				$morder->billing->name = $order->billing->name;
				$morder->billing->street = $order->billing->street;
				$morder->billing->city = $order->billing->city;
				$morder->billing->state = $order->billing->state;
				$morder->billing->zip = $order->billing->zip;
				$morder->billing->country = $order->billing->country;

				//get timestamp for new order
				$order_timestamp = strtotime("+" . $combo, $order->timestamp);

				//let's skip if there is already an order for this user/level/timestamp
				// make sure there's no order for the current order_timesamp (ignore hours/seconds and focus on the day value itself).
				$dupe = $wpdb->get_var( "SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . esc_sql( $order->user_id ) . "' AND membership_id = '" . esc_sql( $order->membership_id ) . "' AND timestamp LIKE '" . esc_sql( date( 'Y-m-d', $order_timestamp ) ) . "%' LIMIT 1" );

				if(!empty($dupe))
					continue;

				//save it
				$morder->process();
				$morder->timestamp = $order_timestamp;
				$morder->saveOrder();

				// Send email.
				pmpropbc_send_check_pending_email( $morder );
			}
		}
	}
}
