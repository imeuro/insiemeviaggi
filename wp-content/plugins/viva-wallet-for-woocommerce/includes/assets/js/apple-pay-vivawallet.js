/**
 * Viva Wallet Standard Checkout (Apple Pay)
 *
 * @package WC_Vivawallet
 */

jQuery(
	function ($) {
		'use strict';

		var session;

		var checkStatusRes = null;
		var APSessionToken;

		var shippingIsRequired = false;
		var shippingSelectedId;

		var clientDetails;

		function getCartData() {
				var data = {
					security: vivawallet_apple_pay_params.security.payment
			};
				$.ajax(
					{
						type:    'POST',
						data:    data,
						url:     getUrl( 'get_cart_data' ),
						success: function( response ) {
							startApplePayRequest( response );
						}
					}
				);
		}

		function abort( payment, message ) {

			session.abort();
			$( '.woocommerce-error' ).remove();

			if ( vivawallet_apple_pay_params.is_product ) {
				var element = $( '.product' );

				element.before( message );

				$( 'html, body' ).animate( { scrollTop: element.prev( '.woocommerce-error' ).offset().top }, 600 );
			} else {

				var $form = $( '.shop_table.cart' ).closest( 'form' );

				$form.before( message );

				$( 'html, body' ).animate(
					{
						scrollTop: $form.prev( '.woocommerce-error' ).offset().top
						},
					600
				);
			}

		}

		function updateShipping( details, address ) {

				clientDetails = address;

				var data = {
					security:  vivawallet_apple_pay_params.security.shipping,
					country:   address.shippingContact.countryCode.toUpperCase(),
					state:     address.shippingContact.country,
					postcode:  address.shippingContact.postalCode,
					city:      '',

					// todo maybe fix this. (this address is to fix shipping details.. only country and post code are needed).
					// address:  typeof address.shippingContact.addressLine[0] === 'undefined' ? '' : address.shippingContact.addressLine[0], .
					// address_2: (address.shippingContact.addressLine[1] !== 'undefined') ? address.shippingContact.addressLine[1] : ''  , .
					is_product: vivawallet_apple_pay_params.is_product,
			};

				return $.ajax(
					{
						type:    'POST',
						data:    data,
						url:     getUrl( 'get_shipping_data' )
					}
				);
		}

		function updateShippingInfo( details, shippingMethod ) {
				shippingSelectedId = shippingMethod.shippingMethod.identifier;
				var data           = {
					security: vivawallet_apple_pay_params.security.update_shipping,
					shipping_method: [ shippingMethod.shippingMethod.identifier ],
					payment_request_type: 'apple_pay',
					is_product: vivawallet_apple_pay_params.is_product,
			};
				return $.ajax(
					{
						type: 'POST',
						data: data,
						url:  getUrl( 'update_shipping_data' )
					}
				);
		}

		function startApplePayRequest (cart){

				var paymentDetails,
					options;

			if ( ! vivawallet_apple_pay_params.is_product ) {

				var requiredShippingContactFields = [ 'name', 'phone', 'email'];
				if ( cart.shipping_required === true ) {
					shippingIsRequired            = true;
					requiredShippingContactFields = ['postalAddress', 'name', 'phone', 'email']
				}

				var lineItems = [];
				cart.order_data.lineItems.forEach(
					function(item){
						var displayItem = {
							label: item.label,
							type: 'final',
							amount:item.amount
						}
						lineItems.push( displayItem );
					}
				);

				paymentDetails = {
					total: cart.order_data.total,
					lineItems: lineItems,
					countryCode: cart.order_data.country_code,
					currencyCode: cart.order_data.currency,
					merchantCapabilities: [ "supports3DS" ],
					supportedNetworks: [ "amex", "masterCard", "visa" ],
					requiredBillingContactFields: [ 'postalAddress', 'name', 'phone', 'email' ],
					requiredShippingContactFields: requiredShippingContactFields ,
				};

				options = cart.order_data;

			}

			attachCartEvents( paymentDetails, options );

		}

		function createOrder( appleData, appleEvent  ) {
			var data = getOrderData( appleData, appleEvent );
			$.ajax(
				{
					type:    'POST',
					data:    data,
					dataType: 'json',
					url:     getUrl( 'create_order' ),
					success: function(result){
						if (result.result === 'success') {
							var authorizationResult = {
								status: window.ApplePaySession.STATUS_SUCCESS,
								errors: []
							};
							session.completePayment( authorizationResult );
							window.location = result.redirect;
						} else {
							abort( appleData, result.messages );
						}
					}
				}
			);

		}

		function getOrderData( data, evt ) {

			var data = {
				_wpnonce:                  vivawallet_apple_pay_params.security.checkout,
				billing_first_name:        evt.payment.billingContact.givenName,
				billing_last_name:         evt.payment.billingContact.familyName,

				billing_company:           '', // '',
				billing_email:             evt.payment.shippingContact.emailAddress,
				billing_phone:             evt.payment.shippingContact.phoneNumber,
				billing_country:           evt.payment.billingContact.countryCode.toUpperCase(),
				billing_address_1:         undefined !== evt.payment.billingContact.addressLines[0] ? evt.payment.billingContact.addressLines[0] : '',
				billing_address_2:         undefined !== evt.payment.billingContact.addressLines[1] ? evt.payment.billingContact.addressLines[1] : '',
				billing_city:              undefined !== evt.payment.billingContact.locality ? evt.payment.billingContact.locality : '',
				billing_state:             undefined !== evt.payment.billingContact.country ? evt.payment.billingContact.country : '',
				billing_postcode:          evt.payment.billingContact.postalCode,

				shipping_first_name:       '',
				shipping_last_name:        '',
				shipping_company:          '',
				shipping_country:          '',
				shipping_address_1:        '',
				shipping_address_2:        '',
				shipping_city:             '',
				shipping_state:            '',
				shipping_postcode:         '',
				shipping_method:           [ shippingSelectedId ],

				order_comments:            '',
				payment_method:            'vivawallet_native',
				payment_request_type: 			'apple_pay',

				ship_to_different_address: 1,
				terms:                     1,
				applePayAccessToken     	:  vivawallet_apple_pay_params.token,
				applePayChargeToken : 		APSessionToken,

			};

			if ( shippingIsRequired ) {
				data.shipping_first_name = evt.payment.shippingContact.givenName;
				data.shipping_last_name  = evt.payment.shippingContact.familyName;
				data.shipping_company    = '';
				data.shipping_country    = evt.payment.shippingContact.countryCode.toUpperCase();
				data.shipping_address_1  = undefined !== evt.payment.shippingContact.addressLines[0] ? evt.payment.shippingContact.addressLines[0] : '';
				data.shipping_address_2  = undefined !== evt.payment.shippingContact.addressLines[1] ? evt.payment.shippingContact.addressLines[1] : '';
				data.shipping_city       = undefined !== evt.payment.shippingContact.locality ? evt.payment.shippingContact.locality : '';
				data.shipping_state      = undefined !== evt.payment.shippingContact.country ? evt.payment.shippingContact.country : '';
				data.shipping_postcode   = evt.payment.shippingContact.postalCode;
			}

			return data;
		}

		function attachCartEvents (  paymentRequest, options ) {

				$( "#apple-pay-button" ).on(
					'click',
					function ( evt ) {
						evt.preventDefault();
						session = new window.ApplePaySession( 6, paymentRequest );

						session.onvalidatemerchant = function (event) {
							var data = {
								providerId : 'applepay',
								sourceCode : vivawallet_apple_pay_params.sourceCode,
								validationUrl: event.validationURL
							};
							$.ajax(
								{
									data: JSON.stringify( data ),
									type: "POST",
									beforeSend: function(xhr){
										xhr.setRequestHeader( 'Authorization', 'Bearer ' + vivawallet_apple_pay_params.token );
										xhr.setRequestHeader( 'Content-Type', 'application/json' );
									},
									url: vivawallet_apple_pay_params.applePayTokenUrl ,
									success: function ( data ) {
										session.completeMerchantValidation( JSON.parse( data.chargeToken ) );

									},
									error: function ( data) {
										alert( 'Connection to VivaWallet API failed. Please try again later.' )
										console.log( data );
									}
								}
							);
						}

						session.onshippingcontactselected = function (event) {
							$.when( updateShipping( paymentRequest, event ) ).then(
								function( response ) {
									if ( 'shipping_address_not_valid' === response.result ) {
											var err  = new window.ApplePayError( "shippingContactInvalid", "postalAddress" );
											var data = {
												errors: [err],
												newTotal: response.total,
												newLineItems: response.lineItems,
												newShippingMethods: [],
										}
									}
									if ( 'success' === response.result ) {
										var data = {
											newTotal: response.total,
											newLineItems: response.lineItems,
											newShippingMethods: response.shipping_methods
										}
									}
									session.completeShippingContactSelection( data );
								}
							);
						}

						session.onshippingmethodselected = function (event) {
							$.when( updateShippingInfo( paymentRequest, event ) ).then(
								function( response ) {
									if ( 'success' === response.result ) {
										var data = {
											newTotal: response.total,
											newLineItems: response.lineItems,
										}
									}
									if ( 'fail' === response.result ) {
										var err  = new window.ApplePayError( "shippingContactInvalid", "postalAddress" );
										var data = {
											errors: [err],
											newTotal: response.total,
											newLineItems: response.lineItems,
										}
									}
									session.completeShippingMethodSelection( data );
								}
							);

						}

						session.onpaymentauthorized = function (event) {
							var token      = event.payment.token;
							APSessionToken = JSON.stringify( token );
							createOrder( paymentRequest, event );
						}
						session.begin();
					}
				);
		}

		function getUrl ( endpoint ) {
			return vivawallet_apple_pay_params.ajax_url
				.toString()
				.replace( '%%endpoint%%', 'wc_vivawallet_' + endpoint );
		}

		function enableApplePay(){
				$( '#VWapplePayBut' ).show();
		}
		function disableApplePay(){
				$( '#VWapplePayBut' ).hide();
		}

		function checkStatus() {
			if (null === checkStatusRes) {   // first time check if apple session is available and store the result
				// check if website is over https.
				if (window.location.href.indexOf( "https" ) === -1) {
					disableApplePay();
					checkStatusRes = false;
					console.warn( 'VivaWallet: Your website does not appear to be using a secure connection. Apple pay must be served over https.' )
				} else {

					if (window.ApplePaySession) {
						if (window.ApplePaySession.canMakePayments() === true) {
							enableApplePay();
							checkStatusRes = true;
						} else {
							window.ApplePaySession.canMakePaymentsWithActiveCard().then(
								function (canMakePayments) {
									if (canMakePayments === true) {
										enableApplePay();
										checkStatusRes = true;
									} else {
										disableApplePay();
										checkStatusRes = false;
									}
								}
							);
						}
					} else {
						disableApplePay();
						checkStatusRes = false;
						console.warn( 'VivaWallet: This device and/or browser does not support Apple Pay.' );
					}
				}
				return;
			}
			if (true === checkStatusRes) {
				enableApplePay();
			} else {
				disableApplePay();
			}
		}

		function init() {
				checkStatus();
			if (vivawallet_apple_pay_params.is_product) {
				startApplePayRequest( '' );
			} else {
				getCartData();
			}

		}

		$( init );

		$( document.body ).on( 'updated_cart_totals', function() { init(); } );

		$( document.body ).on( 'updated_checkout', function() { init(); } );
	}
);
