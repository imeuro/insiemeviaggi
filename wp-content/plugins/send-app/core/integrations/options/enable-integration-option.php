<?php
namespace Send_App\Core\Integrations\Options;

use Send_App\Core\Options_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Enable_Integration_Option {

	const OPTION_NAME = 'send_app_enabled_integrations';

	public static function get_enabled_integration_modules(): array {
		return Options_Utils::get_option( self::OPTION_NAME, [] );
	}

	public static function add_enabled_integration_module( string $module_name ): void {
		$enabled_modules = self::get_enabled_integration_modules();

		if ( ! in_array( $module_name, $enabled_modules, true ) ) {
			$enabled_modules[] = $module_name;
			Options_Utils::update_option( self::OPTION_NAME, $enabled_modules );
			do_action( "send_app/{$module_name}/form/enabled" );
		}
	}

	public static function remove_enabled_integration_module( string $module_name ): void {
		$enabled_modules = self::get_enabled_integration_modules();

		$enabled_modules = array_values( array_diff( $enabled_modules, [ $module_name ] ) );

		Options_Utils::update_option( self::OPTION_NAME, $enabled_modules );
		do_action( "send_app/{$module_name}/form/disabled" );
	}
}
