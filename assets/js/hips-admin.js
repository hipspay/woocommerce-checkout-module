jQuery( function( $ ) {
	'use strict';

	/**
	 * Object to handle hips admin functions.
	 */
	var wc_hips_admin = {
		isTestMode: function() {
			return $( '#woocommerce_hips_testmode' ).is( ':checked' );
		},

		getSecretKey: function() {
			if ( wc_hips_admin.isTestMode() ) {
				return $( '#woocommerce_hips_test_secret_key' ).val();
			} else {
				return $( '#woocommerce_hips_secret_key' ).val();
			}
		},

		/**
		 * Initialize.
		 */
		init: function() {
			$( document.body ).on( 'change', '#woocommerce_hips_testmode', function() {
				var test_secret_key = $( '#woocommerce_hips_test_secret_key' ).parents( 'tr' ).eq( 0 ),
					test_publishable_key = $( '#woocommerce_hips_test_publishable_key' ).parents( 'tr' ).eq( 0 ),
					live_secret_key = $( '#woocommerce_hips_secret_key' ).parents( 'tr' ).eq( 0 ),
					live_publishable_key = $( '#woocommerce_hips_publishable_key' ).parents( 'tr' ).eq( 0 );

				if ( $( this ).is( ':checked' ) ) {
					test_secret_key.show();
					test_publishable_key.show();
					live_secret_key.hide();
					live_publishable_key.hide();
				} else {
					test_secret_key.hide();
					test_publishable_key.hide();
					live_secret_key.show();
					live_publishable_key.show();
				}
			} );

			$( '#woocommerce_hips_testmode' ).change();

			// Toggle hips Checkout settings.
			$( '#woocommerce_hips_hips_checkout' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_hips_hips_checkout_locale, #woocommerce_hips_hips_bitcoin, #woocommerce_hips_hips_checkout_image' ).closest( 'tr' ).show();
					$( '#woocommerce_hips_request_payment_api' ).closest( 'tr' ).hide();
				} else {
					$( '#woocommerce_hips_hips_checkout_locale, #woocommerce_hips_hips_bitcoin, #woocommerce_hips_hips_checkout_image' ).closest( 'tr' ).hide();
					$( '#woocommerce_hips_request_payment_api' ).closest( 'tr' ).show();
				}
			}).change();

			// Toggle Apple Pay settings.
			$( '#woocommerce_hips_apple_pay' ).change( function() {
				if ( $( this ).is( ':checked' ) ) {
					$( '#woocommerce_hips_apple_pay_button, #woocommerce_hips_apple_pay_button_lang' ).closest( 'tr' ).show();
				} else {
					$( '#woocommerce_hips_apple_pay_button, #woocommerce_hips_apple_pay_button_lang' ).closest( 'tr' ).hide();
				}
			}).change();

			// Validate the keys to make sure it is matching test with test field.
			$( '#woocommerce_hips_secret_key, #woocommerce_hips_publishable_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_test_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description hips-error-description" style="color:red; display:block;">' + wc_hips_admin_params.localized_messages.not_valid_live_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.hips-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );

			// Validate the keys to make sure it is matching live with live field.
			$( '#woocommerce_hips_test_secret_key, #woocommerce_hips_test_publishable_key' ).on( 'input', function() {
				var value = $( this ).val();

				if ( value.indexOf( '_live_' ) >= 0 ) {
					$( this ).css( 'border-color', 'red' ).after( '<span class="description hips-error-description" style="color:red; display:block;">' + wc_hips_admin_params.localized_messages.not_valid_test_key_msg + '</span>' );
				} else {
					$( this ).css( 'border-color', '' );
					$( '.hips-error-description', $( this ).parent() ).remove();
				}
			}).trigger( 'input' );
		}
	};

	wc_hips_admin.init();
});
