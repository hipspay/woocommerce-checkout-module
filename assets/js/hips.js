/* global wc_hips_params */
Hips.public_key = wc_hips_params.key;

jQuery( function( $ ) {
	'use strict';

	/* Open and close for legacy class */
	$( 'form.checkout, form#order_review' ).on( 'change', 'input[name="wc-hips-payment-token"]', function() {
		if ( 'new' === $( '.hips-legacy-payment-fields input[name="wc-hips-payment-token"]:checked' ).val() ) {
			$( '.hips-legacy-payment-fields #hips-payment-data' ).slideDown( 200 );
		} else {
			$( '.hips-legacy-payment-fields #hips-payment-data' ).slideUp( 200 );
		}
	} );

	
	/**
	 * Object to handle hips payment forms.
	 */
	var wc_hips_form = {

		/**
		 * Initialize event handlers and UI state.
		 */
		init: function() {
			// checkout page


			if ( $( 'form.woocommerce-checkout' ).length ) {
				this.form = $( 'form.woocommerce-checkout' );
			}

			$( 'form.woocommerce-checkout' )
				.on(
					'checkout_place_order_hips',
					this.onSubmit
				);

			// pay order page
			if ( $( 'form#order_review' ).length ) {
				this.form = $( 'form#order_review' );
			}

			$( 'form#order_review' )
				.on(
					'submit',
					this.onSubmit
				);

			// add payment method page
			if ( $( 'form#add_payment_method' ).length ) {
				this.form = $( 'form#add_payment_method' );
			}

			$( 'form#add_payment_method' )
				.on(
					'submit',
					this.onSubmit
				);

			$( document )
				.on(
					'change',
					'#wc-hips-cc-form :input',
					this.onCCFormChange
				)
				.on(
					'hipsError',
					this.onError
				)
				.on(
					'checkout_error',
					this.clearToken
				);
		},

		ishipsChosen: function() {
			return $( '#payment_method_hips' ).is( ':checked' ) && ( ! $( 'input[name="wc-hips-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-hips-payment-token"]:checked' ).val() );
		},

		hasToken: function() {
			return 0 < $( 'input.hips_token' ).length;
		},

		block: function() {
			wc_hips_form.form.block({
				message: null,
				overlayCSS: {
					background: '#fff',
					opacity: 0.6
				}
			});
		},

		unblock: function() {
			wc_hips_form.form.unblock();
		},

		onError: function( e, responseObject ) {
			var message = responseObject.response.error.message;

			// Customers do not need to know the specifics of the below type of errors
			// therefore return a generic localizable error message.
			if ( 
				'invalid_request_error' === responseObject.response.error.type ||
				'api_connection_error'  === responseObject.response.error.type ||
				'api_error'             === responseObject.response.error.type ||
				'authentication_error'  === responseObject.response.error.type ||
				'rate_limit_error'      === responseObject.response.error.type
			) {
				message = wc_hips_params.invalid_request_error;
			}

			if ( 'card_error' === responseObject.response.error.type && wc_hips_params.hasOwnProperty( responseObject.response.error.code ) ) {
				message = wc_hips_params[ responseObject.response.error.code ];
			}

			$( '.wc-hips-error, .hips_token' ).remove();
			$( '#hips-card-number' ).closest( 'p' ).before( '<ul class="woocommerce_error woocommerce-error wc-hips-error"><li>' + message + '</li></ul>' );
			wc_hips_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_hips_form.ishipsChosen() && ! wc_hips_form.hasToken() ) {
				e.preventDefault();
				wc_hips_form.block();

				

				var card       = $( '#hips-card-number' ).val(),
					cvc        = $( '#hips-card-cvc' ).val(),
					expires    = $( '#hips-card-expiry' ).payment( 'cardExpiryVal' ),
					first_name = $( '#billing_first_name' ).length ? $( '#billing_first_name' ).val() : wc_hips_params.billing_first_name,
					last_name  = $( '#billing_last_name' ).length ? $( '#billing_last_name' ).val() : wc_hips_params.billing_last_name,
					data       = {
						number   : card,
						cvc      : cvc,
						exp_month: parseInt( expires.month, 10 ) || 0,
						exp_year : parseInt( expires.year, 10 ) || 0
					};

				$('#hips-card-expiry-month').val(data.exp_month);
				$('#hips-card-expiry-year').val(data.exp_year);

				if ( first_name && last_name ) {
					data.name = first_name + ' ' + last_name;
				}

				if ( $( '#billing_address_1' ).length > 0 ) {
					data.address_line1   = $( '#billing_address_1' ).val();
					data.address_line2   = $( '#billing_address_2' ).val();
					data.address_state   = $( '#billing_state' ).val();
					data.address_city    = $( '#billing_city' ).val();
					data.address_zip     = $( '#billing_postcode' ).val();
					data.address_country = $( '#billing_country' ).val();
				} else if ( wc_hips_params.billing_address_1 ) {
					data.address_line1   = wc_hips_params.billing_address_1;
					data.address_line2   = wc_hips_params.billing_address_2;
					data.address_state   = wc_hips_params.billing_state;
					data.address_city    = wc_hips_params.billing_city;
					data.address_zip     = wc_hips_params.billing_postcode;
					data.address_country = wc_hips_params.billing_country;
				}

				tokenize();
				// Prevent form submitting
				return false;
			}
		},

		onCCFormChange: function() {
			$( '.wc-hips-error, .hips_token' ).remove();
		},

		onHipsResponse: function( response ) {
			
			if ( response.error ) {
				$( document ).trigger( 'hipsError', { response: response } );
			} else {
				// check if we allow prepaid cards
				if ( 'no' === wc_hips_params.allow_prepaid_card && 'prepaid' === response.card.funding ) {
					response.error = { message: wc_hips_params.no_prepaid_card_msg };

					$( document ).trigger( 'hipsError', { response: response } );
					
					return false;
				}

				// token contains id, last4, and card type
				var token = response.payload.token;

				// insert the token into the form so it gets submitted to the server
				wc_hips_form.form.append( "<input type='hidden' class='hips_token' name='hips_token' value='" + token + "'/>" );
			
				wc_hips_form.form.submit();
				
			}
		},

		clearToken: function() {
			$( '.hips_token' ).remove();
		}
	};

	wc_hips_form.init();

	function tokenize(){
		
	    Hips.card.getToken('form.woocommerce-checkout', function(token, error){

	      	if(error){
	      		alert('Error: '+error.message);	 
	      		wc_hips_form.unblock();     		
	        } else {
	        	wc_hips_form.form.append( "<input type='hidden' class='hips_token' name='hips_token' value='" + token + "'/>" );			
				wc_hips_form.form.submit();
	      	}
	    });
  	}
} );
