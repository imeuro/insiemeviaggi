<?php
namespace Send_App\Modules\Woocommerce\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Store_Helper {
	public static function get_store_metadata(): array {
		return [
			'currency' => [
				'code' => \get_woocommerce_currency(),
				'symbol' => \get_woocommerce_currency_symbol(),
			],
			'address' => [
				'address_line_1' => \WC()->countries->get_base_address(),
				'address_line_2' => \WC()->countries->get_base_address_2(),
				'city' => \WC()->countries->get_base_city(),
				'zip_code' => \WC()->countries->get_base_postcode(),
				'state' => \WC()->countries->get_base_state(),
			],
		];
	}
}
