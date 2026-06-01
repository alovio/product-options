/**
 * Product-page runtime: hydrate option groups printed by ProductFormRenderer,
 * then wire conditional visibility and the display-only options total.
 */
import { wire } from './frontend/conditional-logic';
import { wirePrices } from './frontend/price-update';
import '../assets/css/frontend.css';

function init() {
	document.querySelectorAll( 'script.apo-rules' ).forEach( ( node ) => {
		let group;
		try {
			group = JSON.parse( node.textContent );
		} catch ( e ) {
			return;
		}
		const fields = ( group && group.fields ) || [];
		const form = node.closest( 'form.cart' ) || node.closest( 'form' );
		if ( ! form || ! fields.length ) {
			return;
		}
		wire( form, fields );
		const optionsEl = node.closest( '.apo-options' );
		const base = optionsEl ? parseFloat( optionsEl.dataset.apoBase ) || 0 : 0;
		const totalEl = form.querySelector( '.apo-options-total__value' );
		wirePrices( form, fields, totalEl, base );
	} );
}

if ( document.readyState !== 'loading' ) {
	init();
} else {
	document.addEventListener( 'DOMContentLoaded', init );
}
