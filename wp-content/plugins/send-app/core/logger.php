<?php
namespace Send_App\Core;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Logger {
	public const LEVEL_ERROR = 'error';
	public const LEVEL_WARN = 'warn';
	public const LEVEL_INFO = 'info';
	public const LEVEL_DEBUG = 'debug';

	private static function actual_log( string $log_level, $message ): void {
		if ( self::maybe_use_wc_logger( $log_level, $message ) ) {
			return;
		}

		$backtrace = debug_backtrace(); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_debug_backtrace

		$class    = $backtrace[2]['class'] ?? null;
		$type     = $backtrace[2]['type'] ?? null;
		$function = $backtrace[2]['function'];

		if ( $class ) {
			$message = '[Send]: ' . $log_level . ' in ' . "$class$type$function()" . ': ' . $message;
		} else {
			$message = '[Send]: ' . $log_level . ' in ' . "$function()" . ': ' . $message;
		}

		error_log( $message ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
	}

	private static function maybe_use_wc_logger( $log_level, $message ): bool {
		static $wc_logger;
		if ( ! class_exists( 'WC_Logger' ) ) {
			return false;
		}

		if ( ! $wc_logger ) {
			$wc_logger = wc_get_logger();
		}

		$wc_logger->log( $log_level, $message );

		return true;
	}

	public static function log( string $log_level, $message ): void {
		self::actual_log( $log_level, $message );
	}

	public static function error( $message ): void {
		self::actual_log( self::LEVEL_ERROR, $message );
	}

	public static function warn( $message ): void {
		self::actual_log( self::LEVEL_WARN, $message );
	}

	public static function info( $message ): void {
		self::actual_log( self::LEVEL_INFO, $message );
	}

	public static function debug( $message ): void {
		self::actual_log( self::LEVEL_DEBUG, $message );
	}
}
