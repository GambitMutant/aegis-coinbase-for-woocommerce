/**
 * WooCommerce Blocks integration for AEGIS Coinbase.
 */
( function () {
	const { registerPaymentMethod } = window.wc.wcBlocksRegistry;
	const { getSetting } = window.wc.wcSettings;
	const { decodeEntities } = window.wp.htmlEntities;
	const { createElement } = window.wp.element;

	const settings = getSetting( 'aegis_coinbase_data', {} );
	const label = decodeEntities( settings.title || 'Cryptocurrency (AEGIS Coinbase)' );
	const mode = settings.mode || 'personal';

	const Content = () => {
		if ( mode !== 'personal' ) {
			return createElement( 'p', null, decodeEntities( settings.description || '' ) );
		}

		const cryptos = settings.cryptos || [ 'USDC' ];
		const networkMap = { USDC: 'Base', USDT: 'Ethereum', BTC: 'Bitcoin', ETH: 'Ethereum', DOGE: 'Dogecoin' };

		return createElement( 'div', null,
			createElement( 'p', null, decodeEntities( settings.description || '' ) ),
			createElement( 'p', null, 'You will be shown a unique address and the correct network on the next page.' ),
			createElement( 'label', null, 'Pay with ' ),
			createElement( 'select', { name: 'acw_crypto', required: true },
				cryptos.map( function ( c ) {
					const net = networkMap[ c ] || c;
					return createElement( 'option', { value: c, key: c }, c + ' — ' + net + ' network' );
				} )
			)
		);
	};

	registerPaymentMethod( {
		name: 'aegis_coinbase',
		label: label,
		content: createElement( Content ),
		edit: createElement( Content ),
		canMakePayment: () => true,
		ariaLabel: label,
		supports: { features: settings.supports || [ 'products' ] }
	} );
} )();
