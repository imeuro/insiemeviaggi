<?php
namespace Send_App\Modules\Woocommerce\Rest;

use Send_App\Core\Base\Route_Base;
use Send_App\Core\Integrations\Classes\Integration_Module_Base;
use Send_App\Core\Integrations\Options\Enable_Integration_Option;
use Send_App\Modules\Woocommerce\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Sync_Woo_Route extends Route_Base {
	protected string $path = '/sync-woo';

	public function get_name(): string {
		return 'sync-woo';
	}

	/**
	 * @inheritDoc
	 */
	public function get_methods(): array {
		return [
			'GET',
		];
	}

	/**
	 * @param \WP_REST_Request $request
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function GET( \WP_REST_Request $request ) {
		/** @var Integration_Module_Base $module */
		$module = Module::get_instance();
		// Enable the integration:
		Enable_Integration_Option::add_enabled_integration_module( $module::get_name() );

		$ready = $module->ready_to_sync();
		if ( is_wp_error( $ready ) ) {
			return $this->respond_error_json( $ready );
		}

		// Trigger sync
		$result = $module->sync();

		if ( is_wp_error( $result ) ) {
			return $this->respond_error_json( $result );
		}

		// Backward compatibility:
		$result['contacts'] = true;

		return $this->respond_success_json( $result );
	}
}
