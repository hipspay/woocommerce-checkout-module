=== WooCommerce hips Payment Gateway ===
Contributors: automattic, woothemes, mikejolley, akeda, royho, mattyza, slash1andy, woosteve, spraveenitpro, mikedmoore, fernashes, shellbeezy, danieldudzic, mikaey, dsmithweb, fullysupportedphil, corsonr, deskroid, luminus, tiagonoronha, zandyring, bor0
Tags: credit card, hips, woocommerce
Requires at least: 4.4
Tested up to: 3.5.4
Stable tag: 1.1.4

License: GPLv3
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Take credit card payments on your store using hips.

== Description ==

Accept Visa, MasterCard, American Express, Discover, JCB, Diners Club, and more cards directly on your store with the hips payment gateway for WooCommerce, including for mobile and desktop.

= Take Credit card payments easily and directly on your store =

The hips plugin extends WooCommerce allowing you to take payments directly on your store via hips’s API.

hips is available in:

* United States
* Canada
* UK
* Australia
* Belgium
* France
* Germany
* Ireland
* The Netherlands
* [and more](https://support.hips.com/questions/what-countries-does-hips-support)

hips is a simple way to accept payments online. With hips you can accept Visa, MasterCard, American Express, Discover, JCB, and Diners Club cards, even Bitcoin, directly on your store.

= Why choose hips? =

Hips has no setup fees, no monthly fees, no hidden costs: you only get charged when you earn money! Earnings are transferred to your bank account on a 7-day rolling basis.

hips also supports the [Subscriptions extension](http://www.woothemes.com/extension/subscriptions/) and re-using cards. When a customer pays, they are set up in hips as a customer. If they create another order, they can check out using the same card. A massive timesaver for returning customers.

= Web Payments API Support =

WooCommerce hips now includes [Web Payments API](https://www.w3.org/TR/payment-request/) support, which means customers can pay using payment details associated to their mobile devices, in browsers supporting the Web Payments API (Chrome for Android, amongst others). Checkout is now just a few taps away. on mobile.

== Installation ==

Please note, v3 of this gateway requires WooCommerce 2.5 and above.

You can download an [older version of this gateway for older versions of WooCommerce from here](https://wordpress.org/plugins/woocommerce-gateway-hips/developers/).

= Automatic installation =

Automatic installation is the easiest option as WordPress handles the file transfers itself and you don’t need to leave your web browser. To
do an automatic install of, log in to your WordPress dashboard, navigate to the Plugins menu and click Add New.

In the search field type “WooCommerce hips Payment Gateway” and click Search Plugins. Once you’ve found our plugin you can view details about it such as the point release, rating and description. Most importantly of course, you can install it by simply clicking "Install Now".

= Manual installation =

The manual installation method involves downloading our plugin and uploading it to your web server via your favorite FTP application. The WordPress codex contains [instructions on how to do this here](http://codex.wordpress.org/Managing_Plugins#Manual_Plugin_Installation).

= Updating =

Automatic updates should work like a charm; as always though, ensure you backup your site just in case.

== Frequently Asked Questions ==

= Does this support recurring payments, like for subscriptions? =

Yes!

= Does this support both production mode and sandbox mode for testing? =

Yes it does - production and sandbox mode is driven by the API keys you use.

= Where can I find documentation? =

For help setting up and configuring, please refer to our [user guide](https://docs.woothemes.com/document/hips/)

= Where can I get support or talk to other users? =

If you get stuck, you can ask for help in the Plugin Forum.

== Screenshots ==

1. The settings panel used to configure the gateway.
2. Normal checkout with hips.
3. Option to save a card to your account.
4. Checking out with a saved card.
5. Using the hips checkout modal.

== Changelog ==
= 1.1.4
  - 2019-02-25 =
  * Fix the order duplicating issue
  * Fix the missing customer info issue
  * Added auto-fulfill option

= 1.1.3
 - 2018-05-18 =
* Update the order status on verifying Webhook 
* Update the hook for Google Analytics
* Fix the empty order issue for guest users

= 1.0.9 - 2018-04-26 =
* Fix the redirection issue for failed orders
* Fix the empty cart issue after hips checkout

= 1.0.8 - 2018-04-18 =
* Add the redirection parameters for the 3dsecure method

= 1.0.7 - 2018-03-27 =
* Redirection issue on hips checkout page
* Tax issues on the hips checkout page

== Upgrade Notice ==