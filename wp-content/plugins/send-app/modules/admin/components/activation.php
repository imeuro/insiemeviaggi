<?php

namespace Send_App\Modules\Admin\Components;

use Send_App\Core\Utils;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Activation {
	public function activated() {
		if ( ! is_admin() ) {
			return;
		}

		wp_safe_redirect( self_admin_url( 'admin.php?page=' . Utils::get_admin_menu_slug() ) );
		exit;
	}

	public function check_activation_dependencies() {
		if ( ! is_admin() ) {
			return;
		}

		$emp_plugin_slug = 'send/send.php';
		if ( \is_plugin_active( $emp_plugin_slug ) ) {
			\deactivate_plugins( SEND_PLUGIN_BASE );

			$error_message = __( 'It looks like you already have a <strong>Send plugin</strong> installed.<br>To activate this version, please deactivate the existing <strong>Send plugin</strong> first.', 'send-app' );
			wp_die(
				wp_kses( $error_message, [
					'br' => [],
					'strong' => [],
				] ),
				esc_html__( 'Plugin Activation Error', 'send-app' ),
				[ 'back_link' => true ]
			);
		}
	}

	public function __construct() {
		add_action( 'send_app/activate', [ $this, 'check_activation_dependencies' ] );
		add_action( 'send_app/activated', [ $this, 'activated' ] );
	}
}
