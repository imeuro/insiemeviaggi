<?php
namespace Send_App\Core;

use Send_App\Core\Integrations\Classes\Integration_Module_Base;
use Send_App\Core\Integrations\Options\Enable_Integration_Option;
use Send_App\Core\Integrations\Rest;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * class Integrations_Manager
 *
 * Manage the modules that hold integrations.
 */
class Integrations_Manager {
	const INTEGRATION_STATUS_ENABLED = 'enabled';
	const INTEGRATION_STATUS_ACTIVE_AND_ENABLED = 'active_enabled';
	const INTEGRATION_STATUS_DISABLED = 'disabled';
	const INTEGRATION_STATUS_ALL = 'all';

	/**
	 * @var Integration_Module_Base[]
	 */
	private array $integration_modules = [];

	public function init() {
		$this->register_integration_modules();
	}

	public function register_integration_modules(): void {
		do_action( 'send_app/integration-modules/register', $this );
	}

	public function register( Integration_Module_Base $integration_module ): bool {
		$name = $integration_module::get_name();

		if ( isset( $this->integration_modules[ $name ] ) ) {
			return false;
		}

		$this->integration_modules[ $name ] = $integration_module;

		return true;
	}

	public function get_integration_module( $name ): ?Integration_Module_Base {
		return $this->integration_modules[ $name ] ?? null;
	}

	/**
	 * @param string $filter_by -- values: 'all' | 'enabled' | 'disabled' | 'active_enabled (default))
	 *
	 * @return Integration_Module_Base[]
	 */
	public function get_integration_modules( string $filter_by = self::INTEGRATION_STATUS_ACTIVE_AND_ENABLED ): array {
		$integration_modules = [];

		switch ( $filter_by ) {
			case self::INTEGRATION_STATUS_ALL:
				$integration_modules = $this->integration_modules;
				break;
			case self::INTEGRATION_STATUS_ENABLED:
				$integration_modules = array_filter( $this->integration_modules, function ( $module ) {
					return $module->is_enabled();
				} );
				break;
			case self::INTEGRATION_STATUS_ACTIVE_AND_ENABLED:
				$integration_modules = array_filter( $this->integration_modules, function ( $module ) {
					return $module->is_enabled( true );
				} );
				break;
			case self::INTEGRATION_STATUS_DISABLED:
				$integration_modules = array_filter( $this->integration_modules, function ( $module ) {
					return ! $module->is_enabled();
				} );
				break;
			default:
				break;
		}

		return $integration_modules;
	}

	public function get_default_level(): string {
		return self::INTEGRATION_STATUS_ENABLED;
	}

	private function register_hooks(): void {
		$enabled_integrations = Enable_Integration_Option::get_enabled_integration_modules();

		foreach ( $this->integration_modules as $integration_module ) {
			/** @var Integration_Module_Base $integration_module */
			if ( $integration_module->is_plugin_activated() && in_array( $integration_module::get_name(), $enabled_integrations, true ) ) {
				$integration_module->register_integration_hooks();
			}
		}
	}

	public static function get_instance(): Integrations_Manager {
		static $instance;

		if ( is_null( $instance ) ) {
			$instance = new self();
		}

		return $instance;
	}

	/**
	 * Singleton
	 */
	protected function __construct() {}
}
