<?php
namespace Send_App\Modules\Sitedata\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Sitedata_Helper {
	public static function get_site_metadata(): array {
		static $site_data = null;
		if ( ! is_null( $site_data ) ) {
			return $site_data;
		}

		$timezone = \wp_timezone();
		$gmt_offset = $timezone->getOffset( new \DateTime() ) / HOUR_IN_SECONDS;
		$timezone_name = $timezone->getName();

		if ( false !== strpos( strtolower( $timezone_name ), 'utc' ) || false !== strpos( $timezone_name, ':' ) ) {
			$timezone_name = '';
		}

		$site_data = [
			'site' => [
				'language' => get_locale(),
				'timezone' => [
					'name' => $timezone_name,
					'gmt_offset' => $gmt_offset,
				],
				'name' => get_bloginfo( 'name' ),
				'byline' => get_bloginfo( 'description' ),
			],
			'store' => null,
		];

		/**
		 * Filter the site data to allow expansions by the modules
		 *
		 * @param array $site_data The site data.
		 */
		$site_data = apply_filters( 'send_app/rest/sites/data', $site_data );

		return $site_data;
	}
}
