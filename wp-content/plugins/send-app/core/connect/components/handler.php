<?php
namespace Send_App\Core\Connect\Components;

use Send_App\Core\Connect\Classes\{
	Config,
	Data,
	Grant_Types,
	Service,
	Utils,
};
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Handler {
	const PLG_TRANSIENT_KEY = 'elementor_send_app_campaign';

	private function should_handle_auth_code(): bool {
		global $plugin_page;

		$page_slug = explode( 'page=', Config::ADMIN_PAGE );

		$is_connect_admin_page = false;

		if ( ! empty( $page_slug[1] ) && $page_slug[1] === $plugin_page ) {
			$is_connect_admin_page = true;
		}

		if ( ! $is_connect_admin_page && Config::ADMIN_PAGE === $plugin_page ) {
			$is_connect_admin_page = true;
		}

		if ( ! $is_connect_admin_page ) {
			return false;
		}

		// Silence phpcs warning as nonce verification is used.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( empty( $code ) || empty( $state ) ) {
			return false;
		}

		return true;
	}

	private function validate_nonce( $state ) {
		if ( ! wp_verify_nonce( $state, Config::STATE_NONCE ) ) {
			wp_die( 'Invalid state' );
		}
	}

	public function handle_auth_code() {
		if ( ! $this->should_handle_auth_code() ) {
			return;
		}

		// Silence phpcs warning as nonce verification is used.
		$code = isset( $_GET['code'] ) ? sanitize_text_field( wp_unslash( $_GET['code'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$state = isset( $_GET['state'] ) ? sanitize_text_field( wp_unslash( $_GET['state'] ) ) : null; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		// Check if the state is valid
		$this->validate_nonce( $state );

		try {
			// Exchange the code for an access token and store it
			Service::get_token( Grant_Types::AUTHORIZATION_CODE, $code ); // Makes sure we won't stick in the mismatch limbo

			Data::set_home_url();

			do_action( Config::APP_PREFIX . '/connect/on_connected' ); // Redirect to the redirect URI
		} catch ( \Throwable $t ) {
			Logger::error( 'Unable to handle auth code: ' . $t->getMessage() );
		}

		wp_redirect( Utils::get_redirect_uri() ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect

		exit;
	}

	public function maybe_add_plg_to_authorize_url( $authorize_url ): string {
		$utm_params = [];

		$campaign = get_transient( self::PLG_TRANSIENT_KEY );
		if ( empty( $campaign ) ) {
			return $authorize_url;
		}

		foreach ( [ 'source', 'medium', 'campaign' ] as $key ) {
			if ( ! empty( $campaign[ $key ] ) ) {
				$utm_params[ 'utm_' . $key ] = $campaign[ $key ];
			}
		}

		if ( ! empty( $utm_params ) ) {
			$authorize_url = add_query_arg( $utm_params, $authorize_url );
		}

		return $authorize_url;
	}

	public function __construct() {
		add_action( 'admin_init', [ $this, 'handle_auth_code' ] );
		add_filter( 'send_app/connect/authorize_url', [ $this, 'maybe_add_plg_to_authorize_url' ] );
	}
}
