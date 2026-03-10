<?php
namespace Send_App\Core\Connect;

use Send_App\Core\Base\Route_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Manager {

	/** @var Route_Base[] */
	private $routes = [];

	public function init() {
		$this->register_components();
		$this->register_routes();
	}

	public function get_routes(): array {
		return $this->routes;
	}

	private function register_components(): void {
		( new Components\Handler() );
	}

	private function register_routes(): void {
		$this->routes[] = new Rest\Authorize();
		$this->routes[] = new Rest\Reconnect();
		$this->routes[] = new Rest\Refresh_Token();
		$this->routes[] = new Rest\Switch_Domain();
	}
}
