<?php
namespace Send_App\Modules\Admin\Rest;

use Send_App\Core\Base\Route_Base;
use Send_App\Modules\Admin\Module;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Whats_New extends Route_Base {
	protected string $path = '/service/whatsnew';

	public function get_name(): string {
		return 'whats-new';
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
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		$module = Module::get_instance();

		$notifications_component = $module->get_component( 'Notificator' );
		$notifications = $notifications_component->get_notifications( true );

		if ( is_wp_error( $notifications ) ) {
			return $this->respond_error_json( $notifications );
		}

		return $this->respond_success_json( $notifications );
	}
}
