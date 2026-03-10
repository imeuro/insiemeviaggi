<?php
namespace Send_App\Core\Rest;

use Send_App\Core\Base\Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * class Rest_Manager
 *
 * Manage REST endpoints.
 */
class Rest_Manager {

	/** @var Route_Base[] */
	private $routes = [];

	private static ?Rest_Manager $instance = null;

	/**
	 * @return Rest_Manager
	 */
	public static function get_instance(): Rest_Manager {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * @param Route_Base $route
	 *
	 * @return bool
	 */
	public function register( Route_Base $route ): bool {
		$route_name = $route->get_name();

		if ( isset( $this->routes[ $route_name ] ) ) {
			return false;
		}

		$this->routes[ $route_name ] = $route;

		return true;
	}

	/**
	 * @return Route_Base[]
	 */
	public function get_routes(): array {
		return $this->routes;
	}

	public function get_route( string $route_name ): ?Route_Base {
		return $this->routes[ $route_name ] ?? null;
	}

	public function init() {
		$this->register_routes();
	}

	private function get_default_routes_list(): array {
		return [
			'Integrations',
			'Integrations_Forms',
			'Integrations_Forms_All',
			'Integrations_Forms_Single',
			'Integrations_Single',
			'Integrations_Actions',
			'Gallery_Images',
			'Legacy\\Integrations_Connect_Disconnect',
			'Legacy\\Integrations_Forms_Status',
			'Legacy\\Integration_Status_Route',
		];
	}

	private function register_routes(): void {
		foreach ( $this->get_default_routes_list() as $route_name ) {
			// turn dash-separated into camel case namespace
			$class_name = '\\Send_App\\Core\\Rest\\Routes\\' . $route_name;

			/**
			 * @var Route_Base $class_name
			 */
			$this->routes[ $route_name ] = new $class_name();
		}

		do_action( 'send_app/rest/register', $this );
	}

	// Singleton
	private function __construct() {}
}
