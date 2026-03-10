<?php
namespace Send_App\Core;

use Send_App\Core\Base\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * class Modules_Manager
 *
 * holds all system nodules and initializes any required external libraries.
 */
final class Modules_Manager {

	private static ?Modules_Manager $instance = null;

	/**
	 * @var Module_Base[]
	 */
	private array $modules = [];

	public function register_modules(): void {
		foreach ( $this->get_module_list() as $module_name ) {
			// turn dash-separated into camel case namespace
			$class_name = str_replace( '-', ' ', $module_name );
			$class_name = str_replace( ' ', '', ucwords( $class_name ) );
			$class_name = '\\Send_App\\Modules\\' . $class_name . '\Module';

			/**
			 * @var Module_Base $class_name
			 */
			if ( $class_name::is_active() ) {
				$this->modules[ $module_name ] = $class_name::get_instance();
			}
		}
	}


	/**
	 * Modules namespaces list
	 *
	 * @return string[]
	 */
	private function get_module_list(): array {
		return [
			'Elementor',
			'Wp_Forms',
			'Ninja_Forms',
			'Woocommerce',
			'Admin',
			'CF7',
			'Gravityforms',
			'Sitedata',
		];
	}

	public function get_modules( string $module_name = '' ) {
		if ( $module_name ) {
			return $this->modules[ $module_name ] ?? null;
		}

		return $this->modules;
	}

	public function include_external_libraries() {
		require_once SEND_PATH . 'vendor/woocommerce/action-scheduler/action-scheduler.php';
	}

	public function init() {
		foreach ( $this->modules as $module ) {
			$module->init();
		}
	}

	public static function get_instance(): Modules_Manager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Singleton
	 */
	private function __construct() {
		$this->include_external_libraries();
		$this->register_modules();
	}
}
