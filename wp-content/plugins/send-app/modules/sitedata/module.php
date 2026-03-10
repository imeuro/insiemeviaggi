<?php
namespace Send_App\Modules\Sitedata;

use Send_App\Core\Base\Module_Base;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Module extends Module_Base {

	public static function get_name(): string {
		return 'sitedata';
	}

	public function components_list(): array {
		return [
			'Site_Data_Sync',
		];
	}
}
