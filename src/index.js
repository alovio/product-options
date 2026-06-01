import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import { dispatch } from '@wordpress/data';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from './builder/store';
import App from './builder/App';
import '../assets/css/builder.css';

domReady( () => {
	const el = document.getElementById( 'apo-builder' );
	if ( ! el ) {
		return;
	}
	const productId = parseInt( el.dataset.productId, 10 );

	if ( window.APO_BUILDER ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( window.APO_BUILDER.root ) );
		apiFetch.use( apiFetch.createNonceMiddleware( window.APO_BUILDER.nonce ) );
	}

	apiFetch( { path: `apo/v1/product/${ productId }/fields` } )
		.then( ( group ) => dispatch( STORE ).hydrate( ( group && group.fields ) || [] ) )
		.catch( () => {} );

	createRoot( el ).render( <App productId={ productId } /> );
} );
