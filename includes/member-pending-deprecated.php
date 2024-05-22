<?php

/**
 * Hook in the "member pending" code for sites running PMPro versions lower than 3.0.3.
 *
 * This is because in 3.0.3, we have the ability to overwrite the core Check gateway class. With that overwritten class,
 * we have implemented a checkout process that delays the chekcout completion and level chnage until after the first check is recieved.
 *
 * When a site upgrades from a previous version to 3.0.3+, any previously "pending" members may gain access to restricted content before
 * their initial check is recieved. There is not an easy way to avoid this and should be handled on a per-case basis. This breaking change is why this
 * is being implemented in the 1.0 release of PBC.
 *
 * @since TBD
 */
function pmpropbc_add_member_pending_actions() {
	// If running PMPro v3.0.3+, return.
	if ( ! defined( 'PMPRO_VERSION') || version_compare( PMPRO_VERSION, '3.0.3', '>=' ) ) {
		return;
	}

	add_filter( "pmpro_has_membership_access_filter", "pmpropbc_pmpro_has_membership_access_filter", 10, 4 );
	add_filter( 'pmpro_member_shortcode_access', 'pmpropbc_pmpro_member_shortcode_access', 10, 4 );
	add_filter( 'pmpro_non_member_text_filter', 'pmpropbc_check_pending_lock_text' );
}
add_action( 'init', 'pmpropbc_add_member_pending_actions' );

/**
 * Check if a member's status is still pending, i.e. they haven't made their first check payment.
 *
 * @since .5
 *
 * @param int $user_id ID of the user to check.
 * @param int $level_id ID of the level to check. If 0, will return if user is pending for any level.
 *
 * @return bool If status is pending or not.
 */
function pmpropbc_isMemberPending($user_id, $level_id = 0)
{
	global $pmpropbc_pending_member_cache;

	//check the cache first
	if(isset($pmpropbc_pending_member_cache) && 
	   isset($pmpropbc_pending_member_cache[$user_id]) && 
	   isset($pmpropbc_pending_member_cache[$user_id][$level_id]))
		return $pmpropbc_pending_member_cache[$user_id][$level_id];
	
	//make room for this user's data in the cache
	if(!is_array($pmpropbc_pending_member_cache)) {
		$pmpropbc_pending_member_cache = array();
	} elseif(!is_array($pmpropbc_pending_member_cache[$user_id])) {
		$pmpropbc_pending_member_cache[$user_id] = array();
	}	
	$pmpropbc_pending_member_cache[$user_id][$level_id] = false;

	// If level is 0, we should check if user is pending for any level.
	if ( empty( $level_id ) ) {
		$is_pending = false;
		$levels = pmpro_getMembershipLevelsForUser( $user_id );
		if ( ! empty( $levels) ) {
			foreach ( $levels as $level ) {
				if ( pmpropbc_isMemberPending( $user_id, $level->id ) ) {
					$is_pending = true;
				}
			}
		}
		$pmpropbc_pending_member_cache[$user_id][$level_id] = $is_pending;
		return $is_pending;
	}

	//check their last order
	$order = new MemberOrder();
	$order->getLastMemberOrder($user_id, false, $level_id);		//NULL here means any status

	if(!empty($order->status))
	{
		if($order->status == "pending")
		{
			//for recurring levels, we should check if there is an older successful order
			$membership_level = pmpro_getSpecificMembershipLevelForUser( $user_id, $level_id );
						
			//unless the previous order has status success and we are still within the grace period
			$paid_order = new MemberOrder();
			$paid_order->getLastMemberOrder($user_id, array('success', 'cancelled'), $order->membership_id);
			
			if(!empty($paid_order) && !empty($paid_order->id) && $paid_order->gateway === 'check')
			{
				//how long ago is too long?
				$options = pmpropbc_getOptions($membership_level->id);
				
				if(pmpro_isLevelRecurring($membership_level)) {
					$cutoff = strtotime("- " . $membership_level->cycle_number . " " . $membership_level->cycle_period, current_time("timestamp")) - ($options['cancel_days']*3600*24);
				} else {
					$cutoff = strtotime("- " . $membership_level->expiration_number . " " . $membership_level->expiration_period, current_time("timestamp")) - ($options['cancel_days']*3600*24);
				}
				
				//too long ago?
				if($paid_order->timestamp < $cutoff)
					$pmpropbc_pending_member_cache[$user_id][$level_id] = true;
				else
					$pmpropbc_pending_member_cache[$user_id][$level_id] = false;
			}
			else
			{
				//no previous order, this must be the first
				$pmpropbc_pending_member_cache[$user_id][$level_id] = true;
			}			
		}
	}
	
	return $pmpropbc_pending_member_cache[$user_id][$level_id];
}

/**
 * Check if user has access to content based on their membership level.
 *
 * @param int $user_id ID of the user to check.
 * @param array(int) $content_levels Array of level IDs to check. If empty, will check if user has access to any level.
 *
 *	@return bool If user has access to content or not.
 */
function pmprobpc_memberHasAccessWithAnyLevel( $user_id, $content_levels = null ) {
	$user_levels = pmpro_getMembershipLevelsForUser( $user_id );
	if ( empty( $user_levels ) ) {
		return false;
	}

	$user_level_ids = wp_list_pluck( $user_levels, 'id' );
	if ( empty( $content_levels ) ) {
		// Check all user levels.
		$content_levels = $user_level_ids;
	}

	// Loop through all content levels.
	foreach ( $content_levels as $content_level ) {
		if ( in_array( $content_level, $user_level_ids ) && ! pmpropbc_isMemberPending( $user_id, $content_level ) ) {
			return true;
		}
	}
	return false;
}


/*
 *	In case anyone was using the typo'd function name.
 *
 * @deprecated TBD Use pmpropbc_isMemberPending() instead.
 */
function pmprobpc_isMemberPending($user_id) {
	_deprecated_function( __FUNCTION__, 'TBD', 'pmpropbc_isMemberPending()' );
	return pmpropbc_isMemberPending($user_id);
}

//if a user's last order is pending status, don't give them access
function pmpropbc_pmpro_has_membership_access_filter( $hasaccess, $mypost, $myuser, $post_membership_levels ) {
	//if they don't have access, ignore this
	if ( ! $hasaccess ) {
		return $hasaccess;
	}

	if ( empty( $post_membership_levels ) ) {
		return $hasaccess;
	}

	//if this isn't locked by level, ignore this
	$hasaccess = pmprobpc_memberHasAccessWithAnyLevel( $myuser->ID, wp_list_pluck( $post_membership_levels, 'id' ) );

	return $hasaccess;
}


/**
 * Filter membership shortcode restriction based on pending status.
 * 
 * @since 0.10
 */
function pmpropbc_pmpro_member_shortcode_access( $hasaccess, $content, $levels, $delay ) {
	global $current_user;
	// If they don't have a access already, just bail.
	if ( ! $hasaccess ) {
		return $hasaccess;
	}

	//If no levels attribute is added to the shortcode, assume access for any level
	if( ! is_array( $levels ) ) {
		return pmprobpc_memberHasAccessWithAnyLevel( $current_user->ID, $levels );
	}

	// If we are checking if the user is not a member, we don't want to hide this content if they are pending.
	foreach ( $levels as $level ) {
		if ( intval( $level ) <= 0 ) {
			return $hasaccess;
		}
	}

	// We only need to run this check for logged-in user's as PMPro will handle logged-out users.
	if ( is_user_logged_in() ) {
		$hasaccess = pmprobpc_memberHasAccessWithAnyLevel( $current_user->ID, $levels );
	}

	return $hasaccess;
}

/**
 *  Show a different message for users whose checks are pending
 */
function pmpropbc_check_pending_lock_text( $text ){
	global $current_user;

	//if a user does not have a membership level, return default text.
	if( !pmpro_hasMembershipLevel() ){
		return $text;
	}

	
	
	if(pmpropbc_isMemberPending($current_user->ID)==true && pmpropbc_wouldHaveMembershipAccessIfNotPending()==true){
		$text = __("Your payment is currently pending. You will gain access to this page once it is approved.", "pmpro-pay-by-check");
	}
	return $text;
}

function pmpropbc_wouldHaveMembershipAccessIfNotPending($user_id = NULL){
	global $current_user;
	if(!$user_id)
		$user_id = $current_user->ID;
	
	remove_filter("pmpro_has_membership_access_filter", "pmpropbc_pmpro_has_membership_access_filter", 10, 4);
	$toReturn = pmpro_has_membership_access(NULL, NULL, true)[0];
	add_filter("pmpro_has_membership_access_filter", "pmpropbc_pmpro_has_membership_access_filter", 10, 4);
	return $toReturn;
}