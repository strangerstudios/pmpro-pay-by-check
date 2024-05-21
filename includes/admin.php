<?php

/*
	Add settings to the edit levels page
*/
//show the checkbox on the edit level page
function pmpropbc_pmpro_membership_level_after_other_settings()
{
	$level_id = intval($_REQUEST['edit']);
	$options = pmpropbc_getOptions($level_id);
	$check_gateway_label = get_option( 'pmpro_check_gateway_label' ) ?: __( 'Check', 'pmpro-pay-by-check' ); // Default to 'Pay by Check' if no option is set.
?>
<h3 class="topborder"><?php  echo esc_html( sprintf( __( 'Pay by %s Settings', 'pmpro-pay-by-check' ), $check_gateway_label ) ); ?></h3>
<p><?php echo esc_html( sprintf( __( 'Change this setting to allow or disallow the "Pay by %s" option for this level.', 'pmpro-pay-by-check' ), $check_gateway_label ) ); ?></p>
<table>
<tbody class="form-table">
	<tr>
		<th scope="row" valign="top"><label for="pbc_setting"><?php echo esc_html( sprintf( __( 'Allow Paying by %s:', 'pmpro-pay-by-check' ), $check_gateway_label ) );?></label></th>
		<td>
			<select id="pbc_setting" name="pbc_setting">
				<option value="0" <?php selected($options['setting'], 0);?>><?php esc_html_e( 'No. Use the default gateway only.', 'pmpro-pay-by-check' );?></option>
				<option value="1" <?php selected($options['setting'], 1);?>><?php echo esc_html( sprintf( __( 'Yes. Users choose between default gateway and %s.', 'pmpro-pay-by-check' ), $check_gateway_label ) );?></option>
				<option value="2" <?php selected($options['setting'], 2);?>><?php echo esc_html( sprintf( __( 'Yes. Users can only pay by %s.', 'pmpro-pay-by-check' ), $check_gateway_label ) );?></option>
			</select>
		</td>
	</tr>
	<tr class="pbc_recurring_field">
		<th scope="row" valign="top"><label for="pbc_renewal_days"><?php _e('Send Renewal Emails:', 'pmpro-pay-by-check');?></label></th>
		<td>
			<input type="text" id="pbc_renewal_days" name="pbc_renewal_days" size="5" value="<?php echo esc_attr($options['renewal_days']);?>" /> <?php _e('days before renewal.', 'pmpro-pay-by-check');?>
		</td>
	</tr>
	<tr class="pbc_recurring_field">
		<th scope="row" valign="top"><label for="pbc_reminder_days"><?php _e('Send Reminder Emails:', 'pmpro-pay-by-check');?></label></th>
		<td>
			<input type="text" id="pbc_reminder_days" name="pbc_reminder_days" size="5" value="<?php echo esc_attr($options['reminder_days']);?>" /> <?php _e('days after a missed payment.', 'pmpro-pay-by-check');?>
		</td>
	</tr>
	<tr class="pbc_recurring_field">
		<th scope="row" valign="top"><label for="pbc_cancel_days"><?php _e('Cancel Membership:', 'pmpro-pay-by-check');?></label></th>
		<td>
			<input type="text" id="pbc_cancel_days" name="pbc_cancel_days" size="5" value="<?php echo esc_attr($options['cancel_days']);?>" /> <?php _e('days after a missed payment.', 'pmpro-pay-by-check');?>
		</td>
	</tr>
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

/**
 * Enqueue scripts in the dashboard.
 */
function pmpropbc_admin_enqueue_scripts() {
	//make sure this is the edit level page
	
	wp_register_script('pmpropbc-admin', plugins_url( 'js/pmpro-pay-by-check-admin.js', PMPRO_PAY_BY_CHECK_BASE_FILE ), array( 'jquery' ), PMPROPBC_VER );
	wp_enqueue_script('pmpropbc-admin');
}
add_action('admin_enqueue_scripts', 'pmpropbc_admin_enqueue_scripts' );
