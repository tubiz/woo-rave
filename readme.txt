=== WooCommerce Rave Payment Gateway ===
Contributors: tubiz
Donate link: https://bosun.me/donate
Tags: woocommerce, rave, flutterwave, payment gateway, payment gateways, mastercard, visa cards, verve cards, tubiz plugins, verve, nigeria, ghana, kenya, south africa, mpesa
Requires at least: 4.7
Requires PHP: 5.6
Tested up to: 5.5
Stable tag: 2.2.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

WooCommerce Rave payment gateway plugin allows you to accept payment on your WooCommerce store through multiple payment channels via Rave by Flutterwave





== Description ==

This is a Rave payment gateway for WooCommerce.

You can signup for a Rave merchant account [here](https://rave.flutterwave.com)

WooCommerce Rave payment gateway plugin allows you to accept payment on your WooCommerce store through multiple payment channels via Rave by Flutterwave

With this WooCommerce Rave Payment Gateway plugin, you will be able to accept the following payment methods in your shop:

* __Card Payments__
* __Bank Account Payments__
* __Mobile Wallet Payments__

= Plugin Features =

* __Multiple payment channels__ available for your customers.
* __Seamless integration__ into the WooCommerce checkout page.
* __Recurring payment__ using [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) plugin

= WooCommerce Subscriptions Integration =

*	The [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) integration only works with WooCommerce v2.6 and above and WooCommerce Subscriptions v2.0 and above.

*	No subscription plans is created on Rave. The [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) handles all the subscription functionality.


= Note =

*	You will need to set your store country to either Nigeria, Kenya, Ghana or South Africa in the General tab on the WooCommerce settings page
*	Currencies supported by Rave: NGN, USD, EUR, GBP, KES, GHS, ZAR



= Suggestions / Feature Request =

If you have suggestions or a new feature request, feel free to get in touch with me via the contact form on my website [here](http://bosun.me/get-in-touch/)

You can also follow me on Twitter! **[@tubiz](http://twitter.com/tubiz)**


= Contribute =
To contribute to this plugin feel free to fork it on GitHub [WooCommerce Rave Payment Gateway](https://github.com/tubiz/woo-rave)


== Installation ==

= Automatic Installation =
* 	Login to your WordPress Admin area
* 	Go to "Plugins > Add New" from the left hand menu
* 	In the search box type "WooCommerce Rave Payment Gateway"
*	From the search result you will see "WooCommerce Rave Payment Gateway" click on "Install Now" to install the plugin
*	A popup window will ask you to confirm your wish to install the Plugin.

= Note: =
If this is the first time you've installed a WordPress Plugin, you may need to enter the FTP login credential information. If you've installed a Plugin before, it will still have the login information. This information is available through your web server host.

* Click "Proceed" to continue the installation. The resulting installation screen will list the installation as successful or note any problems during the install.
* If successful, click "Activate Plugin" to activate it.
* 	Open the settings page for WooCommerce and click the "Payment Gateways," tab.
* 	Click on the sub tab for "Rave".
*	Configure your "Rave" settings. See below for details.

= Manual Installation =
1. 	Download the plugin zip file
2. 	Login to your WordPress Admin. Click on "Plugins > Add New" from the left hand menu.
3.  Click on the "Upload" option, then click "Choose File" to select the zip file from your computer. Once selected, press "OK" and press the "Install Now" button.
4.  Activate the plugin.
5. 	Open the settings page for WooCommerce and click the "Payment Gateways," tab.
6. 	Click on the sub tab for "Rave".
7.	Configure your "Rave" settings. See below for details.



= Configure the plugin =
To configure the plugin, go to __WooCommerce > Settings__ from the left hand menu, then click "Payment Gateways" from the top tab. You should see __"Rave by Flutterwave"__ as an option at the top of the screen. Click on it to configure the payment gateway.

* __Enable/Disable__ - Check the box to enable Rave Payment Gateway.
* __Title__ - Allows you to set the payment method title that your customers will see this payment option as on the checkout page.
* __Description__ - Controls the message that is shown under the Rave payment method on the checkout page. Here you can list the types of cards you accept.
* __Test Mode__  - Check this to enable test mode, remember to uncheck this if you are ready to accepting live payment on your site.
* __Public Key__  - Enter your public key here.
* __Secret Key__  - Enter your private key here.
* __Payment Method__  - Set the payment options you want for your customers.
* __Custom Title__  - Optional: Text to be displayed as the title of the payment modal.
* __Custom Description__  - Optional: Text to be displayed as a short modal description.
* __Custom Logo__  - Optional: Enter the link to a image to be displayed on the payment popup. Preferably a square image.

* Click on __Save Changes__ for the changes you made to be effected.





== Frequently Asked Questions ==

= What Do I Need To Use The Plugin =

1.	You need to have the WooCommerce plugin installed and activated on your WordPress site.
2.	You need to open a merchant account on [Rave](https://rave.flutterwave.com)


= WooCommerce Subscriptions Integration =

*	The [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) integration only works with WooCommerce v2.6 and above and WooCommerce Subscriptions v2.0 and above.

*	No subscription plans is created on Rave. The [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) handles all the subscription functionality.


== Changelog ==

= 2.2.4 (March 12, 2020) =
*   Update: WooCommerce 4.0 compatibility

= 2.2.3 (November 19, 2019) =
*   Fix: Fatal error on settings page

= 2.2.2 (November 13, 2019) =
*   Update: WooCommerce 3.8 compatibility

= 2.2.1 (September 5, 2019) =
*   New: Add support for Zambian kwacha (ZMW)

= 2.2.0 (August 6, 2019) =
*   Bug fixes
*   Update: WC 3.7 compatibility

= 2.1.0 (December 9, 2018) =
*   New: Display Rave fee and Rave payout amount on the order details page
*   Fix: Saved cards not working
*   Misc: Add support for WooCommerce 3.5

= 2.0.0 (June 03, 2018) =
* 	New: Saved cards - allow store customers to save their card details and pay again using the same card. Card details are saved on Rave servers and not on your store.
* 	New: Add support for recurring payment using [WooCommerce Subscriptions](https://woocommerce.com/products/woocommerce-subscriptions/) plugin.

= 1.0.1 (May 08, 2018) =
*   Fix: Bad integrity hash if & is used in the custom title or custom description text

= 1.0.0 (March 26, 2018) =
*   First release





== Upgrade Notice ==

= 2.2.4 =
*   WooCommerce 4.0 compatibility



== Screenshots ==

1. Rave WooCommerce payment gateway settings page

2. Rave payment popup on the website