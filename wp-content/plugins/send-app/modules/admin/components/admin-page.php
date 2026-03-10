<?php
namespace Send_App\Modules\Admin\Components;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

use Send_App\Core\API;
use Send_App\Core\Utils;
use Send_App\Modules\Admin\Module;
use Send_App\Core\Connect\Classes\{
	Data,
	Config as Connect_Config,
	Utils as Connect_Utils,
};

class Admin_Page {
	const MENU_SLUG = 'send-app';

	const ADMIN_APP_SCRIPT_HANDLE = 'send-app-admin-app';
	const ADMIN_APP_STYLE_HANDLE = 'send-app-admin-app';

	private function is_send_app_menu_page(): bool {
		$screen = get_current_screen();

		$len = strlen( self::MENU_SLUG );
		if ( 0 === $len ) {
			return true;
		}

		return ( substr( $screen->id, -$len ) === self::MENU_SLUG );
	}

	public function app_register_assets(): void {
		if ( ! $this->is_send_app_menu_page() ) {
			return;
		}

		$script_asset_path = SEND_ASSETS_PATH . 'js/send-app-admin-app.asset.php';
		if ( ! file_exists( $script_asset_path ) ) {
			throw new \Error( 'You must to run `npm run build`. ' . $script_asset_path ); //@phpcs:ignore WordPress.Security.EscapeOutput.ExceptionNotEscaped
		}

		$script_asset = require $script_asset_path;
		$app_js_url = SEND_ASSETS_URL . 'js/send-app-admin-app.js';

		wp_register_script(
			self::ADMIN_APP_SCRIPT_HANDLE,
			$app_js_url,
			$script_asset['dependencies'],
			$script_asset['version'],
			true
		);
		wp_set_script_translations( self::ADMIN_APP_SCRIPT_HANDLE, 'send-app' );

		$config = [
			'baseRestUrl' => get_rest_url(),
			'iframeUrl' => self::get_iframe_url(),
			'nonce' => wp_create_nonce( 'wp_rest' ),
			'isConnected' => Connect_Utils::is_connected(),
			'isValidRedirectUri' => Connect_Utils::is_valid_home_url(),
			'storeCurrency' => $this->get_store_currency_code(),
			'headers' => [
				'x-elementor-send' => SEND_VERSION,
				'x-elementor-apps' => Connect_Config::APP_NAME,
			],
			'siteData' => [
				'subscriptionId' => Data::get_subscription_id(),
				'clientId' => Data::get_client_id(),
				'pluginVersion' => SEND_VERSION,
			],
		];

		if ( isset( $config['isConnected'] ) && $config['isConnected'] ) {
			$config['headers']['Authorization'] = 'Bearer ' . Data::get_access_token();
		}

		\wp_add_inline_script( self::ADMIN_APP_SCRIPT_HANDLE, 'const eSendAdminAppConfig = ' . wp_json_encode( $config ), 'before' );

		$style_asset_path = SEND_ASSETS_PATH . 'css/send-app-admin-app.asset.php';
		if ( ! file_exists( $style_asset_path ) ) {
			throw new \Error( 'You must to run `npm run build`.' );
		}

		$style_asset = require $style_asset_path;
		$app_css_url = SEND_ASSETS_URL . 'css/send-app-admin-app.css';

		wp_register_style(
			self::ADMIN_APP_STYLE_HANDLE,
			$app_css_url,
			$style_asset['dependencies'],
			$style_asset['version']
		);
	}

	private static function get_iframe_url() {
		return apply_filters( 'send_app_admin_app_iframe_url', Module::ADMIN_APP_IFRAME_URL );
	}

	private function get_store_currency_code(): string {
		if ( function_exists( 'get_woocommerce_currency' ) ) {
			return \get_woocommerce_currency();
		}

		return 'USD';
	}

	public function add_menu(): void {
		$screen_hook_suffix = add_menu_page(
			__( 'Send', 'send-app' ),
			__( 'Send', 'send-app' ),
			'manage_options',
			Utils::get_admin_menu_slug(),
			[ __CLASS__, 'render_page' ],
			SEND_ASSETS_URL . 'images/send-icon.svg',
			45
		);

		add_action( "admin_print_scripts-{$screen_hook_suffix}", [ __CLASS__, 'app_enqueue_assets' ] );
	}

	public static function render_page(): void {
		?>
		<div class="wrap">
			<div class="send-app-admin-app"></div>
		</div>
		<?php
	}

	public static function app_enqueue_assets(): void {
		wp_enqueue_script( self::ADMIN_APP_SCRIPT_HANDLE );
		wp_enqueue_style( self::ADMIN_APP_STYLE_HANDLE );
	}

	public static function handle_on_connected() {
		if ( ! Connect_Utils::is_connected() ) {
			return;
		}

		API::register_connected_site();

		do_action( 'send_app/site/on_registered' );
	}

	public function view_details( $links, $plugin_file ) {
		if ( SEND_PLUGIN_BASE === $plugin_file ) {
			$plugin_uri = esc_url( 'https://send2.co/?utm_source=plugin-row-metas&utm_campaign=plugin-uri&utm_medium=wp-admin' );
			$label = esc_html__( 'Visit Send website', 'send-app' );
			$links['pluginuri'] = '<a href="' . $plugin_uri . '" target="_blank" rel="noopener noreferrer" aria-label="' . $label . '">' . $label . '</a>';
		}
		return $links;
	}

	public function __construct() {
		add_filter( 'plugin_row_meta', [ $this, 'view_details' ], 10, 2 );
		add_action( 'admin_enqueue_scripts', [ $this, 'app_register_assets' ] );
		add_action( 'admin_menu', [ $this, 'add_menu' ] );

		add_action( Connect_Config::APP_PREFIX . '/connect/on_connected', [ __CLASS__, 'handle_on_connected' ] );
	}
}
