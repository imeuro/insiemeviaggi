<?php

namespace Send_App\Core\Connect\Classes;

use Send_App\Core\Base\Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

abstract class Connect_Route_Base extends Route_Base {

	/**
	 * holds current authenticated user id
	 * @var int
	 */
	protected $current_user_id;

	public function get_endpoint(): string {
		return 'connect/' . $this->get_path();
	}

	public function get_user_from_request() {
		return get_user_by( 'id', $this->current_user_id );
	}

	public function post_permission_callback( \WP_REST_Request $request ): bool {
		return $this->get_permission_callback( $request );
	}

	public function get_permission_callback( \WP_REST_Request $request ): bool {
		$valid = $this->permission_callback( $request );

		return $valid && user_can( $this->current_user_id, 'manage_options' );
	}

	public function permission_callback( \WP_REST_Request $request ): bool {
		if ( ! parent::permission_callback( $request ) ) {
			return false;
		}

		// try to get current user
		$this->current_user_id = get_current_user_id();
		if ( $this->auth ) {
			return $this->current_user_id > 0;
		}

		return true;
	}
}
