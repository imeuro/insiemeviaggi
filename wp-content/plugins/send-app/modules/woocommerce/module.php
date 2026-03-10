<?php
namespace Send_App\Modules\Woocommerce;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Integrations\Classes\Async_Integration_Base;
use Send_App\Core\Integrations\Classes\Integration_Module_Base;
use Send_App\Core\Rest\Rest_Manager;
use Send_App\Modules\Woocommerce\Rest\Sync_Woo_Route;

/**
 * class Module
 **/
class Module extends Integration_Module_Base {
	/**
	 * @inheritDoc
	 */
	public static function get_name(): string {
		return 'woocommerce';
	}

	/**
	 * component_list
	 * @return string[]
	 */
	protected function integrations_list(): array {
		return [
			'Orders',
			'Products',
			'Cart',
		];
	}

	protected function components_list(): array {
		return [
			'Store',
		];
	}

	public function is_plugin_activated(): bool {
		return function_exists( 'WC' );
	}

	public function register_rest_routes( Rest_Manager $rest_manager ) {
		$rest_manager->register( new Sync_Woo_Route() );
	}

	/**
	 * @return bool | \WP_Error
	 */
	public function ready_to_sync() {
		/** @var Async_Integration_Base $integration */
		foreach ( $this->integrations as $integration ) {
			if ( $integration->is_sync_in_progress() ) {
				return new \WP_Error( 'sync_error', $integration::ENTITY . ' sync in progress', [ 'status' => 409 ] );
			}
		}

		return true;
	}

	public function register_hooks(): void {
		parent::register_hooks();

		add_action( 'send_app/rest/register', [ $this, 'register_rest_routes' ] );
	}
}
