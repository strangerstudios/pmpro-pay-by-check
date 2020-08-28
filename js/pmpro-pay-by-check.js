/*
	Note that the PMPro Pay by Check plugin only loads this JS on the PMPro checkout page.
*/

//set some vars
if ( typeof pmpro_require_billing === 'undefined' ) {
    var pmpro_require_billing;
    var pmpro_pbc_interval_handle;
}

if ( typeof code_level === 'undefined' ) {	
	var code_level;
	code_level = pmpropbc.code_level;
}

function pmpropbc_isLevelFree() {
	"use strict";
	var check_level;
	var has_variable_pricing = ( jQuery('#price').length > 0 );
	var has_donation = ( jQuery('#donation').length > 0 );

	if(typeof code_level === 'undefined' || code_level === false) {
		//no code or an invalid code was applied
		check_level = pmpropbc.nocode_level;
	} else {
		//default pmpro_level or level with current code applied
		check_level = code_level;
	}
		
	//check if level is paid or free
	if( false === has_variable_pricing && ( parseFloat(check_level.billing_amount) > 0 || parseFloat(check_level.initial_payment) > 0 ) ) {
		return false;
	} else if ( true === has_variable_pricing && ( parseFloat( jQuery('#price').val() ) > 0 || ( parseFloat(check_level.billing_amount) > 0 || parseFloat(check_level.initial_payment) > 0 ) ) ) {
		return false;
	} else if ( true === has_donation && ( parseFloat( jQuery('#donation').val() ) > 0 || ( parseFloat(check_level.billing_amount) > 0 || parseFloat(check_level.initial_payment) > 0 ) ) ) {
		return false;
	} else {
	    return true;
    }
}

function pmpropbc_isCheckGatewayChosen() {
	if(jQuery('input[name=gateway]:checked').val() === 'check') {
		return true;
	} else {
		return false;
	}
}

function pmpropbc_isPayPalExpressChosen() {
	if(jQuery('input[name=gateway]:checked').val() == 'paypalexpress'  ) {
		return true;
	} else {
		return false;
	}
}

function pmpropbc_isPayFast() {
	if(jQuery('input[name=gateway]:checked').val() == 'payfast'  ) {
		return true;
	} else {
		return false;
	}
}

function pmpropbc_toggleCheckoutFields() {
    "use strict";
			
	//check for free/paid
	if(pmpropbc_isLevelFree()) {
		//free, now check if using check gateway
		jQuery('#pmpro_billing_address_fields').hide();
		jQuery('#pmpro_payment_information_fields').hide();			
		jQuery('.pmpro_check_instructions').hide();
		pmpro_require_billing = false;
		
		//hide paypal button if applicable
		if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard' )
		{
			jQuery('#pmpro_paypalexpress_checkout').hide();
			jQuery('#pmpro_submit_span').show();
		}
	} else {
		//paid, now check if using check gateway
		if(pmpropbc_isCheckGatewayChosen()) {
			//paid and check
			jQuery('#pmpro_billing_address_fields').show();
			jQuery('#pmpro_payment_information_fields').hide();			
			jQuery('.pmpro_check_instructions').show();
			pmpro_require_billing = false;
		} else if(pmpropbc_isPayPalExpressChosen()) {
			//paypal express
			jQuery('#pmpro_billing_address_fields').hide();
			jQuery('#pmpro_payment_information_fields').hide();			
			jQuery('#pmpro_submit_span').hide();
			jQuery('#pmpro_paypalexpress_checkout').show();
			jQuery('.pmpro_check_instructions').hide();
			pmpro_require_billing = false;
		} else if ( pmpropbc_isPayFast()) {
			jQuery('#pmpro_billing_address_fields').hide();
			jQuery('#pmpro_payment_information_fields').hide();			
			jQuery('.pmpro_check_instructions').hide();
			pmpro_require_billing = false;
		} else {
			//paid and default
			jQuery('#pmpro_billing_address_fields').show();
			jQuery('#pmpro_payment_information_fields').show();			
			jQuery('.pmpro_check_instructions').hide();
			pmpro_require_billing = true;
		}

		//show paypal button if applicable
		if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard' ) {
			if(pmpropbc_isCheckGatewayChosen()) {
				jQuery('#pmpro_paypalexpress_checkout').hide();
				jQuery('#pmpro_submit_span').show();				
			} else {
				jQuery('#pmpro_paypalexpress_checkout').show();
				jQuery('#pmpro_submit_span').hide();
			}
		}

		//Integration for PayPal Website Payments Pro.
		if ( pmpropbc.gateway == 'paypal' ) {
			// Figure out if they selected check or not.
			if ( pmpropbc_isCheckGatewayChosen() ) {
				jQuery('#pmpro_paypalexpress_checkout').hide();
				jQuery('#pmpro_submit_span').show();				
			} else if( pmpropbc_isPayPalExpressChosen() ) { // see if PayPal Express is selected.
				jQuery('#pmpro_paypalexpress_checkout').show();
				jQuery('#pmpro_submit_span').hide();
			} else { // Revert back to defaults just in-case.
				jQuery('#pmpro_paypalexpress_checkout').hide();
				jQuery('#pmpro_submit_span').show();
			}
		}

		// If only Pay By Check is chosen.
		if ( pmpropbc.gateway === 'check' ) {
			jQuery('#pmpro_billing_address_fields').show();
			jQuery('#pmpro_payment_information_fields').hide();			
			jQuery('.pmpro_check_instructions').show();
			pmpro_require_billing = false;
		}
	}
	
	//check if billing address hide/show is overriden by filters
	if (parseInt(pmpropbc.hide_billing_address_fields) === 1) {
		jQuery('#pmpro_billing_address_fields').hide();
	}
}

function pmpropbc_togglePaymentMethodBox()  {
    "use strict";
    	
	//check if level is paid or free
	if(pmpropbc_isLevelFree()) {
		//free
		jQuery('#pmpro_payment_method').hide();
	} else {
		//not free
		jQuery('#pmpro_payment_method').show();
	}
		
	//update checkout fields as well
    pmpropbc_toggleCheckoutFields();
}

jQuery(document).ready(function () {
    "use strict";	
	
	//choosing payment method
	jQuery('input[name=gateway]').bind('click change keyup', function () {
		pmpropbc_toggleCheckoutFields();
	});

	//run on load
	if ( !pmpropbc.pmpro_review ) {
		pmpropbc_togglePaymentMethodBox();		
	}

	//select the radio button if the label is clicked on
	jQuery('a.pmpro_radio').click(function () {
		jQuery(this).prev().click();
	});	
});