<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


return apply_filters(
	'wc_vivawallet_wechatpay_settings',
	array(

		'enabled'     => array(
			'title'   => __( 'Enable Viva Wallet WeChat Pay Gateway', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Viva Wallet WeChat Pay Gateway and receive WeChat Pay payments', 'viva-wallet-for-woocommerce' ),
			'default' => 'no',
		),

		'title'       => array(
			'title'       => __( 'Title', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'WeChat Pay', 'viva-wallet-for-woocommerce' ),
		),
		'description' => array(
			'title'       => __( 'Description', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'You will be redirected to WeChatPay.', 'viva-wallet-for-woocommerce' ),
		),
	)
);
