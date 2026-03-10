<?php
/**
 * Logger
 *
 * @package   VivaComSmartForWooCommerce
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class WC_Vivacom_Smart_Logger
 *
 * @class   WC_Vivacom_Smart_Logger
 */
class WC_Vivacom_Smart_Logger {
	/**
	 * Payment method id
	 *
	 * @var WC_Logger
	 */
	public static $logger;

	const WC_LOG_FILENAME = 'wc-vivacom-smart';

	const WC_LOG_FILENAME_UPDATE = 'wc-vivacom-smart-update';

	/**
	 * Log messages
	 *
	 * @param string $message message.
	 * @param bool   $plugin_update true if log is during plugin update.
	 *
	 * @return void
	 */
	public static function log( $message, $plugin_update = false ) {
		if ( ! class_exists( 'WC_Logger' ) ) {
			return;
		}

		if ( empty( self::$logger ) ) {
			self::$logger = wc_get_logger();
		}

		$log_entry  = "\n" . 'Viva.com: ' . WC_VIVA_COM_SMART_VERSION . "\n";
		$log_entry .= '---Start Log---' . "\n" . $message . "\n" . '---End Log---' . "\n\n";

		if ( $plugin_update ) {
			self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME_UPDATE ) );
			return;
		}
		self::$logger->debug( $log_entry, array( 'source' => self::WC_LOG_FILENAME ) );
	}
}
