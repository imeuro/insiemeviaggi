<?php
namespace Send_App\Modules\Admin\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

use Elementor\WPNotificationsPackage\V120\Notifications as Notifications_SDK;
use Send_App\Modules\Admin\Classes\Notification_Template;

class Notificator {
	const WHATSNEW_STYLE_HANDLE = 'send-app-notifications';

	private ?Notifications_SDK $notificator = null;

	public function get_notifications( $force_request = false ) {
		return $this->notificator->get_notifications_by_conditions( $force_request );
	}

	public function print_notifications_dialog() {
		$notifications = $this->get_notifications();

		$notification_template = new Notification_Template( $notifications );
		$notification_template->print_dialog();
	}

	public function plugin_row_meta( $plugin_meta, $plugin_file ) {
		if ( SEND_PLUGIN_BASE === $plugin_file ) {
			$plugin_meta['whatsnew'] = '<a href="#" id="send-app-notifications-dialog-open" aria-label="' . esc_html__( 'What\'s New', 'send-app' ) . '">' . esc_html__( 'What\'s New', 'send-app' ) . '</a>';
		}

		return $plugin_meta;
	}

	public function enqueue_assets() {
		$style_asset_path = SEND_ASSETS_PATH . 'css/send-app-notifications.asset.php';
		if ( ! file_exists( $style_asset_path ) ) {
			throw new \Error( 'You must to run `npm run build`.' );
		}

		$style_asset = require $style_asset_path;

		wp_enqueue_style(
			self::WHATSNEW_STYLE_HANDLE,
			SEND_ASSETS_URL . 'css/send-app-notifications.css',
			$style_asset['dependencies'],
			$style_asset['version']
		);
	}

	public function __construct() {
		if ( ! class_exists( 'Elementor\WPNotificationsPackage\V120\Notifications' ) ) {
			require_once SEND_PATH . 'vendor/autoload.php';
		}

		$this->notificator = new Notifications_SDK( [
			'app_name' => 'send',
			'app_version' => SEND_VERSION,
			'short_app_name' => 'snd',
			'app_data' => [
				'plugin_basename' => SEND_PLUGIN_BASE,
			],
		] );

		add_action( 'admin_footer', [ $this, 'print_notifications_dialog' ] );
		add_filter( 'plugin_row_meta', [ $this, 'plugin_row_meta' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}
}
