<?php
namespace Send_App\Core\Connect\Classes;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly
}

class Config {
	const APP_NAME = 'esend';
	const APP_PREFIX = 'send_app';
	const BASE_URL = 'https://my.elementor.com/connect';
	const ADMIN_PAGE = 'send-app';
	const APP_TYPE = 'app_empma';
	const SCOPES = 'openid offline_access';
	const STATE_NONCE = 'send-app-auth-nonce';
	const CONNECT_MODE = 'site';
}
