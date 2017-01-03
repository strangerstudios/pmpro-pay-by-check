/**
 * Copyright (c) 2017 - Stranger Studios LLC (Thomas Sjolshagen <thomas@eighty20results.com>). ALL RIGHTS RESERVED
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 *
 * PMPro Pay By Check add-on, Copyright 2016 - 2017 Stranger Studios, LLC.
 * PMPro Pay By Check add-on is distributed under the terms of the GNU GPL
 *
 **/

jQuery.noConflict();

if ( typeof pmpro_require_billing === 'undefined' ) {
    var pmpro_require_billing;
    var pmpro_pbc_interval_handle;
}

function pmpropbc_toggleCheckoutFields() {
    "use strict";

    if(jQuery('input[name=gateway]:checked').val() === 'check') {

        if (1 === parseInt(pmpropbc.hide_billing_address_fields)) {
            jQuery('#pmpro_billing_address_fields').hide();
        }

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

        if ( (pmpropbc.code_level !== null) && (parseFloat(pmpropbc.code_level.billing_amount) > 0 || parseFloat(pmpropbc.code_level.initial_payment) > 0) ) {
            jQuery('#pmpro_payment_information_fields').show();
            pmpro_require_billing = true;
        } else {
            pmpro_require_billing = false;
        }

        // jQuery('.pmpro_check_instructions').hide();

        if(pmpropbc.gateway === 'paypalexpress' || pmpropbc.gateway === 'paypalstandard')
        {
            jQuery('#pmpro_paypalexpress_checkout').show();
            jQuery('#pmpro_submit_span').hide();
        }
    }
}

function togglePaymentMethodBox()  {
    "use strict";
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
    "use strict";

    if (jQuery('#pbc_setting').val() > 0 && jQuery('#recurring').is(':checked')) {
        jQuery('tr.pbc_recurring_field').show();
    } else {
        jQuery('tr.pbc_recurring_field').hide();
    }
}


jQuery(document).ready(function () {
    "use strict";
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
        if ( 1 !== parseInt( pmpropbc.pmpro_review ) ) {
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