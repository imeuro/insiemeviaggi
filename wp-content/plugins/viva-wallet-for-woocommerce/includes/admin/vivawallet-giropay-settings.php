<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


return apply_filters(
	'wc_vivawallet_giropay_settings',
	array(

		'enabled'     => array(
			'title'   => __( 'Enable Viva Wallet Giropay Gateway', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Viva Wallet Giropay Gateway and receive Giropay payments', 'viva-wallet-for-woocommerce' ),
			'default' => 'no',
		),

		'title'       => array(
			'title'       => __( 'Title', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'Giropay', 'viva-wallet-for-woocommerce' ),
		),
		'description' => array(
			'title'       => __( 'Description', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'You will be redirected to Giropay.', 'viva-wallet-for-woocommerce' ),
		),
	)
);
