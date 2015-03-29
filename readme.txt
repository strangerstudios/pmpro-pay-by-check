=== Paid Memberships Pro: Pay by Check Add On ===
Contributors: strangerstudios
Tags: pmpro, paid memberships pro, members, memberships, check, cheque, payments, offline
Requires at least: 3.5
Tested up to: 4.1.1
Stable tag: .3.1

A collection of customizations useful when allowing users to pay by check for Paid Memberships Pro levels.

== Description ==

Adds a radio option to checkout to pay by credit card or PayPal now or pay by check.

Users who choose to pay by check will have their order to "pending" status.

Users with a pending order will not have access based on their level.

After you receive and cash the check, you can edit the order to change the status to "success", which will give the user access.

An email is sent to the user RE the status change.

== Installation ==

1. Upload the `pmpro-pay-by-check` folder to the `/wp-content/plugins/` directory.
1. Activate the plugin through the 'Plugins' menu in WordPress.
1. Change your Payment Settings to the "Pay by Check" gateway and make sure to set the "Instructions" with instructions for how to pay by check. Save.
1. Change the Payment Settings back to use your gateway of choice. Behind the scenes the Pay by Check settings are still stored.

== Changelog == 
= .3.1 =
* Hiding the payment option radio buttons on the review page when using PayPal Express/Standard/etc.

= .3 =
* Added readme.
* If using PayPal Standard or Express gateway, the PayPal checkout submit button will swap out for the default button when choosing to pay by check.

= .1 =
* First version.