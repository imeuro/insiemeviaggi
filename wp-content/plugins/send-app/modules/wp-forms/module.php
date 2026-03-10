<?php
namespace Send_App\Modules\WP_Forms;

use Send_App\Core\Integrations\Classes\Integration_Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * WPForms Integration Module
 *
 * Handles integration with WPForms plugin, providing form data and submission handling.
 */
class Module extends Integration_Module_Base {

	/**
	 * Get the integration name
	 *
	 * @return string The integration name
	 */
	public static function get_name(): string {
		return 'wpforms';
	}

	/**
	 * Get list of available integrations
	 *
	 * @return array Array of integration class names
	 */
	protected function integrations_list(): array {
		return [
			'All_Forms',
			'Form_View',
			'Form_Submit',
		];
	}

	/**
	 * Get list of available components
	 *
	 * @return array Array of component class names
	 */
	protected function components_list(): array {
		return [
			'Forms',
		];
	}

	/**
	 * Check if WPForms plugin is activated
	 *
	 * @return bool True if WPForms is active, false otherwise
	 */
	public function is_plugin_activated(): bool {
		$is_active = class_exists( '\WPForms\WPForms' ) || class_exists( '\WPForms_Pro' );
		return $is_active;
	}
}
