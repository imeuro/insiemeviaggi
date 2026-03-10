<?php
declare(strict_types=1);

namespace Send_App\Core\Integrations\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Integrations\Options\Enable_Integration_Option;
use Send_App\Core\Integrations_Manager;
use Send_App\Core\Base\Module_Base;

/**
 * class Integration_Module
 *
 * Base class for all modules that hold & manage integration components.
 */
abstract class Integration_Module_Base extends Module_Base {

	/**
	 * @var Integration_Base[]
	 */
	protected array $integrations = [];

	/**
	 * If the Integration plugin is installed and active.
	 *
	 * @return boolean
	 */
	abstract public function is_plugin_activated(): bool;

	/**
	 * If the Integration is connected.
	 *
	 * @param bool $with_plugin_activated
	 *
	 * @return boolean
	 */
	public function is_enabled( bool $with_plugin_activated = false ): bool {
		$activated = ! $with_plugin_activated || $this->is_plugin_activated();
		$enabled_modules = Enable_Integration_Option::get_enabled_integration_modules();

		return $activated && in_array( static::get_name(), $enabled_modules, true );
	}

	/**
	 * Get all the Integration information.
	 *
	 * @return array
	 */
	public function get_info(): array {
		return [
			'active'  => $this->is_plugin_activated(),
			'enabled' => $this->is_enabled(),
		];
	}

	/**
	 * Check if the integration is ready to sync.
	 *
	 * @return bool|\WP_Error
	 */
	public function ready_to_sync() {
		return true;
	}

	/**
	 * Sync all integrations.
	 *
	 * @return array
	 */
	public function sync(): array {
		$sync_result = [];
		foreach ( $this->integrations as $id => $integration ) {
			$sync_result[ strtolower( $id ) ] = $integration->sync();
		}

		return $sync_result;
	}

	/**
	 * Register REST API hooks for the module.
	 *
	 * @return void
	 */
	public function register_rest_hooks(): void {
		// Add the module specific endpoints for enabling & disabling the entire module-integration.
		// To enable/disable specific integrations with the module, tweak the route's "name" part and register the filter.
		$actions_filter_prefix = 'send_app/rest/integrations/' . static::get_name() . '/actions/';

		add_filter( $actions_filter_prefix . 'enable', [ $this, 'enable' ], 10, 2 );
		add_filter( $actions_filter_prefix . 'disable', [ $this, 'disable' ], 10, 2 );
		add_filter( $actions_filter_prefix . 'sync', [ $this, 'sync_handler' ], 10, 2 );

		add_filter( 'send_app/rest/integrations/' . static::get_name(), [ $this, 'status_handler' ], 10, 2 );

		//Legacy:
		$legacy_actions_filter_prefix = 'send_app/rest/integrations/' . static::get_name();
		add_filter( $legacy_actions_filter_prefix . '/connect', [ $this, 'enable' ], 10, 2 );
		add_filter( $legacy_actions_filter_prefix . '/disconnect', [ $this, 'disable' ], 10, 2 );
	}

	/**
	 * Register hooks for the module.
	 *
	 * @return void
	 */
	public function register_hooks(): void {
		parent::register_hooks();
		add_action( 'send_app/integration-modules/register', [ $this, 'register' ] );
		add_action( 'rest_api_init', [ $this, 'register_rest_hooks' ], 9 );
	}

	/**
	 * Register the module with the integrations manager.
	 *
	 * @param Integrations_Manager $manager
	 * @return void
	 */
	public function register( Integrations_Manager $manager ): void {
		$this->register_integrations();

		// TODO: maybe register only if enabled?

		$manager->register( $this );
	}

	/**
	 * Add an integration to the module.
	 *
	 * @param string $id Component ID.
	 * @param Integration_Base $instance An instance of the component.
	 * @return void
	 */
	public function add_integration( string $id, Integration_Base $instance ): void {
		$this->integrations[ $id ] = $instance;
	}

	/**
	 * Get an integration by ID.
	 *
	 * @param string $id
	 * @return Integration_Base|null
	 */
	public function get_integration( string $id ): ?Integration_Base {
		return $this->integrations[ $id ] ?? null;
	}

	/**
	 * Enable the module.
	 *
	 * @param mixed $response
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function enable( $response, $request ) {
		// Check the integration plugin is activated.
		if ( ! $this->is_plugin_activated() ) {
			return new \WP_Error(
				'integration_not_activated',
				/* translators: %s the Module name */
				sprintf( __( 'The module %s plugin is not activated', 'send-app' ), static::get_name() )
			);
		}

		$with_sync = $request->get_param( 'withSync' );
		// Check the integration is not already enabled.
		if ( empty( $with_sync ) && $this->is_enabled() ) {
			return new \WP_Error(
				'integration_enabled_already',
				/* translators: %s the Module name */
				sprintf( __( 'The module %s is already enabled', 'send-app' ), static::get_name() )
			);
		}

		// Enable the integration in the options.
		Enable_Integration_Option::add_enabled_integration_module( static::get_name() );

		if ( $with_sync ) {
			$this->sync();
		}

		return true;
	}

	/**
	 * Disable the module.
	 *
	 * @param mixed $response
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function disable( $response, $request ) {
		// Check the integration is not already disabled.
		if ( ! $this->is_enabled() ) {
			return new \WP_Error(
				'integration_disabled_already',
				/* translators: %s the Module name */
				sprintf( __( 'The module %s is already disabled', 'send-app' ), static::get_name() )
			);
		}

		// Enable the integration in the options.
		Enable_Integration_Option::remove_enabled_integration_module( static::get_name() );

		return true;
	}

	/**
	 * Handle sync request.
	 *
	 * @param mixed $response
	 * @param \WP_REST_Request $request
	 * @return bool|\WP_Error
	 */
	public function sync_handler( $response, $request ) {
		// Check the integration is not already disabled.
		if ( ! $this->is_enabled() ) {
			return new \WP_Error(
				'integration_disabled',
				/* translators: %s the Module name */
				sprintf( __( 'The module %s is disabled', 'send-app' ), static::get_name() )
			);
		}

		// Sync.
		$ready = $this->ready_to_sync();
		if ( is_wp_error( $ready ) ) {
			return $ready;
		}

		$this->sync();

		return true;
	}

	/**
	 * Handle status request.
	 *
	 * @param mixed $response
	 * @param \WP_REST_Request $request
	 * @return array
	 */
	public function status_handler( $response, $request ): array {
		return $this->get_info();
	}

	/**
	 * Get all integrations.
	 *
	 * @return Integration_Base[]
	 */
	public function get_integrations(): array {
		return $this->integrations;
	}

	/**
	 * Adds an array of integration-components.
	 * Assumes namespace structure contains `\Integrations\`
	 *
	 * @access protected
	 *
	 * @param ?array $integration_ids => integration's class name.
	 *
	 * @return void
	 */
	protected function register_integrations( ?array $integration_ids = null ): void {
		if ( empty( $integration_ids ) ) {
			$integration_ids = $this->integrations_list();
		}

		$namespace = static::namespace_name();
		foreach ( $integration_ids as $integration_id ) {
			$class_name = $namespace . '\\Integrations\\' . $integration_id;
			$integration = new $class_name();
			$this->add_integration( $integration_id, $integration );
			$integration->register_hooks();
		}
	}

	/**
	 * Get list of integration IDs.
	 *
	 * @return array
	 */
	protected function integrations_list(): array {
		return [];
	}

	/**
	 * Register hooks for all integrations.
	 *
	 * @return void
	 */
	public function register_integration_hooks(): void {
		foreach ( $this->integrations as $integration ) {
			$integration->register_hooks();
		}
	}
}
