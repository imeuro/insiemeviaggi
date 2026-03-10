<?php
namespace Send_App\Core\Connect\Classes\Exceptions;

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

class Service_Exception extends \Exception {
	protected $message = 'Service Exception';
}
