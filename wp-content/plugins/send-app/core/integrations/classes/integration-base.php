<?php

namespace Send_App\Core\Integrations\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

/**
 * class Base
 *
 * base class for Integration Components
 **/
abstract class Integration_Base {

	/**
	 * sync data to Send service
	 *
	 * @return bool
	 */
	abstract public function sync(): bool;

	/**
	 * register any integration related hooks
	 *
	 * @return void
	 */
	public function register_hooks(): void {}
}
