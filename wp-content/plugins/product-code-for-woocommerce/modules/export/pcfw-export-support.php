<?php


if (!defined('ABSPATH')) {

	exit;
}

class PCFW_Wc_Export_Filter {



	public function __construct() {

		//Export

		add_filter('woocommerce_product_export_column_names', array($this, 'add_export_column'));

		add_filter('woocommerce_product_export_product_default_columns', array($this, 'add_export_column'));

		add_filter('woocommerce_product_export_product_column_product_code', array($this, 'add_export_data_product_code'), 10, 2);

		add_filter('woocommerce_product_export_product_column_product_code_second', array($this, 'add_export_data_product_code_second'), 10, 2);



		// Import

		add_filter('woocommerce_csv_product_import_mapping_options', array($this, 'add_export_column'), 10);

		add_filter('woocommerce_product_import_pre_insert_product_object', array($this, 'process_import'), 10, 2);
	}

	public function add_export_column($columns) {



		$columns['product_code'] = 'Product Code';

		$columns['product_code_second'] = 'Second Product Code';



		return $columns;
	}



	public function process_import($object, $data) {

		if (!empty($data['product_code'])) {

			$object->update_meta_data('_product_code', $data['product_code']);
		}

		if (!empty($data['product_code_second'])) {

			$object->update_meta_data('_product_code_second', $data['product_code_second']);
		}





		return $object;
	}

	public function add_export_data_product_code($value, $product) {

		return $product->get_meta('_product_code', true, 'edit');
	}

	public function add_export_data_product_code_second($value, $product) {

		return $product->get_meta('_product_code_second', true, 'edit');
	}
}

return new PCFW_Wc_Export_Filter();
