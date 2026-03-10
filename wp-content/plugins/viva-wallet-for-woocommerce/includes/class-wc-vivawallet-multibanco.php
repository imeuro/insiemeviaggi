<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}
/**
 * Class WC_Vivawallet_Multibanco
 *
 * @extends WC_Vivawallet_Apm
 *
 * @class   WC_Vivawallet_Multibanco
 * @package VivaWalletForWooCommerce
 */
class WC_Vivawallet_Multibanco extends  WC_Vivawallet_Apm {

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

		$this->id           = 'vivawallet-multibanco';
		$this->method_title = __( 'Viva Wallet Standard Checkout - Multibanco Payment Gateway', 'viva-wallet-for-woocommerce' );

		$this->vivawallet_id = 14;

		$this->icon = apply_filters( 'woocommerce_vivawallet_multibanco_icon', WC_Vivawallet_Helper::VW_CHECKOUT_MULTIBANCO_LOGO_URL );

		parent::__construct();

	}

}

