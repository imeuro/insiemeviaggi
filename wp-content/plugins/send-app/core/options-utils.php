<?php
namespace Send_App\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Options_Utils {
	public static function get_option( string $option_name, $default = false ) {
		$with_prefix = Utils::prefix_by_env( $option_name );

		return \get_option( $with_prefix, $default );
	}

	public static function update_option( string $option_name, $new_value, $autoload = null ): bool {
		$with_prefix = Utils::prefix_by_env( $option_name );

		return \update_option( $with_prefix, $new_value, $autoload );
	}

	public static function delete_option( string $option_name ): bool {
		$with_prefix = Utils::prefix_by_env( $option_name );

		return \delete_option( $with_prefix );
	}
}
