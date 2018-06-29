<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_HIPS_API class.
 *
 * Communicates with hips API.
 */
class WC_HIPS_API {

	/**
	 * hips API Endpoint
	 */
	const ENDPOINT = 'https://api.hips.com/v1/';

	/**
	 * Secret API Key.
	 * @var string
	 */
	private static $secret_key = '';

	/**
	 * Set secret API Key.
	 * @param string $key
	 */
	public static function set_secret_key( $secret_key ) {
		self::$secret_key = $secret_key;
	}

	/**
	 * Get secret key.
	 * @return string
	 */
	public static function get_secret_key() {
		if ( ! self::$secret_key ) {
			$options = get_option( 'woocommerce_hips_settings' );

			if ( isset( $options['testmode'], $options['secret_key'], $options['test_secret_key'] ) ) {
				self::set_secret_key( 'yes' === $options['testmode'] ? $options['test_secret_key'] : $options['secret_key'] );
			}
		}
		return self::$secret_key;
	}

	/**
	 * Send the request to hips's API
	 *
	 * @param array $request
	 * @param string $api
	 * @return array|WP_Error
	 */
	public static function request( $request, $api = 'payments', $method = 'POST' ) {
		self::log( "{$api} request: " . print_r( $request, true ) );

		$response = wp_safe_remote_post(
			self::ENDPOINT . $api,
			array(
				'method'        => $method,
				'headers'       => array(
					'Authorization'  => 'Basic ' . base64_encode( self::get_secret_key() . ':' ),
					'hips-Version' => '2016-03-07',
				),
				'body'       => apply_filters( 'woocommerce_hips_request_body', $request, $api ),
				'timeout'    => 70,
				'user-agent' => 'WooCommerce ' . WC()->version,
			)
		);

		if ( is_wp_error( $response ) || empty( $response['body'] ) ) {
			self::log( 'Error Response: ' . print_r( $response, true ) );
			return new WP_Error( 'hips_error', __( 'There was a problem connecting to the payment gateway.', 'woocommerce-gateway-hips' ) );
		}

		$parsed_response = json_decode( $response['body'] );

		// Handle response
		if ( ! empty( $parsed_response->error ) ) {
			if ( ! empty( $parsed_response->error->code ) ) {
				$code = $parsed_response->error->code;
			} else {
				$code = 'hips_error';
			}
			return new WP_Error( $code, $parsed_response->error->message );
		} else {
			return $parsed_response;
		}
	}

	/**
     * Do the API call
     *
     * @param string $methodName
     * @param array $request
     * @return array
     * @throws Mage_Core_Exception
     */
    public static function call( $request, $path, $method = 'POST' ) {

        try {

            $key = self::get_secret_key(); 
            $url = self::ENDPOINT . $path;
            $data = json_encode( $request );
            $ch = curl_init();
            curl_setopt( $ch, CURLOPT_URL, $url );
            curl_setopt( $ch,CURLOPT_SSL_VERIFYPEER, false );
            curl_setopt( $ch, CURLOPT_CUSTOMREQUEST, $method );
            if( $method == 'POST' ){
                curl_setopt( $ch, CURLOPT_POSTFIELDS, $data );
            }
            curl_setopt( $ch, CURLOPT_FOLLOWLOCATION, 1 );
            curl_setopt( $ch, CURLOPT_RETURNTRANSFER, true );
            curl_setopt( $ch, CURLOPT_HTTPHEADER, array(
                    'Content-Type: application/json',
                    'Accept: application/json',
                    'Authorization:'. $key
                )
            );
            curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT, 120 );
            $result = curl_exec( $ch ); 
            curl_close( $ch );
            return json_decode( $result );
        } catch ( Exception $e ) {
            throw $e;
        }
    }

	/**
	 * Logs
	 *
	 * @since 1.0.5
	 * @version 1.1.2

	 *
	 * @param string $message
	 */
	public static function log( $message ) {
		$options = get_option( 'woocommerce_hips_settings' );

		if ( 'yes' === $options['logging'] ) {
			WC_hips::log( $message );
		}
	}
}