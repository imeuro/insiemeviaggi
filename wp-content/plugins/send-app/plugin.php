<?php
namespace Send_App;

use Send_App\Core\Modules_Manager;
use Send_App\Core\Integrations_Manager;
use Send_App\Core\Rest\Rest_Manager;
use Send_App\Core\Connect\Manager as Connect_Manager;
	use Send_App\Core\Classes\Upgrade_Link;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Plugin {

	protected static ?Plugin $instance = null;

	protected Modules_Manager $modules_manager;

	protected Integrations_Manager $integrations_manager;

	protected Rest_Manager $rest_manager;

	protected Connect_Manager $connect_manager;

	protected Upgrade_Link $upgrade_link;

	public function __clone() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf( 'Cloning instances of the singleton "%s" class is forbidden.', get_class( $this ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'1.0.0'
		);
	}

	public function __wakeup() {
		_doing_it_wrong(
			__FUNCTION__,
			sprintf( 'Unserializing instances of the singleton "%s" class is forbidden.', get_class( $this ) ), // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
			'1.0.0'
		);
	}

	public static function instance(): self {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	public function activated( $plugin_file ): void {
		if ( ! defined( 'SEND_PLUGIN_BASE' ) || SEND_PLUGIN_BASE !== $plugin_file ) {
			return;
		}

		/**
		 * Fires after plugin activation
		 *
		 * @since 1.0.0
		 */
		do_action( 'send_app/activated' );
	}

	public function activate(): void {
		/**
		 * Fires on plugin activation
		 *
		 * @since 1.0.0
		 */
		do_action( 'send_app/activate' );
	}

	private function register_autoloader(): void {
		require_once SEND_PATH . 'autoloader.php';
		Autoloader::run();
	}

	private function core_components_create(): void {
		//init Plugin core components:
		$this->modules_manager = Modules_Manager::get_instance();
		$this->integrations_manager = Integrations_Manager::get_instance();
		$this->rest_manager = Rest_Manager::get_instance();
		$this->connect_manager = new Connect_Manager();
		$this->upgrade_link = new Upgrade_Link();
	}

	private function core_components_init(): void {
		$this->modules_manager->init();
		$this->integrations_manager->init();
		$this->rest_manager->init();
		$this->connect_manager->init();
		$this->upgrade_link->init();

		/**
		 * Fires after the plugin core components have been initialized.
		 *
		 * @param Plugin $plugin The main plugin instance.
		 */
		do_action( 'send_app/init', $this );
	}

	public function get_integrations_manager(): Integrations_Manager {
		return $this->integrations_manager;
	}

	private function __construct() {
		$this->register_autoloader();
		$this->core_components_create();
		$this->core_components_init();

		add_action( 'activated_plugin', [ $this, 'activated' ] );
		register_activation_hook( SEND_PLUGIN_BASE, [ $this, 'activate' ] );
	}
}

Plugin::instance();
