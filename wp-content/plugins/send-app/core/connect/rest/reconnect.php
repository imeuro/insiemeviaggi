<?php
namespace Send_App\Core\Connect\Rest;

use Send_App\Core\Connect\Classes\{
	Connect_Route_Base,
	Service
};

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Reconnect extends Connect_Route_Base {
	public string $path = 'reconnect';

	public function get_methods(): array {
		return [ 'POST' ];
	}

	public function get_name(): string {
		return 'reconnect';
	}

	public function POST() {
		$valid = $this->verify_capability();
		if ( is_wp_error( $valid ) ) {
			return $valid;
		}

		try {
			Service::reconnect();
			return $this->respond_success_json();
		} catch ( \Throwable $t ) {
			return $this->respond_error_json( [
				'message' => $t->getMessage(),
				'code' => 'internal_server_error',
			] );
		}
	}
}
