<?php
namespace Send_App\Modules\Admin;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Base\Module_Base;
use Send_App\Core\Rest\Rest_Manager;
use Send_App\Modules\Admin\Rest\Whats_New;

class Module extends Module_Base {

	const ADMIN_APP_IFRAME_URL = 'https://emp-platform.send2app.com';

	public static function get_name(): string {
		return 'admin';
	}

	public function components_list(): array {
		return [
			'Admin_Page',
			'Activation',
			'Notificator',
			'Feedback',
		];
	}

	public function register_rest_routes( Rest_Manager $rest_manager ) {
		$rest_manager->register( new Whats_New() );
	}

	public function register_hooks(): void {
		parent::register_hooks();

		add_action( 'send_app/rest/register', [ $this, 'register_rest_routes' ] );
	}
}
