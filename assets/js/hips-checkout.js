jQuery( function( $ ) {
	'use strict';
	
	/**
	 * Object to handle hips payment forms.
	 */
	var wc_hips_form = {

		/**
		 * Initialize e handlers and UI state.
		 */
		init: function( form ) {
			this.form          = form;
			this.hips_submit = false;
			
			$( this.form )
				// We need to bind directly to the click (and not checkout_place_order_hips) to avoid popup blockers
				// especially on mobile devices (like on Chrome for iOS) from blocking hipsCheckout.open from opening a tab
				.on( 'click', '#place_order', this.onSubmit )

				// WooCommerce lets us return a false on checkout_place_order_{gateway} to keep the form from submitting
				.on( 'submit checkout_place_order_hips' );

			$( document.body ).on( 'checkout_error', this.resetModal );
		},

		ishipsChosen: function() {
			return $( '#payment_method_hips' ).is( ':checked' ) && ( ! $( 'input[name="wc-hips-payment-token"]:checked' ).length || 'new' === $( 'input[name="wc-hips-payment-token"]:checked' ).val() );
		},

		ishipsModalNeeded: function( e ) {
			var token = wc_hips_form.form.find( 'input.hips_token' ),
				$required_inputs;

			// If this is a hips submission (after modal) and token exists, allow submit.
			if ( wc_hips_form.hips_submit && token ) {
				return false;
			}

			// Don't affect submission if modal is not needed.
			if ( ! wc_hips_form.ishipsChosen() ) {
				return false;
			}

			// Don't open modal if required fields are not complete
			if ( $( 'input#terms' ).length === 1 && $( 'input#terms:checked' ).length === 0 ) {
				return false;
			}

			if ( $( '#createaccount' ).is( ':checked' ) && $( '#account_password' ).length && $( '#account_password' ).val() === '' ) {
				return false;
			}

			// check to see if we need to validate shipping address
			if ( $( '#ship-to-different-address-checkbox' ).is( ':checked' ) ) {
				$required_inputs = $( '.woocommerce-billing-fields .validate-required, .woocommerce-shipping-fields .validate-required' );
			} else {
				$required_inputs = $( '.woocommerce-billing-fields .validate-required' );
			}

			if ( $required_inputs.length ) {
				var required_error = false;

				$required_inputs.each( function() {
					if ( $( this ).find( 'input.input-text, select' ).not( $( '#account_password, #account_username' ) ).val() === '' ) {
						required_error = true;
					}

					var emailField = $( this ).find( '#billing_email' );

					if ( emailField.length ) {
						var re = /^([a-zA-Z0-9_\.\-\+])+\@(([a-zA-Z0-9\-])+\.)+([a-zA-Z0-9]{2,4})+$/;

						if ( ! re.test( emailField.val() ) ) {
							required_error = true;
						}
					}
				});

				if ( required_error ) {
					return false;
				}
			}

			return true;
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

		onClose: function() {
			wc_hips_form.unblock();
		},

		onSubmit: function( e ) {
			if ( wc_hips_form.ishipsModalNeeded() ) {
				e.preventDefault();

				// Capture submittal and open hipscheckout
				var $form = wc_hips_form.form,
					$data = $( '#hips-payment-data' ),
					token = $form.find( 'input.hips_token' );

				token.val( '' );

				var token_action = function( res ) {
					$form.find( 'input.hips_token' ).remove();
					$form.append( '<input type="hidden" class="hips_token" name="hips_token" value="' + res.id + '"/>' );
					wc_hips_form.hips_submit = true;
					$form.submit();
				};

				hipsCheckout.open({
					key               : wc_hips_params.key,
					billingAddress    : 'yes' === wc_hips_params.hips_checkout_require_billing_address,
					amount            : $data.data( 'amount' ),
					name              : $data.data( 'name' ),
					description       : $data.data( 'description' ),
					currency          : $data.data( 'currency' ),
					image             : $data.data( 'image' ),
					bitcoin           : $data.data( 'bitcoin' ),
					locale            : $data.data( 'locale' ),
					email             : $( '#billing_email' ).val() || $data.data( 'email' ),
					panelLabel        : $data.data( 'panel-label' ),
					allowRememberMe   : $data.data( 'allow-remember-me' ),
					token             : token_action,
					closed            : wc_hips_form.onClose()
				});

				return false;
			}

			return true;
		},

		resetModal: function() {
			wc_hips_form.form.find( 'input.hips_token' ).remove();
			wc_hips_form.hips_submit = false;
		}
	};

	wc_hips_form.init( $( "form.checkout, form#order_review, form#add_payment_method" ) );
} );
