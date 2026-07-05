import domReady from '@wordpress/dom-ready';
import { createRoot } from '@wordpress/element';

domReady( () => {
	const el = document.getElementById( 'clpo-hub-root' );
	if ( ! el ) {
		return;
	}
	createRoot( el ).render( <p>Hub OK</p> );
} );
