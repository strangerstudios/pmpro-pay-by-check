/*
	Note that the PMPro Pay by Check plugin only loads this JS on the edit membership level page.
*/
function toggle_pbc_level_settings_fields() {
    "use strict";

    if (jQuery('#pbc_setting').val() > 0 ) {
        jQuery('tr.pbc_level_settings_field').show();
    } else {
        jQuery('tr.pbc_level_settings_field').hide();
    }
}

jQuery(document).ready(function () {
	"use strict";
	
	toggle_pbc_level_settings_fields();

	//hide/show recurring fields when pbc or recurring settings change
	jQuery('#pbc_setting').change(function () {
		toggle_pbc_level_settings_fields();
	});
});