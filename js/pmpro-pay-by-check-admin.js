/*
	Note that the PMPro Pay by Check plugin only loads this JS on the edit membership level page.
*/
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
	
	togglePBCRecurringOptions();

	//hide/show recurring fields when pbc or recurring settings change
	jQuery('#pbc_setting').change(function () {
		togglePBCRecurringOptions();
	});
	jQuery('#recurring').change(function () {
		togglePBCRecurringOptions();
	});
});