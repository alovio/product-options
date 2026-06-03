import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';
import apiFetch from '@wordpress/api-fetch';
import './builder/store';
import App from './builder/App';
import '../assets/css/builder.css';

domReady( () => {
	const el = document.getElementById( 'clpo-builder' );
	if ( ! el ) {
		return;
	}
	const productId = parseInt( el.dataset.productId, 10 );

	if ( window.CLPO_BUILDER ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( window.CLPO_BUILDER.root ) );
		apiFetch.use( apiFetch.createNonceMiddleware( window.CLPO_BUILDER.nonce ) );
	}

	createRoot( el ).render( <App productId={ productId } /> );
} );
