<?php
namespace Send_App\Core\Rest\Routes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Send_App\Core\Base\Route_Base;
use Send_App\Core\Integrations_Manager;
use Send_App\Core\Logger;
use Send_App\Plugin;

class Integrations extends Route_Base {
	public string $path = 'integrations';

	public const NONCE_NAME = 'wp_rest';
	public const LEVEL = 'level';

	public function get_methods(): array {
		return [ 'GET' ];
	}

	public function get_name(): string {
		return 'integrations';
	}

	public function GET( \WP_REST_Request $request ) {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$integration_manager = Plugin::instance()->get_integrations_manager();

		try {
			$integrations = $integration_manager->get_integration_modules( Integrations_Manager::INTEGRATION_STATUS_ALL );
			$integrations_info = [];
			foreach ( $integrations as $integration ) {
				$integrations_info[ $integration::get_name() ] = $integration->get_info();
			}
			return $this->respond_success_json( $integrations_info );
		} catch ( \Throwable $t ) {
			Logger::error( '[' . $t->getCode() . '] ' . $t->getMessage() );

			return $this->respond_error_json( new \WP_Error(
				$t->getCode(),
				$t->getMessage(),
				[ 'status' => 500 ]
			) );
		}
	}
}
