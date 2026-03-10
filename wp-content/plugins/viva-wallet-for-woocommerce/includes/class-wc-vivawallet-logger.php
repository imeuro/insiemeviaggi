<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Vivawallet_Logger.
 */
class WC_Vivawallet_Logger {

	/**
	 * $logger.
	 *
	 * @var class
	 */
	public static $logger;

	const WC_LOG_FILENAME = 'wc-vivawallet-native';

	const WC_LOG_FILENAME_UPDATE = 'wc-vivawallet-native_update';

	/**
	 * Log.
	 *
	 * @param string $message Message.
	 * @param bool   $for_plugin_update Use log for Updating the plugin.
	 */
	public static function log( $message, $for_plugin_update = false ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		$log_entry  = "\n" . 'Viva Wallet Version: ' . WC_VIVAWALLET_VERSION;
		$log_entry .= "\n--- LOG STARTS HERE:\n";
		$log_entry .= $message;
		$log_entry .= "\n--- LOG ENDS HERE \n\n";

		if ( $for_plugin_update ) {
			self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME_UPDATE ) );
			return;
		}
		self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME ) );
	}
}
