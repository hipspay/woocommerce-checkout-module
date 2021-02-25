<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_hips class.
 *
 * @extends WC_Payment_Gateway
 */
class WC_Gateway_hips extends WC_Payment_Gateway_CC {

	/**
	 * Should we capture Credit cards
	 *
	 * @var bool
	 */
	public $capture;

	/**
	 * Should we fulfill the orders
	 *
	 * @var bool
	 */
	public $fulfill;

	/**
	 * Alternate credit card statement name
	 *
	 * @var bool
	 */
	public $statement_descriptor;

	/**
	 * Checkout enabled
	 *
	 * @var bool
	 */
	public $hips_checkout;

	/**
	 * Page for Hips checkout
	 *
	 * @var bool
	 */
	public $hips_checkout_page_id;

	/**
	 * Checkout Locale
	 *
	 * @var string
	 */
	public $hips_checkout_locale;

	/**
	 * Credit card image
	 *
	 * @var string
	 */
	public $hips_checkout_image;

	/**
	 * Should we store the users credit cards?
	 *
	 * @var bool
	 */
	public $saved_cards;

	/**
	 * API access secret key
	 *
	 * @var string
	 */
	public $secret_key;

	/**
	 * Api access publishable key
	 *
	 * @var string
	 */
	public $publishable_key;

	/**
	 * Do we accept bitcoin?
	 *
	 * @var bool
	 */
	public $bitcoin;

	/**
	 * Is test mode active?
	 *
	 * @var bool
	 */
	public $testmode;

	/**
	 * Logging enabled?
	 *
	 * @var bool
	 */
	public $logging;

	/**
	 * Endpoint
	 *
	 * @var string
	 */
	public $endpoint;

	/**
	 * Constructor
	 */
	public function __construct() {
		$this->id                   = 'hips';
		$this->method_title         = __( 'Hips', 'woocommerce-gateway-hips' );
		$this->method_description   = sprintf( __( 'Hips works by adding credit card fields on the checkout and then sending the details to Hips for verification. <a href="%1$s" target="_blank">Sign up</a> for a Hips account, and <a href="%2$s" target="_blank">get your Hips account keys</a>.', 'woocommerce-gateway-hips' ), 'https://dashboard.hips.com/register', 'https://dashboard.hips.com/account/apikeys' );
		$this->has_fields           = true;
		$this->view_transaction_url = 'https://dashboard.hips.com/payments/%s';
		$this->supports             = array(
			'subscriptions',
			'products',
			'refunds',
			'subscription_cancellation',
			'subscription_reactivation',
			'subscription_suspension',
			'subscription_amount_changes',
			'subscription_payment_method_change', // Subs 1.n compatibility.
			'subscription_payment_method_change_customer',
			'subscription_payment_method_change_admin',
			'subscription_date_changes',
			'multiple_subscriptions',
			'pre-orders',
			'tokenization',
			'add_payment_method',
		);

		// Load the form fields.
		$this->init_form_fields();


		// Load the settings.
		$this->init_settings();

		// Get setting values.
		$this->title                   	= $this->get_option( 'title' );
		$this->description             	= $this->get_option( 'description' );
		$this->enabled                 	= $this->get_option( 'enabled' );
		$this->testmode                	= 'yes' === $this->get_option( 'testmode' );
		$this->capture                 	= 'yes' === $this->get_option( 'capture', 'yes' );
		$this->fulfill                 	= 'yes' === $this->get_option( 'auto_fulfill', 'yes' );
		$this->statement_descriptor    	= $this->get_option( 'statement_descriptor', wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES ) );
		$this->hips_checkout         	= 'yes' === $this->get_option( 'hips_checkout' );
		$this->hips_checkout_page_id    = 'yes' === $this->get_option( 'hips_checkout_page_id' );
		$this->hips_checkout_locale  	= $this->get_option( 'hips_checkout_locale' );
		$this->hips_checkout_image  	= $this->get_option( 'hips_checkout_image', '' );
		$this->saved_cards             	= 'yes' === $this->get_option( 'saved_cards' );
		$this->secret_key              	= $this->testmode ? $this->get_option( 'test_secret_key' ) : $this->get_option( 'secret_key' );
		$this->publishable_key         	= $this->testmode ? $this->get_option( 'test_publishable_key' ) : $this->get_option( 'publishable_key' );
		$this->logging                 	= 'yes' === $this->get_option( 'logging' );
		

		if ( $this->testmode ) {
			$this->description .= '';
			$this->description  = trim( $this->description );
		}

		WC_HIPS_API::set_secret_key( $this->secret_key );

		// Hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'payment_scripts' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'admin_notices', array( $this, 'admin_notices' ) );
		add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
		add_filter( 'woocommerce_credit_card_form_fields' , array( $this, 'woocommerce_credit_card_form_fields' ), 10, 2 );

		if( $this->enabled=='yes' && $this->hips_checkout ){			
			add_filter( 'woocommerce_checkout_init', array( $this, 'redirect_to_hips_checkout_page' ) );
			add_action( 'woocommerce_shipping_init', array( $this, 'add_hips_shipping_method' ) );
			add_filter( 'woocommerce_shipping_methods', array( $this, 'set_hips_shipping_method' ) );
			add_filter( 'woocommerce_is_order_received_page', array( $this, 'filter_woocommerce_is_order_received_page' ), 10, 1 );
		}	
	}

	/**
	 * Get_icon function.
	 *
	 * @access public
	 * @return string
	 */
	public function get_icon() {
		$ext   = version_compare( WC()->version, '2.6', '>=' ) ? '.svg' : '.png';
		$style = version_compare( WC()->version, '2.6', '>=' ) ? 'style="margin-left: 0.3em"' : '';

		$icon  = '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/visa' . $ext ) . '" alt="Visa" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/mastercard' . $ext ) . '" alt="Mastercard" width="32" ' . $style . ' />';
		$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/amex' . $ext ) . '" alt="Amex" width="32" ' . $style . ' />';

		if ( 'USD' === get_woocommerce_currency() ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/discover' . $ext ) . '" alt="Discover" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/jcb' . $ext ) . '" alt="JCB" width="32" ' . $style . ' />';
			$icon .= '<img src="' . WC_HTTPS::force_https_url( WC()->plugin_url() . '/assets/images/icons/credit-cards/diners' . $ext ) . '" alt="Diners" width="32" ' . $style . ' />';
		}

		if ( $this->bitcoin && $this->hips_checkout ) {
			$icon .= '<img src="' . WC_HTTPS::force_https_url( plugins_url( '/assets/images/bitcoin' . $ext, WC_HIPS_MAIN_FILE ) ) . '" alt="Bitcoin" width="24" ' . $style . ' />';
		}

		return apply_filters( 'woocommerce_gateway_icon', $icon, $this->id );
	}

	/**
	 * Get hips amount to pay
	 *
	 * @param float  $total Amount due.
	 * @param string $currency Accepted currency.
	 *
	 * @return float|int
	 */

	public function get_hips_amount( $total, $currency = '' ) {
		if ( ! $currency ) {
			$currency = get_woocommerce_currency();
		}
		switch ( strtoupper( $currency ) ) {
			// Zero decimal currencies.
			case 'BIF' :
			case 'CLP' :
			case 'DJF' :
			case 'GNF' :
			case 'JPY' :
			case 'KMF' :
			case 'KRW' :
			case 'MGA' :
			case 'PYG' :
			case 'RWF' :
			case 'VND' :
			case 'VUV' :
			case 'XAF' :
			case 'XOF' :
			case 'XPF' :
				$total = absint( $total );
				break;
			default :
				$total = round( $total, 2 ) * 100; // In cents.
				break;
		}
		return $total;
	}

	/**
	 * Check if SSL is enabled and notify the user
	 */

	public function admin_notices() {
		if ( 'no' === $this->enabled ) {
			return;
		}

		// Show message if enabled and FORCE SSL is disabled and WordpressHTTPS plugin is not detected.
		/*if ( ( function_exists( 'get_woocommerce_currency' ) ) && ( get_woocommerce_currency() != 'SEK' ) ) {
			echo '<div class="error hips-ssl-message"><p>' . sprintf( __( 'Hips is enabled, but the currency is set as %s; Please ensure currency is set as SEK.  Hips will only work with the currency SEK.', 'woocommerce-gateway-hips' ), get_woocommerce_currency()  ) . '</p></div>';
		}*/
	}

	/**
	 * Check if this gateway is enabled
	 */
	public function is_available() {
		if ( 'yes' === $this->enabled ) {
			if ( ! $this->testmode && is_checkout() && ! is_ssl() ) {
				return false;
			}
			if ( ! $this->secret_key || ! $this->publishable_key ) {
				return false;
			}
			return true;
		}
		return false;
	}

	/**
	 * Initialise Gateway Settings Form Fields
	 */

	public function init_form_fields() {
		$this->form_fields = include( 'settings-hips.php' );
	}

	/**
	 * Payment form on checkout page
	 */

	public function payment_fields() {
		$user                 = wp_get_current_user();
		$display_tokenization = $this->supports( 'tokenization' ) && is_checkout() && $this->saved_cards;
		$total                = WC()->cart->total;

		// If paying from order, we need to get total from order not cart.
		if ( isset( $_GET['pay_for_order'] ) && ! empty( $_GET['key'] ) ) {
			$order = wc_get_order( wc_get_order_id_by_order_key( wc_clean( $_GET['key'] ) ) );
			$total = $order->get_total();
		}

		if ( $user->ID ) {
			$user_email = get_user_meta( $user->ID, 'billing_email', true );
			$user_email = $user_email ? $user_email : $user->user_email;
		} else {
			$user_email = '';
		}

		if ( is_add_payment_method_page() ) {
			$pay_button_text = __( 'Add Card', 'woocommerce-gateway-hips' );
			$total        		= '';
		} else {
			$pay_button_text = '';
		}

		echo '<div
			id="hips-payment-data"
			data-panel-label="' . esc_attr( $pay_button_text ) . '"
			data-description=""
			data-email="' . esc_attr( $user_email ) . '"
			data-amount="' . esc_attr( $this->get_hips_amount( $total ) ) . '"
			data-name="' . esc_attr( $this->statement_descriptor ) . '"
			data-currency="' . esc_attr( strtolower( get_woocommerce_currency() ) ) . '"
			data-image="' . esc_attr( $this->hips_checkout_image ) . '"
			data-bitcoin="' . esc_attr( $this->bitcoin ? 'true' : 'false' ) . '"
			data-locale="' . esc_attr( $this->hips_checkout_locale ? $this->hips_checkout_locale : 'en' ) . '"
			data-allow-remember-me="' . esc_attr( $this->saved_cards ? 'true' : 'false' ) . '">';

		if ( $this->description ) {
			echo apply_filters( 'wc_hips_description', wpautop( wp_kses_post( $this->description ) ) );
		}

		if ( $display_tokenization ) {
			$this->tokenization_script();
			$this->saved_payment_methods();
		}

		if ( ! $this->hips_checkout ) {
			$this->form();

			if ( apply_filters( 'wc_hips_display_save_payment_method_checkbox', $display_tokenization ) ) {
				$this->save_payment_method_checkbox();
			}
		}

		echo '</div>';
	}

	/**
	 * Localize hips messages based on code
	 *
	 * @since 1.0.7
	 * @version 1.1.4

	 * @return array
	 */

	public function get_localized_messages() {
		return apply_filters( 'wc_hips_localized_messages', array(
			'invalid_number'        => __( 'The card number is not a valid credit card number.', 'woocommerce-gateway-hips' ),
			'invalid_expiry_month'  => __( 'The card\'s expiration month is invalid.', 'woocommerce-gateway-hips' ),
			'invalid_expiry_year'   => __( 'The card\'s expiration year is invalid.', 'woocommerce-gateway-hips' ),
			'invalid_cvc'           => __( 'The card\'s security code is invalid.', 'woocommerce-gateway-hips' ),
			'incorrect_number'      => __( 'The card number is incorrect.', 'woocommerce-gateway-hips' ),
			'expired_card'          => __( 'The card has expired.', 'woocommerce-gateway-hips' ),
			'incorrect_cvc'         => __( 'The card\'s security code is incorrect.', 'woocommerce-gateway-hips' ),
			'incorrect_zip'         => __( 'The card\'s zip code failed validation.', 'woocommerce-gateway-hips' ),
			'card_declined'         => __( 'The card was declined.', 'woocommerce-gateway-hips' ),
			'missing'               => __( 'There is no card on a customer that is being charged.', 'woocommerce-gateway-hips' ),
			'processing_error'      => __( 'An error occurred while processing the card.', 'woocommerce-gateway-hips' ),
			'invalid_request_error' => __( 'Could not find payment information.', 'woocommerce-gateway-hips' ),
		) );
	}

	/**
	 * Load admin scripts.
	 *
	 * @since 1.0.7
	 * @version 1.1.4

	 */

	public function admin_scripts() {
		if ( 'woocommerce_page_wc-settings' !== get_current_screen()->id ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';

		wp_enqueue_script( 'woocommerce_hips_admin', plugins_url( 'assets/js/hips-admin' . $suffix . '.js', WC_HIPS_MAIN_FILE ), array(), WC_HIPS_VERSION, true );

		$hips_admin_params = array(
			'localized_messages' => array(
				'not_valid_live_key_msg' => __( 'This is not a valid live key. Live keys start with "sk_live_" and "pk_live_".', 'woocommerce-gateway-hips' ),
				'not_valid_test_key_msg' => __( 'This is not a valid test key. Test keys start with "sk_test_" and "pk_test_".', 'woocommerce-gateway-hips' ),
				're_verify_button_text'  => __( 'Re-verify Domain', 'woocommerce-gateway-hips' ),
				'missing_secret_key'     => __( 'Missing Secret Key. Please set the secret key field above and re-try.', 'woocommerce-gateway-hips' ),
			),
			'ajaxurl'            => admin_url( 'admin-ajax.php' ),			
		);

		wp_localize_script( 'woocommerce_hips_admin', 'wc_hips_admin_params', apply_filters( 'wc_hips_admin_params', $hips_admin_params ) );
	}

	/**
	 * payment_scripts function.
	 *
	 * Outputs scripts used for hips payment
	 *
	 * @access public
	 */

	public function payment_scripts() {
		if ( ! is_cart() && ! is_checkout() && ! isset( $_GET['pay_for_order'] ) && ! is_add_payment_method_page() ) {
			return;
		}

		$suffix = defined( 'SCRIPT_DEBUG' ) && SCRIPT_DEBUG ? '' : '.min';
		$suffix = '';
		
		
		if ( $this->hips_checkout ) {
			wp_enqueue_script( 'hips_checkout', 'https://checkout.hips.com/checkout.js', '', WC_HIPS_VERSION, true );
			wp_enqueue_script( 'woocommerce_hips', plugins_url( 'assets/js/hips-checkout' . $suffix . '.js', WC_HIPS_MAIN_FILE ), array( 'hips_checkout' ), WC_HIPS_VERSION, true );
		} else {
			wp_enqueue_script( 'hips', 'https://cdn.hips.com/js/v1/hips.js', '', '2.0', true );
			wp_enqueue_script( 'woocommerce_hips', plugins_url( 'assets/js/hips' . $suffix . '.js', WC_HIPS_MAIN_FILE ), array( 'jquery-payment', 'hips' ), filemtime(plugin_dir_path(WC_HIPS_MAIN_FILE )  . 'assets/js/hips' . $suffix . '.js' ), true );
		}

		$hips_params = array(
			'key'                  => $this->publishable_key,
			'i18n_terms'           => __( 'Please accept the terms and conditions first', 'woocommerce-gateway-hips' ),
			'i18n_required_fields' => __( 'Please fill in required checkout fields first', 'woocommerce-gateway-hips' ),
		);

		// If we're on the pay page we need to pass hips.js the address of the order.
		if ( isset( $_GET['pay_for_order'] ) && 'true' === $_GET['pay_for_order'] ) {
			$order_id = wc_get_order_id_by_order_key( urldecode( $_GET['key'] ) );
			$order    = wc_get_order( $order_id );

			$hips_params['billing_first_name'] = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name();
			$hips_params['billing_last_name']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name();
			$hips_params['billing_address_1']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1();
			$hips_params['billing_address_2']  = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_2 : $order->get_billing_address_2();
			$hips_params['billing_state']      = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_state : $order->get_billing_state();
			$hips_params['billing_city']       = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_city : $order->get_billing_city();
			$hips_params['billing_postcode']   = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode();
			$hips_params['billing_country']    = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_country : $order->get_billing_country();
		}

		$hips_params['no_prepaid_card_msg']                     = __( 'Sorry, we\'re not accepting prepaid cards at this time.', 'woocommerce-gateway-hips' );
		$hips_params['allow_prepaid_card']                      = apply_filters( 'wc_hips_allow_prepaid_card', true ) ? 'yes' : 'no';
		$hips_params['hips_checkout_require_billing_address'] = apply_filters( 'wc_hips_checkout_require_billing_address', false ) ? 'yes' : 'no';

		// merge localized messages to be use in JS
		$hips_params = array_merge( $hips_params, $this->get_localized_messages() );
		wp_localize_script( 'woocommerce_hips', 'wc_hips_params', apply_filters( 'wc_hips_params', $hips_params ) );
	}

	/**
	 * Generate the request for the payment.
	 * @param  WC_Order $order
	 * @param  object $source
	 * @return array()
	 */

	protected function generate_payment_request( $order, $source ) {

		$post_data = $metadata = array();
		$post_data['purchase_currency']	=  version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->get_order_currency() : $order->get_currency();
		$post_data['amount'] = $this->get_hips_amount( $order->get_total(), $post_data['purchase_currency'] );
		$post_data['description'] = sprintf( __( '%1$s - Order %2$s', 'woocommerce-gateway-hips' ), $this->statement_descriptor, $order->get_order_number() );
		$post_data['capture']     = $this->capture ? 'true' : 'false';
		$post_data['fulfill']     = $this->fulfill ? 'true' : 'false';

		$billing_email      	= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_email : $order->get_billing_email();
		$billing_first_name 	= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_first_name : $order->get_billing_first_name();
		$billing_last_name  	= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_last_name : $order->get_billing_last_name();
		$billing_address_1 		= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_1 : $order->get_billing_address_1();
		$billing_address_2  	= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_address_2 : $order->get_billing_address_2();
		$billing_postcode  		= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_postcode : $order->get_billing_postcode();
		$billing_country  		= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->billing_country : $order->get_billing_country();
		$customer_ip_address  	= version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->customer_ip_address : $order->get_customer_ip_address();

		if ( ! empty( $billing_email ) && apply_filters( 'wc_hips_send_hips_receipt', false ) ) {
			$post_data['receipt_email'] = $billing_email;
		}

		$post_data['expand[]']   					= 'balance_transaction';
		$post_data['customer']['email'] 			= sanitize_email( $billing_email );
		$post_data['customer']['name'] 				= sanitize_text_field( $billing_first_name ) . ' ' . sanitize_text_field( $billing_last_name );
		$post_data['customer']['street_address'] 	= sanitize_text_field( $billing_address_1 ) . ', ' . sanitize_text_field( $billing_address_2 );
		$post_data['customer']['postal_code']		= sanitize_text_field( $billing_postcode );
		$post_data['customer']['country'] 			= sanitize_text_field( $billing_country );
		$post_data['customer']['ip_address'] 		= sanitize_text_field( $customer_ip_address );
		$post_data['metadata'] 						= apply_filters( 'wc_hips_payment_metadata', $metadata, $order, $source );
		$post_data['order_id'] 						= $order->get_id();
		$post_data['vat_amount'] 					= $order->get_total_tax();

		if ( $source->source ) {
			$post_data['source'] 					= $source->source;
		}

		if ( $source->card_token ) {
			$post_data['card_token'] 				= $source->card_token;
		}

		$order_key 									= $order->get_order_key();	 
		$order_id 									= $order->get_id();	 
		$post_data['hooks']['user_return_url_on_success'] 	= esc_url( wc_get_checkout_url() ) . 'order-received/' . $order_id . '/?key=' . $order_key;
		$post_data['hooks']['user_return_url_on_fail'] 		= esc_url( wc_get_checkout_url() );

		$hips_webhook_page_id = get_option( 'hips_webhook_page_id' );
		$post_data['hooks']['webhook_url'] = get_permalink( $hips_webhook_page_id );

		/**
		 * Filter the return value of the WC_Payment_Gateway_CC::generate_payment_request.
		 *
		 * @since 1.0.7
		 * @param array $post_data
		 * @param WC_Order $order
		 * @param object $source
		 */
		return apply_filters( 'wc_hips_generate_payment_request', $post_data, $order, $source );
	}

	/**
	 * Get payment source. This can be a new token or existing card.
	 *
	 * @param string $user_id
	 * @param bool  $force_customer Should we force customer creation.
	 *
	 * @throws Exception When card was not added or for and invalid card.
	 * @return object
	 */

	protected function get_source( $user_id, $force_customer = false ) {

		$hips_customer 		= new WC_hips_Customer( $user_id );
		$force_customer  	= apply_filters( 'wc_hips_force_customer_creation', $force_customer, $hips_customer );
		$hips_source   		= false;
		$token_id        	= false;

		// New CC info was entered and we have a new token to process
		if ( isset( $_POST['hips_token'] ) ) {
			$hips_token     = wc_clean( $_POST['hips_token'] );
			$maybe_saved_card = isset( $_POST['wc-hips-new-payment-method'] ) && ! empty( $_POST['wc-hips-new-payment-method'] );

			// This is true if the user wants to store the card to their account.
			if ( ( $user_id && $this->saved_cards && $maybe_saved_card ) || $force_customer ) {
				$hips_source = $hips_customer->add_card( $hips_token );

				if ( is_wp_error( $hips_source ) ) {
					throw new Exception( $hips_source->get_error_message() );
				}
			} else {
				// Not saving token, so don't define customer either.
				$hips_source   = $hips_token;
				$hips_customer = false;
			}
		} elseif ( isset( $_POST['wc-hips-payment-token'] ) && 'new' !== $_POST['wc-hips-payment-token'] ) {
			// Use an existing token, and then process the payment

			$token_id = wc_clean( $_POST['wc-hips-payment-token'] );
			$token    = WC_Payment_Tokens::get( $token_id );

			if ( ! $token || $token->get_user_id() !== get_current_user_id() ) {
				WC()->session->set( 'refresh_totals', true );
				throw new Exception( __( 'Invalid payment method. Please input a new card number.', 'woocommerce-gateway-hips' ) );
			}

			$hips_source = $token->get_token();
		}

		return (object) array(
			'card_token' 	=> $hips_source,			
			'source'   		=> 'card_token',
		);
	}

	/**
	 * Get payment source from an order. This could be used in the future for
	 * a subscription as an example, therefore using the current user ID would
	 * not work - the customer won't be logged in :)
	 *
	 * Not using 2.6 tokens for this part since we need a customer AND a card
	 * token, and not just one.
	 *
	 * @param object $order
	 * @return object
	 */
	protected function get_order_source( $order = null ) {

		$hips_customer = new WC_hips_Customer();
		$hips_source   = false;
		$token_id        = false;

		if ( $order ) {
			$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

			if ( $meta_value = get_post_meta( $order_id, '_hips_customer_id', true ) ) {
				$hips_customer->set_id( $meta_value );
			}

			if ( $meta_value = get_post_meta( $order_id, '_hips_card_id', true ) ) {
				$hips_source = $meta_value;
			}
		}

		return (object) array(
			'token_id' => $token_id,
			'customer' => $hips_customer ? $hips_customer->get_id() : false,
			'source'   => $hips_source,
		);
	}

	/**
	 * Process the payment
	 *
	 * @param int  $order_id Reference.
	 * @param bool $retry Should we retry on fail.
	 * @param bool $force_customer Force user creation.
	 *
	 * @throws Exception If payment will not be accepted.
	 *
	 * @return array|void
	 */

	public function process_payment( $order_id, $retry = true, $force_customer = false ) {
		try {
			$order  = wc_get_order( $order_id );

			$source = $this->get_source( get_current_user_id(), $force_customer );
			
			if ( empty( $source->source ) && empty( $source->card_token ) ) {
				$error_msg = __( 'Please enter your card details to make a payment.', 'woocommerce-gateway-hips' );
				$error_msg .= ' ' . __( 'Developers: Please make sure that you are including jQuery and there are no JavaScript errors on the page.', 'woocommerce-gateway-hips' );
				throw new Exception( $error_msg );
			}

			// Store source to order meta.
			$this->save_source( $order, $source );

			// Result from hips API request.
			$response = null;

			// Handle payment.
			if ( $order->get_total() > 0 ) {

				if ( $order->get_total() * 100 < WC_hips::get_minimum_amount() ) {
					throw new Exception( sprintf( __( 'Sorry, the minimum allowed order total is %1$s to use this payment method.', 'woocommerce-gateway-hips' ), wc_price( WC_hips::get_minimum_amount() / 100 ) ) );
				}

				$this->log( "Info: Begin processing payment for order $order_id for the amount of {$order->get_total()}" );
				
				// Make the request.
				$response = WC_HIPS_API::request( $this->generate_payment_request( $order, $source ) );

				if ( is_wp_error( $response ) ) {
					
					if ( 'source' === $response->get_error_code() && $source->card_token ) {
						$token = WC_Payment_Tokens::get( $source->card_token );
						$token->delete();
						$message = __( 'This card is no longer available and has been removed.', 'woocommerce-gateway-hips' );
						$order->add_order_note( $message );
						throw new Exception( $message );
					}

					$localized_messages = $this->get_localized_messages();

					$message = isset( $localized_messages[ $response->get_error_code() ] ) ? $localized_messages[ $response->get_error_code() ] : $response->get_error_message();

					$order->add_order_note( $message );

					throw new Exception( $message );
				}

				$this->log( 'Processing response: ' . print_r( $response, true ) );
				// Redirects user to 3d secure
				if( isset( $response->preflight ) && ( $response->preflight->status == 'require_3ds' ) && $response->preflight->requires_redirect ){
					// Return thank you page redirect.
					return array(
						'result'   => 'success',
						'redirect' => $response->preflight->redirect_user_to_url,
					);					
				}

				// Process valid response.
				$this->process_response( $response, $order );
			} else {
				$order->payment_complete();
			}
			
			if ( $response->status == 'failed') {
				return array(
					'result'   => 'fail',
					'redirect' => '',
				);
			} else {

				// Remove cart.
				WC()->cart->empty_cart();

				do_action( 'wc_gateway_hips_process_payment', $response, $order );

				// Return thank you page redirect.
				return array(
					'result'   => 'success',
					'redirect' => $this->get_return_url( $order ),
				);
			}

		} catch ( Exception $e ) {
			wc_add_notice( $e->getMessage(), 'error' );
			$this->log( sprintf( __( 'Error: %s', 'woocommerce-gateway-hips' ), $e->getMessage() ) );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				$this->send_failed_order_email( $order_id );
			}

			do_action( 'wc_gateway_hips_process_payment_error', $e, $order );

			return array(
				'result'   => 'fail',
				'redirect' => '',
			);
		}
	}

	/**
	 * Save source to order.
	 *
	 * @param WC_Order $order For to which the source applies.
	 * @param stdClass $source Source information.
	 */

	protected function save_source( $order, $source ) {
		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

	
		if ( $source->source ) {
			version_compare( WC_VERSION, '3.0.0', '<' ) ? update_post_meta( $order_id, '_hips_card_id', $source->source ) : $order->update_meta_data( '_hips_card_id', $source->source );
		}

		if ( is_callable( array( $order, 'save' ) ) ) {
			$order->save();
		}
	}

	/**
	 * Store extra meta data for an order from a hips Response.
	 */

	public function process_response( $response, $order ) {
		$this->log( 'Processing response test: ' . print_r( $response, true ) );

		$order_id = version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->id : $order->get_id();

		// Store Payment data
		update_post_meta( $order_id, '_hips_payment_id', $response->id );
		update_post_meta( $order_id, '_hips_order_id', $response->order->id );
		update_post_meta( $order_id, '_hips_payment_captured', $response->status == 'successful' ? 'yes' : 'no' );
		
	
		if ( $response->status == 'successful') {
			
			//$order->payment_complete( $response->id );
			$order->update_status( 'on-hold', sprintf( __( 'Order status changed from %s to On Hold.', 'woocommerce-gateway-hips' ), 'Pending' ) );
			$message = sprintf( __( 'Hips payment complete (Payment ID: %s)', 'woocommerce-gateway-hips' ), $response->id );
			$order->add_order_note( $message );
			$this->log( 'Success: ' . $message );

			if( 'yes' == $this->fulfill )
					$order->update_status( 'wc-completed', sprintf( __( 'Order status changed from %s to Completed.', 'woocommerce-gateway-hips' ), 'Processing' ) );

		} else {
			update_post_meta( $order_id, '_transaction_id', $response->id, true );

			if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
				version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );
			}

			/*$order->update_status( 'on-hold', sprintf( __( 'Hips payment %s (Payment ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-hips' ), $response->status , $response->id ) );
			$this->log( "Successful auth: $response->id" );*/
		}

		do_action( 'wc_gateway_hips_process_response', $response, $order );

		return $response;
	}

	/**
	 * Add payment method via account screen.
	 * We don't store the token locally, but to the hips API.
	 * @since 1.0.7
	 */

	public function add_payment_method() {
		if ( empty( $_POST['hips_token'] ) || ! is_user_logged_in() ) {
			wc_add_notice( __( 'There was a problem adding the card.', 'woocommerce-gateway-hips' ), 'error' );
			return;
		}

		$hips_customer = new WC_hips_Customer( get_current_user_id() );
		$card            = $hips_customer->add_card( wc_clean( $_POST['hips_token'] ) );

		if ( is_wp_error( $card ) ) {
			$localized_messages = $this->get_localized_messages();
			$error_msg = __( 'There was a problem adding the card.', 'woocommerce-gateway-hips' );

			// loop through the errors to find matching localized message
			foreach ( $card->errors as $error => $msg ) {
				if ( isset( $localized_messages[ $error ] ) ) {
					$error_msg = $localized_messages[ $error ];
				}
			}

			wc_add_notice( $error_msg, 'error' );
			return;
		}

		return array(
			'result'   => 'success',
			'redirect' => wc_get_endpoint_url( 'payment-methods' ),
		);
	}

	/**
	 * Refund a Payment
	 * @param  int $order_id
	 * @param  float $amount
	 * @return bool
	 */
	public function process_refund( $order_id, $amount = null, $reason = '' ) {
		$order = wc_get_order( $order_id );

		if ( ! $order || ! $order->get_transaction_id() ) {
			return false;
		}

		$body = array();
		$body['id'] = get_post_meta( $order_id, $key = '_hips_order_id', $single = true );
		$_via_hips_checkout = get_post_meta( $order_id, $key = '_via_hips_checkout', $single = true );	

		if ( ! is_null( $amount ) ) {
			$body['amount']	= $this->get_hips_amount( $amount );
		}

		if ( $reason ) {
			$body['description'] = $reason;			
		}

		$this->log( "Info: Beginning refund for order $order_id for the amount of {$amount}" ); 
		if($_via_hips_checkout){
			$response = WC_HIPS_API::request( $body, 'orders/' . $body['id'] . '/revoke' );
		}else{
			$response = WC_HIPS_API::request( $body, 'payments/' . $order->get_transaction_id() . '/refund' );
		}

		if ( is_wp_error( $response ) ) {
			$this->log( 'Error: ' . $response->get_error_message() );
			return $response;
		}
		else{

			if ( $_via_hips_checkout ) {
				$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-hips' ), wc_price( $body['amount'] / 100 ), $body['id'], $reason );
				
				$order->add_order_note( $refund_message );
				$this->log( 'Success: ' . html_entity_decode( strip_tags( $refund_message ) ) );
				return true;
			}
			else if( ($response->status == 'successful' ) &&  ( ! empty( $response->id ) ) ) {
				$refund_message = sprintf( __( 'Refunded %1$s - Refund ID: %2$s - Reason: %3$s', 'woocommerce-gateway-hips' ), wc_price( $response->amount / 100 ), $response->id, $reason );
				$order->add_order_note( $refund_message );
				$this->log( 'Success: ' . html_entity_decode( strip_tags( $refund_message ) ) );
				return true;
			}
		}		

	}

	/**
	 * Sends the failed order email to admin
	 *
	 * @version 1.1.4

	 * @since 1.0.7
	 * @param int $order_id
	 * @return null
	 */
	public function send_failed_order_email( $order_id ) {
		$emails = WC()->mailer()->get_emails();
		if ( ! empty( $emails ) && ! empty( $order_id ) ) {
			$emails['WC_Email_Failed_Order']->trigger( $order_id );
		}
	}

	/**
	 * Redirect to Hips Checkout page instead of default Checkout page if Hips Checkout is enabled
	 *
	 * @version 1.1.4

	 * @since 1.0.7	 
	 * @return null
	 */
	public function redirect_to_hips_checkout_page() {
		if ( ! is_admin() ) {
			$hips_checkout_page_id = get_option( 'hips_checkout_page_id' );
			wp_safe_redirect( get_permalink( $hips_checkout_page_id ) );
			exit();
		}			
	}

	/**
	 * Set Custom Shipping Method for Hips Checkout 
	 *
	 * @version 1.1.4

	 * @since 1.0.7 
	 * @return null
	*/
	public function add_hips_shipping_method($methods) {
		
		include_once( dirname( __FILE__ ) . '/includes/class-hips-shipping-method.php' );		
			
	}

	/**
	 * Set Custom Shipping Method for Hips Checkout 
	 *
	 * @version 1.1.4

	 * @since 1.0.7	 
	 * @return null
	*/
	public function set_hips_shipping_method($methods) {
		
		$methods[] = 'hips_shipping_method';
       	return $methods;			
		
	}

	/**
	 * Returns in Hips order recieved page 
	 *
	 * @version 1.1.4

	 * @since 1.0.7	 
	 * @return null
	*/
	function filter_woocommerce_is_order_received_page( $is_checkout_order_received ) { 

		$hips_checkout_page_id = get_option( 'hips_checkout_page_id' ); 
		
		if( is_page( $hips_checkout_page_id ) && isset( $_GET['hips-order-key-success'] ) ) 
			return true;
	    else 
	    	return $is_checkout_order_received;     
	}

	/**
	 * Set Custom Credit Card Fields for Hips Checkout 
	 *
	 * @version 1.1.4

	 * @since 1.0.7 
	 * @return null
	*/
	public function woocommerce_credit_card_form_fields($default_fields, $id) {
		
		$default_fields['card-expiry-month'] = '<p class="form-row form-row-wide" style="display:none;" >
				<label for="' . esc_attr( $id ) . '-card-expiry-month">' . esc_html__( 'Expiry Month', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input data-hips-tokenizer="exp_month" type="hidden" id="' . esc_attr( $id ) . '-card-expiry-month" class="input-text wc-credit-card-form-card-expiry-month" inputmode="numeric" autocomplete="cc-expiry-month" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'expiry-month' ) . ' />
			</p>';

		$default_fields['card-expiry-year'] = '<p class="form-row form-row-wide" style="display:none;" >
				<label for="' . esc_attr( $id ) . '-card-expiry-year">' . esc_html__( 'Expiry Year', 'woocommerce' ) . ' <span class="required">*</span></label>
				<input data-hips-tokenizer="exp_year" type="hidden" id="' . esc_attr( $id ) . '-card-expiry-year" class="input-text wc-credit-card-form-card-expiry-year" inputmode="numeric" autocomplete="cc-expiry-year" autocorrect="no" autocapitalize="no" spellcheck="no" type="tel" placeholder="&bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull; &bull;&bull;&bull;&bull;" ' . $this->field_name( 'expiry-year' ) . ' />				
			</p>';

		$default_fields['card-number-field'] =	str_replace('id="'.esc_attr( $id ).'-card-number"', 'id="'.esc_attr( $id ).'-card-number" data-hips-tokenizer="number" ', $default_fields['card-number-field']);

		$default_fields['card-cvc-field'] =	str_replace('id="'.esc_attr( $id ).'-card-cvc"', 'id="'.esc_attr( $id ).'-card-cvc" data-hips-tokenizer="cvc" ', $default_fields['card-cvc-field']);
		
		return $default_fields;			
	}
		
	/**
	 * Logs
	 *
	 * @since 1.0.7
	 * @version 1.1.4

	 *
	 * @param string $message
	 */
	public function log( $message ) {
		if ( $this->logging ) {
			WC_hips::log( $message );
		}
	}
}