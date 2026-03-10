<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

$vivawallet_woo_docs_url = 'https://docs.woocommerce.com/document/viva-wallet-for-woocommerce/';
$vivawallet_demo_url     = 'https://demo.vivapayments.com/';
$vivawallet_live_url     = 'https://www.vivapayments.com/';

/* translators: credentials */
$main_desc = __(
	'Set the title and description of the payment gateway. Title and description are visible to end users in the checkout page.',
	'viva-wallet-for-woocommerce'
);

/* translators: credentials */
$credentials_desc = sprintf( __( 'To find out how to retrieve your credentials for the payment gateway, please visit the Viva Wallet Standard Checkout <a target="_blank" href="%s">installation guide</a>.', 'viva-wallet-for-woocommerce' ), $vivawallet_woo_docs_url );
/* translators: Demo Mode */
$test_mode_desc = sprintf( __( 'If Demo Mode is enabled, please use the credentials you got from <a target="_blank" href="%s">demo.vivapayments.com</a>.', 'viva-wallet-for-woocommerce' ), $vivawallet_demo_url );


return apply_filters(
	'wc_vivawallet_settings',
	array(

		'main_title'                => array(
			'title' => __( 'Viva Wallet Standard Checkout settings', 'viva-wallet-for-woocommerce' ),
			'type'  => 'title',
		),

		'enabled'                   => array(
			'title'   => __( 'Enable Viva Wallet Standard Checkout', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Viva Wallet Standard Checkout to accept payments from all major credit cards, and offer other payment methods, such as Apple Pay and Google Pay, local card schemes, local wallets, and alternative payment methods.', 'viva-wallet-for-woocommerce' ),
			'default' => 'no',
		),


		'sep'                       => array(
			'title'       => '',
			'type'        => 'title',
			'description' => '<hr>',
		),

		'credentials'               => array(
			'title'       => __( 'Set Viva Wallet API credentials', 'viva-wallet-for-woocommerce' ),
			'type'        => 'title',
			'description' => $credentials_desc,
		),
		'test_mode'                 => array(
			'title'       => __( 'Demo mode', 'viva-wallet-for-woocommerce' ),
			'type'        => 'checkbox',
			'label'       => __( 'Enable demo mode', 'viva-wallet-for-woocommerce' ),
			'description' => $test_mode_desc,
			'default'     => 'yes',
		),



		'title_2'                   => array(
			'title' => __( 'Live mode credentials', 'viva-wallet-for-woocommerce' ),
			'type'  => 'title',
		),

		'title_3'                   => array(
			'title' => __( 'Demo mode credentials', 'viva-wallet-for-woocommerce' ),
			'type'  => 'title',
		),

		'client_id'                 => array(
			'title'       => __( 'Live Client ID', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Client ID provided by Viva Wallet.', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
		),
		'test_client_id'            => array(
			'title'       => __( 'Demo Client ID', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Client ID provided by Viva Wallet. ', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
		),



		'client_secret'             => array(
			'title'       => __( 'Live Client Secret', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Client Secret provided by Viva Wallet.', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
		),
		'test_client_secret'        => array(
			'title'       => __( 'Demo Client Secret', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'Client Secret provided by Viva Wallet.', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
		),




		'sep_2'                     => array(
			'title'       => '',
			'type'        => 'title',
			'description' => '<hr>',
		),

		'apple_pay'                 => array(
			'title'       => __( 'Live Apple Pay enable', 'viva-wallet-for-woocommerce' ),
			/* translators: apple link to terms. */
			'label'       => sprintf( __( 'Enable Apple Pay API in Live Mode. %1$sBy using Apple Pay, you agree to %2$s\'s terms of service.', 'viva-wallet-for-woocommerce' ), '<br />', '<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay using Apple Pay if supported by the browser.', 'viva-wallet-for-woocommerce' ),
			'default'     => 'no',
		),

		'test_apple_pay'            => array(
			'title'       => __( 'Demo Apple Pay enable', 'viva-wallet-for-woocommerce' ),
			/* translators: apple link to terms. */
			'label'       => sprintf( __( 'Enable Apple Pay API in Demo Mode. %1$sBy using Apple Pay, you agree to %2$s\'s terms of service.', 'viva-wallet-for-woocommerce' ), '<br />', '<a href="https://developer.apple.com/apple-pay/acceptable-use-guidelines-for-websites/" target="_blank">Apple</a>' ),
			'type'        => 'checkbox',
			'description' => __( 'If enabled, users will be able to pay using Apple Pay if supported by the browser.', 'viva-wallet-for-woocommerce' ),
			'default'     => 'no',
		),

		'sep_3'                     => array(
			'title'       => '',
			'type'        => 'title',
			'description' => '<hr>',
		),


		'advanced_settings_title'   => array(
			'title' => __( 'Advanced settings', 'viva-wallet-for-woocommerce' ),
			'type'  => 'title',
		),

		'advanced_settings_enabled' => array(
			'title'   => __( 'Show advanced settings', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Show advanced settings. If this checkbox is unchecked, the plugin will use default settings.', 'viva-wallet-for-woocommerce' ),
			'default' => 'no',
		),

		'sep_3'                     => array(
			'title'       => '',
			'type'        => 'title',
			'description' => '<hr>',
		),


		'main_descr'                => array(
			'title' => $main_desc,
			'type'  => 'title',
		),
		'title'                     => array(
			'title'       => __( 'Title', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'Card payment', 'viva-wallet-for-woocommerce' ),
		),
		'description'               => array(
			'title'       => __( 'Description', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees on checkout page.', 'viva-wallet-for-woocommerce' ),
			'default'     => __( 'Accept all major debit and credit cards', 'viva-wallet-for-woocommerce' ),
		),


		'instalments'               => array(
			'title'       => __( 'Installments', 'viva-wallet-for-woocommerce' ),
			'type'        => 'text',
			'description' => __( 'WARNING: Only available to Greek Viva Wallet accounts. <br>Example: 90:3,180:6<br>Order total 90 euro -> allow 0 and 3 installments <br>Order total 180 euro -> allow 0, 3 and 6 installments<br>Leave empty in case you do not want to offer installments.', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
		),

		'source_code'               => array(
			'title'       => __( 'Live Source Code List', 'viva-wallet-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Provides a list with all source codes that are set in your Viva Wallet banking app.', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
			'options'     => array(),
		),
		'test_source_code'          => array(
			'title'       => __( 'Demo Source Code List', 'viva-wallet-for-woocommerce' ),
			'type'        => 'select',
			'description' => __( 'Provides a list with all source codes that are set in the Viva Wallet banking app', 'viva-wallet-for-woocommerce' ),
			'default'     => '',
			'options'     => array(),
		),

		'logo_enabled'              => array(
			'title'   => __( 'Show Powered by Viva Wallet and logo on payment form', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable Viva Wallet logo on checkout page (default = yes).', 'viva-wallet-for-woocommerce' ),
			'default' => 'yes',
		),
		'cc_logo_enabled'           => array(
			'title'   => __( 'Show credit card logo on checkout page. ', 'viva-wallet-for-woocommerce' ),
			'type'    => 'checkbox',
			'label'   => __( 'Enable credit card logo in the form element input as the user types credit card number info (default = yes).', 'viva-wallet-for-woocommerce' ),
			'default' => 'yes',
		),
		'order_status'              => array(
			'title'       => __( 'Order status after successful payment.', 'viva-wallet-for-woocommerce' ),
			'description' => __( 'Your WooCommerce will be updated to this status after successful payment on Viva Wallet (default = completed).', 'viva-wallet-for-woocommerce' ),
			'default'     => 'completed',
			'type'        => 'select',
			'options'     => array(
				'completed'  => __( 'Completed', 'viva-wallet-for-woocommerce' ),
				'processing' => __( 'Processing', 'viva-wallet-for-woocommerce' ),
			),
		),

		// helpers.. dont delete..

		'source_error'              => array(
			'default' => '',
			'title'   => '',
			'type'    => 'title',
		),

	)
);
