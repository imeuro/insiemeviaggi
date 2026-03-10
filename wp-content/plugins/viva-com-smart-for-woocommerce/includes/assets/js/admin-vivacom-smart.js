jQuery( document ).ready(
	function() {

		var demoCheckbox = jQuery( '#woocommerce_vivacom_smart_test_mode' );
		var demoMode     = demoCheckbox.is( ':checked' );

		var advanced_settings_checkbox = jQuery( '#woocommerce_vivacom_smart_advanced_settings_enabled' );
		var advancedSettingsEnabled    = advanced_settings_checkbox.is( ':checked' );

		jQuery('#woocommerce_vivacom_smart_brand_color').wpColorPicker();

		if ( demoMode ) {
			jQuery( '#woocommerce_vivacom_smart_client_id' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_client_secret' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_source_code' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_title_live' ).hide();
		} else {
			jQuery( '#woocommerce_vivacom_smart_demo_client_id' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_demo_client_secret' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_demo_source_code' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_title_demo' ).hide();
		}

		if ( advancedSettingsEnabled) {
			jQuery( '#woocommerce_vivacom_smart_main_descr' ).show();
			jQuery( '#woocommerce_vivacom_smart_title' ).closest( 'tr' ).show();
			jQuery( '#woocommerce_vivacom_smart_description' ).closest( 'tr' ).show();
			jQuery( '#woocommerce_vivacom_smart_order_status' ).closest( 'tr' ).show();
			jQuery( '#woocommerce_vivacom_smart_logo_enabled' ).closest( 'tr' ).show();
			jQuery( '#woocommerce_vivacom_smart_installments' ).closest( 'tr' ).show();
			if (demoCheckbox.is( ':checked' )) {
				jQuery( '#woocommerce_vivacom_smart_demo_source_code' ).closest( 'tr' ).show();
			} else {
				jQuery( '#woocommerce_vivacom_smart_source_code' ).closest( 'tr' ).show();
			}

		} else {
			jQuery( '#woocommerce_vivacom_smart_main_descr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_title' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_description' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_order_status' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_logo_enabled' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_installments' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_demo_source_code' ).closest( 'tr' ).hide();
			jQuery( '#woocommerce_vivacom_smart_source_code' ).closest( 'tr' ).hide();

		}

		advanced_settings_checkbox.on(
			'change',
			function() {
				jQuery( '#woocommerce_vivacom_smart_main_descr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_title' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_description' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_order_status' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_logo_enabled' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_installments' ).closest( 'tr' ).toggle();
				if (demoCheckbox.is( ':checked' )) {
					jQuery( '#woocommerce_vivacom_smart_demo_source_code' ).closest( 'tr' ).toggle();
				} else {
					jQuery( '#woocommerce_vivacom_smart_source_code' ).closest( 'tr' ).toggle();
				}
			}
		);

		demoCheckbox.on(
			'change',
			function(){
				jQuery( '#woocommerce_vivacom_smart_client_id' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_client_secret' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_demo_client_id' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_demo_client_secret' ).closest( 'tr' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_title_live' ).toggle();
				jQuery( '#woocommerce_vivacom_smart_title_demo' ).toggle();

				if ( advanced_settings_checkbox.is( ':checked' ) ) {
					jQuery( '#woocommerce_vivacom_smart_demo_source_code' ).closest( 'tr' ).toggle();
					jQuery( '#woocommerce_vivacom_smart_source_code' ).closest( 'tr' ).toggle();
				}
			}
		)

	}
)
