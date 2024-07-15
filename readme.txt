=== Paid Memberships Pro: Pay by Check Add On ===
Contributors: strangerstudios, eighty20results
Tags: pmpro, paid memberships pro, members, memberships, check, cheque, payments, offline
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.1

A collection of customizations useful when allowing users to pay by check for Paid Memberships Pro levels.

== Description ==

Adds a radio option to checkout to pay by credit card or PayPal now or pay by check.

Users who choose to pay by check will have their order to "pending" status.

Users with a pending order will not receive a membership level immediately.

After you receive and cash the check, you can edit the order to change the status to "success", which will complete the membership puchase.

== Installation ==

1. Upload the `pmpro-pay-by-check` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Change your Payment Settings to the "Pay by Check" gateway and make sure to set the "Instructions" with instructions for how to pay by check. Save.
1. Change the Payment Settings back to use your gateway of choice. Behind the scenes the Pay by Check settings are still stored.
1. Edit your membership levels and set the "Pay by Check Settings" for each level.

If you would like to change the wording from "Pay by Check" to something else, you can use this custom code:
https://gist.github.com/strangerstudios/68bb75bf3b83530390d4

== Changelog ==
= 1.1 - TBD =
* ENHANCEMENT: Added support for v3.1+ Paid Memberships Pro frontend changes. #120 (@MaximilianoRicoTabo, @kimcoleman)
* BUG FIX: Now only sending the cancellation email if a level was removed. #118 (@dparker1005)
* BUG FIX: Fixed issue where crons were not being set up or disabled. #121 (@dparker1005)

= 1.0.1.1 - 2024-06-10 =
* BUG FIX: Fixed incorrect parameters passed to the pmpro_confirmation_url filter. #117 (@mircobabini)

= 1.0.1 - 2024-06-08 =
* BUG FIX: Fixing broken SQL query.

= 1.0 - 2024-06-03 =
* ENHANCEMENT: When using PMPro v3.0+, recurring orders are now generated based on information in the PMPro Subscriptions table.
* ENHANCEMENT: When using PMPro v3.0.3+, checkouts using the Check gateway are now processed once the payment is received instead of assigning the membership immediately.
* ENHANCEMENT: Initial payments now respect the "Send Reminder Emails" and "Cancel Membership" after x days level settings.
* ENHANCEMENT: Improved default values for the "Send Renewal Emails", "Send Reminder Emails", and "Cancel Membership" after x days level settings.
* BUG FIX/ENHANCEMENT: When using PMPro v3.0.3+, Check subscriptions now respect profile start dates set at checkout.
* REFACTOR: Organized code into separate files.
* DEPRECATED: Removed HTML email templates. Emails should now be customized from the PMPro Email Templates settings page.

= 0.12.1 - 2024-03-28 =
* BUG FIX: Fixed an issue for sites running PMPro v3.0+ where recurring check orders could be created in pending status when the most recent order for a user wasn't a check order. #115 (@dparker1005)

= 0.12 - 2023-12-11 =
* FEATURE: Email templates for this Add On can now be edited from the "Memberships" > "Settings" > "Email Templates" settings page. #106 (@MaximilianoRicoTabo)
* ENHANCEMENT: Now respecting the "Gateway Name Label" setting in core PMPro v3.0+ for updating "Check" wording. #108 (@MaximilianoRicoTabo)
* ENHANCEMENT: Adding compatibility with the PMPro v3.0+ Subscriptions Table by updating the subscription object when a recurring invoice is generated and no longer processing recurring orders for cancelled subscriptions. #110 (@dparker1005)

= 0.11.3 - 2023-10-04 =
* SECURITY: General improvements and sanitization of the codebase. (@andrewlimaza)
* BUG FIX: Fixed an issue where more than one order would be created mistakenly when there were different payment plans for a level. (@andrewlimaza, @dparker1005)
* BUG FIX: Fixed an issue with the "Address for Free & Offsite Levels" Add On would not show billing fields. (@JarrydLong)
* BUG FIX: Fixed an issue where the `[pmpro_member]` shortcode would throw a warning in certain cases for Pay By Check members. (@JarrydLong)

= 0.11.2 - 2023-08-30 =
* BUG FIX/ENHANCEMENT: Improved performance when loading the checkout page for a "check only" level to avoid issue where some sites could run out of PHP memory. #99 (@dparker1005)
* BUG FIX: Fixed PHP warnings that could occur when the user is logged out. #98 (@dparker1005)
* REFACTOR: Deprecating the function `pmpropbc_pmpro_get_gateway()` as a part of the performance improvements. #99 (@dparker1005)

= 0.11.1 - 2023-08-14 =
* ENHANCEMENT: Updating `<h3>` tags to `<h2>` tags for better accessibility. #92 (@kimcoleman)
* ENHANCEMENT: Now listing all levels with pending payments in the Membership Account page. #80 (@dparker1005)
* ENHANCEMENT: Now copying billing address to new orders when a recurring check order is generated in pending status. #82 (@dparker1005)
* ENHANCEMENT: Added French translation files. #91 (@michaelbeil)
* BUG FIX/ENHANCEMENT: Improved compatibility with PMPro Multiple Memberships Per User Add On. #80, #96 (@dparker1005)
* BUG FIX/ENHANCEMENT: Now passing post IDs to `pmprobpc_memberHasAccessWithAnyLevel()` when checking if a user has access to specific restricted content. #80 (@dparker1005)
* BUG FIX/ENHANCMENET: Overdue orders will now be moved into "error" status. #95 (@dparker1005)
* BUG FIX: Now sending an email after a recurring check order is generated in pending status. #84 (@becleung)
* BUG FIX: Fixed issue where content set to be restricted to non-members would be hidden from logged-in non-members. #83 (@dparker1005)
* BUG FIX: Fixed issue where an invoice email would be sent every time that a check order in "success" status was saved. #94 (@dparker1005)
* BUG FIX: Fixed issue where the PMPro Add PayPal Express Add On would still give the option to pay with PayPal at checkout even when the level is set to only allow check payments. #87 (@JarrydLong)
* REFACTOR: Now using the function `get_option()` instead of `pmpro_getOption()` when retrieving the "Pay by Check" settings. #90 (@JarrydLong)
* REFACTOR: No longer pulling the checkout level ID directly from the `$_REQUEST` variable. #88 (@dparker1005)
* REFACTOR: Deprecating misspelled function `pmprobpc_isMemberPending()` in favor of `pmpropbc_isMemberPending()`. #80 (@dparker1005)

= 0.11 - 2022-09-14 =
* ENHANCEMENT: Tweaked the confirmation message on the confirmation page to clearly show that no access is available until payment is received.
* ENHANCEMENT: Improved cases where orders would stay in pending if members checked out for the same level but change their payment method. Previous orders now are set with the "token" status if switching gateways and had a pending status with check payment.
* BUG FIX: Improved compatibility for cases like Auto Renewal Checkbox where a level may have recurring and non-recurring options. This fixes an issue where non-recurring members would still be cancelled or receive renewal reminders.
* BUG FIX: Various fixes to support and improve compatibility with PHP8+ (Thanks @ZebulanStanphill, @jarrydlong).
* BUG FIX: Fixed an issue where [membership level="0"] would display incorrectly (Thanks @ipokkel)

= 0.10 - 2022-04-01 =
* ENHANCEMENT: Changed table layout to div instead for checkout payment method selection.
* ENHANCEMENT: Added in support for [membership] shortcode logic. Pending members will no longer gain access until approved.
* BUG FIX: Only reference check orders for grace period settings. Fixes an issue for existing user's having previous orders with other gateways besides 'check'. Resolves issues with MMPU.

= 0.9 - 2020-08-31 =
* ENHANCEMENT: Improved SQL queries around sending email reminders, cancelling outstanding memberships. Thanks @swhytehead
* ENHANCEMENT: Support PayFast gateway.
* ENHANCEMENT: Support PayPal Website Payments Pro.

= .8.1 =
* BUG FIX: Fixed issue when using PMPro v2.1+.
* BUG FIX: Fixed issue with billing address or payment info fields not being shown when switching back to the default gateway after having an error with checking out by check.

= .8 =
* BUG FIX: Fixed issue where JavaScript was loaded on non-post pages (e.g. archives).
* BUG FIX: Now using the correct text domain for localization.
* BUG FIX: Fixed bug in pmprobpc_isMemberPending when the user has no last order.
* BUG FIX/ENHANCEMENT: Added support for Variable Pricing add-on.
* BUG FIX/ENHANCEMENT: Added pmprobpc_memberHasAccessWithAnyLevel() to use with PMPro MMPU. Needs more testing.
* ENHANCEMENT: Change Text Domain for plugin/add-on.
* FEATURE: Added French Translation. (Thanks, Alfonso Sánchez Uzábal)

= .7.8 =
* BUG FIX/ENHANCEMENT: pmpropbc_isMemberPending can now accept a level ID as a 2nd parameter to check status for a user's specific level.

= .7.8 =
* BUG FIX: Fixed issue where PayPal button was showing sometimes when "check" was chosen.
* ENHANCEMENT: Now showing a better non-member-text notice when pending members try to access content.

= .7.7 =
* BUG: Updated to better support the PayPal Website Payments Pro gateway option. Shows 3 gateway options in one box now.

= .7.6 =
* BUG: Fixed bug in pmpropbc_send_invoice_email().
* BUG: Fixed issue with PMPro 1.8.14+ where a discount code error would show up at checkout even if no code was used.
* BUG/ENHANCEMENT: Users are no longer considered "pending" (although the order still is) if they renew their expiring membership early. The code will check if the user has successfully paid order within the membership period, including the grace period set for the level in the pay by check options. We were doing this check for recurring memberships before, but will do them for one time payments as well now.

= .7.5 =
* BUG: Check of discounted price would sometimes fail
* BUG: Would sometimes cause JavaScript error if Stripe gateway was configured & discount code set cost to 0
* BUG: Infinite loop when discount code sets cost to 0
* BUG: Correctly toggle payment information field when discount code(s) are present
* BUG: Warning when order isn't found
* BUG/ENHANCEMENT: Updated to better support using this addon along with the Add PayPal Express addon. Make sure both are up to date.
* BUG/ENHANCEMENT: Updated the Choose a Payment Method box to hook into pmpro_checkout_boxes with priority 20. This will make it more likely for the Payment Method box to show up closer to the billing address and payment address sections (e.g. after any custom Register Helper fields).
* ENHANCEMENT: Added a PMPROPBC_VER constant used during enqueue operations
* ENHANCEMENT: Separated JavaScript into their own files to make them debuggable & load during enqueue operations

= .7 =
* NOTE: Changed togglePaymentMethodBox() function to have a prefix, pmpropbc_togglePaymentMethodBox().
* BUG: Along with update 1.8.10.4 of PMPro, fixes an issue where users could not checkout when they applied a discount code that made the level free.
* BUG/ENHANCEMENT: Better integration with the Address for Free Levels addon.

= .6 =
* FEATURE: Updated for localization with new pmpropbc.pot/po files.

= .5 =
* Added support for recurring levels.
* Create a new "pending" invoice automatically on renewal date.
* Send emails when the invoice is created asking for payment.
* Send email if the invoice isn't paid within 30, 45 days.
* Cancel the subscription and mark invoice as "unpaid" after 60 days.

= .4 =
* Added ability to set certain levels to be check only.
* Updated readme with info on using gettext filter to change language from "check" to "wire transfer" etc.

= .3.1 =
* Hiding the payment option radio buttons on the review page when using PayPal Express/Standard/etc.

= .3 =
* Added readme.
* If using PayPal Standard or Express gateway, the PayPal checkout submit button will swap out for the default button when choosing to pay by check.

= .1 =
* First version.
