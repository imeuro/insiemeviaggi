<?php
namespace Send_App\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Send_App\Core\Connect\Classes\Config;
use Send_App\Core\Connect\Classes\Exceptions\Service_Exception;
use Send_App\Core\Connect\Classes\Service as Connect_Service;
use Send_App\Core\Connect\Classes\Data as Connect_Data;
use Send_App\Core\Connect\Classes\Utils as Connect_Utils;
use Send_App\Core\Classes\Data_Reporting;

final class API {

	const BASE_URL = 'https://emp.send2app.com/emp/api/';

	private static bool $refreshed = false;

	public static function get_site_info(): array {
		return [
			// Which API version is used.
			'app_version' => SEND_VERSION,
			// Which language to return.
			'site_lang' => \get_bloginfo( 'language' ),
			// site to connect
			'site_url' => \trailingslashit( home_url() ),
			// current user
			'client_id' => \get_current_user_id(),
			// User Agent
			'user_agent' => ! empty( $_SERVER['HTTP_USER_AGENT'] )
				? \sanitize_text_field( \wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
				: 'Unknown',
			'webhook_url' => \rest_url( 'send/v1/webhook' ),
		];
	}

	public static function make_request( $method, $endpoint, $body = [], array $headers = [], $file = false, $file_name = '' ) {
		$additional_headers = [
			'x-elementor-send' => SEND_VERSION,
			'x-elementor-apps' => Config::APP_NAME,
			'Content-Type' => 'application/json',
		];

		$site_id = Connect_Data::get_client_id();

		if ( ! empty( $site_id ) ) {
			$additional_headers['site-id'] = $site_id;
		} else {
			// Prevent form/woo events from being sent without site-id
			$is_form_event = strpos( $endpoint, 'events/form/' ) !== false;
			$is_woo_event = strpos( $endpoint, 'woo/' ) !== false;
			$is_client_logs = strpos( $endpoint, 'client-logs' ) !== false;

			if ( ( $is_form_event || $is_woo_event ) && ! $is_client_logs ) {
				$event_type = $is_form_event ? 'form' : 'woo';

				// Log the prevention
				$data_reporting = new Data_Reporting();
				$data_reporting->send_client_log(
					"Prevented sending {$event_type} event without site-id",
					'warn',
					'plugin',
					[
						'endpoint' => $endpoint,
						'method' => $method,
						'event_type' => $event_type,
						'has_access_token' => ! empty( Connect_Data::get_access_token() ),
						'is_connected' => Connect_Utils::is_connected(),
					]
				);

				return;
			}
		}

		// Replace the headers array
		$headers = array_replace_recursive( $additional_headers, $headers );

		$headers = array_replace_recursive(
			$headers,
			self::is_connected() ? self::generate_authentication_headers( $endpoint ) : []
		);

		$body = array_replace_recursive( $body, self::get_site_info() );

		$args = [
			'timeout' => 100,
			'headers' => $headers,
			'body'    => wp_json_encode( $body ),
		];

		try {
			return self::request(
				$method,
				$endpoint,
				$args,
			);
		} catch ( Service_Exception $e ) {
			return new \WP_Error( $e->getCode(), $e->getMessage(), $args );
		}
	}

	private static function get_remote_url( $endpoint ): string {
		return self::get_base_url() . $endpoint;
	}

	private static function get_base_url() {
		return apply_filters( 'send_app_api_base_url', self::BASE_URL );
	}

	private static function is_connected(): bool {
		return Connect_Utils::is_connected();
	}

	private static function add_bearer_token( $headers ) {
		if ( self::is_connected() ) {
			$headers['Authorization'] = 'Bearer ' . Connect_Data::get_access_token();
		}
		return $headers;
	}

	private static function generate_authentication_headers( $endpoint ): array {
		$headers = [
			'endpoint' => $endpoint,
		];

		return self::add_bearer_token( $headers );
	}

	/**
	 * @throws Service_Exception
	 */
	private static function request( $method, $endpoint, $args = [] ) {
		$args['method'] = $method;

		$response = \wp_safe_remote_request(
			self::get_remote_url( $endpoint ),
			$args
		);

		if ( \is_wp_error( $response ) ) {
			$message = $response->get_error_message();

			return new \WP_Error(
				$response->get_error_code(),
				is_array( $message ) ? join( ', ', $message ) : $message
			);
		}

		$body = \wp_remote_retrieve_body( $response );

		$response_code = (int) \wp_remote_retrieve_response_code( $response );

		if ( ! $response_code ) {
			return new \WP_Error( 500, 'No Response' );
		}

		// If the token is invalid (401 Unauthorized), refresh it and try again once only.
		if ( ! self::$refreshed && 401 === $response_code ) {
			Connect_Service::refresh_token();
			self::$refreshed = true;
			$args['headers'] = self::add_bearer_token( $args['headers'] );
			return self::request( $method, $endpoint, $args );
		}

		// Return with no content on successfull delettion of domain from service.
		if ( 204 === $response_code ) {
			return true;
		}

		// Server sent a success message without content.
		$body = ( 'null' === $body ) ? true : json_decode( $body );

		if ( false === $body ) {
			return new \WP_Error( 422, 'Wrong Server Response' );
		}

		if ( 200 !== $response_code && 201 !== $response_code ) {
			// In case $as_array = true.
			$message = $body->message ?? \wp_remote_retrieve_response_message( $response );
			$message = is_array( $message ) ? wp_json_encode( $message ) : $message;
			$code = isset( $body->code ) ? (int) $body->code : $response_code;

			return new \WP_Error( $code, $message );
		}

		return $body;
	}

	public static function register_connected_site(): bool {
		$token = Connect_Data::get_access_token();

		if ( ! $token ) {
			Logger::warn( 'No access token found' );
			return false;
		}

		try {
			$response = self::make_request( 'POST', 'sites', [
				'token' => $token,
			] );
		} catch ( \Exception $e ) {
			Logger::error( 'Error registering site on collection: ' . $e->getMessage() );

			return false;
		}
		if ( is_wp_error( $response ) ) {
			Logger::debug( $response->get_error_code() . ': "' . $response->get_error_message() . '" | data: ' . wp_json_encode( $response->get_error_data() ) );
			Logger::error( 'Failed to create site on collection: ' . wp_json_encode( $response->get_error_messages() ) );

			return false;
		}
		if ( ! empty( $response->subscriptionId ) ) { //@phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
			Connect_Data::set_subscription_id( $response->subscriptionId ); //@phpcs:ignore WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
		}
		Logger::info( 'Site registered on collection' );

		return true;
	}
}
