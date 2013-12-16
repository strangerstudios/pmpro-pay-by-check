<?php
/*
Plugin Name: PMPro Pay by Check
Plugin URI: http://www.paidmembershipspro.com/wp/pmpro-pay-by-check/
Description: A collection of customizations useful when allowing users to pay by check for Paid Memberships Pro levels.
Version: .1
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
	Add pay by check as an option
*/
//add option to checkout along with JS
function pmpropbc_checkout_boxes()
{
	global $gateway, $pmpro_level;
	$gateway_setting = pmpro_getOption("gateway");

	//only show if the main gateway is not check
	if($gateway_setting != "check")
	{
	?>
	<table id="pmpro_payment_method" class="pmpro_checkout top1em" width="100%" cellpadding="0" cellspacing="0" border="0">
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
													<a href="javascript:void(0);" class="pmpro_radio">Pay by Credit Card</a> &nbsp;
											<input type="radio" name="gateway" value="check" <?php if($gateway == "check") { ?>checked="checked"<?php } ?> />
													<a href="javascript:void(0);" class="pmpro_radio">Pay by Check</a> &nbsp;                                        
									</div>
							</td>
					</tr>
			</tbody>
	</table>
	<div class="clear"></div>
	<script>        
		jQuery(document).ready(function() {			
			//choosing payment method
			jQuery('input[name=gateway]').click(function() {                
					if(jQuery(this).val() == 'check')
					{
							jQuery('#pmpro_billing_address_fields').hide();
							jQuery('#pmpro_payment_information_fields').hide();
							pmpro_require_billing = false;
					}
					else
					{                        
							jQuery('#pmpro_billing_address_fields').show();
							jQuery('#pmpro_payment_information_fields').show();                                                
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
	Handle pending check payments
*/
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

//add note to non-member text RE waiting for check to clear

/*
	Send email to user when order status is changed to success
*/
