<?php
namespace Send_App\Core\Integrations\Options;

use Send_App\Core\Options_Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Disabled_Forms_Option {

	const PREFIX_OPTION_NAME = 'send_app_disabled_forms_';

	public static function get_all( $integration ): array {
		return Options_Utils::get_option( self::PREFIX_OPTION_NAME . $integration, [] );
	}

	public static function add( string $form_id, string $integration ): void {
		$disabled_forms = self::get_all( $integration );

		if ( ! in_array( $form_id, $disabled_forms, true ) ) {
			$disabled_forms[] = $form_id;
			Options_Utils::update_option( self::PREFIX_OPTION_NAME . $integration, $disabled_forms );
			do_action( "send_app/form/{$integration}/integration/disabled" );
		}
	}

	public static function remove( string $form_id, string $integration ): void {
		$disabled_forms = self::get_all( $integration );

		$disabled_forms = array_diff( $disabled_forms, [ $form_id ] );

		Options_Utils::update_option( self::PREFIX_OPTION_NAME . $integration, $disabled_forms );
		do_action( "send_app/form/{$integration}/integration/enabled" );
	}
}
