<?php
/*
 * Plugin Name: WooCommerce Hips Gateway
 * Plugin URI: 
 * Description: Take credit card payments on your store using Hips.
 * Author: Virtina
 * Author URI: https://virtina.com/
 * Version: 1.1.5
 * Requires at least: 4.4
 * Tested up to: 5.0.3
 * WC requires at least: 3.0
 * WC tested up to: 3.5.4
 * Text Domain: woocommerce-gateway-hips
 * Domain Path: /languages
 *
 * Copyright (c) 2018 hips.com
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Required minimums and constants
 */
define( 'WC_HIPS_VERSION', '1.1.5' );
define( 'WC_HIPS_MIN_PHP_VER', '5.6.0' );
define( 'WC_HIPS_MIN_WC_VER', '3.2' );
define( 'WC_HIPS_MAIN_FILE', __FILE__ );
define( 'WC_HIPS_PLUGIN_URL', untrailingslashit( plugins_url( basename( plugin_dir_path( __FILE__ ) ), basename( __FILE__ ) ) ) );
define( 'WC_HIPS_PLUGIN_PATH', untrailingslashit( plugin_dir_path( __FILE__ ) ) );


if ( ! class_exists( 'WC_hips' ) ) :

	class WC_hips {

		/**
		 * @var Singleton The reference the *Singleton* instance of this class
		 */
		private static $instance;

		/**
		 * @var Reference to logging class.
		 */
		private static $log;

		/**
		 * Returns the *Singleton* instance of this class.
		 *
		 * @return Singleton The *Singleton* instance.
		 */
		public static function get_instance() {
			if ( null === self::$instance ) {
				self::$instance = new self();
			}
			return self::$instance;
		}

		/**
		 * Private clone method to prevent cloning of the instance of the
		 * *Singleton* instance.
		 *
		 * @return void
		 */
		private function __clone() {}

		/**
		 * Private unserialize method to prevent unserializing of the *Singleton*
		 * instance.
		 *
		 * @return void
		 */
		private function __wakeup() {}

		/**
		 * Flag to indicate whether or not we need to load code for / support subscriptions.
		 *
		 * @var bool
		 */
		private $subscription_support_enabled = false;

		/**
		 * Flag to indicate whether or not we need to load support for pre-orders.
		 *
		 * @version 1.1.4
		 *
		 * @var bool
		 */
		private $pre_order_enabled = false;

		/**
		 * Notices (array)
		 * @var array
		 */
		public $notices = array();

		/**
		 * Protected constructor to prevent creating a new instance of the
		 * *Singleton* via the `new` operator from outside of this class.
		 */
		protected function __construct() {
			add_action( 'admin_init', array( $this, 'check_environment' ) );
			add_action( 'admin_notices', array( $this, 'admin_notices' ), 15 );
			add_action( 'plugins_loaded', array( $this, 'init' ) );
			add_action( 'init', array( $this, 'create_hips_checkout_page' ) );
		}

		/**
		 * Init the plugin after plugins_loaded so environment variables are set.
		 */
		public function init() {
			// Don't hook anything else in the plugin if we're in an incompatible environment
			if ( self::get_environment_warning() ) {
				return;
			}

			include_once( dirname( __FILE__ ) . '/includes/class-wc-hips-api.php' );
			include_once( dirname( __FILE__ ) . '/includes/class-wc-hips-customer.php' );

			// Init the gateway itself
			$this->init_gateways();

			add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'plugin_action_links' ) );
			add_action( 'woocommerce_order_status_on-hold_to_processing', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_completed', array( $this, 'capture_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_cancelled', array( $this, 'cancel_payment' ) );
			add_action( 'woocommerce_order_status_on-hold_to_refunded', array( $this, 'cancel_payment' ) );
			add_filter( 'woocommerce_get_customer_payment_tokens', array( $this, 'woocommerce_get_customer_payment_tokens' ), 10, 3 );
			add_action( 'woocommerce_payment_token_deleted', array( $this, 'woocommerce_payment_token_deleted' ), 10, 2 );
			add_action( 'woocommerce_payment_token_set_default', array( $this, 'woocommerce_payment_token_set_default' ) );
			add_action( 'wp_ajax_hips_dismiss_request_api_notice', array( $this, 'dismiss_request_api_notice' ) );
			$hips_settings = get_option( 'woocommerce_Hips_settings' );  

			if( $hips_settings['enabled'] == 'yes' && $hips_settings['hips_checkout'] == 'yes' ){
				add_action( 'woocommerce_before_shipping_calculator', array( $this, 'hide_shipping_calculator_start' ) );
				add_action( 'woocommerce_after_shipping_calculator', array( $this, 'hide_shipping_calculator_end' ) );
			}
			
			include_once( dirname( __FILE__ ) . '/includes/class-wc-hips-payment-request.php' );
		}

		/**
		 * Allow this class and other classes to add slug keyed notices (to avoid duplication)
		 */
		public function add_admin_notice( $slug, $class, $message ) {
			$this->notices[ $slug ] = array(
				'class'   => $class,
				'message' => $message,
			);
		}

		/**
		 * The backup sanity check, in case the plugin is activated in a weird way,
		 * or the environment changes after activation. Also handles upgrade routines.
		 */
		public function check_environment() {
			if ( ! defined( 'IFRAME_REQUEST' ) && ( WC_HIPS_VERSION !== get_option( 'WC_HIPS_VERSION' ) ) ) {
				$this->install();

				do_action( 'woocommerce_hips_updated' );
			}

			$environment_warning = self::get_environment_warning();

			if ( $environment_warning && is_plugin_active( plugin_basename( __FILE__ ) ) ) {
				$this->add_admin_notice( 'bad_environment', 'error', $environment_warning );
			}

			// Check if secret key present. Otherwise prompt, via notice, to go to
			// setting.
			if ( ! class_exists( 'WC_HIPS_API' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-hips-api.php' );
			}

			$secret = WC_HIPS_API::get_secret_key();

			if ( empty( $secret ) && ! ( isset( $_GET['page'], $_GET['section'] ) && 'wc-settings' === $_GET['page'] && 'hips' === $_GET['section'] ) ) {
				$setting_link = $this->get_setting_link();
				$this->add_admin_notice( 'prompt_connect', 'notice notice-warning', sprintf( __( 'Hips is almost ready. To get started, <a href="%s">set your Hips account keys</a>.', 'woocommerce-gateway-hips' ), $setting_link ) );
			}
		}

		/**
		 * Updates the plugin version in db
		 *
		 * @since 1.0.7
		 * @version 1.1.4

		 * @return bool
		 */
		private static function _update_plugin_version() {
			delete_option( 'WC_HIPS_VERSION' );
			update_option( 'WC_HIPS_VERSION', WC_HIPS_VERSION );

			return true;
		}

		/**
		 * Dismiss the Google Payment Request API Feature notice.
		 *
		 * @since 1.0.7
		 * @version 1.1.4

		 */
		public function dismiss_request_api_notice() {
			update_option( 'wc_hips_show_request_api_notice', 'no' );
		}

		
		/**
		 * Handles upgrade routines.
		 *
		 * @since 1.0.7
		 * @version 1.1.4

		 */
		public function install() {
			if ( ! defined( 'WC_hips_INSTALLING' ) ) {
				define( 'WC_hips_INSTALLING', true );
			}
			
			$this->_update_plugin_version();
		}

		/**
		 * Checks the environment for compatibility problems.  Returns a string with the first incompatibility
		 * found or false if the environment has no problems.
		 */
		static function get_environment_warning() {
			if ( version_compare( phpversion(), WC_HIPS_MIN_PHP_VER, '<' ) ) {
				$message = __( 'WooCommerce Hips - The minimum PHP version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-hips' );

				return sprintf( $message, WC_HIPS_MIN_PHP_VER, phpversion() );
			}

			if ( ! defined( 'WC_VERSION' ) ) {
				return __( 'WooCommerce Hips requires WooCommerce to be activated to work.', 'woocommerce-gateway-hips' );
			}

			if ( version_compare( WC_VERSION, WC_HIPS_MIN_WC_VER, '<' ) ) {
				$message = __( 'WooCommerce Hips - The minimum WooCommerce version required for this plugin is %1$s. You are running %2$s.', 'woocommerce-gateway-hips' );

				return sprintf( $message, WC_HIPS_MIN_WC_VER, WC_VERSION );
			}

			if ( ! function_exists( 'curl_init' ) ) {
				return __( 'WooCommerce Hips - cURL is not installed.', 'woocommerce-gateway-hips' );
			}

			return false;
		}

		/**
		 * Adds plugin action links
		 *
		 * @since 1.0.5
		 */
		public function plugin_action_links( $links ) {
			$setting_link = $this->get_setting_link();

			$plugin_links = array(
				'<a href="' . $setting_link . '">' . __( 'Settings', 'woocommerce-gateway-hips' ) . '</a>',
				'<a href="https://static.hips.com/doc/api/">' . __( 'Docs', 'woocommerce-gateway-hips' ) . '</a>',
				'<a href="https://hips.com/gb/support">' . __( 'Support', 'woocommerce-gateway-hips' ) . '</a>',
			);
			return array_merge( $plugin_links, $links );
		}

		/**
		 * Get setting link.
		 *
		 * @version 1.1.4

		 *
		 * @return string Setting link
		 */
		public function get_setting_link() {

			$use_id_as_section = function_exists( 'WC' ) ? version_compare( WC()->version, '2.6', '>=' ) : false;
			$section_slug = $use_id_as_section ? 'hips' : strtolower( 'WC_Gateway_hips' );
			return admin_url( 'admin.php?page=wc-settings&tab=checkout&section=' . $section_slug );
		}

		/**
		 * Display any notices we've collected thus far (e.g. for connection, disconnection)
		 */
		public function admin_notices() {
			$show_request_api_notice = get_option( 'wc_hips_show_request_api_notice' );
			

			if ( empty( $show_request_api_notice ) ) {
				// @TODO remove this notice in the future.
				?>
				<div class="notice notice-warning wc-hips-request-api-notice is-dismissible"><p><?php esc_html_e( 'New Feature! hips now supports Google Payment Request. Your customers can now use mobile phones with supported browsers such as Chrome to make purchases easier and faster.', 'woocommerce-gateway-hips' ); ?></p></div>

				<script type="application/javascript">
					jQuery( '.wc-hips-request-api-notice' ).on( 'click', '.notice-dismiss', function() {
						var data = {
							action: 'hips_dismiss_request_api_notice'
						};

						jQuery.post( '<?php echo admin_url( 'admin-ajax.php' ); ?>', data );
					});
				</script>

				<?php
			}

			foreach ( (array) $this->notices as $notice_key => $notice ) {
				echo "<div class='" . esc_attr( $notice['class'] ) . "'><p>";
				echo wp_kses( $notice['message'], array( 'a' => array( 'href' => array() ) ) );
				echo '</p></div>';
			}
		}

		/**
		 * Initialize the gateway. Called very early - in the context of the plugins_loaded action
		 *
		 * @since 1.0.7
		 */
		public function init_gateways() {

			if ( class_exists( 'WC_Subscriptions_Order' ) && function_exists( 'wcs_create_renewal_order' ) ) {
				$this->subscription_support_enabled = true;
			}

			if ( class_exists( 'WC_Pre_Orders_Order' ) ) {
				$this->pre_order_enabled = true;
			}

			if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
				return;
			}

			if ( class_exists( 'WC_Payment_Gateway_CC' ) ) {
				include_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-hips.php' );
				include_once( dirname( __FILE__ ) . '/includes/shortcodes/class-wc-hips-shortcode-checkout.php' );
			} else {
				include_once( dirname( __FILE__ ) . '/includes/legacy/class-wc-gateway-hips.php' );				
			}
			
			load_plugin_textdomain( 'woocommerce-gateway-hips', false, plugin_basename( dirname( __FILE__ ) ) . '/languages' );
			add_filter( 'woocommerce_payment_gateways', array( $this, 'add_gateways' ) );

			$load_addons = (
				$this->subscription_support_enabled
				||
				$this->pre_order_enabled
			);

			if ( $load_addons ) {
				require_once( dirname( __FILE__ ) . '/includes/class-wc-gateway-hips-addons.php' );
			}
		}

		/**
		 * Add the gateways to WooCommerce
		 *
		 * @since 1.0.7
		 */
		public function add_gateways( $methods ) {

			if ( $this->subscription_support_enabled || $this->pre_order_enabled ) {
				$methods[] = 'WC_Gateway_hips_Addons';
			} else {
				$methods[] = 'WC_Gateway_hips';
			}
			return $methods;
		}

		/**
		 * List of currencies supported by hips that has no decimals.
		 *
		 * @return array $currencies
		 */
		public static function no_decimal_currencies() {
			return array(
				'bif', // Burundian Franc
				'djf', // Djiboutian Franc
				'jpy', // Japanese Yen
				'krw', // South Korean Won
				'pyg', // Paraguayan Guaraní
				'vnd', // Vietnamese Đồng
				'xaf', // Central African Cfa Franc
				'xpf', // Cfp Franc
				'clp', // Chilean Peso
				'gnf', // Guinean Franc
				'kmf', // Comorian Franc
				'mga', // Malagasy Ariary
				'rwf', // Rwandan Franc
				'vuv', // Vanuatu Vatu
				'xof', // West African Cfa Franc
			);
		}

		/**
		 * hips uses smallest denomination in currencies such as cents.
		 * We need to format the returned currency from hips into human readable form.
		 *
		 * @param object $balance_transaction
		 * @param string $type Type of number to format
		 */
		public static function format_number( $balance_transaction, $type = 'fee' ) {
			if ( ! is_object( $balance_transaction ) ) {
				return;
			}

			if ( in_array( strtolower( $balance_transaction->currency ), self::no_decimal_currencies() ) ) {
				if ( 'fee' === $type ) {
					return $balance_transaction->fee;
				}

				return $balance_transaction->net;
			}

			if ( 'fee' === $type ) {
				return number_format( $balance_transaction->fee / 100, 2, '.', '' );
			}

			return number_format( $balance_transaction->net / 100, 2, '.', '' );
		}

		/**
		 * Capture payment when the order is changed from on-hold to complete or processing
		 *
		 * @param  int $order_id
		 */

		public function capture_payment( $order_id ) {

			$order = wc_get_order( $order_id );

			if ( 'hips' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
				$payment   = get_post_meta( $order_id, '_hips_payment_id', true );
				$captured = get_post_meta( $order_id, '_hips_payment_captured', true );
				$hips_checkout = get_post_meta( $order_id, '_via_hips_checkout', true );
				$_hips_order_id = get_post_meta( $order_id, '_hips_order_id', true );
				
				if ( $payment && 'no' === $captured ) {
					// Capture Payment
					$result = WC_HIPS_API::request( array(
						'amount'   => $order->get_total() * 100,
						'expand[]' => 'balance_transaction',
					), 'payments/' . $payment . '/capture' );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to capture payment!', 'woocommerce-gateway-hips' ) . ' ' . $result->get_error_message() );
					} else {
						$order->add_order_note( sprintf( __( 'Hips payment complete (Payment ID: %s)', 'woocommerce-gateway-hips' ), $payment ) );
						update_post_meta( $order_id, '_hips_payment_captured', 'yes' );		
					
					}
				}
				else if ( $hips_checkout && 'no' === $captured ) {
					// Fullfill Order to take payment for the Orders placed via Hips Checkout
					$result = WC_HIPS_API::request( array(), 'orders/' . $_hips_order_id . '/fulfill' );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to Fullfill Order!', 'woocommerce-gateway-hips' ) . ' ' . $result->get_error_message() );
					} else {
						if($result->success){
							$order->add_order_note( sprintf( __( 'Hips payment complete (Payment ID: %s)', 'woocommerce-gateway-hips' ), $_hips_order_id ) );
							update_post_meta( $order_id, '_hips_payment_captured', 'yes' );							
						}else{
							$order->add_order_note( sprintf( __( 'Hips Order Fullfilment failed. (Reason: %s)', 'woocommerce-gateway-hips' ), $result->message ) );
						}
						
					
					}

				}
			}
		}

		/**
		 * Cancel pre-auth on refund/cancellation
		 *
		 * @param  int $order_id
		 */
		public function cancel_payment( $order_id ) {
			$order = wc_get_order( $order_id );

			if ( 'hips' === ( version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->payment_method : $order->get_payment_method() ) ) {
				$payment   = get_post_meta( $order_id, '_hips_payment_id', true );
				$_via_hips_checkout = get_post_meta( $order_id, $key = '_via_hips_checkout', $single = true );
				$_hips_order_id = get_post_meta( $order_id, '_hips_order_id', true ); 
				if ( $payment ) {
					$result = WC_HIPS_API::request( array(
						'amount' => $order->get_total() * 100,
					), 'payments/' . $payment . '/refund' );

					if ( is_wp_error( $result ) ) {
						$order->add_order_note( __( 'Unable to refund payment!', 'woocommerce-gateway-hips' ) . ' ' . $result->get_error_message() );
					} else {
						$order->add_order_note( sprintf( __( 'Hips payment refunded (Payment ID: %s)', 'woocommerce-gateway-hips' ), $result->id ) );
						delete_post_meta( $order_id, '_hips_payment_captured' );
						delete_post_meta( $order_id, '_hips_payment_id' );
					}
				}elseif ($_via_hips_checkout) {
					$body = array();
					$body['id']	= $_hips_order_id;
					//$body['amount']	= $order->get_total() * 100;
					$response = WC_HIPS_API::request( $body, 'orders/' . $_hips_order_id . '/revoke' ); print_r($response); 
					if ( is_wp_error( $response ) ) {
						$this->log( 'Error: ' . $response->get_error_message() );
						return $response;
					}else{
						$order->add_order_note( sprintf( __( 'Hips payment refunded (Payment ID: %s)', 'woocommerce-gateway-hips' ), $_hips_order_id ) );
						delete_post_meta( $order_id, '_hips_payment_captured' );						
					}
				}
			}
		}

		/**
		 * Gets saved tokens from API if they don't already exist in WooCommerce.
		 * @param array $tokens
		 * @return array
		 */
		public function woocommerce_get_customer_payment_tokens( $tokens, $customer_id, $gateway_id ) {
			if ( is_user_logged_in() && 'hips' === $gateway_id && class_exists( 'WC_Payment_Token_CC' ) ) {
				$hips_customer = new WC_hips_Customer( $customer_id );
				$hips_cards    = $hips_customer->get_cards();
				$stored_tokens   = array();

				foreach ( $tokens as $token ) {
					$stored_tokens[] = $token->get_token();
				}

				foreach ( $hips_cards as $card ) {
					if ( ! in_array( $card->id, $stored_tokens ) ) {
						$token = new WC_Payment_Token_CC();
						$token->set_token( $card->id );
						$token->set_gateway_id( 'hips' );
						$token->set_card_type( strtolower( $card->brand ) );
						$token->set_last4( $card->last4 );
						$token->set_expiry_month( $card->exp_month );
						$token->set_expiry_year( $card->exp_year );
						$token->set_user_id( $customer_id );
						$token->save();
						$tokens[ $token->get_id() ] = $token;
					}
				}
			}
			return $tokens;
		}

		/**
		 * Delete token from hips
		 */
		public function woocommerce_payment_token_deleted( $token_id, $token ) {
			if ( 'hips' === $token->get_gateway_id() ) {
				$hips_customer = new WC_hips_Customer( get_current_user_id() );
				$hips_customer->delete_card( $token->get_token() );
			}
		}

		/**
		 * Set as default in hips
		 */
		public function woocommerce_payment_token_set_default( $token_id ) {
			$token = WC_Payment_Tokens::get( $token_id );
			if ( 'hips' === $token->get_gateway_id() ) {
				$hips_customer = new WC_hips_Customer( get_current_user_id() );
				$hips_customer->set_default_card( $token->get_token() );
			}
		}

		/**
		 * Checks hips minimum order value authorized per currency
		 */
		public static function get_minimum_amount() {
			// Check order amount
			switch ( get_woocommerce_currency() ) {
				case 'USD':
				case 'CAD':
				case 'EUR':
				case 'CHF':
				case 'AUD':
				case 'SGD':
					$minimum_amount = 50;
					break;
				case 'GBP':
					$minimum_amount = 30;
					break;
				case 'DKK':
					$minimum_amount = 250;
					break;
				case 'NOK':
				case 'SEK':
					$minimum_amount = 300;
					break;
				case 'JPY':
					$minimum_amount = 5000;
					break;
				case 'MXN':
					$minimum_amount = 1000;
					break;
				case 'HKD':
					$minimum_amount = 400;
					break;
				default:
					$minimum_amount = 50;
					break;
			}

			return $minimum_amount;
		}

		/**
		 * Hide Shipping Calculator in Cart Page if Hips Checkout is enabled
		 *
		 * @version 1.1.4

		 * @since 1.0.7	 
		 * @return null
		*/
		public function hide_shipping_calculator_start() {
			
			echo '<div style="display:none;">';
			
		}

		/**
		 * Hide Shipping Calculator in Cart Page if Hips Checkout is enabled
		 *
		 * @version 1.1.4

		 * @since 1.0.7	 
		 * @return null
		*/
		public function hide_shipping_calculator_end() {
			
			echo '</div>';
			
		}

		/**
		 * Create a page for Hips Checkout and Webhook with shortcode
		 *
		 * @version 1.1.4

		 * @since 1.0.7	 
		 * @return null
		*/
		public function create_hips_checkout_page() { 

			$hips_checkout_page_id = get_option( 'hips_checkout_page_id' ); 
			$hips_webhook_page_id = get_option( 'hips_webhook_page_id' ); 

			$checkout =	get_post( $hips_checkout_page_id );

			if( empty( $checkout ) ){  
				$postarr = array(
					'post_content'	=>'[hips_checkout]',
					'post_title' 	=>__( 'Hips Checkout', 'woocommerce-gateway-hips' ),
					'post_status'	=> 'publish',
					'post_type'		=> 'page'
				);

				$page_id =	wp_insert_post( $postarr, $wp_error = false );	

				update_option( 'hips_checkout_page_id', $page_id );			
			}	  

			$webhook =	get_post( $hips_webhook_page_id );

			if( empty( $webhook ) ){  
				$webhook_arr = array(
					'post_content'	=>'[hips_webhook]',
					'post_title' 	=>__( 'Hips Webhook', 'woocommerce-gateway-hips' ),
					'post_status'	=> 'publish',
					'post_type'		=> 'page'
				);

				$webhook_page_id =	wp_insert_post( $webhook_arr, $wp_error = false );	

				update_option( 'hips_webhook_page_id', $webhook_page_id );			
			}     					
		}

		/**
		 * What rolls down stairs
		 * alone or in pairs,
		 * and over your neighbor's dog?
		 * What's great for a snack,
		 * And fits on your back?
		 * It's log, log, log
		 */
		public static function log( $message ) {
			if ( empty( self::$log ) ) {
				self::$log = new WC_Logger();
			}

			self::$log->add( 'woocommerce-gateway-hips', $message );
		}
	}

	$GLOBALS['wc_hips'] = WC_hips::get_instance();

endif;