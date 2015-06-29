<?php
/*
Plugin Name: Paid Memberships Pro - Pay by Check Add On
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-pay-by-check/
Description: A collection of customizations useful when allowing users to pay by check for Paid Memberships Pro levels.
Version: .4
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

/*
	Add settings to the edit levels page
*/
//show the checkbox on the edit level page
function pmpropbc_pmpro_membership_level_after_other_settings()
{	
	$level_id = intval($_REQUEST['edit']);
	if($level_id > 0)
		$pbc_setting = get_option('pmpro_pay_by_check_setting_' . $level_id);	
	else
		$pbc_setting = 0;
?>
<h3 class="topborder">Pay by Check Settings</h3>
<p>Change this setting to allow or disallow the pay by check option for this level.</p>
<table>
<tbody class="form-table">
	<tr>
		<th scope="row" valign="top"><label for="pbc_setting"><?php _e('Allow Pay by Check:', 'pmpro');?></label></th>
		<td>
			<select name="pbc_setting">
				<option value="0" <?php selected($pbc_setting, 0);?>>No. Use the default gateway only.</option>
				<option value="1" <?php selected($pbc_setting, 1);?>>Yes. Users choose between default gateway and check.</option>
				<option value="2" <?php selected($pbc_setting, 2);?>>Yes. Users can only pay by check.</option>
			</select>
		</td>
	</tr>
</tbody>
</table>
<?php
}
add_action('pmpro_membership_level_after_other_settings', 'pmpropbc_pmpro_membership_level_after_other_settings');
//save hide shipping setting when the level is saved/added
function pmpropbc_pmpro_save_membership_level($level_id)
{
	if(isset($_REQUEST['pbc_setting']))
		$pbc_setting = intval($_REQUEST['pbc_setting']);
	else
		$pbc_setting = 0;
	delete_option('pmpro_pay_by_check_setting_' . $level_id);
	add_option('pmpro_pay_by_check_setting_' . intval($level_id), $pbc_setting, "", "no");
}
add_action("pmpro_save_membership_level", "pmpropbc_pmpro_save_membership_level");

/*
	Add pay by check as an option
*/
//add option to checkout along with JS
function pmpropbc_checkout_boxes()
{
	global $gateway, $pmpro_level, $pmpro_review;
	$gateway_setting = pmpro_getOption("gateway");

	$pbc_setting = get_option('pmpro_pay_by_check_setting_' . $pmpro_level->id, 0);

	//only show if the main gateway is not check and setting value == 1 (value == 2 means only do check payments)
	if($gateway_setting != "check" && $pbc_setting == 1)
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
	if(!empty($_REQUEST['level']))
	{
		$level_id = intval($_REQUEST['level']);
		$pbc_setting = get_option('pmpro_pay_by_check_setting_' . $level_id, 0);
    	
    	if($pbc_setting == 2)
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
		$pbc_setting = get_option('pmpro_pay_by_check_setting_' . $level_id);
		if($pbc_setting == 2)
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

//if a user's last order is pending status, don't give them access
function pmpropbc_pmpro_has_membership_access_filter($hasaccess, $mypost, $myuser, $post_membership_levels)
{
	//if they don't have access, ignore this
	if(!$hasaccess)
		return $hasaccess;
		
	//if this isn't locked by level, ignore this
	if(empty($post_membership_levels))
		return $hasaccess;
	
	//okay, let's check their last order
	$order = new MemberOrder();
	$order->getLastMemberOrder($myuser->ID, NULL);		//NULL here means any status
		
	if(!empty($order))
	{
		if($order->status == "pending")
		{
			//unless the previous order has status success and we are still within that pay period


			$hasaccess = false;	//this is where the magic happens
		}
	}
	
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
				<li><?php _e('<strong>Membership pending.</strong> We are still waiting for payment of this invoice.', 'pmpropbc');?></li>
				<?php
			}
			else
			{
				?>
				<li><?php printf(__('<strong>Membership pending.</strong> We are still waiting for payment for <a href="%s">your latest invoice</a>.', 'pmpropbc'), pmpro_url('invoice', '?invoice=' . $pmpro_invoice->code));?></li>
				<?php
			}
		}
	}
}
add_action('pmpro_account_bullets_bottom', 'pmpropbc_pmpro_account_bullets_bottom');
add_action('pmpro_invoice_bullets_bottom', 'pmpropbc_pmpro_account_bullets_bottom');

//add note to non-member text RE waiting for check to clear

/*
	TODO Send email to user when order status is changed to success
*/


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