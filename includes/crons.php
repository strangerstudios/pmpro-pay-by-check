<?php

/*
	Activation/Deactivation
*/
function pmpropbc_activation()
{
	//schedule crons
	wp_schedule_event(current_time('timestamp'), 'daily', 'pmpropbc_cancel_overdue_orders');
	wp_schedule_event(current_time('timestamp')+1, 'daily', 'pmpropbc_recurring_orders');
	wp_schedule_event(current_time('timestamp')+2, 'daily', 'pmpropbc_reminder_emails');

	do_action('pmpropbc_activation');
}
register_activation_hook(__FILE__, 'pmpropbc_activation');

function pmpropbc_deactivation()
{
	//remove crons
	wp_clear_scheduled_hook('pmpropbc_cancel_overdue_orders');
	wp_clear_scheduled_hook('pmpropbc_recurring_orders');
	wp_clear_scheduled_hook('pmpropbc_reminder_emails');

	do_action('pmpropbc_deactivation');
}
register_deactivation_hook(__FILE__, 'pmpropbc_deactivation');

/*
	Create pending orders for recurring levels.
*/
function pmpropbc_recurring_orders()
{
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

				//send emails
				$email = new PMProEmail();
				$email->template = "check_pending";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("New Invoice for %s at %s", "pmpro-pay-by-check"), $user->membership_level->name, get_option("blogname"));

				//get body from template
				$email->body = file_get_contents(PMPRO_PAY_BY_CHECK_DIR . "/email/" . $email->template . ".html");

				//setup more data
				$email->data = array(
					"name" => $user->display_name,
					"user_login" => $user->user_login,
					"sitename" => get_option("blogname"),
					"siteemail" => pmpro_getOption("from_email"),
					"membership_id" => $user->membership_level->id,
					"membership_level_name" => $user->membership_level->name,
					"membership_cost" => pmpro_getLevelCost($user->membership_level),
					"login_link" => wp_login_url(pmpro_url("account")),
					"display_name" => $user->display_name,
					"user_email" => $user->user_email,
				);

				$email->data["instructions"] = wp_unslash(  pmpro_getOption('instructions') );
				$email->data["invoice_id"] = $morder->code;
				$email->data["invoice_total"] = pmpro_formatPrice($morder->total);
				$email->data["invoice_date"] = date(get_option('date_format'), $morder->timestamp);
				$email->data["billing_name"] = $morder->billing->name;
				$email->data["billing_street"] = $morder->billing->street;
				$email->data["billing_city"] = $morder->billing->city;
				$email->data["billing_state"] = $morder->billing->state;
				$email->data["billing_zip"] = $morder->billing->zip;
				$email->data["billing_country"] = $morder->billing->country;
				$email->data["billing_phone"] = $morder->billing->phone;
				$email->data["cardtype"] = $morder->cardtype;
				$email->data["accountnumber"] = hideCardNumber($morder->accountnumber);
				$email->data["expirationmonth"] = $morder->expirationmonth;
				$email->data["expirationyear"] = $morder->expirationyear;
				$email->data["billing_address"] = pmpro_formatAddress($morder->billing->name,
																	 $morder->billing->street,
																	 "", //address 2
																	 $morder->billing->city,
																	 $morder->billing->state,
																	 $morder->billing->zip,
																	 $morder->billing->country,
																	 $morder->billing->phone);

				if($morder->getDiscountCode())
					$email->data["discount_code"] = "<p>" . __("Discount Code", "pmpro") . ": " . $morder->discount_code->code . "</p>\n";
				else
					$email->data["discount_code"] = "";

				//send the email
				$email->sendEmail();
			}
		}
	}
}
add_action('pmpropbc_recurring_orders', 'pmpropbc_recurring_orders');

/*
	Send reminder emails for pending invoices.
*/
function pmpropbc_reminder_emails()
{
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
		// subtract reminder_days from current date as we are looking for invoices from or before that date
		// this is relative to the date the reminder was sent out not when it was due I think
		if(!empty($options['reminder_days']))
			$date = date("Y-m-d", strtotime("- " . $options['reminder_days'] . " days", $now));
		else
			$date = $today;

		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";
		$combos = $wpdb->get_col($sqlQuery);

		if(empty($combos))
			continue;

		foreach($combos as $combo)
		{
			//get all check orders still pending after X days
		  // don't add the INTERVAL here!
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

			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;

			foreach($orders as $order_id)
			{
				//get some data
				$order = new MemberOrder($order_id);

				// If using PMPro v3.0+, only send reminders if the subscription is still active.
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

				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Reminder Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}

				// If Paid Memberships Pro - Auto-Renewal Checkbox is active there may be mixed recurring and non-recurring users at this level
				if ( $user->membership_level->cycle_number == 0 || $user->membership_level->billing_amount == 0 ) {
					continue;
				}

				// Make sure that the user's billing structure is the same as the billing structure that we are checking for ($combo).
				if ( $user->membership_level->cycle_number . ' ' . $user->membership_level->cycle_period != $combo ) {
					continue;
				}

				//note when we send the reminder
				$new_notes = $order->notes . "Reminder Sent:" . $today . "\n";
				$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

				//setup email to send
				$email = new PMProEmail();
				$email->template = "check_pending_reminder";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("Reminder: New Invoice for %s at %s", "pmpro-pay-by-check"), $user->membership_level->name, get_option("blogname"));
				//get body from template
				$email->body = file_get_contents(PMPRO_PAY_BY_CHECK_DIR . "/email/" . $email->template . ".html");

				//setup more data
				$email->data = array(
					"name" => $user->display_name,
					"user_login" => $user->user_login,
					"sitename" => get_option("blogname"),
					"siteemail" => get_option("pmpro_from_email"),
					"membership_id" => $user->membership_level->id,
					"membership_level_name" => $user->membership_level->name,
					"membership_cost" => pmpro_getLevelCost($user->membership_level),
					"login_link" => wp_login_url(pmpro_url("account")),
					"display_name" => $user->display_name,
					"user_email" => $user->user_email,
				);

				$email->data["instructions"] = wp_unslash( get_option('pmpro_instructions') );
				$email->data["invoice_id"] = $order->code;
				$email->data["invoice_total"] = pmpro_formatPrice($order->total);
				$email->data["invoice_date"] = date(get_option('date_format'), $order->timestamp);
				$email->data["billing_name"] = $order->billing->name;
				$email->data["billing_street"] = $order->billing->street;
				$email->data["billing_city"] = $order->billing->city;
				$email->data["billing_state"] = $order->billing->state;
				$email->data["billing_zip"] = $order->billing->zip;
				$email->data["billing_country"] = $order->billing->country;
				$email->data["billing_phone"] = $order->billing->phone;
				$email->data["cardtype"] = $order->cardtype;
				$email->data["accountnumber"] = hideCardNumber($order->accountnumber);
				$email->data["expirationmonth"] = $order->expirationmonth;
				$email->data["expirationyear"] = $order->expirationyear;
				$email->data["billing_address"] = pmpro_formatAddress($order->billing->name,
																	 $order->billing->street,
																	 "", //address 2
																	 $order->billing->city,
																	 $order->billing->state,
																	 $order->billing->zip,
																	 $order->billing->country,
																	 $order->billing->phone);

				if($order->getDiscountCode())
					$email->data["discount_code"] = "<p>" . __("Discount Code", "pmpro") . ": " . $order->discount_code->code . "</p>\n";
				else
					$email->data["discount_code"] = "";

				//send the email
				$email->sendEmail();
			}
		}
	}
}
add_action('pmpropbc_reminder_emails', 'pmpropbc_reminder_emails');

/*
	Cancel overdue members.
*/
function pmpropbc_cancel_overdue_orders()
{
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
		
		// subtract cancel_days not add, we want the older orders not paid
		// this is relative to the date the reminder was sent out not when it was due
		if(!empty($options['cancel_days']))
			$date = date("Y-m-d", strtotime("- " . $options['cancel_days'] . " days", $now));
		else
			$date = $today;

		//need to get all combos of pay cycle and period
		$sqlQuery = "SELECT DISTINCT(CONCAT(cycle_number, ' ', cycle_period)) FROM $wpdb->pmpro_memberships_users WHERE membership_id = '" . $level->id . "' AND cycle_number > 0 AND status = 'active'";
		$combos = $wpdb->get_col($sqlQuery);

		if(empty($combos))
			continue;

		foreach($combos as $combo)
		{
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT id 
				FROM $wpdb->pmpro_membership_orders 
				WHERE membership_id = $level->id 
					AND gateway = 'check' 
					AND status = 'pending' 
					AND timestamp <= '" . $date . "'
					AND notes NOT LIKE '%Cancelled:%' AND notes NOT LIKE '%Cancellation Skipped:%'
				ORDER BY id
			";

			if(defined('PMPRO_CRON_LIMIT'))
				$sqlQuery .= " LIMIT " . PMPRO_CRON_LIMIT;

			$orders = $wpdb->get_col($sqlQuery);

			if(empty($orders))
				continue;

			foreach($orders as $order_id)
			{
				//get the order and user data
				$order = new MemberOrder($order_id);

				// If using PMPro v3.0+, only process overdue orders if the subscription is still active.
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

				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Cancellation Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}

				// If Paid Memberships Pro - Auto-Renewal Checkbox is active there may be mixed recurring and non-recurring users at this level
				if ( $user->membership_level->cycle_number == 0 || $user->membership_level->billing_amount == 0 ) {
					continue;
				}

				// Make sure that the user's billing structure is the same as the billing structure that we are checking for ($combo).
				if ( $user->membership_level->cycle_number . ' ' . $user->membership_level->cycle_period != $combo ) {
					continue;
				}

				//cancel the order and subscription
				do_action("pmpro_membership_pre_membership_expiry", $order->user_id, $order->membership_id );

				//remove their membership
				pmpro_cancelMembershipLevel( $order->membership_id, $order->user_id, 'expired' );

				// Update the order.
				$order->status = 'error';
				$order->notes .= "Cancelled:" . $today . "\n";
				$order->saveOrder();

				do_action("pmpro_membership_post_membership_expiry", $order->user_id, $order->membership_id );
				$send_email = apply_filters("pmpro_send_expiration_email", true, $order->user_id);
				if($send_email)
				{
					//send an email
					$pmproemail = new PMProEmail();
					$euser = get_userdata($order->user_id);
					$pmproemail->sendMembershipExpiredEmail($euser);
					if(current_user_can('manage_options'))
						printf(__("Membership expired email sent to %s. ", "pmpro"), $euser->user_email);
					else
						echo ". ";
				}
			}
		}
	}
}
add_action('pmpropbc_cancel_overdue_orders', 'pmpropbc_cancel_overdue_orders');