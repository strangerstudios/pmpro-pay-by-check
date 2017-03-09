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
	var check_level;
	if(typeof code_level === 'undefined' || code_level === false) {
		//no code or an invalid code was applied
		check_level = pmpropbc.nocode_level;
	} else {
		//default pmpro_level or level with current code applied
		check_level = code_level;
	}
		
	//check if level is paid or free
	if(parseFloat(check_level.billing_amount) > 0 || parseFloat(check_level.initial_payment) > 0) {
		return false;
	} else {
		return true;
	}
}

function pmprobpc_isCheckGatewayChosen() {
	if( 'check' === jQuery('input[name=gateway]:checked').val() || 'check' === pmpropbc.gateway ) {
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
		if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard')
		{
			jQuery('#pmpro_paypalexpress_checkout').hide();
			jQuery('#pmpro_submit_span').show();
		}
	} else {
		//paid, now check if using check gateway
		if(pmprobpc_isCheckGatewayChosen()) {
			//paid and check
			jQuery('#pmpro_billing_address_fields').show();
			jQuery('#pmpro_payment_information_fields').hide();			
			jQuery('.pmpro_check_instructions').show();
			pmpro_require_billing = false;
		} else {
			//paid and default
			jQuery('#pmpro_billing_address_fields').show();
			jQuery('#pmpro_payment_information_fields').show();			
			jQuery('.pmpro_check_instructions').hide();
			pmpro_require_billing = true;
		}
		
		//show paypal button if applicable
		if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard')
		{
			jQuery('#pmpro_paypalexpress_checkout').show();
			jQuery('#pmpro_submit_span').hide();
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
