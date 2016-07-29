/**
 * Created by sjolshag on 7/27/16.
 */

jQuery.noConflict();

if ( typeof pmpro_require_billing === 'undefined' ) {
    var pmpro_require_billing;
    var pmpro_pbc_interval_handle;
}

function pmpropbc_toggleCheckoutFields() {

    if(jQuery('input[name=gateway]:checked').val() === 'check')
    {
        jQuery('#pmpro_billing_address_fields').hide();
        jQuery('#pmpro_payment_information_fields').hide();

        jQuery('.pmpro_check_instructions').show();

        if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard')
        {
            jQuery('#pmpro_paypalexpress_checkout').hide();
            jQuery('#pmpro_submit_span').show();
        }

        pmpro_require_billing = false;
    }
    else
    {
        jQuery('#pmpro_billing_address_fields').show();

        if ( (typeof code_level !== 'undefined') && (parseFloat(code_level.billing_amount) > 0 || parseFloat(code_level.initial_payent) > 0) ) {
            jQuery('#pmpro_payment_information_fields').show();
        }

        // jQuery('.pmpro_check_instructions').hide();

        if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard')
        {
            jQuery('#pmpro_paypalexpress_checkout').show();
            jQuery('#pmpro_submit_span').hide();
        }

        pmpro_require_billing = true;
    }
}

function togglePaymentMethodBox()  {

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

        pmpropbc_toggleCheckoutFields();
    }

}

function togglePBCRecurringOptions() {
    if (jQuery('#pbc_setting').val() > 0 && jQuery('#recurring').is(':checked')) {
        jQuery('tr.pbc_recurring_field').show();
    } else {
        jQuery('tr.pbc_recurring_field').hide();
    }
}


jQuery(document).ready(function () {

    if (pmpropbc.is_admin) {

        togglePBCRecurringOptions();

        //hide/show recurring fields when pbc or recurring settings change
        jQuery('#pbc_setting').change(function () {
            togglePBCRecurringOptions();
        });
        jQuery('#recurring').change(function () {
            togglePBCRecurringOptions();
        });

    } else {


        //choosing payment method
        jQuery('input[name=gateway]').bind('click change keyup', function () {
            pmpropbc_toggleCheckoutFields();
        });

        //run on load
        if (false === pmpropbc.pmpro_review) {
            pmpropbc_toggleCheckoutFields();
        }

        //select the radio button if the label is clicked on
        jQuery('a.pmpro_radio').click(function () {
            jQuery(this).prev().click();
        });

        //every couple seconds, hide the payment method box if the level is free
        pmpro_pbc_interval_handle = setInterval(togglePaymentMethodBox, 2000);

    }
});