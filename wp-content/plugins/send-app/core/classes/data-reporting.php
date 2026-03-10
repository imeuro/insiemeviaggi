<?php
namespace Send_App\Core\Classes;

use Send_App\Core\Connect\Classes\Data as Connect_Data;
use Send_App\Core\Logger;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Data_Reporting
 *
 * Handles reporting client logs and diagnostic data to the EMP API
 */
class Data_Reporting {

	const ENDPOINT = 'client-logs';
	const THROTTLE_TRANSIENT_KEY = 'send_app_data_reporting_throttle';
	const THROTTLE_DURATION = 3 * HOUR_IN_SECONDS;

	/**
	 * Send client log to EMP API
	 *
	 * @param string $message Log message
	 * @param string $level Log level (error, info, warn)
	 * @param string $source Log source (plugin)
	 * @param array $context Additional context data
	 * @return bool|\WP_Error
	 */
	public function send_client_log( string $message, string $level = 'error', string $source = 'plugin', array $context = [] ) {
		if ( $this->is_throttled() ) {
			return true;
		}

		$payload = [
			'message' => $message,
			'level' => $level,
			'source' => $source,
			'context' => array_merge( $context, $this->get_diagnostic_data() ),
		];

		$response = $this->make_api_request( 'POST', self::ENDPOINT, $payload );

		if ( is_wp_error( $response ) ) {
			return $response;
		}

		$this->set_throttle();

		return true;
	}

	/**
	 * Check if we're currently throttled
	 *
	 * @return bool
	 */
	private function is_throttled(): bool {
		return (bool) get_transient( self::THROTTLE_TRANSIENT_KEY );
	}

	/**
	 * Set the throttle transient
	 *
	 * @return void
	 */
	private function set_throttle(): void {
		set_transient( self::THROTTLE_TRANSIENT_KEY, true, self::THROTTLE_DURATION );
	}

	/**
	 * Get diagnostic data
	 *
	 * @return array
	 */
	private function get_diagnostic_data(): array {
		return [
			'connection_data' => $this->get_connection_data(),
		];
	}

	/**
	 * Get non-sensitive connection data
	 *
	 * @return array
	 */
	private function get_connection_data(): array {
		return [
			'client_id' => Connect_Data::get_client_id(),
			'subscription_id' => Connect_Data::get_subscription_id(),
			'home_url' => Connect_Data::get_home_url(),
			'has_access_token' => ! empty( Connect_Data::get_access_token() ),
			'has_refresh_token' => ! empty( Connect_Data::get_refresh_token() ),
			'connect_mode' => \Send_App\Core\Connect\Classes\Config::CONNECT_MODE ?? 'unknown',
		];
	}

	/**
	 * Make API request using existing API class
	 *
	 * @param string $method
	 * @param string $endpoint
	 * @param array $body
	 * @return mixed|\WP_Error
	 */
	private function make_api_request( string $method, string $endpoint, array $body = [] ) {
		return \Send_App\Core\API::make_request( $method, $endpoint, $body );
	}

	/**
	 * Convenience method to send error logs
	 *
	 * @param string $message
	 * @param array $context
	 * @return bool|\WP_Error
	 */
	public function send_error( string $message, array $context = [] ) {
		return $this->send_client_log( $message, 'error', 'plugin', $context );
	}

	/**
	 * Convenience method to send warning logs
	 *
	 * @param string $message
	 * @param array $context
	 * @return bool|\WP_Error
	 */
	public function send_warning( string $message, array $context = [] ) {
		return $this->send_client_log( $message, 'warn', 'plugin', $context );
	}

	/**
	 * Convenience method to send info logs
	 *
	 * @param string $message
	 * @param array $context
	 * @return bool|\WP_Error
	 */
	public function send_info( string $message, array $context = [] ) {
		return $this->send_client_log( $message, 'info', 'plugin', $context );
	}
}
