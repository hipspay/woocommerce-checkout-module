<?php 
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * WC_Gateway_hips class.
 *
 * @extends WC_Payment_Gateway
 */

class Hips_Shipping_Method extends WC_Shipping_Method {
    /**
     * Constructor for your shipping class
     *
     * @access public
     * @return void
     */
    public function __construct() {
        $this->id                 = 'hips_shipping'; 
        $this->method_title       = __( 'Shipping Hips', 'hips' );  
        $this->method_description = __( 'Custom Shipping Method for Hips', 'hips' ); 

        $this->init();

        $this->enabled = isset( $this->settings['enabled'] ) ? $this->settings['enabled'] : 'yes';
        $this->title = isset( $this->settings['title'] ) ? $this->settings['title'] : __( 'Hips Shipping', 'hips' );
    }

    /**
     * Init your settings
     *
     * @access public
     * @return void
     */
    function init() {
        // Load the settings API
        $this->init_form_fields(); 
        $this->init_settings(); 

        // Save settings in admin if you have any defined
        add_action( 'woocommerce_update_options_shipping_' . $this->id, array( $this, 'process_admin_options' ) );
    }

    /**
     * Define settings field for this shipping
     * @return void 
     */
    function init_form_fields() { 

        // We will add our settings here
        $this->form_fields = array(
            'title' => array(
                'title' => __( 'Title', 'hips' ),
                'type' => 'text',
                'description' => __( 'Title to be display on site', 'hips' ),
                'default' => __( 'Shipping Hips', 'hips' )
                ),
 
         );
    }  
}