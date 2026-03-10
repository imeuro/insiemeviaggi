<?php
declare(strict_types=1);

namespace Send_App\Core\Classes;

use Send_App\Core\Connect\Classes\Data as Connect_Data;
use Send_App\Core\Connect\Classes\Config;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Upgrade_Link {
	private const DEFAULT_BASE_URL = 'https://my.elementor.com/upgrade-send';

	public function init(): void {
		$this->register();
	}

	public function register(): void {
		if ( ! defined( 'SEND_PLUGIN_BASE' ) ) {
			return;
		}

		add_filter( 'plugin_action_links_' . SEND_PLUGIN_BASE, [ $this, 'add_upgrade_link' ] );
		add_filter( 'network_admin_plugin_action_links_' . SEND_PLUGIN_BASE, [ $this, 'add_upgrade_link' ] );
	}

	public function add_upgrade_link( array $links ): array {
		$url = $this->get_upgrade_url();
		if ( empty( $url ) ) {
			return $links;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			return $links;
		}

		$label = esc_html__( 'Upgrade', 'send-app' );
		$style = (string) apply_filters( Config::APP_PREFIX . '_upgrade_link_style', 'font-weight:600;color:#6C4CF5;' );
		$links[] = sprintf(
			'<a href="%s" target="_blank" rel="noopener noreferrer" style="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $style ),
			$label
		);

		return $links;
	}

	private function get_upgrade_url(): string {
		$raw_subscription_id = Connect_Data::get_subscription_id();
		$subscription_id = (string) ( empty( $raw_subscription_id ) ? '' : $raw_subscription_id );

		$base_url = self::DEFAULT_BASE_URL;
		$args = [
			'utm_source' => 'send-panel',
			'utm_medium' => 'wp-dash',
			'utm_campaign' => 'send-plugins-upgrade',
		];
		if ( '' !== $subscription_id ) {
			$args['subscription_id'] = $subscription_id;
		}
		$url = add_query_arg( $args, $base_url );

		return (string) apply_filters( Config::APP_PREFIX . '_upgrade_url', $url, $subscription_id );
	}
}
