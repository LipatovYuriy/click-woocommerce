( function () {
	var settings = window.wc.wcSettings.getSetting( 'clickuz_data', {} ) || {};
	var decode = window.wp.htmlEntities.decodeEntities;
	var label = decode( settings.title || 'CLICK' );

	var Content = function () {
		return decode( settings.description || '' );
	};

	var ClickuzGateway = {
		name: 'clickuz',
		label: label,
		ariaLabel: label,
		content: window.wp.element.createElement( Content, null ),
		edit: window.wp.element.createElement( Content, null ),
		canMakePayment: function () {
			return true;
		},
		supports: {
			features: settings.supports || [ 'products' ]
		}
	};

	window.wc.wcBlocksRegistry.registerPaymentMethod( ClickuzGateway );
} )();
