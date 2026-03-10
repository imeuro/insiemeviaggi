<?php
namespace Send_App\Modules\CF7;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Integrations\Classes\Integration_Module_Base;

/**
 * class Module
 **/
class Module extends Integration_Module_Base {

	public static function get_name(): string {
		return 'cf7';
	}

	/**
	 * @return array
	 */
	protected function integrations_list(): array {
		return [
			'Form_Submit',
			'Form_View',
			'All_Forms',
		];
	}

	/**
	 * @return array
	 */
	protected function components_list(): array {
		return [
			'Forms',
		];
	}

	public function is_plugin_activated(): bool {
		return function_exists( 'wpcf7' );
	}
}
