<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_hips_Customer class.
 *
 * Represents a hips Customer.
 */
class WC_hips_Customer {

	/**
	 * hips customer ID
	 * @var string
	 */
	private $id = '';

	/**
	 * WP User ID
	 * @var integer
	 */
	private $user_id = 0;

	/**
	 * Data from API
	 * @var array
	 */
	private $customer_data = array();

	/**
	 * Constructor
	 * @param integer $user_id
	 */
	public function __construct( $user_id = 0 ) {
		if ( $user_id ) {
			$this->set_user_id( $user_id );
			$this->set_id( get_user_meta( $user_id, '_hips_customer_id', true ) );
		}
	}

	/**
	 * Get hips customer ID.
	 * @return string
	 */
	public function get_id() {
		return $this->id;
	}

	/**
	 * Set hips customer ID.
	 * @param [type] $id [description]
	 */
	public function set_id( $id ) {
		$this->id = wc_clean( $id );
	}

	/**
	 * User ID in WordPress.
	 * @return int
	 */
	public function get_user_id() {
		return absint( $this->user_id );
	}

	/**
	 * Set User ID used by WordPress.
	 * @param int $user_id
	 */
	public function set_user_id( $user_id ) {
		$this->user_id = absint( $user_id );
	}

	/**
	 * Get user object.
	 * @return WP_User
	 */
	protected function get_user() {
		return $this->get_user_id() ? get_user_by( 'id', $this->get_user_id() ) : false;
	}

	/**
	 * Store data from the hips API about this customer
	 */
	public function set_customer_data( $data ) {
		$this->customer_data = $data;
	}

	/**
	 * Get data from the hips API about this customer
	 */
	public function get_customer_data() {
		if ( empty( $this->customer_data ) && $this->get_id() && false === ( $this->customer_data = get_transient( 'hips_customer_' . $this->get_id() ) ) ) {
			$response = WC_HIPS_API::request( array(), 'customers/' . $this->get_id() );

			if ( ! is_wp_error( $response ) ) {
				$this->set_customer_data( $response );
				set_transient( 'hips_customer_' . $this->get_id(), $response, HOUR_IN_SECONDS * 48 );
			}
		}
		return $this->customer_data;
	}

	/**
	 * Get default card/source
	 * @return string
	 */
	public function get_default_card() {
		$data   = $this->get_customer_data();
		$source = '';

		if ( $data ) {
			$source = $data->default_source;
		}

		return $source;
	}

	/**
	 * Create a customer via API.
	 * @param array $args
	 * @return WP_Error|int
	 */
	public function create_customer( $args = array() ) {
		if ( $user = $this->get_user() ) {
			$billing_first_name = get_user_meta( $user->ID, 'billing_first_name', true );
			$billing_last_name  = get_user_meta( $user->ID, 'billing_last_name', true );

			$defaults = array(
				'email'       => $user->user_email,
				'description' => $billing_first_name . ' ' . $billing_last_name,
			);
		} else {
			$defaults = array(
				'email'       => '',
				'description' => '',
			);
		}

		$metadata = array();

		$defaults['metadata'] = apply_filters( 'wc_hips_customer_metadata', $metadata, $user );

		$args     = wp_parse_args( $args, $defaults );
		$response = WC_HIPS_API::request( $args, 'customers' );

		if ( is_wp_error( $response ) ) {
			return $response;
		} elseif ( empty( $response->id ) ) {
			return new WP_Error( 'hips_error', __( 'Could not create hips customer.', 'woocommerce-gateway-hips' ) );
		}

		$this->set_id( $response->id );
		$this->clear_cache();
		$this->set_customer_data( $response );

		if ( $this->get_user_id() ) {
			update_user_meta( $this->get_user_id(), '_hips_customer_id', $response->id );
		}

		do_action( 'woocommerce_hips_add_customer', $args, $response );

		return $response->id;
	}

	/**
	 * Add a card for this hips customer.
	 * @param string $token
	 * @param bool $retry
	 * @return WP_Error|int
	 */
	public function add_card( $token, $retry = true ) {
		if ( ! $this->get_id() ) {
			if ( ( $response = $this->create_customer() ) && is_wp_error( $response ) ) {
				return $response;
			}
		}

		$response = WC_HIPS_API::request( array(
			'source' => $token,
		), 'customers/' . $this->get_id() . '/sources' );

		if ( is_wp_error( $response ) ) {
			// It is possible the WC user once was linked to a customer on hips
			// but no longer exists. Instead of failing, lets try to create a
			// new customer.
			if ( preg_match( '/No such customer:/', $response->get_error_message() ) ) {
				delete_user_meta( $this->get_user_id(), '_hips_customer_id' );
				$this->create_customer();
				return $this->add_card( $token, false );
			} elseif ( 'customer' === $response->get_error_code() && $retry ) {
				$this->create_customer();
				return $this->add_card( $token, false );
			} else {
				return $response;
			}
		} elseif ( empty( $response->id ) ) {
			return new WP_Error( 'error', __( 'Unable to add card', 'woocommerce-gateway-hips' ) );
		}

		// Add token to WooCommerce
		if ( $this->get_user_id() && class_exists( 'WC_Payment_Token_CC' ) ) {
			$token = new WC_Payment_Token_CC();
			$token->set_token( $response->id );
			$token->set_gateway_id( 'hips' );
			$token->set_card_type( strtolower( $response->brand ) );
			$token->set_last4( $response->last4 );
			$token->set_expiry_month( $response->exp_month );
			$token->set_expiry_year( $response->exp_year );
			$token->set_user_id( $this->get_user_id() );
			$token->save();
		}

		$this->clear_cache();
		do_action( 'woocommerce_hips_add_card', $this->get_id(), $token, $response );

		return $response->id;
	}

	/**
	 * Get a customers saved cards using their hips ID. Cached.
	 *
	 * @param  string $customer_id
	 * @return array
	 */
	public function get_cards() {
		$cards = array();

		if ( $this->get_id() && false === ( $cards = get_transient( 'hips_cards_' . $this->get_id() ) ) ) {
			$response = WC_HIPS_API::request( array(
				'limit'       => 100,
			), 'customers/' . $this->get_id() . '/sources', 'GET' );

			if ( is_wp_error( $response ) ) {
				return array();
			}

			if ( is_array( $response->data ) ) {
				$cards = $response->data;
			}

			set_transient( 'hips_cards_' . $this->get_id(), $cards, HOUR_IN_SECONDS * 48 );
		}

		return $cards;
	}

	/**
	 * Delete a card from hips.
	 * @param string $card_id
	 */
	public function delete_card( $card_id ) {
		$response = WC_HIPS_API::request( array(), 'customers/' . $this->get_id() . '/sources/' . sanitize_text_field( $card_id ), 'DELETE' );

		$this->clear_cache();

		if ( ! is_wp_error( $response ) ) {
			do_action( 'wc_hips_delete_card', $this->get_id(), $response );

			return true;
		}

		return false;
	}

	/**
	 * Set default card in hips
	 * @param string $card_id
	 */
	public function set_default_card( $card_id ) {
		$response = WC_HIPS_API::request( array(
			'default_source' => sanitize_text_field( $card_id ),
		), 'customers/' . $this->get_id(), 'POST' );

		$this->clear_cache();

		if ( ! is_wp_error( $response ) ) {
			do_action( 'wc_hips_set_default_card', $this->get_id(), $response );

			return true;
		}

		return false;
	}

	/**
	 * Deletes caches for this users cards.
	 */
	public function clear_cache() {
		delete_transient( 'hips_cards_' . $this->get_id() );
		delete_transient( 'hips_customer_' . $this->get_id() );
		$this->customer_data = array();
	}
}