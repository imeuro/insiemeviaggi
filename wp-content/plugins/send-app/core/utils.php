<?php

namespace Send_App\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Utils {
	public static function prefix_by_env( string $option_name ): string {
		if ( defined( 'ELEMENTOR_SEND_DEV_ENV' ) ) {
			return ELEMENTOR_SEND_DEV_ENV . '_' . $option_name;
		}

		return $option_name;
	}

	public static function get_current_host(): string {
		return home_url();
	}

	public static function get_admin_menu_slug(): string {
		return 'send-app';
	}

	public static function get_page_url( $post_id ): string {
		$page_url = \get_permalink( $post_id );

		if ( empty( $page_url ) ) {
			return '';
		}

		return \wp_parse_url( $page_url, PHP_URL_PATH );
	}

	public static function debug_on(): bool {
		return defined( 'WP_DEBUG' ) && WP_DEBUG;
	}
}
