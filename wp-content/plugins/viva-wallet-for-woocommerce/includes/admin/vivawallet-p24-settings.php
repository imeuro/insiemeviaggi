<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


return apply_filters(
	'wc_vivawallet_p24_settings',
	array(

		'enabled'     => array(
			'title'   => __( 'Enable Viva Wallet P24 Gateway', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Viva Wallet P24 Gateway and receive P24 payments', 'viva-wallet-for-woocommerce' ),
			'default' => 'no',
		),

		'title'       => array(
			'title'       => __( 'Title', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'Przelewy24 (P24)', 'viva-wallet-for-woocommerce' ),
		),
		'description' => array(
			'title'       => __( 'Description', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'You will be redirected to P24.', 'viva-wallet-for-woocommerce' ),
		),
	)
);
