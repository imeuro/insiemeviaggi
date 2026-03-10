<?php

namespace Send_App\Modules\Woocommerce\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Send_App\Modules\Woocommerce\Classes\Store_Helper;

class Store {
	public function store_meta_data( array $meta_data ): array {
		$store_data = Store_Helper::get_store_metadata();
		$meta_data['store'] = $store_data;

		return $meta_data;
	}

	public function trigger_sitedata_update( $old_value = null, $new_value = null ): void {
		static $has_triggered = false;

		if ( $has_triggered ) {
			return;
		}

		$has_triggered = true;
		do_action( 'send_app/sitedata/updated' );
	}

	public function __construct() {
		add_filter( 'send_app/rest/sites/data', [ $this, 'store_meta_data' ] );
		add_action( 'woocommerce_settings_saved', [ $this, 'trigger_sitedata_update' ], 10, 0 );
	}
}
