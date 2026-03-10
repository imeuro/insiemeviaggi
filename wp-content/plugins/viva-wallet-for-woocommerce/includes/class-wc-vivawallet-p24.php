<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/**
 * Class WC_Vivawallet_P24
 *
 * @extends WC_Vivawallet_Apm
 *
 * @class   WC_Vivawallet_P24
 * @package VivaWalletForWooCommerce
 */
class WC_Vivawallet_P24 extends WC_Vivawallet_Apm {

	/**
	 * Payment method id
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Method Title
	 *
	 * @var string
	 */
	public $method_title;

	/**
	 * Viva wallet id
	 *
	 * @var int
	 */
	public $vivawallet_id;

	/**
	 * Icon
	 *
	 * @var int
	 */
	public $icon;

	/**
	 * Constructor.
	 */
	public function __construct() {

		$this->id           = 'vivawallet-p24';
		$this->method_title = __( 'Viva Wallet Standard Checkout - P24 Payment Gateway', 'viva-wallet-for-woocommerce' );

		$this->vivawallet_id = 11;

		$this->icon = apply_filters( 'woocommerce_vivawallet_p24_icon', WC_Vivawallet_Helper::VW_CHECKOUT_P24_LOGO_URL );

		parent::__construct();
	}

}

