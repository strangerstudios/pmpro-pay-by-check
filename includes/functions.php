<?php

/**
 * Helper function to get options.
 *
 * @param int $level_id - The ID of the level or 0 if this is for a new level.
 * @return array $options - The options for the level.
 */
function pmpropbc_getOptions( int $level_id ) {
	// Set the default options.
	$options = array(
		'setting' => 0, // Not allowing users to pay by check.
		'renewal_days' => 7, // Creating a pending invoice and notifying the user 7 days before the next payment is due.
		'reminder_days' => 3, // Sending a reminder email 3 days after a missed payment.
		'cancel_days' => 7, // Canceling the membership 7 days after a missed payment.
	);

	// Get the settings for the passed level.
	if ( $level_id > 0 ) {
		// Check the db to see if the options exist.
		$db_options = get_option( 'pmpro_pay_by_check_options_' . $level_id, false );
		if ( ! empty( $options ) && is_array( $db_options) ) {
			// Make sure that the old default settings are not being used.
			if ( ! empty( $db_options['renewal_days'] ) || ! empty( $db_options['reminder_days'] ) || ! empty( $db_options['cancel_days'] ) ) {
				// Merge the db options with the default options.
				$options = array_merge( $options, $db_options );
			}
		}
	}

	return $options;
}