(function(window, document, $, undefined){

	var woogtin = {};

	woogtin.init = function() {

		if( window.wooGtinVars.is_composite && window.wooGtinVars.is_composite === '1' ) {
			woogtin.compositeVariationListener();
		} else {
			woogtin.singleVariationListener();
		}

	}

	// listens for variation change, sends ID as necessary
	woogtin.singleVariationListener = function() {

		$( ".single_variation_wrap" ).on( "show_variation", function ( event, variation ) {
		    // Fired when the user selects all the required dropdowns / attributes
		    // and a final variation is selected / shown
		    if( variation.variation_id ) {
			    var id = variation.variation_id;
			    $(".wo_productcode span.stl_codenum").text(window.wooGtinVars.variation_gtins[id]);
			}
		} );

	}

	// listens for variation change, sends ID as necessary
	woogtin.compositeVariationListener = function() {

		$( ".single_variation_wrap" ).on( "show_variation", function ( event, variation ) {
		    // Fired when the user selects all the required dropdowns / attributes
		    // and a final variation is selected / shown

		    if( variation.variation_id ) {
			    var id = variation.variation_id;

			    $(".wo_productcode span.stl_codenum").text( window.wooGtinVars.composite_variation_pcm[id] );
			}
		} );

	}

	woogtin.init();

	window.wooGtin = woogtin;

})(window, document, jQuery);