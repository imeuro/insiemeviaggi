<?php
namespace Send_App\Modules\Elementor;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Integrations\Classes\Integration_Module_Base;

/**
 * class Module
 **/
class Module extends Integration_Module_Base {

	public static function get_name(): string {
		return 'elementor';
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
		return defined( 'ELEMENTOR_VERSION' ) && defined( 'ELEMENTOR_PRO_VERSION' );
	}

	public function get_elementor_plugin(): ?\Elementor\Plugin {
		return \Elementor\Plugin::instance();
	}

	public function is_elementor_preview(): bool {
		$elementor_preview = $this->get_elementor_plugin()->preview;
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return ( $elementor_preview->is_preview() || $elementor_preview->is_preview_mode() || \Elementor\Utils::get_super_global_value( $_GET, 'preview' ) );
	}

	public function clear_elementor_cache_on_update(): void {
		if ( class_exists( '\Elementor\Plugin' ) ) {
			$this->get_elementor_plugin()->files_manager->clear_cache();
		}
	}

	public function register_hooks(): void {
		parent::register_hooks();
		add_action( 'send_app/elementor/form/enabled', [ $this, 'clear_elementor_cache_on_update' ] );
		add_action( 'send_app/elementor/form/disabled', [ $this, 'clear_elementor_cache_on_update' ] );
	}
}
