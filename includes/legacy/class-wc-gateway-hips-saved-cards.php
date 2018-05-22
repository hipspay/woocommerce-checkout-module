<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Gateway_hips_Saved_Cards class.
 */
class WC_Gateway_hips_Saved_Cards {

	/**
	 * Constructor
	 */
	public function __construct() {
		add_action( 'wp', array( $this, 'delete_card' ) );
		add_action( 'woocommerce_after_my_account', array( $this, 'output' ) );
		add_action( 'wp', array( $this, 'default_card' ) );
	}

	/**
	 * Display saved cards
	 */
	public function output() {
		if ( ! is_user_logged_in() ) {
			return;
		}

		$hips_customer = new WC_hips_Customer( get_current_user_id() );
		$hips_cards    = $hips_customer->get_cards();
		$default_card    = $hips_customer->get_default_card();

		if ( $hips_cards ) {
			wc_get_template( 'saved-cards.php', array( 'cards' => $hips_cards, 'default_card' => $default_card ), 'woocommerce-gateway-hips/', untrailingslashit( plugin_dir_path( WC_HIPS_MAIN_FILE ) ) . '/includes/legacy/templates/' );
		}
	}

	/**
	 * Delete a card
	 */
	public function delete_card() {
		if ( ! isset( $_POST['hips_delete_card'] ) || ! is_account_page() ) {
			return;
		}

		$hips_customer    = new WC_hips_Customer( get_current_user_id() );
		$hips_customer_id = $hips_customer->get_id();
		$delete_card        = sanitize_text_field( $_POST['hips_delete_card'] );

		if ( ! is_user_logged_in() || ! $hips_customer_id || ! wp_verify_nonce( $_POST['_wpnonce'], "hips_del_card" ) ) {
			wp_die( __( 'Unable to make default card, please try again', 'woocommerce-gateway-hips' ) );
		}

		if ( ! $hips_customer->delete_card( $delete_card ) ) {
			wc_add_notice( __( 'Unable to delete card.', 'woocommerce-gateway-hips' ), 'error' );
		} else {
			wc_add_notice( __( 'Card deleted.', 'woocommerce-gateway-hips' ), 'success' );
		}
	}

	/**
	 * Make a card as default method
	 */
	public function default_card() {
		if ( ! isset( $_POST['hips_default_card'] ) || ! is_account_page() ) {
			return;
		}

		$hips_customer    = new WC_hips_Customer( get_current_user_id() );
		$hips_customer_id = $hips_customer->get_id();
		$default_source     = sanitize_text_field( $_POST['hips_default_card'] );

		if ( ! is_user_logged_in() || ! $hips_customer_id || ! wp_verify_nonce( $_POST['_wpnonce'], "hips_default_card" ) ) {
			wp_die( __( 'Unable to make default card, please try again', 'woocommerce-gateway-hips' ) );
		}

		if ( ! $hips_customer->set_default_card( $default_source ) ) {
			wc_add_notice( __( 'Unable to update default card.', 'woocommerce-gateway-hips' ), 'error' );
		} else {
			wc_add_notice( __( 'Default card updated.', 'woocommerce-gateway-hips' ), 'success' );
		}
	}
}
new WC_Gateway_hips_Saved_Cards();