<?php
/*
Plugin Name: Paid Memberships Pro - Pay by Check Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-pay-by-check/
Description: A collection of customizations useful when allowing users to pay by check for Paid Memberships Pro levels.
Version: .5
Author: Stranger Studios
Author URI: http://www.strangerstudios.com
*/
/*
	Sample use case: You have a paid level that you want to allow people to pay by check for.
	
	1. Change your Payment Settings to the "Pay by Check" gateway and make sure to set the "Instructions" with instructions for how to pay by check. Save.
	2. Change the Payment Settings back to use your gateway of choice. Behind the scenes the Pay by Check settings are still stored.
	
	* Users who choose to pay by check will have their order to "pending" status.
	* Users with a pending order will not have access based on their level.
	* After you recieve and cash the check, you can edit the order to change the status to "success", which will give the user access.
	* An email is sent to the user RE the status change.
*/

/*
	Settings, Globals and Constants
*/
define("PMPRO_PAY_BY_CHECK_DIR", dirname(__FILE__));

/*
	Add settings to the edit levels page
*/
//show the checkbox on the edit level page
function pmpropbc_pmpro_membership_level_after_other_settings()
{	
	$level_id = intval($_REQUEST['edit']);
	$options = pmpropbc_getOptions($level_id);	
?>
<h3 class="topborder">Pay by Check Settings</h3>
<p>Change this setting to allow or disallow the pay by check option for this level.</p>
<table>
<tbody class="form-table">
	<tr>
		<th scope="row" valign="top"><label for="pbc_setting"><?php _e('Allow Pay by Check:', 'pmpro');?></label></th>
		<td>
			<select id="pbc_setting" name="pbc_setting">
				<option value="0" <?php selected($options['setting'], 0);?>>No. Use the default gateway only.</option>
				<option value="1" <?php selected($options['setting'], 1);?>>Yes. Users choose between default gateway and check.</option>
				<option value="2" <?php selected($options['setting'], 2);?>>Yes. Users can only pay by check.</option>
			</select>
		</td>
	</tr>
	<tr class="pbc_recurring_field">
		<th scope="row" valign="top"><label for="pbc_renewal_days"><?php _e('Send Renewal Emails:', 'pmpro');?></label></th>
		<td>
			<input type="text" id="pbc_renewal_days" name="pbc_renewal_days" size="5" value="<?php echo esc_attr($options['renewal_days']);?>" /> days before renewal.
		</td>
	</tr>
	<tr class="pbc_recurring_field">
		<th scope="row" valign="top"><label for="pbc_reminder_days"><?php _e('Send Reminder Emails:', 'pmpro');?></label></th>
		<td>
			<input type="text" id="pbc_reminder_days" name="pbc_reminder_days" size="5" value="<?php echo esc_attr($options['reminder_days']);?>" /> days after a missed payment.
		</td>
	</tr>
	<tr class="pbc_recurring_field">
		<th scope="row" valign="top"><label for="pbc_cancel_days"><?php _e('Cancel Membership:', 'pmpro');?></label></th>
		<td>
			<input type="text" id="pbc_cancel_days" name="pbc_cancel_days" size="5" value="<?php echo esc_attr($options['cancel_days']);?>" /> days after a missed payment.
		</td>
	</tr>
	<script>
		function togglePBCRecurringOptions() {
			if(jQuery('#pbc_setting').val() > 0 && jQuery('#recurring').is(':checked')) { 
				jQuery('tr.pbc_recurring_field').show(); 
			} else {
				jQuery('tr.pbc_recurring_field').hide(); 
			}
		}
		
		jQuery(document).ready(function(){
			//hide/show recurring fields on page load
			togglePBCRecurringOptions();
			
			//hide/show recurring fields when pbc or recurring settings change
			jQuery('#pbc_setting').change(function() { togglePBCRecurringOptions() });
			jQuery('#recurring').change(function() { togglePBCRecurringOptions() });
		});
	</script>
</tbody>
</table>
<?php
}
add_action('pmpro_membership_level_after_other_settings', 'pmpropbc_pmpro_membership_level_after_other_settings');

//save pay by check settings when the level is saved/added
function pmpropbc_pmpro_save_membership_level($level_id)
{
	//get values
	if(isset($_REQUEST['pbc_setting']))
		$pbc_setting = intval($_REQUEST['pbc_setting']);
	else
		$pbc_setting = 0;
		
	$renewal_days = intval($_REQUEST['pbc_renewal_days']);
	$reminder_days = intval($_REQUEST['pbc_reminder_days']);
	$cancel_days = intval($_REQUEST['pbc_cancel_days']);
	
	//build array
	$options = array(
		'setting' => $pbc_setting,
		'renewal_days' => $renewal_days,
		'reminder_days' => $reminder_days,
		'cancel_days' => $cancel_days,
	);
	
	//save
	delete_option('pmpro_pay_by_check_setting_' . $level_id);
	delete_option('pmpro_pay_by_check_options_' . $level_id);
	add_option('pmpro_pay_by_check_options_' . intval($level_id), $options, "", "no");
}
add_action("pmpro_save_membership_level", "pmpropbc_pmpro_save_membership_level");

/*
	Helper function to get options.
*/
function pmpropbc_getOptions($level_id)
{
	if($level_id > 0)
	{
		//option for level, check the DB
		$options = get_option('pmpro_pay_by_check_options_' . $level_id, false);
		if(empty($options))
		{
			//check for old format to convert (_setting_ without an s)
			$options = get_option('pmpro_pay_by_check_setting_' . $level_id, false);						
			if(!empty($options))
			{
				delete_option('pmpro_pay_by_check_setting_' . $level_id);
				$options = array('setting'=>$options, 'renewal_days'=>'', 'reminder_days'=>'', 'cancel_days'=>'');
				add_option('pmpro_pay_by_check_options_' . $level_id, $options, NULL, 'no');
			}
			else
			{
				//default
				$options = array('setting'=>0, 'renewal_days'=>'', 'reminder_days'=>'', 'cancel_days'=>'');
			}
		}
	}
	else
	{
		//default for new level
		$options = array('setting'=>0, 'renewal_days'=>'', 'reminder_days'=>'', 'cancel_days'=>'');
	}
	
	return $options;
}

/*
	Add pay by check as an option
*/
//add option to checkout along with JS
function pmpropbc_checkout_boxes()
{
	global $gateway, $pmpro_level, $pmpro_review;
	$gateway_setting = pmpro_getOption("gateway");

	$options = pmpropbc_getOptions($pmpro_level->id);

	//only show if the main gateway is not check and setting value == 1 (value == 2 means only do check payments)
	if($gateway_setting != "check" && $options['setting'] == 1)
	{
	?>
	<table id="pmpro_payment_method" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0" <?php if(!empty($pmpro_review)) { ?>style="display: none;"<?php } ?>>
			<thead>
					<tr>
							<th>Choose Your Payment Method</th>
					</tr>
			</thead>
			<tbody>
					<tr>
							<td>
									<div>
											<input type="radio" name="gateway" value="<?php echo $gateway_setting;?>" <?php if(!$gateway || $gateway == $gateway_setting) { ?>checked="checked"<?php } ?> />
													<?php if($gateway == "paypalexpress" || $gateway == "paypalstandard") { ?>
														<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay with PayPal', 'pmpropbc');?></a> &nbsp;
													<?php } else { ?>
														<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay by Credit Card', 'pmpropbc');?></a> &nbsp;
													<?php } ?>
											<input type="radio" name="gateway" value="check" <?php if($gateway == "check") { ?>checked="checked"<?php } ?> />
													<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay by Check', 'pmpropbc');?></a> &nbsp;                                        
									</div>
							</td>
					</tr>
			</tbody>
	</table>
	<div class="clear"></div>
	<script>        
		jQuery(document).ready(function() {			
			var pmpro_gateway = '<?php echo pmpro_getOption('gateway');?>';
			
			//choosing payment method
			jQuery('input[name=gateway]').click(function() {                
					if(jQuery(this).val() == 'check')
					{
							jQuery('#pmpro_billing_address_fields').hide();
							jQuery('#pmpro_payment_information_fields').hide();
							
							if(pmpro_gateway == 'paypalexpress' || pmpro_gateway == 'paypalstandard')
							{
								jQuery('#pmpro_paypalexpress_checkout').hide();
								jQuery('#pmpro_submit_span').show();
							}
							
							pmpro_require_billing = false;
					}
					else
					{                        
							jQuery('#pmpro_billing_address_fields').show();
							jQuery('#pmpro_payment_information_fields').show();                                                
							
							if(pmpro_gateway == 'paypalexpress' || pmpro_gateway == 'paypalstandard')
							{
								jQuery('#pmpro_paypalexpress_checkout').show();
								jQuery('#pmpro_submit_span').hide();
							}
							
							pmpro_require_billing = true;
					}
			});
			
			//select the radio button if the label is clicked on
			jQuery('a.pmpro_radio').click(function() {
					jQuery(this).prev().click();
			});
			
			//every couple seconds, hide the payment method box if the level is free
			function togglePaymentMethodBox()
			{
				if (typeof code_level !== 'undefined')
				{
					if(parseFloat(code_level.billing_amount) > 0 || parseFloat(code_level.initial_payent) > 0)
					{
						//not free
						jQuery('#pmpro_payment_method').show();
					}
					else
					{
						//free
						jQuery('#pmpro_payment_method').hide();
					}
				}
				pmpro_toggle_payment_method_box_timer = setTimeout(function(){togglePaymentMethodBox();}, 200);
			}
			togglePaymentMethodBox();
		});
	</script>
	<?php
	}
}
add_action("pmpro_checkout_boxes", "pmpropbc_checkout_boxes");

//add check as a valid gateway
function pmpropbc_pmpro_valid_gateways($gateways)
{
    $gateways[] = "check";
    return $gateways;
}
add_filter("pmpro_valid_gateways", "pmpropbc_pmpro_valid_gateways");

/*
	Force check gateway if pbc_setting is 2
*/
function pmpropbc_pmpro_get_gateway($gateway)
{
	global $pmpro_level;
	
	if(!empty($pmpro_level) || !empty($_REQUEST['level']))
	{
		if(!empty($pmpro_level))
			$level_id = $pmpro_level->id;
		else
			$level_id = intval($_REQUEST['level']);
		
		$options = pmpropbc_getOptions($level_id);
		    	
    	if($options['setting'] == 2)
    		$gateway = "check";
	}	
	
	return $gateway;
}
add_filter('pmpro_get_gateway', 'pmpropbc_pmpro_get_gateway');
add_filter('option_pmpro_gateway', 'pmpropbc_pmpro_get_gateway');

/*
	Need to remove this filter added by the check gateway.
	The default gateway will have it's own idea RE this.
*/
function pmpropbc_init_include_billing_address_fields()
{
	if(pmpro_getGateway() !== 'check')
		remove_filter('pmpro_include_billing_address_fields', '__return_false');
	elseif(!empty($_REQUEST['level']))
	{
		$level_id = intval($_REQUEST['level']);
		$options = pmpropbc_getOptions($level_id);		    
    	if($options['setting'] == 2)
		{
			//hide billing address and payment info fields
			add_filter('pmpro_include_billing_address_fields', '__return_false', 20);
			add_filter('pmpro_include_payment_information_fields', '__return_false', 20);
		}
	}
}
add_action('init', 'pmpropbc_init_include_billing_address_fields', 20);

/*
	Handle pending check payments
*/
//add pending as a default status when editing orders
function pmpropbc_pmpro_order_statuses($statuses)
{
	if(!in_array('pending', $statuses))
	{
		$statuses[] = 'pending';
	}
	
	return $statuses;
}
add_filter('pmpro_order_statuses', 'pmpropbc_pmpro_order_statuses');

//set check orders to pending until they are paid
function pmpropbc_pmpro_check_status_after_checkout($status) 
{	
	return "pending"; 
}
add_filter("pmpro_check_status_after_checkout", "pmpropbc_pmpro_check_status_after_checkout");

/*
 * Check if a member's status is still pending, i.e. they haven't made their first check payment.
 *
 * @return bool If status is pending or not.
 * @param user_id ID of the user to check.
 * @since .5
 */
function pmprobpc_isMemberPending($user_id)
{
	global $pmprobpc_pending_member_cache;
		
	//check the cache first
	if(isset($pmprobpc_pending_member_cache[$user_id]))
		return $pmprobpc_pending_member_cache[$user_id];
	
	//no cache, assume they aren't pending
	$pmprobpc_pending_member_cache[$user_id] = false;
	
	//check their last order
	$order = new MemberOrder();
	$order->getLastMemberOrder($user_id, NULL);		//NULL here means any status
		
	if(!empty($order))
	{
		if($order->status == "pending")
		{
			//for recurring levels, we should check if there is an older successful order
			$membership_level = pmpro_getMembershipLevelForUser($user_id);
			if(pmpro_isLevelRecurring($membership_level))
			{			
				//unless the previous order has status success and we are still within the grace period
				$paid_order = new MemberOrder();
				$paid_order->getLastMemberOrder($user_id, 'success', $order->membership_id);
				
				if(!empty($paid_order) && !empty($paid_order->id))
				{					
					//how long ago is too long?
					$options = pmpropbc_getOptions($membership_level->id);
					$cutoff = strtotime("- " . $membership_level->cycle_number . " " . $membership_level->cycle_period, current_time("timestamp")) - ($options['cancel_days']*3600*24);
					
					//too long ago?
					if($paid_order->timestamp < $cutoff)
						$pmprobpc_pending_member_cache[$user_id] = true;
					else
						$pmprobpc_pending_member_cache[$user_id] = false;
					
				}
				else
				{
					//no previous order, this must be the first
					$pmprobpc_pending_member_cache[$user_id] = true;
				}								
			}
			else
			{
				//one time payment, so only interested in the last payment
				$pmprobpc_pending_member_cache[$user_id] = true;
			}
		}
	}
	
	return $pmprobpc_pending_member_cache[$user_id];
}

//if a user's last order is pending status, don't give them access
function pmpropbc_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
	//if they don't have access, ignore this
	if(!$hasaccess)
		return $hasaccess;
	
	//if this isn't locked by level, ignore this
	if(empty($post_membership_levels))
		return $hasaccess;
	
	$hasaccess = ! pmprobpc_isMemberPending($myuser->ID);
	
	return $hasaccess;
}
add_filter("pmpro_has_membership_access_filter", "pmpropbc_pmpro_has_membership_access_filter", 10, 4);

/*
	Some notes RE pending status.
*/
//add note to account page RE waiting for check to clear
function pmpropbc_pmpro_account_bullets_bottom()
{
	//get invoice from DB
	if(!empty($_REQUEST['invoice']))
	{
	    $invoice_code = $_REQUEST['invoice'];

	    if (!empty($invoice_code))
	    	$pmpro_invoice = new MemberOrder($invoice_code);
	}
	
	//no specific invoice, check current user's last order
	if(empty($pmpro_invoice) || empty($pmpro_invoice->id))
	{
		$pmpro_invoice = new MemberOrder();
		$pmpro_invoice->getLastMemberOrder(NULL, array('success', 'pending', 'cancelled', ''));
	}

	if(!empty($pmpro_invoice) && !empty($pmpro_invoice->id))
	{
		if($pmpro_invoice->status == "pending" && $pmpro_invoice->gateway == "check")
		{
			if(!empty($_REQUEST['invoice']))
			{
				?>
				<li>
					<?php						
						if(pmprobpc_isMemberPending($pmpro_invoice->user_id))
							_e('<strong>Membership pending.</strong> We are still waiting for payment of this invoice.', 'pmpropbc');
						else						
							_e('<strong>Important Notice:</strong> We are still waiting for payment of this invoice.', 'pmpropbc');
					?>
				</li>
				<?php
			}
			else
			{
				?>
				<li><?php						
						if(pmprobpc_isMemberPending($pmpro_invoice->user_id))
							printf(__('<strong>Membership pending.</strong> We are still waiting for payment for <a href="%s">your latest invoice</a>.', 'pmpropbc'), pmpro_url('invoice', '?invoice=' . $pmpro_invoice->code));
						else
							printf(__('<strong>Important Notice:</strong> We are still waiting for payment for <a href="%s">your latest invoice</a>.', 'pmpropbc'), pmpro_url('invoice', '?invoice=' . $pmpro_invoice->code));
					?>
				</li>
				<?php
			}
		}
	}
}
add_action('pmpro_account_bullets_bottom', 'pmpropbc_pmpro_account_bullets_bottom');
add_action('pmpro_invoice_bullets_bottom', 'pmpropbc_pmpro_account_bullets_bottom');

/*
	TODO Add note to non-member text RE waiting for check to clear
*/

/*
	TODO Send email to user when order status is changed to success
*/

/*
	Create pending orders for recurring levels.
*/
function pmprobpc_recurring_orders()
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
			*/
			//get all check orders still pending after X days
			$sqlQuery = "
				SELECT o1.id FROM
				    (SELECT id, user_id, timestamp
				    FROM $wpdb->pmpro_membership_orders
				    WHERE membership_id = $level->id
				        AND gateway = 'check' 
				        AND status IN('pending', 'success')
				    ) as o1

					LEFT OUTER JOIN 
					
					(SELECT id, user_id, timestamp
				    FROM dev_pmpro_membership_orders
				    WHERE membership_id = $level->id
				        AND gateway = 'check' 
				        AND status IN('pending', 'success')
				    ) as o2

					ON o1.user_id = o2.user_id
					AND o1.timestamp < o2.timestamp
					OR (o1.timestamp = o2.timestamp AND o1.id < o2.id)
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
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//check that user still has same level?
				if(empty($user->membership_level) || $order->membership_id != $user->membership_level->id)
					continue;
				
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

				//get timestamp for new order
				$order_timestamp = strtotime("+" . $combo, $order->timestamp);
				
				//let's skip if there is already an order for this user/level/timestamp
				$sqlQuery = "SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $order->user_id . "' AND membership_id = '" . $order->membership_id . "' AND timestamp = '" . date('d', $order_timestamp) . "' LIMIT 1";			
				$dupe = $wpdb->get_var("SELECT id FROM $wpdb->pmpro_membership_orders WHERE user_id = '" . $order->user_id . "' AND membership_id = '" . $order->membership_id . "' AND timestamp = '" . $order_timestamp . "' LIMIT 1");				
				if(!empty($dupe))
					continue;
				
				//save it
				$morder->process();
				$morder->saveOrder();

				//update the timestamp				
				$morder->updateTimestamp(date("Y", $order_timestamp), date("m", $order_timestamp), date("d", $order_timestamp));

				//send emails				
				$email = new PMProEmail();
				$email->template = "check_pending";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("New Invoice for %s at %s", "pmpropbc"), $user->membership_level->name, get_option("blogname"));
			}
		}
	}	
}
add_action('pmprobpc_recurring_orders', 'pmprobpc_recurring_orders');

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
		if(!empty($options['reminder_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['reminder_days'] . " days", $now));
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
					AND DATE_ADD(timestamp, INTERVAL $combo) <= '" . $date . "'
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
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Reminder Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}

				//note when we send the reminder
				$new_notes = $order->notes . "Reminder Sent:" . $today . "\n";
				$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

				//setup email to send
				$email = new PMProEmail();
				$email->template = "check_pending_reminder";
				$email->email = $user->user_email;
				$email->subject = sprintf(__("Reminder: New Invoice for %s at %s", "pmpropbc"), $user->membership_level->name, get_option("blogname"));											
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
				
				$email->data["instructions"] = pmpro_getOption('instructions');
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
		if(!empty($options['cancel_days']))
			$date = date("Y-m-d", strtotime("+ " . $options['cancel_days'] . " days", $now));
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
					AND DATE_ADD(timestamp, INTERVAL $combo) <= '" . $date . "'
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
				$user = get_userdata($order->user_id);
				$user->membership_level = pmpro_getMembershipLevelForUser($order->user_id);
				
				//if they are no longer a member, let's not send them an email
				if(empty($user->membership_level) || empty($user->membership_level->ID) || $user->membership_level->id != $order->membership_id)
				{
					//note when we send the reminder
					$new_notes = $order->notes . "Cancellation Skipped:" . $today . "\n";
					$wpdb->query("UPDATE $wpdb->pmpro_membership_orders SET notes = '" . esc_sql($new_notes) . "' WHERE id = '" . $order_id . "' LIMIT 1");

					continue;
				}
				
				//cancel the order and subscription
				do_action("pmpro_membership_pre_membership_expiry", $order->user_id, $order->membership_id );
				
				//remove their membership
				pmpro_changeMembershipLevel(false, $order->user_id, 'expired');
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

/*
	Activation/Deactivation
*/
function pmpropbc_activation()
{
	//schedule crons
	wp_schedule_event(current_time('timestamp'), 'daily', 'pmpropbc_cancel_overdue_orders');
	wp_schedule_event(current_time('timestamp')+1, 'daily', 'pmprobpc_recurring_orders');
	wp_schedule_event(current_time('timestamp')+2, 'daily', 'pmpropbc_reminder_emails');	

	do_action('pmpropbc_activation');
}
function pmpropbc_deactivation()
{
	//remove crons
	wp_clear_scheduled_hook('pmpropbc_cancel_overdue_orders');
	wp_clear_scheduled_hook('pmprobpc_recurring_orders');
	wp_clear_scheduled_hook('pmpropbc_reminder_emails');	

	do_action('pmpropbc_deactivation');
}
register_activation_hook(__FILE__, 'pmpropbc_activation');
register_deactivation_hook(__FILE__, 'pmpropbc_deactivation');

/*
Function to add links to the plugin row meta
*/
function pmpropbc_plugin_row_meta($links, $file) {
	if(strpos($file, 'pmpro-pay-by-check.php') !== false)
	{
		$new_links = array(
			'<a href="' . esc_url('http://www.paidmembershipspro.com/add-ons/plugins-on-github/pmpro-pay-by-check-add-on/')  . '" title="' . esc_attr( __( 'View Documentation', 'pmpro' ) ) . '">' . __( 'Docs', 'pmpro' ) . '</a>',
			'<a href="' . esc_url('http://paidmembershipspro.com/support/') . '" title="' . esc_attr( __( 'Visit Customer Support Forum', 'pmpro' ) ) . '">' . __( 'Support', 'pmpro' ) . '</a>',
		);
		$links = array_merge($links, $new_links);
	}
	return $links;
}
add_filter('plugin_row_meta', 'pmpropbc_plugin_row_meta', 10, 2);

function init_test()
{
	if(!empty($_REQUEST['test']))
	{
		//pmprobpc_recurring_orders();
		//pmpropbc_reminder_emails();
		pmpropbc_cancel_overdue_orders();
		exit;
	}
}
add_action('init', 'init_test');