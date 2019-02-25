<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Checkout Shortcode
 *
 * Used on the checkout page, the checkout shortcode displays the checkout process.
 *
 * @author 		WooThemes
 * @category 	Shortcodes
 * @package 	WooCommerce/Shortcodes/Checkout
 * @version     1.1.4

 */
class WC_Hips_Shortcode_Checkout {

	protected function __construct() {
		add_action( 'init', array( $this, 'hips_webhook_callback' ) );
	}

	/**
	 * Get the shortcode content.
	 *
	 * @param array $atts
	 * @return string
	 */
	public static function get( $atts ) {
		return WC_Shortcodes::shortcode_wrapper( array( __CLASS__, 'output' ), $atts );
	}

	/**
	 * Output the shortcode.
	 *
	 * @param array $atts
	 */
	public static function output( $atts ) {
		global $wp;

		// Check cart class is loaded or abort
		if ( is_null( WC()->cart ) ) {
			return;
		}

		// Backwards compat with old pay and thanks link arguments
		if ( isset( $_REQUEST['order'] ) && isset( $_REQUEST['key'] ) ) {
			wc_deprecated_argument( __CLASS__ . '->' . __FUNCTION__, '2.1', '"order" is no longer used to pass an order ID. Use the order-pay or order-received endpoint instead.' );

			// Get the order to work out what we are showing
			$order_id = absint( $_REQUEST['order'] );
			$order    = wc_get_order( $order_id );

			if ( $order && $order->has_status( 'pending' ) ) {
				$wp->query_vars['order-pay'] = absint( $_REQUEST['order'] );
			} else {
				$wp->query_vars['order-received'] = absint( $_REQUEST['order'] );
			}
		}

		// Handle checkout actions
		if ( isset( $wp->query_vars['order-received'] ) ) {
			self::order_received( $wp->query_vars['order-received'] );
		} else {
			self::checkout();
		}
	}


	/**
	 * Show the thanks page.
	 *
	 * @param int $order_id
	 */
	private static function order_received( $order_id = 0 ) {

		wc_print_notices();
		$order = false;

		// Get the order
		$order_id  = apply_filters( 'woocommerce_thankyou_order_id', absint( $order_id ) );
		$order_key = apply_filters( 'woocommerce_thankyou_order_key', WC()->session->get( 'hips_order_key' ) );
		
		if ( $order_id > 0 ) {
			$order = wc_get_order( $order_id );
			if ( ! $order ) {
				$order = false;
			}
		}

		// Empty awaiting payment session
		unset( WC()->session->order_awaiting_payment );
		unset( WC()->session->order_id );
		unset( WC()->session->hips_order_id );
		unset( WC()->session->hips_order_key );

		// Empty current cart
		WC()->cart->empty_cart();

		wc_get_template( 'checkout/thankyou.php', array( 'order' => $order ) );
	}

	/**
	 * Show the checkout.
	 */
	private static function checkout() {

		// Show non-cart errors
		wc_print_notices();
		
		// Check cart has contents
		if ( WC()->cart->is_empty() && empty( $_GET['hips-order-key-success'] ) ) {
			return;
		}

		// Check cart contents for errors
		do_action( 'woocommerce_check_cart_items' ); 

		// Calc totals
		WC()->cart->calculate_totals();  
		
		if ( empty( $_POST ) && wc_notice_count( 'error' ) > 0 ) { 

			wc_get_template( 'checkout/cart-errors.php', array( 'checkout' => $checkout ) );

		} else {

			$non_js_checkout = ! empty( $_POST['woocommerce_checkout_update_totals'] ) ? true : false; 

			if ( wc_notice_count( 'error' ) == 0 && $non_js_checkout ) {
				wc_add_notice( __( 'The order totals have been updated. Please confirm your order by pressing the "Place order" button at the bottom of the page.', 'woocommerce' ) );
			}

			if( isset( $_GET['hips-order-key-success'] ) && ! empty( $_GET['hips-order-key-success'] ) ) {

				$order_key = wc_clean( $_GET['hips-order-key-success'] ); 
				$order_id = wc_get_order_id_by_order_key( $order_key );
				self::order_received( $order_id );
			} else {
				$request = self::process_checkout();
				// Make the request.
				$response = WC_HIPS_API::call( $request, 'orders' );

				if( ! empty( $response->error ) ){					
					
					$message = $response->error;
					if( is_object( $response->error ) ){
						$message = $response->error->message;
					}

					self::log( 'Hips API Error', $message );					
				} else {
					WC()->session->set( 'hips_order_id', $response->id );
					echo $response->html_snippet;
				}
			}
		}
	}

	public static function set_shipping_method( $order, $shipping ) {

		$order_id = $order->get_id();
		$item_id = wc_add_order_item( $order_id, array(
                    'order_item_name'       => $shipping->name,
                    'order_item_type'       => 'shipping'
            ) );

        if ( $item_id ) {
        	$shipping_fee = ( $shipping->fee ) / 100;
        	$shipping_vat = ( $shipping->vat ) / 100;
        	$shipping_fee = $shipping_fee - $shipping_vat;
            wc_add_order_item_meta( $item_id, 'method_id', 'hips_shipping_method' );
            wc_add_order_item_meta( $item_id, 'cost', $shipping_fee );

            /* Shipping Fee returned from Hips is including Vat */
            wc_add_order_item_meta( $item_id, 'total_tax', $shipping_vat );
        }

        $order->calculate_totals();
	}

	public static function set_coupon( $order, $coupon ) {

		$order_id = $coupon['order_id'];

		if ( version_compare( WOOCOMMERCE_VERSION, '3.0' ) < 0 ) {
            $item_id = $order->add_coupon( $coupon['coupon_code'], $coupon['coupon_amount'], $coupon['coupon_tax'] );
        } else {
            $item = new WC_Order_Item_Coupon();
            $item->set_props( array(
                'code'         => $coupon['coupon_code'],
                'discount'     => $coupon['coupon_amount'],
                'discount_tax' => $coupon['coupon_tax'],
                'order_id'     => $order_id,
            ) );
            // $item_id = $item->save();
            $coupon_data = new WC_Coupon( $coupon['coupon_code'] );
            $item->add_meta_data( 'coupon_data', $coupon_data->get_data() );

            /**
			 * Action hook to adjust item before save.
			 * @since 3.0.0
			 */
			do_action( 'woocommerce_checkout_create_order_coupon_item', $item, $coupon['coupon_code'], $coupon_data, $order );

			// Add item to order and save.
			$order->add_item( $item );
        }

        update_post_meta( $order_id, '_cart_discount', $coupon['coupon_amount'] );
		update_post_meta( $order_id, '_cart_discount_tax', $coupon['coupon_tax'] );
	}

	public static function process_response( $response ) {

		if( ! empty( $response ) ) {
			$_hips_order_id = $response->resource->id;
			
			$args = array(
				'post_type' => 'shop_order',
				'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed'),
				'meta_key'   => '_hips_order_id',
				'meta_value' => $response->resource->merchant_reference->order_id
			);
			$posts = new WP_Query( $args );
			if( ! $posts->have_posts() ) {
				$coupon = $items_data = array();
				$order = wc_create_order( array( 'status' => 'wc-pending' ) );
				$order_id = $order->get_id();

				if( ! empty( $response->resource->cart->items ) ) {
					foreach ( $response->resource->cart->items as $key => $value ) {
						if( 'discount' == $value->type ) {
							$coupon = array(
								'coupon_code' => $value->name, 
								'coupon_amount' => wc_format_decimal( absint( $value->price ) / 100 ), 
								'coupon_tax' => wc_format_decimal( $value->tax / 100 ), 
								'order_id' => $order_id, 
							);					
						}					
					}
				}

				if( $response->resource->status ) {
								
					update_post_meta( $order_id, '_hips_order_response', $response );
					update_post_meta( $order_id, '_hips_payment_id', $response->resource->id );
					update_post_meta( $order_id, '_hips_order_id', $response->resource->merchant_reference->order_id );
					
					if( ! empty( $response->resource->billing_address ) ) {

						// Update Billing, Shipping, Payment Details 
						$order_billing_props = array(					
							
							'billing_first_name'   => $response->resource->billing_address->given_name,
							'billing_last_name'    => $response->resource->billing_address->family_name,
							'billing_company'      => $response->resource->billing_address->company_name,
							'billing_address_1'    => $response->resource->billing_address->street_address,
							'billing_address_2'    => $response->resource->billing_address->street_number,
							'billing_city'         => $response->resource->billing_address->city,
							'billing_postcode'     => $response->resource->billing_address->postal_code,
							'billing_country'      => $response->resource->billing_address->country,
							'billing_email'        => $response->resource->billing_address->email,
							'billing_phone'        => $response->resource->billing_address->phone_mobile,								
						);
					}			

					if( $response->resource->shipping_address->is_billing ){
						$order_shipping_props = array(						
						
							'shipping_first_name'  => $response->resource->billing_address->given_name,
							'shipping_last_name'   => $response->resource->billing_address->family_name,
							'shipping_company'     => $response->resource->billing_address->company_name,
							'shipping_address_1'   => $response->resource->billing_address->street_address,
							'shipping_address_2'   => $response->resource->billing_address->street_number,
							'shipping_city'        => $response->resource->billing_address->city,
							'shipping_postcode'    => $response->resource->billing_address->postal_code,
							'shipping_country'     => $response->resource->billing_address->country,				
						);

					} else {
						$order_shipping_props = array(						
						
							'shipping_first_name'  => $response->resource->shipping_address->given_name,
							'shipping_last_name'   => $response->resource->shipping_address->family_name,
							'shipping_company'     => $response->resource->shipping_address->company_name,
							'shipping_address_1'   => $response->resource->shipping_address->street_address,
							'shipping_address_2'   => $response->resource->shipping_address->street_number,
							'shipping_city'        => $response->resource->shipping_address->city,
							'shipping_postcode'    => $response->resource->shipping_address->postal_code,
							'shipping_country'     => $response->resource->shipping_address->country,
						);
					}

					$order_payment_props =	array(						
						
						'payment_method'       => 'hips',
						'payment_method_title' => 'Hips',
						'transaction_id'       => $_hips_order_id,							
						'created_via'          => 'checkout',							
					);

					$order_props = array_merge( $order_billing_props, $order_shipping_props, $order_payment_props );
					$customer_id =	self::create_customer( $order_billing_props );

					if( ! empty( $order_props ) ) {
						foreach ( $order_props as $key => $value ) {
							if ( is_callable( array( $order, "set_{$key}" ) ) ) {
								$order->{"set_{$key}"}( $value );

							// Store custom fields prefixed with wither shipping_ or billing_. This is for backwards compatibility with 2.6.x.
							} elseif ( 0 === stripos( $key, 'billing_' ) || 0 === stripos( $key, 'shipping_' ) ) {
								$order->update_meta_data( '_' . $key, $value );
							}
						}
					}

					$cart = json_decode( $response->resource->merchant_reference->meta_data_1 );

					if( ! empty( $cart ) ) {
						foreach ( $cart->cart_contents as $cart_item_key => $values ) {
							
							$product_id = $values->product_id;
							$variation_id = $values->variation_id; 

							if( ! empty( $product_id ) ) {
								$product = wc_get_product( $product_id );	
								
								if( ! empty( $product ) ) {						
									$item_id = wc_add_order_item( $order_id, array(
											'order_item_name'       => $product->get_name(),
											'order_item_type'       => 'line_item'
									) );

									// Add line item meta.
									if ( $item_id ) {
										wc_add_order_item_meta( $item_id, '_qty', absint( $values->quantity ) );
										wc_add_order_item_meta( $item_id, '_product_id', $product_id );
										wc_add_order_item_meta( $item_id, '_variation_id', $variation_id );
										wc_add_order_item_meta( $item_id, '_line_subtotal', $values->line_subtotal );
										wc_add_order_item_meta( $item_id, '_line_subtotal_tax', $values->line_subtotal_tax );
										wc_add_order_item_meta( $item_id, '_line_total', $values->line_total );
										wc_add_order_item_meta( $item_id, '_line_tax', $values->line_tax );	
									}
								}
							}						
						}	
					}

					// Process Order received
					if( $response->resource->require_shipping && ! empty( $response->resource->shipping ) ){  
						self::set_shipping_method( $order, $response->resource->shipping );
					}

					if( ! empty( $coupon ) ) {  
						self::set_coupon( $order, $coupon );
					}

					update_post_meta( $order_id, '_via_hips_checkout', 'yes' ); 
					$hips_settings = get_option( 'woocommerce_Hips_settings' );
					update_post_meta( $order_id, '_hips_payment_captured', $hips_settings['capture'] );				
					$order->set_order_key( 'wc_order_' . $response->resource->merchant_reference->order_id );
					$order->calculate_totals();
					$order->save();

					if( $hips_settings['capture'] == 'no' ){
						update_post_meta( $order_id, '_transaction_id', $response->resource->id, true );
						
						if ( $order->has_status( array( 'pending', 'failed' ) ) ) {
							version_compare( WC_VERSION, '3.0.0', '<' ) ? $order->reduce_order_stock() : wc_reduce_stock_levels( $order_id );
						}

						$order->update_status( 'on-hold', sprintf( __( 'Hips payment %s (Authorize ID: %s). Process order to take payment, or cancel to remove the pre-authorization.', 'woocommerce-gateway-hips' ), 'authorised' , $response->resource->id ) );	
						
					} else {
						if( 'successful' == $response->resource->status ) {
							//$order->payment_complete( $response->resource->id );
							$order->update_status( 'on-hold', sprintf( __( 'Order status changed from %s to On Hold.', 'woocommerce-gateway-hips' ), 'Pending' ) );
							$message = sprintf( __( 'Hips payment complete (Payment ID: %s)', 'woocommerce-gateway-hips' ), $response->resource->id );
							$order->add_order_note( $message );	
							self::log( 'Hips Payment Complete for the Order #' . $order_id . ', Payment ID', $response->resource->id );
						}					
					}										
					if( 'yes' == $hips_settings['auto_fulfill'] ){
						if( 'successful' == $response->resource->status )
							$order->update_status( 'wc-processing', sprintf( __( 'Order status changed from %s to Completed.', 'woocommerce-gateway-hips' ), 'Processing' ) );
					}
				}
			}
			else{
				$posts->the_post();
				if( 'successful' == $response->resource->status ) {
					$order_status = 'wc-on-hold';
					$order_status_name = 'On Hold';
				}
				if( 'awaiting_payments' == $response->resource->status ) {
					$order_status = 'wc-pending';
					$order_status_name = 'Payment Pending';
				}
				if( 'expired' == $response->resource->status ) {
					$order_status = 'wc-failed';
					$order_status_name = 'Failed';
				}
				if( 'pending' == $response->resource->status ) {
					$order_status = 'wc-on-hold';
					$order_status_name = 'On Hold';
				}

				$order = wc_get_order(get_the_id());
				$order->update_status( $order_status, sprintf( __( 'Order status changed from %s to %s.', 'woocommerce-gateway-hips' ), $order->get_status( ), $order_status ) );
				
				if( 'yes' == $hips_settings['auto_fulfill'] && $order_status == 'wc-processing' )
					$order->update_status( 'wc-processing', sprintf( __( 'Order status changed from %s to Processing.', 'woocommerce-gateway-hips' ), 'Processing' ) );
			}
		}
	}

	public static function create_customer( $billing_address ){

		$customer_id = false;
		$enable_guest_checkout = get_option( 'woocommerce_enable_guest_checkout' ) == 'yes' ? true : false;

		if ( ! is_user_logged_in() && ( !$enable_guest_checkout  ) && (!email_exists( $billing_address['billing_email'] )) ) {
			$email = $billing_address['billing_email'];
			$username = sanitize_user( current( explode( '@', $email ) ), true );

			// Ensure username is unique.
			$append     = 1;
			$o_username = $username;

			while ( username_exists( $username ) ) {
				$username = $o_username . $append;
				$append++;
			}
			$password = wp_generate_password();
			$new_customer = wc_create_new_customer( $email , '', $password);

			if ( is_wp_error( $new_customer ) ) {
				//throw new Exception( $new_customer->get_error_message() );
			} else {
				$customer_id = absint( $new_customer );
			}

			wc_set_customer_auth_cookie( $customer_id );

			// As we are now logged in, checkout will need to refresh to show logged in data
			WC()->session->set( 'reload_checkout', true );			

			// Add customer info from other billing fields
			if ( $billing_address['billing_first_name']  ) {
				$userdata = array(
					'ID'           => $customer_id,
					'first_name'   => $billing_address['billing_first_name'] ? $billing_address['billing_first_name'] : '',
					'last_name'    => $billing_address['billing_last_name'] ? $billing_address['billing_last_name'] : '',
					'display_name' => $billing_address['billing_first_name'] ? $billing_address['billing_first_name'] : ''
				);
				wp_update_user( $userdata );
			}
		}
		if( is_user_logged_in() ){			
			$customer_id = get_current_user_id();
		}
		
		return $customer_id;
	}

	public static function process_checkout() { 

		$hips_settings = get_option( 'woocommerce_Hips_settings' );
		$cart = WC()->cart->get_cart();
		$total_tax = WC()->cart->tax_total;
		$items = array();
		$total_items = WC()->cart->get_cart_contents_count();
		$item_tax = $total_tax / $total_items;
		$weight_unit =	get_option('woocommerce_weight_unit');
		$weights_units_array = array( 'kg'=>'kg', 'g'=>'gram', 'lbs'=>'lb' );
		$hips_weight_unit = 'gram';

		if( isset( $weights_units_array[$weight_unit] ) ){ 
			$hips_weight_unit = $weights_units_array[$weight_unit]; 
		}

		$cart_discount_total = WC()->cart->get_cart_discount_total();
		$cart_discount_total_tax = WC()->cart->get_cart_discount_tax_total();

		if( wc_tax_enabled() && wc_prices_include_tax() ) {
			$total_discount = $cart_discount_total + $cart_discount_total_tax;
		} else {
			$total_discount = $cart_discount_total;
		}
		
		foreach ( $cart as $cart_item_key => $values ) {

			$product = $values['data']; 
			$variation_id = ( ! empty( $values['variation_id'] ) ) ? $values['variation_id'] : 0;

			// Weight of Item
			$weight = (float) $values['data']->get_weight() * $values['quantity'];
			// Prices
			$base_price = $product->get_price(); 
			$line_price = $product->get_price() * $values['quantity'];

			if( wc_tax_enabled() && wc_prices_include_tax() ) {
				$unit_price = $base_price;
			} else {
				$unit_price = $base_price + $item_tax;
			}

			$items[] = array(
						'type' => 'physical',
						'sku'  => $product->get_sku(),
						'name' => $product->get_name(),
						'quantity' => $values['quantity'],
						'unit_price' => ( $unit_price ) * 100,
						'discount_rate' => '0',
						'vat_amount' => $item_tax * 100,
						'weight'	=> $weight,
						'weight_unit' => $hips_weight_unit,
						"meta_data_1" => $product->get_id(),
						"meta_data_2" => $variation_id
					);
		}
		
		if( $total_discount ){
			$items[] = array(
						'type' => 'discount',
						'sku'  => '',
						'name' => WC()->session->applied_coupons[0],
						'quantity' => 1,
						'unit_price' => -( $total_discount * 100 ),
						'discount_rate' => 0,
						'vat_amount' => $cart_discount_total_tax,
						'weight'	=> 0,						
					);
		}

		$order_id = uniqid();
		WC()->session->set( 'order_id', $order_id );
		$order_key = 'wc_order_' . $order_id;
		WC()->session->set( 'hips_order_key', $order_key );
		$request = new stdclass();
		$request->order_id = $order_id;
		$request->purchase_currency = get_woocommerce_currency();
		$request->user_session_id = '43535456464';
		$request->user_identifier = 'SmartShopper17';
		$request->ecommerce_platform = 'Woocommerce '. WC_VERSION;
		$request->ecommerce_module = 'Hips Woocommerce Plugin '. WC_HIPS_VERSION;						
		$request->cart = new stdClass();
		$request->cart->items = $items;
		$request->checkout_settings = new stdClass();
		$request->checkout_settings->extended_cart = 'true';
		$request->require_shipping = 'true';
		$request->express_shipping = 'true';  
		$request->meta_data_1 = json_encode( WC()->cart );  

		//Override Shipping 
		if( $hips_settings['hips_shipping'] == 'yes' ){
			$request->require_shipping = 'true';
			$request->express_shipping = 'true';
		} else {
			$request->require_shipping = 'false';
			$request->express_shipping = 'false';  
		}		
		
		if( $hips_settings['auto_fulfill'] == 'yes' )
			$request->fulfill = 'true';
		
			// Authorize and Capture Management
		if( $hips_settings['capture'] == 'yes' )
			$request->capture = 'true';
		
		$request->hooks = new stdClass();
		$hips_checkout_page_id = get_option( 'hips_checkout_page_id' );
		$hips_webhook_page_id = get_option( 'hips_webhook_page_id' );

		$request->hooks->user_return_url_on_success = add_query_arg( array( 'hips-order-key-success' => $order_key ), get_permalink( $hips_checkout_page_id ) );
		$request->hooks->user_return_url_on_fail = add_query_arg( array( 'hips-order-key-failed' => $order_key ), get_permalink( $hips_checkout_page_id ) );
		$request->hooks->terms_url = get_permalink( get_option( 'woocommerce_terms_page_id' ) );
		$request->hooks->webhook_url = add_query_arg( array( 'wc-hips-webhook' => 'successful' ), get_permalink( $hips_webhook_page_id ) ); 
		self::log( 'Hips API Request', $request );
		
		return $request;
	}

	public static function hips_webhook_callback() {
		$response = json_decode( file_get_contents( 'php://input' ) ); 
		if( ! empty($response) ){
			//self::log( 'Hips API Response Webhook', $response );
			if( isset( $_GET['wc-hips-webhook'] ) && 'successful' == $_GET['wc-hips-webhook'] ) {

				if( 'order.successful' == $response->event ) { 
					self::process_response( $response );
				}	
				elseif( 'order.fulfilled' == $response->event && 1 == $response->resource->fulfilled ) {
					$args = array(
						'post_type' => 'shop_order',
						'post_status' => array('wc-pending', 'wc-processing', 'wc-on-hold', 'wc-completed', 'wc-cancelled', 'wc-refunded', 'wc-failed'),
						'meta_key'   => '_hips_order_id',
						'meta_value' => $response->resource->merchant_reference->order_id
					);
					$posts = new WP_Query( $args );
					if( $posts->have_posts() ) {
						$posts->the_post();
						$order_id = get_the_id();	
						self::fulfill_order( $order_id, 'wc-processing' );
					}
				}
			}
			else{
				if( 'payment.created' == $response->event ){
					$order_id = $response->resource->merchant_order_id;
					$status = 'wc-pending';
				}
				if( 'order.successful' == $response->event ){
					$order_id = $response->resource->merchant_reference->order_id;
					$status = 'wc-on-hold';
					if( 1 == $response->resource->fulfilled )
						$status = 'wc-processing';
				}
				if( 'payment.successful' == $response->event ){
					$order_id = $response->resource->merchant_order_id;
					$status = 'wc-on-hold';
					if( 1 == $response->resource->fulfilled )
						$status = 'wc-processing';
				}
				if( ( 'order.fulfilled' == $response->event && 1 == $response->resource->fulfilled ) ) {
					$order_id = $response->resource->merchant_reference->order_id;
					$status = 'wc-processing';				
				}

				if( 'payment.purchase.successful' == $response->event ){
					$order_id = $response->resource->merchant_order_id;
					$status = 'wc-on-hold';
				}

				if( 'order.modified' == $response->event && 1 ==  $response->resource->fulfilled ){
					$order_id = $response->resource->merchant_reference->order_id;
					$status = 'wc-processing';
				}

				if( $status && $order_id ) {
					$order = wc_get_order($order_id);
					//self::log( 'Order status', $order->get_status() );
					if( 'processing' != $order->get_status() )
						self::fulfill_order( $order_id, $status );
				}
			}
		}
	}

	public static function fulfill_order( $order_id, $status ) {
		$order = wc_get_order($order_id);
		$order->update_status( 
			$status, 
			sprintf( __( 'Order status changed from %s to %s.', 'woocommerce-gateway-hips' ), $order->get_status( ), $status ) 
		);
	}

	public static function log( $text, $message ) {
		$log_entry = '';
		$log = new WC_Logger(); 
		$log_entry = $text . ': ' . print_r( $message, true ); 
		$log->add( 'woocommerce-gateway-hips', $log_entry );
	}
}

add_shortcode( 'hips_checkout', array( 'WC_Hips_Shortcode_Checkout', 'output' ) );
add_shortcode( 'hips_webhook', array( 'WC_Hips_Shortcode_Checkout', 'hips_webhook_callback' ) );
