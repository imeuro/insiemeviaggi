<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit();
}


/**
 * WC_Vivawallet_Source
 */
class WC_Vivawallet_Source {

	/**
	 * Create source
	 *
	 * @param string $token token.
	 * @param string $source_code source_code.
	 * @param string $test_mode test mode.
	 *
	 * @return string
	 */
	public static function create_source( $token, $source_code, $test_mode ) {
		$site_url = get_site_url();
		$scheme   = wp_parse_url( $site_url, PHP_URL_SCHEME );
		$domain   = wp_parse_url( $site_url, PHP_URL_HOST );
		$name     = $source_code;

		$body = array(
			'domain'      => $domain,
			'sourceCode'  => $name,
			'name'        => 'Viva Wallet For WC - ' . $domain,
			'pathSuccess' => 'wc-api/wc_vivawallet_native_success',
			'pathFail'    => 'wc-api/wc_vivawallet_native_fail',
		);

		$args = array(
			'method'  => 'POST',
			'headers' => array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
			),
			'body'    => wp_json_encode( $body ),
			'timeout' => WC_Vivawallet_Helper::REQUEST_TIMEOUT,
		);

		$url = WC_Vivawallet_Helper::get_api_url_endpoint( $test_mode, WC_Vivawallet_Helper::ENDPOINT_GET_SOURCES );

		$response = WC_Vivawallet_Helper::remote_request( $url, $args );

		if ( ! is_wp_error( $response ) && isset( $response['response']['code'] ) && 204 === $response['response']['code'] ) {
			WC_Vivawallet_Logger::log( "API CREATE SOURCE SUCCESS \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $response ) );
			return 'yes';
		} else {
			WC_Vivawallet_Logger::log( "API CREATE SOURCE FAILED \nURL: " . $url . "\nARGS: " . wp_json_encode( $args ) . "\nRESULT: " . wp_json_encode( $response ) );
			$res = json_decode( $response['body'] );
			return $res->message;
		}
	}
}
