<?php

//add check as a valid gateway
function pmpropbc_pmpro_valid_gateways($gateways)
{
    $gateways[] = "check";
    return $gateways;
}
add_filter("pmpro_valid_gateways", "pmpropbc_pmpro_valid_gateways");

/*
	Add pay by check as an option
*/
//add option to checkout along with JS
function pmpropbc_checkout_boxes()
{
	global $gateway, $pmpro_level, $pmpro_review;
	$gateway_setting = get_option("pmpro_gateway");

	$options = pmpropbc_getOptions($pmpro_level->id);

	$check_gateway_label = get_option( 'pmpro_check_gateway_label' ) ?: __( 'Check', 'pmpro-pay-by-check' );

	//only show if the main gateway is not check and setting value == 1 (value == 2 means only do check payments)
	if ( $gateway_setting != "check" && $options['setting'] == 1 ) { ?>
	<div id="pmpro_payment_method" class="pmpro_checkout">
		<hr />
		<h2>
			<span class="pmpro_checkout-h2-name"><?php esc_html_e( 'Choose Your Payment Method', 'pmpro-pay-by-check'); ?></span>
		</h2>
		<div class="pmpro_checkout-fields">
			<span class="gateway_<?php echo esc_attr($gateway_setting); ?>">
					<input type="radio" name="gateway" value="<?php echo $gateway_setting;?>" <?php if(!$gateway || $gateway == $gateway_setting) { ?>checked="checked"<?php } ?> />
							<?php if($gateway_setting == "paypalexpress" || $gateway_setting == "paypalstandard") { ?>
								<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay with PayPal', 'pmpro-pay-by-check');?></a> &nbsp;
							<?php } elseif($gateway_setting == 'twocheckout') { ?>
								<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay with 2Checkout', 'pmpro-pay-by-check');?></a> &nbsp;
							<?php } elseif( $gateway_setting == 'payfast' ) { ?>
								<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay with PayFast', 'pmpro-pay-by-check');?></a> &nbsp;
							<?php } else { ?>
								<a href="javascript:void(0);" class="pmpro_radio"><?php _e('Pay by Credit Card', 'pmpro-pay-by-check');?></a> &nbsp;
							<?php } ?>
			</span> <!-- end gateway_$gateway_setting -->
			<span class="gateway_check">
					<input type="radio" name="gateway" value="check" <?php if($gateway == "check") { ?>checked="checked"<?php } ?> />
					<a href="javascript:void(0);" class="pmpro_radio"><?php echo esc_html( sprintf( __( 'Pay by %s', 'pmpro-pay-by-check' ), $check_gateway_label ) ); ?></a> &nbsp;
			</span> <!-- end gateway_check -->
			<?php
				//support the PayPal Website Payments Pro Gateway which has PayPal Express as a second option natively
				if ( $gateway_setting == "paypal" ) { ?>
					<span class="gateway_paypalexpress">
						<input type="radio" name="gateway" value="paypalexpress" <?php if($gateway == "paypalexpress") { ?>checked="checked"<?php } ?> />
						<a href="javascript:void(0);" class="pmpro_radio"><?php esc_html_e( 'Check Out with PayPal', 'pmpro-pay-by-check' ); ?></a>
					</span>
				<?php
				}
			?>
		</div> <!-- end pmpro_checkout-fields -->
	</div> <!-- end #pmpro_payment_method -->
	<?php
	} elseif ( $gateway_setting != "check" && $options['setting'] == 2 ) { ?>
		<input type="hidden" name="gateway" value="check" />
	<?php
	}
}
add_action("pmpro_checkout_boxes", "pmpropbc_checkout_boxes", 20);

/**
 * Toggle payment method when discount code is updated
 */
function pmpropbc_pmpro_applydiscountcode_return_js() {
	?>
	pmpropbc_togglePaymentMethodBox();
	<?php
}
add_action('pmpro_applydiscountcode_return_js', 'pmpropbc_pmpro_applydiscountcode_return_js');

/**
 * Enqueue scripts on the frontend.
 */
function pmpropbc_enqueue_scripts() {

	if(!function_exists('pmpro_getLevelAtCheckout'))
		return;
	
	global $gateway, $pmpro_level, $pmpro_review, $pmpro_pages, $post, $pmpro_msg, $pmpro_msgt;

	// If post not set, bail.
	if( ! isset( $post ) ) {
		return;
	}

	//make sure we're on the checkout page
	if(!is_page($pmpro_pages['checkout']) && !empty($post) && strpos($post->post_content, "[pmpro_checkout") === false)
		return;
	
	wp_register_script('pmpro-pay-by-check', plugins_url( 'js/pmpro-pay-by-check.js', PMPRO_PAY_BY_CHECK_BASE_FILE ), array( 'jquery' ), PMPROPBC_VER );
	
	//store original msg and msgt values in case these function calls below affect them
	$omsg = $pmpro_msg;
	$omsgt = $pmpro_msgt;

	//get original checkout level and another with discount code applied	
	$pmpro_nocode_level = pmpro_getLevelAtCheckout(false, '^*NOTAREALCODE*^');
	$pmpro_code_level = pmpro_getLevelAtCheckout();			//NOTE: could be same as $pmpro_nocode_level if no code was used

	// Determine whether this level is a "check only" level.
	$check_only = 0;
	if ( ! empty( $pmpro_code_level->id ) ) {
		$options = pmpropbc_getOptions( $pmpro_code_level->id );
		if ( $options['setting'] == 2 ) {
			$check_only = 1;
		}
	}
	
	//restore these values
	$pmpro_msg = $omsg;
	$pmpro_msgt = $omsgt;
	
	wp_localize_script('pmpro-pay-by-check', 'pmpropbc', array(
			'gateway' => get_option('pmpro_gateway'),
			'nocode_level' => $pmpro_nocode_level,
			'code_level' => $pmpro_code_level,
			'pmpro_review' => (bool)$pmpro_review,
			'is_admin'  =>  is_admin(),
            'hide_billing_address_fields' => apply_filters('pmpro_hide_billing_address_fields', false ),
			'check_only' => $check_only,
		)
	);

	wp_enqueue_script('pmpro-pay-by-check');

}
add_action("wp_enqueue_scripts", 'pmpropbc_enqueue_scripts');

/*
	Need to remove some filters added by the check gateway.
	The default gateway will have it's own idea RE this.
*/
function pmpropbc_init_include_billing_address_fields()
{
	//make sure PMPro is active
	if(!function_exists('pmpro_getGateway'))
		return;

	//billing address and payment info fields
	$level = pmpro_getLevelAtCheckout();
	if ( ! empty( $level->id ) )
	{
		$options = pmpropbc_getOptions( $level->id );
    			
		if($options['setting'] == 2)
		{
			//Only hide the address if we're not using the Address for Free Levels Add On
			if ( ! function_exists( 'pmproaffl_pmpro_required_billing_fields' ) ) {				
				//hide billing address and payment info fields
				add_filter('pmpro_include_billing_address_fields', '__return_false', 20);
				add_filter('pmpro_include_payment_information_fields', '__return_false', 20);
			}

			// Need to also specifically remove them for Stripe.
			remove_filter( 'pmpro_include_payment_information_fields', array( 'PMProGateway_stripe', 'pmpro_include_payment_information_fields' ) );

			//Hide the toggle section if the PayPal Express Add On is active
			remove_action( "pmpro_checkout_boxes", "pmproappe_pmpro_checkout_boxes", 20 );
		} else {
			//keep paypal buttons, billing address fields/etc at checkout
			$default_gateway = get_option('pmpro_gateway');
			if($default_gateway == 'paypalexpress') {
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_paypalexpress', 'pmpro_checkout_default_submit_button'));
				if ( version_compare( PMPRO_VERSION, '2.1', '>=' ) ) {
					add_action( 'pmpro_checkout_preheader', array( 'PMProGateway_paypalexpress', 'pmpro_checkout_preheader' ) );
				} else {
					/**
					 * @deprecated No longer used since paid-memberships-pro v2.1
					 */
					add_action( 'pmpro_checkout_after_form', array( 'PMProGateway_paypalexpress', 'pmpro_checkout_after_form' ) );
				}
			} elseif($default_gateway == 'paypalstandard') {
				add_filter('pmpro_checkout_default_submit_button', array('PMProGateway_paypalstandard', 'pmpro_checkout_default_submit_button'));
			} elseif($default_gateway == 'paypal') {
				if ( version_compare( PMPRO_VERSION, '2.1', '>=' ) ) {
					add_action( 'pmpro_checkout_preheader', array( 'PMProGateway_paypal', 'pmpro_checkout_preheader' ) );
				} else {
					/**
					 * @deprecated No longer used since paid-memberships-pro v2.1
					 */
					add_action( 'pmpro_checkout_after_form', array( 'PMProGateway_paypal', 'pmpro_checkout_after_form' ) );
				}
				add_filter('pmpro_include_payment_option_for_paypal', '__return_false');
			} elseif($default_gateway == 'twocheckout') {
				//undo the filter to change the checkout button text
				remove_filter('pmpro_checkout_default_submit_button', array('PMProGateway_twocheckout', 'pmpro_checkout_default_submit_button'));
			} else if( $default_gateway == 'payfast' ) {
				add_filter( 'pmpro_include_billing_address_fields', '__return_false' );	
			} else {				
				//onsite checkouts
				
				//the check gateway class in core adds filters like these
				remove_filter( 'pmpro_include_billing_address_fields', '__return_false' );
				remove_filter( 'pmpro_include_payment_information_fields', '__return_false' );
				
				//make sure the default gateway is loading their billing address fields
				if(class_exists('PMProGateway_' . $default_gateway) && method_exists('PMProGateway_' . $default_gateway, 'pmpro_include_billing_address_fields')) {
					add_filter('pmpro_include_billing_address_fields', array('PMProGateway_' . $default_gateway, 'pmpro_include_billing_address_fields'));
				}					
			}			
		}
	}

	//instructions at checkout
	remove_filter('pmpro_checkout_after_payment_information_fields', array('PMProGateway_check', 'pmpro_checkout_after_payment_information_fields'));
	add_filter('pmpro_checkout_after_payment_information_fields', 'pmpropbc_pmpro_checkout_after_payment_information_fields');		
}
add_action('init', 'pmpropbc_init_include_billing_address_fields', 20);

/**
 * Cancels all previously pending check orders if a user purchases the same level via a different payment method.
 * 
 * @since 0.11
 */
function pmpropbc_cancel_previous_pending_orders( $user_id, $order ) {
	global $wpdb;

	$membership_id = $order->membership_id;
	//Check to make sure PBC is enabled for the level first.
	$pbc_settings = pmpropbc_getOptions( $membership_id );

	// Assume no PBC setting is enabled for this level, so probably no cancellation setting should run.
	if ( $pbc_settings['setting'] == 0 ) {
		return;
	}
	
	// Not a renewal order for the same level just return.
	if ( ! $order->is_renewal() ) {
		return;
	}

	// Do not run code if the user is spamming checkout with check as the gateway selected.
	if ( $order->gateway == 'check' ) {
		return;
	}

	// Update any outstanding check payments for this level ID.
	$SQLquery = "UPDATE $wpdb->pmpro_membership_orders
					SET `status` = 'token'
					WHERE `user_id` = " . esc_sql( $user_id ) . "					 	
						AND `gateway` = 'check'
						AND `status` = 'pending'
						AND `membership_id` = '" . esc_sql( $membership_id ) . "'
						AND `timestamp` < '" . esc_sql( date( 'Y-m-d H:i:s', $order->timestamp ) ) . "'";

	$results = $wpdb->query( $SQLquery );
}
add_action( 'pmpro_after_checkout', 'pmpropbc_cancel_previous_pending_orders', 10, 2 );

/*
	Show instructions on the checkout page.
*/
function pmpropbc_pmpro_checkout_after_payment_information_fields() {
	global $gateway, $pmpro_level;

	$options = pmpropbc_getOptions($pmpro_level->id);

	if( !empty($options) && $options['setting'] > 0 ) {
		$instructions = get_option("pmpro_instructions");
		if($gateway != 'check')
			$hidden = 'style="display:none;"';
		else
			$hidden = '';
		?>
		<div class="pmpro_check_instructions" <?php echo $hidden; ?>><?php echo wp_kses_post( $instructions ); ?></div>
		<?php
	}
}

/**
 * When getting the gateway object for a "check" order/subscription, swap it
 * for our custom "check" gateway.
 *
 * Will only run for PMPro v3.0.3+.
 *
 * @since TBD
 *
 * @param PMProGateway
 * @return PMProGateway
 */
function pmpropbc_use_custom_gateway_class( $gateway ) {
	// If the passed gateway is not the check gateway, bail.
	if ( ! is_a( $gateway, 'PMProGateway_check' ) ) {
		return $gateway;
	}

	// Swap the gateway object for our custom gateway object.
	require_once PMPRO_PAY_BY_CHECK_DIR . '/classes/class.pmprogateway_pbc.php';
	return new PMProGateway_pbc();
}
add_filter( 'pmpro_order_gateway_object', 'pmpropbc_use_custom_gateway_class' );
add_filter( 'pmpro_subscription_gateway_object', 'pmpropbc_use_custom_gateway_class' );

/**
 * set check orders to pending until they are paid
 * This filter is only run for PMPro versions earlier than 3.0.3 since we are overwriting the core gateway in 3.0.3+.
 */
function pmpropbc_pmpro_check_status_after_checkout($status) {
	return 'pending';
}
add_filter( 'pmpro_check_status_after_checkout', 'pmpropbc_pmpro_check_status_after_checkout' );

/**
 * Whenever a check order is saved, we need to update the subscription data.
 *
 * @param MemberOrder $morder - Updated order as it's being saved
 */
function pmpropbc_update_subscription_data_for_order( $morder ) {
	// Only worry about this if this is a check order.
	if ( 'check' !== strtolower( $morder->payment_type ) ) {
		return;
	}

	// If using PMPro v3.0+, update the subscription data.
	if ( method_exists( $morder, 'get_subscription' ) ) {
		$subscription = $morder->get_subscription();
		if ( ! empty( $subscription ) ) {
			$subscription->update();
		}
	}
}
add_action( 'pmpro_added_order', 'pmpropbc_update_subscription_data_for_order', 10, 1 );
add_action( 'pmpro_updated_order', 'pmpropbc_update_subscription_data_for_order', 10, 1 );

/**
 * Send Invoice to user if/when changing order status to "success" for Check based payment.
 * Also processes checkout if the order was a delayed checkout order.
 *
 * @param MemberOrder $morder - Updated order as it's being saved
 */
function pmpropbc_order_status_success( $morder ) {
    // Only worry about this if this is a check order.
    if ( 'check' !== strtolower( $morder->gateway ) ) {
		return;
	}

	// Check if the order was a chekout order.
	$checkout_request_vars = get_pmpro_membership_order_meta( $morder->id, 'checkout_request_vars', true );
	if ( ! empty( $checkout_request_vars ) ) {
		// Process the checkout and avoid infinite loops. This should send the checkout email.
		$original_request_vars = $_REQUEST;
		pmpro_pull_checkout_data_from_order( $morder );
		remove_action( 'pmpro_order_status_success', 'pmpropbc_order_status_success', 10, 1 );
		pmpro_complete_async_checkout( $morder );
		add_action( 'pmpro_order_status_success', 'pmpropbc_order_status_success', 10, 1 );
		$_REQUEST = $original_request_vars;
	} else {
		// Send an invoice email for the order.
		$recipient = get_user_by( 'ID', $morder->user_id );
		$invoice_email = new PMProEmail();
		$invoice_email->sendInvoiceEmail( $recipient, $morder );

		// Update the subscription for this order if needed.
		pmpropbc_update_subscription_data_for_order( $morder );
	}
}
add_action( 'pmpro_order_status_success', 'pmpropbc_order_status_success', 10, 1 );
