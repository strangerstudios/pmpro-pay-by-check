<?php
/**
 * Email Template: Check Pending Reminder
 *
 * @since 1.1.4
 */
class PMPro_Email_Template_Check_Pending_Reminder extends PMPro_Email_Template {

	/**
	 * The user object of the user to send the email to.
	 *
	 * @var WP_User
	 */
	protected $user;

	/**
	 * The {@link MemberOrder} object of the order that is pending.
	 *
	 * @var MemberOrder
	 */
	protected $order;

	/**
	 * Constructor.
	 *
	 * @since 3.4
	 *
	 * @param WP_User $user The user object of the user to send the email to.
	 * @param MemberOrder $order The order object that is associated to the member.
	 */
	public function __construct( WP_User $user,  MemberOrder $order ) {
		$this->user = $user;
		$this->order = $order;
	}

		/**
	 * Get the email template slug.
	 *
	 * @since 3.4
	 *
	 * @return string The email template slug.
	 */
	public static function get_template_slug() {
		return 'check_pending_reminder';
	}

	/**
	 * Get the "nice name" of the email template.
	 *
	 * @since 3.4
	 *
	 * @return string The "nice name" of the email template.
	 */
	public static function get_template_name() {
		return esc_html__( 'Pay By Check - Check Pending Reminder', 'pmpro-pay-by-check' );
	}

	/**
	 * Get "help text" to display to the admin when editing the email template.
	 *
	 * @since 3.4
	 *
	 * @return string The help text.
	 */
	public static function get_template_description() {
		return esc_html__( 'This email is sent to remind a member when an Pay By Check order is in "pending" status.', 'pmpro-pay-by-check' );
	}	

	/**
	 * Get the email subject.
	 *
	 * @since 3.4
	 *
	 * @return string The email subject.
	 */
	public static function get_default_subject() {
		if ( ! class_exists( 'PMPro_Liquid_Renderer' ) ) {
			// Running a version of PMPro before liquid email rendering was available.
			return esc_html__( "Reminder: New Order for !!display_name!! at !!sitename!!", 'pmpro-pay-by-check' );
		}
		return esc_html__( "Reminder: New Order for {{ display_name }} at {{ sitename }}", 'pmpro-pay-by-check' );
	}

	/**
	 * Get the email body.
	 *
	 * @since 3.4
	 *
	 * @return string The email body.
	 */
	public static function get_default_body() {
		if ( ! class_exists( 'PMPro_Liquid_Renderer' ) ) {
			// Running a version of PMPro before liquid email rendering was available.
			return  wp_kses_post( __( '<p>This is a reminder. You have a new Order for !!sitename!!.</p>

!!instructions!!

<p>Below are details about your membership account and a receipt for your membership order.</p>

<p>Account: !!display_name!! (!!user_email!!)</p>
<p>Membership Level: !!membership_level_name!!</p>

<p>
    Order #!!order_id!! on !!order_date!!<br />
    Total Billed: !!order_total!!
</p>

<p>Log in to your membership account here: !!login_link!!</p>', 'pmpro_pay_by_check' ) );
		}
		return  wp_kses_post( __( '<p>This is a reminder. You have a new Order for {{ sitename }}.</p>

{{ instructions }}

<p>Below are details about your membership account and a receipt for your membership order.</p>

<p>Account: {{ display_name }} ({{ user_email }})</p>
<p>Membership Level: {{ membership_level_name }}</p>

<p>
    Order #{{ order_id }} on {{ order_date }}<br />
    Total Billed: {{ order_total }}
</p>

<p>Log in to your membership account here: {{ login_link }}</p>', 'pmpro_pay_by_check' ) );
	}

	/**
	 * Get the email address to send the email to.
	 *
	 * @since 3.4
	 *
	 * @return string The email address to send the email to.
	 */
	public function get_recipient_email() {
		return $this->user->user_email;
	}

	/**
	 * Get the name of the email recipient.
	 *
	 * @since 3.4
	 *
	 * @return string The name of the email recipient.
	 */
	public function get_recipient_name() {
		return $this->user->display_name;
	}

	/**
	 * Get the email template variables for the email.
	 *
	 * @since 3.4
	 *
	 * @return array The email template variables for the email (key => value pairs).
	 */
	public function get_email_template_variables() {
		$order = $this->order;
		$user = $this->user;
		$membership_level = pmpro_getSpecificMembershipLevelForUser( $user->ID, $order->membership_id );
		if ( empty( $membership_level ) ) {
			$membership_level = pmpro_getLevel( $order->membership_id );
		}

		$discount_code = '';
		if( $order->getDiscountCode() ) {
			$discount_code = "<p>" . esc_html__("Discount Code", 'paid-memberships-pro' ) . ": " . $order->discount_code->code . "</p>\n";
		}

		$email_template_variables = array(
			'subject' => $this->get_default_subject(),
			'name' => $this->get_recipient_name(),
			'display_name' => $this->get_recipient_name(),
			'user_login' => $user->user_login,
			'user_email' => $user->user_email,
			'membership_id' => $membership_level->id,
			'membership_level_name' => $membership_level->name,
			'membership_cost' => pmpro_getLevelCost($membership_level),
            'instructions' => wp_unslash( pmpro_getOption( 'instructions' ) ),
			'order_id' => $order->code,
            'invoice_id' => $order->code, // For compatibility with older templates.
			'order_date' => date_i18n( get_option( 'date_format' ), $order->getTimestamp() ),
            'invoice_date' => date_i18n( get_option( 'date_format' ), $order->getTimestamp() ), // For compatibility with older templates.
			'order_total' => $order->get_formatted_total(),
            'invoice_total' => $order->get_formatted_total(), // For compatibility with older templates.
			'discount_code' => $discount_code,
			'billing_address' => pmpro_formatAddress( $order->billing->name,
														 $order->billing->street,
														 $order->billing->street2,
														 $order->billing->city,
														 $order->billing->state,
														 $order->billing->zip,
														 $order->billing->country,
														 $order->billing->phone ),
			'billing_name' => $order->billing->name,
			'billing_street' => $order->billing->street,
			'billing_street2' => $order->billing->street2,
			'billing_city' => $order->billing->city,
			'billing_state' => $order->billing->state,
			'billing_zip' => $order->billing->zip,
			'billing_country' => $order->billing->country,
			'billing_phone' => $order->billing->phone,
			'cardtype' => $order->cardtype,
			'accountnumber' => hideCardNumber( $order->accountnumber ),
			'expirationmonth' => $order->expirationmonth,
			'expirationyear' => $order->expirationyear,
		);

		return $email_template_variables;
	}

	/**
	* Get the email template variables for the email paired with a description of the variable.
	*
	* @since 3.4
	*
	* @return array The email template variables for the email (key => value pairs).
	*/
	public static function get_email_template_variables_with_description() {
		if ( ! class_exists( 'PMPro_Liquid_Renderer' ) ) {
			// Running a version of PMPro before liquid email rendering was available.
			return array(
				'!!display_name!!' => esc_html__( 'The display name of the user.', 'paid-memberships-pro' ),
				'!!user_login!!' => esc_html__( 'The username of the user.', 'paid-memberships-pro' ),
				'!!user_email!!' => esc_html__( 'The email address of the user.', 'paid-memberships-pro' ),
				'!!membership_id!!' => esc_html__( 'The ID of the membership level.', 'paid-memberships-pro' ),
				'!!membership_level_name!!' => esc_html__( 'The name of the membership level.', 'paid-memberships-pro' ),
				'!!membership_cost!!' => esc_html__( 'The cost of the membership level.', 'paid-memberships-pro' ),
				'!!instructions!!' => esc_html__( 'The instructions for the payment method, as set in the PMPro settings.', 'paid-memberships-pro' ),
				'!!order_id!!' => esc_html__( 'The ID of the order.', 'paid-memberships-pro' ),
				'!!order_date!!' => esc_html__( 'The date of the order.', 'paid-memberships-pro' ),
				'!!order_total!!' => esc_html__( 'The total cost of the order.', 'paid-memberships-pro' ),
				'!!discount_code!!' => esc_html__( 'The discount code used for the order.', 'paid-memberships-pro' ),
				'!!billing_address!!' => esc_html__( 'The complete billing address of the order.', 'paid-memberships-pro' ),
				'!!billing_name!!' => esc_html__( 'The billing name of the order.', 'paid-memberships-pro' ),
				'!!billing_street!!' => esc_html__( 'The billing street of the order.', 'paid-memberships-pro' ),
				'!!billing_street2!!' => esc_html__( 'The billing street line 2 of the order.', 'paid-memberships-pro' ),
				'!!billing_city!!' => esc_html__( 'The billing city of the order.', 'paid-memberships-pro' ),
				'!!billing_state!!' => esc_html__( 'The billing state of the order.', 'paid-memberships-pro' ),
				'!!billing_zip!!' => esc_html__( 'The billing ZIP code of the order.', 'paid-memberships-pro' ),
				'!!billing_country!!' => esc_html__( 'The billing country of the order.', 'paid-memberships-pro' ),
				'!!billing_phone!!' => esc_html__( 'The billing phone number of the order.', 'paid-memberships-pro' ),
			);
		}
		return array(
			'{{ display_name }}' => esc_html__( 'The display name of the user.', 'paid-memberships-pro' ),
			'{{ user_login }}' => esc_html__( 'The username of the user.', 'paid-memberships-pro' ),
			'{{ user_email }}' => esc_html__( 'The email address of the user.', 'paid-memberships-pro' ),
			'{{ membership_id }}' => esc_html__( 'The ID of the membership level.', 'paid-memberships-pro' ),
			'{{ membership_level_name }}' => esc_html__( 'The name of the membership level.', 'paid-memberships-pro' ),
			'{{ membership_cost }}' => esc_html__( 'The cost of the membership level.', 'paid-memberships-pro' ),
			'{{ instructions }}' => esc_html__( 'The instructions for the payment method, as set in the PMPro settings.', 'paid-memberships-pro' ),
			'{{ order_id }}' => esc_html__( 'The ID of the order.', 'paid-memberships-pro' ),
			'{{ order_date }}' => esc_html__( 'The date of the order.', 'paid-memberships-pro' ),
			'{{ order_total }}' => esc_html__( 'The total cost of the order.', 'paid-memberships-pro' ),
			'{{ discount_code }}' => esc_html__( 'The discount code used for the order.', 'paid-memberships-pro' ),
			'{{ billing_address }}' => esc_html__( 'The complete billing address of the order.', 'paid-memberships-pro' ),
			'{{ billing_name }}' => esc_html__( 'The billing name of the order.', 'paid-memberships-pro' ),
			'{{ billing_street }}' => esc_html__( 'The billing street of the order.', 'paid-memberships-pro' ),
			'{{ billing_street2 }}' => esc_html__( 'The billing street line 2 of the order.', 'paid-memberships-pro' ),
			'{{ billing_city }}' => esc_html__( 'The billing city of the order.', 'paid-memberships-pro' ),
			'{{ billing_state }}' => esc_html__( 'The billing state of the order.', 'paid-memberships-pro' ),
			'{{ billing_zip }}' => esc_html__( 'The billing ZIP code of the order.', 'paid-memberships-pro' ),
			'{{ billing_country }}' => esc_html__( 'The billing country of the order.', 'paid-memberships-pro' ),
			'{{ billing_phone }}' => esc_html__( 'The billing phone number of the order.', 'paid-memberships-pro' ),
		);
	}

	/**
	 * Returns the arguments to send the test email from the abstract class.
	 *
	 * @since 1.1.4
	 *
	 * @return array The arguments to send the test email from the abstract class.
	 */
	public static function get_test_email_constructor_args() {
		global $current_user;
		//Create test order
		$test_order = new MemberOrder();

		return array( $current_user, $test_order->get_test_order() );
	}
}

/**
 * Register the email template.
 *
 * @since 3.4
 *
 * @param array $email_templates The email templates (template slug => email template class name)
 * @return array The modified email templates array.
 */
function pmproet_email_templates_check_pending_reminder( $email_templates ) {
	$email_templates['check_pending_reminder'] = 'PMPro_Email_Template_Check_Pending_Reminder';

	return $email_templates;
}
add_filter( 'pmpro_email_templates', 'pmproet_email_templates_check_pending_reminder' );