<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}

/**
 * Class WC_Vivawallet_Credentials
 *
 * @class   WC_Vivawallet_Credentials
 */
class WC_Vivawallet_Credentials {

	/**
	 * Viva settings
	 *
	 * @var array
	 */
	public static $viva_settings;

	/**
	 * Test mode
	 *
	 * @var string
	 */
	public static $test_mode;


	/**
	 * Construct.
	 */
	public function __construct() {

		self::$viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );

	}

	/**
	 * Get_client_data.
	 *
	 * @param bool $force force reload data.
	 *
	 * @return array
	 */
	public static function get_client_data( $force = false ) {

		if ( $force || empty( self::$viva_settings ) ) {
			// update settings from DB.
			self::$viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );
		}

		$client_id     = isset( self::$viva_settings['client_id'] ) ? self::$viva_settings['client_id'] : '';
		$client_secret = isset( self::$viva_settings['client_secret'] ) ? self::$viva_settings['client_secret'] : '';
		$source_code   = isset( self::$viva_settings['source_code'] ) ? self::$viva_settings['source_code'] : '';

		$demo_client_id     = isset( self::$viva_settings['test_client_id'] ) ? self::$viva_settings['test_client_id'] : '';
		$demo_client_secret = isset( self::$viva_settings['test_client_secret'] ) ? self::$viva_settings['test_client_secret'] : '';
		$demo_source_code   = isset( self::$viva_settings['test_source_code'] ) ? self::$viva_settings['test_source_code'] : '';

		$test_mode = isset( self::$viva_settings['test_mode'] ) ? self::$viva_settings['test_mode'] : 'no';

		$client_id     = ( 'yes' === $test_mode ) ? $demo_client_id : $client_id;
		$client_secret = ( 'yes' === $test_mode ) ? $demo_client_secret : $client_secret;
		$source_code   = ( 'yes' === $test_mode ) ? $demo_source_code : $source_code;

		return array(
			'test_mode'     => $test_mode,
			'client_id'     => $client_id,
			'client_secret' => $client_secret,
			'source_code'   => $source_code,
		);
	}

	/**
	 * Get authentication token
	 *
	 * @param string|null $test_mode yes/no.
	 * @param string      $scope     scope of token.
	 * @param bool        $force     force reload credentials.
	 *
	 * @return string
	 */
	public static function get_authentication_token( $test_mode, $scope = 'back', $force = false ) {
		$time             = time();
		$timeout_duration = 600; // 10 min.

		if ( null === WC()->session ) {
			WC()->session = new WC_Session_Handler();
			WC()->session->init();
		}
		$key_suffix                = ( 'yes' === $test_mode ? 'DEMO' : 'LIVE' ) . '_' . strtoupper( $scope );
		$credentials_session_key   = "VW_AUTHENTICATION_CREDENTIALS_$key_suffix";
		$last_activity_session_key = "VW_AUTHENTICATION_LAST_ACTIVITY_$key_suffix";

		if ( $force ) {
			self::clear_sessions();
		}

		if ( ! $force ) { // if force is false check the timestamp.
			if ( null === WC()->session->get( $last_activity_session_key ) ) { // if session var is not set force is true.
				$force = true;
			} elseif ( ( $time - WC()->session->get( $last_activity_session_key ) ) > $timeout_duration ) {
				$force = true; // force to reload if time exceeded.
			}
		}

		if ( $force ) {
			// update settings from DB.
			self::$viva_settings = get_option( 'woocommerce_vivawallet_native_settings', array() );
		}

		if ( empty( WC()->session->get( $credentials_session_key ) ) || $force ) {
			if ( 'yes' === $test_mode ) {
				$client_id     = isset( self::$viva_settings['test_client_id'] ) ? self::$viva_settings['test_client_id'] : '';
				$client_secret = isset( self::$viva_settings['test_client_secret'] ) ? self::$viva_settings['test_client_secret'] : '';
			} else {
				$client_id     = isset( self::$viva_settings['client_id'] ) ? self::$viva_settings['client_id'] : '';
				$client_secret = isset( self::$viva_settings['client_secret'] ) ? self::$viva_settings['client_secret'] : '';
			}

			$token = WC_Vivawallet_Helper::get_token( $client_id, $client_secret, $test_mode, $scope );
			if ( ! empty( $token ) ) {
				WC()->session->set( $credentials_session_key, $token );
				WC()->session->set( $last_activity_session_key, $time );
			}
		} else {
			$token = WC()->session->get( $credentials_session_key );
		}

		return $token;
	}

	/**
	 * Clear all session keys related to credentials
	 */
	private static function clear_sessions() {
		foreach ( array( 'DEMO', 'LIVE' ) as $environment ) {
			foreach ( array( 'BACK', 'FRONT' ) as $scope ) {
				foreach ( array( 'VW_AUTHENTICATION_CREDENTIALS', 'VW_AUTHENTICATION_LAST_ACTIVITY' ) as $key ) {
					$session_key = "{$key}_{$environment}_{$scope}";
					WC()->session->set( $session_key, null );
				}
			}
		}
	}
}

new WC_Vivawallet_Credentials();
