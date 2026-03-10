<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


return apply_filters(
	'wc_vivawallet_directpay_settings',
	array(

		'enabled'     => array(
			'title'   => __( 'Enable Viva Wallet DirectPay Gateway', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Viva Wallet DirectPay Gateway and receive DirectPay payments', 'viva-wallet-for-woocommerce' ),
			'default' => 'no',
		),

		'title'       => array(
			'title'       => __( 'Title', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'DirectPay', 'viva-wallet-for-woocommerce' ),
		),
		'description' => array(
			'title'       => __( 'Description', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'You will be redirected to DirectPay.', 'viva-wallet-for-woocommerce' ),
		),
	)
);
