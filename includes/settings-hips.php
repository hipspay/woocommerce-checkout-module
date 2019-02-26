<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_hips_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Enable Hips', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'no',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-gateway-hips' ),
			'default'     => __( 'Credit Card (Hips)', 'woocommerce-gateway-hips' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-gateway-hips' ),
			'default'     => __( 'Pay with your credit card via Hips.', 'woocommerce-gateway-hips' ),
			'desc_tip'    => true,
		),
		'testmode' => array(
			'title'       => __( 'Test mode', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Enable Test Mode', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => __( 'Place the payment gateway in test mode using test API keys.', 'woocommerce-gateway-hips' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'test_publishable_key' => array(
			'title'       => __( 'Test Publishable Key', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your hips account.', 'woocommerce-gateway-hips' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'test_secret_key' => array(
			'title'       => __( 'Test Private Key', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your hips account.', 'woocommerce-gateway-hips' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'publishable_key' => array(
			'title'       => __( 'Live Publishable Key', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your hips account.', 'woocommerce-gateway-hips' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'secret_key' => array(
			'title'       => __( 'Live Private Key', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'Get your API keys from your hips account.', 'woocommerce-gateway-hips' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'statement_descriptor' => array(
			'title'       => __( 'Statement Descriptor', 'woocommerce-gateway-hips' ),
			'type'        => 'text',
			'description' => __( 'Extra information about a payment. This will appear on your customer’s credit card statement.', 'woocommerce-gateway-hips' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'capture' => array(
			'title'       => __( 'Capture', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Capture payment immediately', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => __( 'Whether or not to immediately capture the payment. When unchecked, the payment issues an authorization and will need to be captured later. Uncaptured payments expire in 7 days.', 'woocommerce-gateway-hips' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'hips_checkout' => array(
			'title'       => __( 'Hips Checkout', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Enable Hips Checkout', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, other payment methods will be disabled and this option shows a "pay" button and credit card form on the checkout, instead of credit card fields directly on the page.', 'woocommerce-gateway-hips' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
		'hips_shipping' => array(
			'title'       => __( 'Hips Shipping', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Enable Hips Shipping', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, customer will be forced to choose shipping method at Hips Checkout page.', 'woocommerce-gateway-hips' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'auto_fulfill' => array(
			'title'       => __( 'Auto FulFill', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Enable Auto FulFill', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, order status will be updated as completed.', 'woocommerce-gateway-hips' ),
			'default'     => 'yes',
			'desc_tip'    => true,
		),
		'logging' => array(
			'title'       => __( 'Logging', 'woocommerce-gateway-hips' ),
			'label'       => __( 'Log debug messages', 'woocommerce-gateway-hips' ),
			'type'        => 'checkbox',
			'description' => __( 'Save debug messages to the WooCommerce System Status log.', 'woocommerce-gateway-hips' ),
			'default'     => 'no',
			'desc_tip'    => true,
		),
	)
);