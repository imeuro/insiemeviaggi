/**
 * Viva Wallet Standard Checkout
 *
 * @package WC_Vivawallet
 */

jQuery(
	function ($) {

		var requiresCvv                  = true;
		var default_payment_jquery_cards = [];
		var orderId                      = vivawallet_params.orderId;
		var savePaymentMethod            = false;
		var maxInstallmentsForCard       = 1;
		var oldInstallmentsValue         = 1;
		var chargeToken;
		var validCard;
		var cardNumber = '';
		var installments;
		var noticesShown = false;

		var blockSettings = {
			message: null,
			overlayCSS: {
				background: '#fff',
				opacity: 0.6,
			}
		};

		function submit_error(error_message){
			$( '.woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' ).remove();
			checkout_form.prepend( '<div class="woocommerce-NoticeGroup woocommerce-NoticeGroup-checkout">' + error_message + '</div>' ); // eslint-disable-line max-len.
			checkout_form.removeClass( 'processing' ).unblock();
			checkout_form.find( '.input-text, select, input:checkbox' ).trigger( 'validate' ).blur();
			scroll_to_notices();

			$( document.body ).trigger( 'checkout_error' );
		}

		function scroll_to_notices(){
			noticesShown      = true;
			var scrollElement = $( '.woocommerce-NoticeGroup-updateOrderReview, .woocommerce-NoticeGroup-checkout, .woocommerce-error, .woocommerce-message' );
			if ( ! scrollElement.length ) {
				scrollElement = $( 'form.checkout' );
			}
			if ( scrollElement.length ) {
				$( 'html, body' ).animate(
					{
						scrollTop: ( scrollElement.offset().top - 100 )
					},
					1000
				);
			}
		}

		function getAjaxURL( endpoint, replaceValue ) {
			return vivawallet_params.ajax_url
				.toString()
				.replace( '%%endpoint%%', replaceValue + endpoint );
		}

		var tokenRequest = function (e) {

			checkout_form.addClass( 'processing' ).block( blockSettings );

			if ( isVivaPaymentSelected() && false === checkCreditCardFields() ) {
				submit_error( '<ul class="woocommerce-error" role="alert"><li>' + vivawallet_params.labelForCCerror + '</li></ul>' );
				return false;
			}

			// fix values to send.
			var name = '';

			if ( ! isAddPaymentMethodForm() ) { // when in normal checkout form.
				if ( 0 < $( '#billing_name' ).length ) {
					name = $( '#billing_name' ).val().trim()
				} else if ( 0 < $( '#billing_first_name' ).length && 0 < $( '#billing_last_name' ).length  ) {
					var firstNameVal = $( '#billing_first_name' ).val().trim();
					var lastNameVal  = $( '#billing_last_name' ).val().trim();
					name             = firstNameVal + ' ' + lastNameVal;
				} else {
					submit_error( '<ul class="woocommerce-error" role="alert"><li>' + vivawallet_params.labelForNameNULLerror + '</li></ul>' );
					return false;
				}
			}

			installments = $( "#drpInstallments" ).val();

			if ( isNaN( installments ) || 1 >= installments || installments === null) {
				installments = "1";
			}

			$( 'input[data-vp="cardholder"]' ).val( name );
			$( 'input[data-vp="accessToken"]' ).val( vivawallet_params.token );

			if ( isAddPaymentMethodForm() ) {
				// when not in simple checkout we dont do the first ajax call to process the checkout form.

				if ( ! checkTermsCheckbox() ) {
					// check if terms exist and handle them.
					submit_error( '<ul class="woocommerce-error" role="alert"><li>' + vivawallet_params.labelForTermsError + '</li></ul>' );
					return false;
				}

				$( 'input[data-vp="cardholder"]' ).val( vivawallet_params.orderCustomerName );
				// $( 'input[data-vp="userEmail"]' ).val( vivawallet_params.orderCustomerEmail );

				if ( isVivaPaymentSelected() ) {
					display3Dsecure();
				} else {
					createTransaction();
				}

			} else {
				// when in simple checkout do the ajax checkout call.
				createOrder();
			}
			return false;
		};

		function checkTermsCheckbox(){
			var target = $( 'input#terms' );
			if ( target.length && ! target.is( ':checked' ) ) {
				return false;
			}
			return true;
		}

		function createOrder(){

			// this function will so an ajax call to wc-ajax-checkout will validate the checkout form against woo validation and will create the order (with status pending payment). then it will come back to 3d validate.

			var data = checkout_form.serialize();
			data    += '&nativeCheckoutForm=true';

			$.ajax(
				{
					url:  getAjaxURL( 'checkout', '' ),
					// (use that wc-ajax=checkout url to send via post the data of the Checkout form fields and validate form)
					type: "POST",
					data: data,
					success: function( result ) {

						if (result.resultApi === 'success') {
							orderId                             = result.orderId
							vivawallet_params.checkoutSecurity  = result.checkoutSecurity
							vivawallet_params.cartTotalSecurity = result.cartTotalSecurity
							vivawallet_params.returnUrl         = result.returnUrl
							savePaymentMethod                   = result.saveCard

							if ( isVivaPaymentSelected() ) {
								updatePrice( display3Dsecure );
							} else {
								updatePrice( createTransaction );
							}
						}
						if (result.result === 'failure' || result.result === 'error') {
							// Trigger update in case we need a fresh nonce.
							if ( true === result.refresh ) {
								checkout_form.removeClass( 'processing' ).unblock();
								$( document.body ).trigger( 'update_checkout' );
							}
							if ( result.messages ) {
								submit_error( result.messages );
								return;
							}
							if ( result.message ) {
								submit_error( '<ul class="woocommerce-error" role="alert"><li>' + result.message + '</li></ul>' );
							}
						}
					},
					error: function() {
						submit_error( '<ul class="woocommerce-error" role="alert"><li>' + vivawallet_params.labelForAJAXerror + '</li></ul>' );
					}
				}
			);
		}

		function updatePrice(  callback ){
			var data = {
				security: vivawallet_params.cartTotalSecurity
			};

			$.ajax(
				{
					type:    'POST',
					data: data,
					url:     getAjaxURL( 'get_cart_total_amount', 'wc_vivawallet_' ),
					success: function( response ) {
						if (response.length !== 0 ) {
							vivawallet_params.amount = Number( response );
							if (callback !== null ) {
								callback();
							}

						}
					}
				}
			);
		}

		function display3Dsecure(){

			VivaPayments.cards.setup(
				{
					authToken: vivawallet_params.token,
					baseURL: vivawallet_params.scriptUrl,
					cardHolderAuthOptions: {
						cardHolderAuthPlaceholderId: 'VWpaymentContainer',
						cardHolderAuthInitiated: function () {
							$( '#VWsecureModal' ).css( {"display":"flex"} )
						},
						cardHolderAuthFinished: function () {
							$( '#VWsecureModal' ).hide();
						}
					}
				}
			);

			VivaPayments.cards.requestToken(
				{
					amount:  Number( vivawallet_params.amount ) * 100, // amount is in currency's minor unit of measurement.
					installments: installments,
				}
			).done(
				function ( responseData ) {
					chargeToken = responseData.chargeToken;
					createTransaction();
				}
			).fail(
				function ( responseData ) {
					console.log( 'Here is the reason it failed: ' + responseData.Error.toString() );
					console.dir( responseData );
				}
			);
		}

		function prepareData(){

			var cardNumber      = $( 'input[data-vp="cardnumber"]' ).val()
			cardNumber          = cardNumber.replace( / /g, '' ); // remove spaces.
			var cardNumberLast4 = cardNumber.substring( cardNumber.length - 4 ); // get last 4 digits.
			var expDateObj      = $.payment.cardExpiryVal( $( 'input[data-vp="expdate"]' ).val() );

			var expiryMonth = expDateObj.month
			var expiryYear  = expDateObj.year

			var data = {
				url: getAjaxURL( 'process_payment', 'wc_vivawallet_' ),
				security: vivawallet_params.checkoutSecurity,
				accessToken: vivawallet_params.token,
				chargeToken: chargeToken,
				cardNumberLast4 : cardNumberLast4,
				expiryMonth: expiryMonth,
				expiryYear: expiryYear,
				cardType: $.payment.cardType( cardNumber ),
				installments: installments,
				savePaymentMethod: savePaymentMethod,
				orderId: orderId,
				returnUrl: vivawallet_params.returnUrl,
				isUserLoggedIn: vivawallet_params.isUserLoggedIn
			}

			if ( isIDealSelected() ) {
				data['paymentMethodId'] = '10';
			}
			if ( isP24Selected() ) {
				data['paymentMethodId'] = '11';
			}
			if ( isPayUSelected() ) {
				data['paymentMethodId'] = '13';
			}
			if ( isMultibancoSelected() ) {
				data['paymentMethodId'] = '14';
			}
			if ( isGiropaySelected() ) {
				data['paymentMethodId'] = '15';
			}
			if ( isDirectPaySelected() ) {
				data['paymentMethodId'] = '16';
			}
			if ( isEpsSelected() ) {
				data['paymentMethodId'] = '17';
			}
			if ( isWeChatPaySelected() ) {
				data['paymentMethodId'] = '18';
			}
			if ( isBitPayPaySelected() ) {
				data['paymentMethodId'] = '19';
			}

			// when we have a change of subscription payment method.
			// or when in adding payment method.
			if ( isAddPaymentMethodForm() && ( false !== hasRelatedSubscription() || false === isPayForOrder() ) ) {
				data['relatedSubscription'] = hasRelatedSubscription();
				data['url']                 = getAjaxURL( 'add_payment_method', 'wc_vivawallet_' );
				data['security']            = vivawallet_params.add_payment_method_nonce;
				data['installments']        = "1";

				// check if customer has checked to update all of his subscriptions peyment methods and send to post vars.
				if ( $( '#update_all_subscriptions_payment_method' ).is( ':checked' ) ) {
					data['updateAllSubscriptionsPayment'] = true
				}
			}

			return data;
		}

		function createTransaction(){

			var data = prepareData();

			$.ajax(
				{
					url: data.url,
					type: 'POST',
					data: data,
					success: function( result ) {
						if ( 'success' === result.result && result.redirect ) { // when success we get a redirect to order completed.
							window.location.href = result.redirect
						}
						if (result.result === 'failure') {
							checkout_form.removeClass( 'processing' ).unblock();
							if ( true === result.reload ) {
								window.location.reload();
								return;
							}

							submit_error( '<ul class="woocommerce-error" role="alert"><li>' + result.messages + '</li></ul>' );
						}
					},
					error: function() {
						submit_error( '<ul class="woocommerce-error" role="alert"><li>' + vivawallet_params.labelForAJAXerror + '</li></ul>' );
					}
				}
			);

		}

		var changeFormInputs = function (e) {

			var $cardInput    = $( "#vivawallet_native-card-number" );
			var $expDateInput = $( "#vivawallet_native-card-expiry" );
			var $cvvInput     = $( "#vivawallet_native-card-cvc" );

			var cardInput = $cardInput.val();
			if ( cardInput.length ) {
				$cardInput.attr( 'value', cardInput );
			}

			var expDateInput = $expDateInput.val();
			if ( expDateInput.length ) {
				$expDateInput.attr( 'value', expDateInput );
			}

			var cvvInput = $cvvInput.val();
			if ( cvvInput.length ) {
				$cvvInput.attr( 'value', cvvInput );
			}

			checkCreditCardFields();
			if ( undefined !== e.target) {
				if ( 'vivawallet_native-card-number' !== e.target.id ) {
					return;
				}
			}

			cardInput = cardInput.replace( / /g, "" );

			if ( validCard && cardNumber !== cardInput ) { // check the old card input.. only call ajax when it is a valid card and the card input has changed.
				cardNumber = cardInput;
				$.ajax(
					{
						type: "GET",
						beforeSend: function(xhr){
							xhr.setRequestHeader( 'CardNumber', cardNumber );
							xhr.setRequestHeader( 'Authorization', 'Bearer ' + vivawallet_params.token );
							xhr.setRequestHeader( 'Content-Type', 'application/json' );
						},
						url: vivawallet_params.installmentsUrl ,
						success: function ( result ) {
							if ( true === result.requiresCvv ) {
								$( "#vivawallet_native-card-cvc" ).show();
								$( "#wc-vivawallet_native-cc-form label[for=vivawallet_native-card-cvc]" ).show();
								requiresCvv = true;
							} else {
								$( "#vivawallet_native-card-cvc" ).hide();
								$( "#vivawallet_native-card-cvc" ).val( '' );
								$( "#wc-vivawallet_native-cc-form label[for=vivawallet_native-card-cvc]" ).hide();
								requiresCvv = false;
							}

							// update max installments for current card.
							maxInstallmentsForCard = result.maxInstallments

							if ( ! isAddPaymentMethodForm() ) { // when in normal checkout.
								checkInstallments();
							}

							// no need to check installments in add payment method form.
							// but check if we are in order-pay page.
							if ( false === hasRelatedSubscription() && true === isPayForOrder()  ) {
								checkInstallments();
							}

						},
						error: function ( data) {
							console.error( "Connection to Viva Wallet API Failed" )
							console.log( JSON.stringify( data ) );
							submit_error( '<ul class="woocommerce-error" role="alert"><li>' + vivawallet_params.labelForAPIerror + '</li></ul>' );
						}
					}
				);

			}
		};

		function checkCreditCardFields(){

			var $cardInput    = $( "#vivawallet_native-card-number" );
			var $expDateInput = $( "#vivawallet_native-card-expiry" );
			var $cvvInput     = $( "#vivawallet_native-card-cvc" );

			$cardInput.parent().removeClass( "woocommerce-invalid woocommerce-invalid-required-field" );
			$expDateInput.parent().removeClass( "woocommerce-invalid woocommerce-invalid-required-field" );
			$cvvInput.parent().removeClass( "woocommerce-invalid woocommerce-invalid-required-field" );

			var cardInput = $( 'input[data-vp="cardnumber"]' ).val();
			validCard     = $.payment.validateCardNumber( cardInput );

			var res = true;

			if ( ! validCard ) {
				$cardInput.parent().addClass( "woocommerce-invalid woocommerce-invalid-required-field" );
				res = false;
			}

			// check expdate input.
			var expDate = $( 'input[data-vp="expdate"]' ).val();
			expDate     = $.payment.cardExpiryVal( expDate );

			var validExp = $.payment.validateCardExpiry( expDate.month.toString(), expDate.year.toString() );
			if ( ! validExp) {
				$expDateInput.parent().addClass( "woocommerce-invalid woocommerce-invalid-required-field" );
				res = false;
			}

			// check cvv input.
			if ( requiresCvv ) {
				var validCVC = $.payment.validateCardCVC( $( 'input[data-vp="cvv"]' ).val(), $.payment.cardType( cardInput ) );
				if ( ! validCVC ) {
					$cvvInput.parent().addClass( "woocommerce-invalid woocommerce-invalid-required-field" );
					res = false;
				}
			}

			return res;
		}

		function changePaymentCardsData( target ){
			if ( $.payment === undefined ) {
				console.warn( 'VivaPayments: jquery.payments.js is required but not found on page load. Please  update your WooCommerce plugin.' )
				return;
			}
			var ln = $.payment.cards.length;

			var ln2 = target.length;

			for ( var x = ln; x > 0; x-- ) {
				$.payment.cards.splice( x - 1, 1 );
			}

			// add from target table.
			for ( var y = 0; y < ln2; y++ ) {
				var patterns      = [];
				var patternLength = target[y].patterns.length;
				for ( var i = 0; i < patternLength; i++ ) {
					if ( Array.isArray( target[y].patterns[i] ) ) {
						for ( var j = target[y].patterns[i][0]; j <= target[y].patterns[i][1]; j++ ) {
							patterns.push( j );
						}
					} else {
						patterns.push( target[y].patterns[i] );
					}
				}
				target[y].patterns = patterns;
				$.payment.cards.push( target[y] );
			}

		}

		function checkInstallments(){
			var installmentsLogic = vivawallet_params.installmentsLogic;
			var allowInstallments = vivawallet_params.allowInstallments;
			var amount            = vivawallet_params.amount;
			var maxInstallments   = 1;

			// check if installments are allowed.
			if ( '1' === allowInstallments && 1 < maxInstallmentsForCard ) {
				// check the logic value passed from admin settings.
				if ( 'string' === typeof (installmentsLogic) && '' !== installmentsLogic ) {

					var _obj      = installmentsLogic.split( ',' );
					var _objLen   = _obj.length;
					var instalMax = [];
					for ( var i = 0; i < _objLen; i++ ) {
						var instalOption = _obj[i].split( ':' );
						var instalAmount = instalOption[0];
						var instalTerm   = instalOption[1];

						if ( Number( amount ) >= Number( instalAmount ) ) {
							instalMax.push( instalTerm );
						}
					}
					var instalMaxLen = instalMax.length;
					if ( 0 < instalMaxLen ) {
						maxInstallments = Math.max.apply( {}, instalMax );
					}
				}

				// limit instalments result by the logic if the amount is greater than the allowed amount by card issuer.
				if ( maxInstallments > maxInstallmentsForCard ) {
					maxInstallments = maxInstallmentsForCard
				}

				// check if this value is more than 1 and that is defferent that the stored value.
				if ( 1 < maxInstallments) {
					if (oldInstallmentsValue !== maxInstallments) {
						showInstallments( maxInstallments );
						// store the value for not injecting the same element.
						oldInstallmentsValue = maxInstallments;
					}
				} else {
					hideInstallments();
					oldInstallmentsValue = 1;
				}
			} else {
				hideInstallments();
				oldInstallmentsValue = 1;
			}
		}

		function showInstallments ( maxInstallments ) {
			$( '#drpInstallments' ).empty();
			$( '#VWinstallments' ).show();
			for ( var i = 1; i <= maxInstallments; i++ ) {
				var label = i;
				if ( label === 1 ) {
					label = '0';
				}
				if ( i <= maxInstallments ) {
					$( '#drpInstallments' ).append( $( "<option>" ).val( i ).text( label ) );
				}
			}
		}

		function hideInstallments () {
			$( '#VWinstallments' ).hide();
			$( '#drpInstallments' ).empty();
		}

		function selectVWPlugin () {
			// update checkout amount when plugin is selected.
			if ( ( isVivaPaymentSelected() || isApmVivaSelected() ) && ! isAddPaymentMethodForm() ) { // only for normal checkout.
				updateCheckout();
			}
		}

		function  updateCheckout() {
			updatePrice( checkInstallments );
		}

		function init() {

			// remove event listeners if already there.
			// and add anew.

			if ( $( '.woocommerce-error' ).length !== 0 && false === noticesShown ) {
				scroll_to_notices();
				noticesShown = true;
			}

			$( document.body ).off( 'updated_checkout', updateCheckout );
			$( document.body ).on( 'updated_checkout', updateCheckout );

			checkout_form.off( 'checkout_place_order', tokenRequest );
			checkout_form.on( 'checkout_place_order', tokenRequest );

			checkout_form.off( 'blur change keydown focusout', changeFormInputs );
			checkout_form.on( 'blur change keydown focusout', changeFormInputs );

			if ( $( 'form#add_payment_method' ).length !== 0 ) {
				$( 'form#add_payment_method' ).off( 'submit', tokenRequest );
				$( 'form#add_payment_method' ).on( 'submit', tokenRequest );
			}

			if ( $( 'form#order_review' ).length !== 0 ) {
				$( 'form#order_review' ).off( 'submit', tokenRequest );
				$( 'form#order_review' ).on( 'submit', tokenRequest );
			}

			// store the old values in a var.
			if ( default_payment_jquery_cards.length === 0 ) {
				var ln = $.payment.cards.length;
				for ( var x = 0; x < ln; x++ ) {
					default_payment_jquery_cards.push( $.payment.cards[x] );
				}
			}
			changePaymentCardsData( VW_cards );

			// inject helper elements.

			if ( 0 === $( '#VWinstallments' ).length ) {

				var res = '<p class="form-row form-row-wide" id="VWinstallments">';
				res    += '<label for="drpInstallments">';
				res    += vivawallet_params.labelForInstallments + ' <span class="required">*</span>';
				res    += '<select id="drpInstallments" name="drpInstallments"></select>';
				res    += '</label>';
				res    += '</p>';

				$( '#wc-vivawallet_native-cc-form' ).append( res );
			}

			if ( 0 === $( '#VWhiddenFields' ).length  ) {
				var res = '<div id="VWhiddenFields" style="clear: both">';
				res    += '<input type="hidden" data-vp="cardholder" placeholder="cardholder name" />';
				res    += '<input type="hidden" data-vp="accessToken" placeholder="card access token" autocomplete="off"/>';
				res    += '</div>';

				$( '#wc-vivawallet_native-cc-form' ).append( res );
			}

			if ( vivawallet_params.showVWLogo && 0 === $( '#VWlogoContainer' ).length  ) {
				var res = '<div class="VWLogoContainer" style="clear: both" id="VWlogoContainer">';
				res    += '<p>';
				res    += vivawallet_params.labelLogoTxt;
				res    += '<a href="https://www.vivawallet.com/" target="_blank"><img src="' + vivawallet_params.logoPath + '"></a>';
				res    += '</p>';
				res    += '</div>';

				$( '#wc-vivawallet_native-cc-form' ).append( res );
			}

			if ( 0 === $( '#VWsecureModal' ).length  ) {
				var res = '<div id="VWsecureModal">';
				res    += '<div id="VWpaymentContainer">';
				res    += '</div>';
				res    += '</div>';

				$( 'body' ).append( res );
			}

		}

		function destroy () {
			// remove event listeners.

			$( document.body ).off( 'updated_checkout', updateCheckout );

			checkout_form.off( 'checkout_place_order', tokenRequest );

			checkout_form.off( 'blur change focusout', changeFormInputs );

			if ( $( 'form#add_payment_method' ).length !== 0 ) {
				$( 'form#add_payment_method' ).off( 'submit', tokenRequest );
			}

			if ( $( 'form#order_review' ).length !== 0 ) {
				$( 'form#order_review' ).off( 'submit', tokenRequest );
			}

			// remove injected elements.
			changePaymentCardsData( default_payment_jquery_cards );
			var vivawalletFormDiv = $( '#wc-vivawallet_native-cc-form' );
			vivawalletFormDiv.find( '#VWinstallments' ).remove();
			vivawalletFormDiv.find( '#VWhiddenFields' ).remove();
			vivawalletFormDiv.find( '#VWlogoContainer' ).remove();
			vivawalletFormDiv.find( '#VWsecureModal' ).remove();

			// reset stored instalments value.
			oldInstallmentsValue = 1;
		}

		function isVivaPaymentSelected(){
			return $( '#payment_method_vivawallet_native' ).is( ':checked' );
		}

		function isApmVivaSelected(){
			return isIDealSelected() || isP24Selected() || isPayUSelected() || isMultibancoSelected() || isGiropaySelected() || isDirectPaySelected() || isEpsSelected() || isWeChatPaySelected() || isBitPayPaySelected();
		}

		function isIDealSelected(){
			return $( '#payment_method_vivawallet-ideal' ).is( ':checked' );
		}

		function isP24Selected(){
			return $( '#payment_method_vivawallet-p24' ).is( ':checked' );
		}

		function isPayUSelected(){
			return $( '#payment_method_vivawallet-payu' ).is( ':checked' );
		}

		function isMultibancoSelected(){
			return $( '#payment_method_vivawallet-multibanco' ).is( ':checked' );
		}

		function isGiropaySelected(){
			return $( '#payment_method_vivawallet-giropay' ).is( ':checked' );
		}

		function isDirectPaySelected(){
			return $( '#payment_method_vivawallet-directpay' ).is( ':checked' );
		}

		function isEpsSelected(){
			return $( '#payment_method_vivawallet-eps' ).is( ':checked' );
		}

		function isWeChatPaySelected(){
			return $( '#payment_method_vivawallet-wechatpay' ).is( ':checked' );
		}

		function isBitPayPaySelected(){
			return $( '#payment_method_vivawallet-bitpay' ).is( ':checked' );
		}

		// checks the form elements to get if we are in a normal checkout or a custom payment page.
		// will return a boolean.
		function isAddPaymentMethodForm(){
			if ( $( 'form#add_payment_method' ).length || $( 'form#order_review' ).length ) {
				return true;
			}
			return false;
		}

		const queryString = window.location.search;
		const urlParams   = new URLSearchParams( queryString );

		// will check the url path to get if we are in a pay for order page
		// will return the id of subscription or false if not found.
		function isPayForOrder(){
			const payForOrder = urlParams.get( 'pay_for_order' );
			if ( 'true' === payForOrder) {
				return true;
			}
			return false
		}

		// will check the url path to get if any related subscriptions exist.
		// will return the id of subscription or false if not found.
		function hasRelatedSubscription(){
			const relatedSubscription = urlParams.get( 'change_payment_method' );

			if ( null !== relatedSubscription ) {
				return relatedSubscription;
			}
			return false;
		}

		function checkStatus( e=null ){

			if ( undefined !== e.target) {
				if ( 'payment_method_vivawallet_native' === e.target.id ) {
					selectVWPlugin();
				}
			}

			if ( isVivaPaymentSelected() || isApmVivaSelected() ) {
				init();
			} else {
				destroy();
			}
		}
		var defaultFormat = /(\d{1,4})/g;

		let VW_cards = [
			{
				type: 'bancontact',
				patterns: [
					479658,
					48107907,
					487104,
					487109,
					51278800,
					516920,
					518272,
					522122,
					522962,
					525565,
					539442,
					[56135900, 56135902],
					56136100,
					56140700,
					606005,
					6703,
				],
				format: defaultFormat,
				length: [12, 13, 14, 15, 16, 17, 18, 19],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'jcb',
				patterns: [
					2131,
					1800,
					[3528, 3589]
				],
				format: defaultFormat,
				length: [16, 17, 18, 19],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'mastercard',
				patterns: [
					[51, 55],
					[2221, 2229],
					[223, 229],
					[23, 26],
					[270, 271],
					2720
				],
				format: defaultFormat,
				length: [16],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'amex',
				patterns: [34, 37],
				format: /(\d{1,4})(\d{1,6})?(\d{1,5})?/,
				length: [15],
				cvcLength: [4],
				luhn: true
		},
			{
				type: 'dinersclub',
				patterns: [
					[300, 305],
					36,
					38,
					39
				],
				format: /(\d{1,4})(\d{1,6})?(\d{1,4})?/,
				length: [14, 16, 19],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'discover',
				patterns: [
					6011,
					[644, 649],
					65
				],
				format: defaultFormat,
				length: [16, 19],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'visa',
				patterns: [4],
				format: defaultFormat,
				length: [16, 18, 19],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'maestro',
				patterns: [
					[500000, 506698],
					[506779, 508999],
					[56, 59],
					63,
					67,
					6
				],
				format: defaultFormat,
				length: [12, 13, 14, 15, 16, 17, 18, 19],
				cvcLength: [3],
				luhn: true
		},
			{
				type: 'default',
				patterns: [0, 1, 2, 3, 4, 5, 6, 7, 8, 9],
				format: defaultFormat,
				length: [12, 13, 14, 15, 16, 17, 18, 19],
				cvcLength: [3, 4],
				luhn: true
		},
		];

		var checkout_form = $( 'form.woocommerce-checkout' );

		if ( $( 'form#add_payment_method' ).length ) {
			checkout_form = $( 'form#add_payment_method' );
		}

		if ( $( 'form#order_review' ).length ) {
			checkout_form = $( 'form#order_review' );
		}

		$( checkStatus );// on load check status.

		// fix attribute of cvv for autocomplete.
		$( '#vivawallet_native-card-cvc' ).attr( "autocomplete", "cc-csc" );

		checkout_form.on(
			'change',
			function (e){
				checkStatus( e );
			}
		);
	}
);
