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
		// Quantity steppers: −/＋ buttons nudge the input and fire change.
		node.closest( '.apo-options' ).querySelectorAll( '.apo-qty__btn' ).forEach( ( btn ) => {
			btn.addEventListener( 'click', () => {
				const input = btn.parentElement.querySelector( 'input[type="number"]' );
				if ( ! input ) {
					return;
				}
				const step = parseInt( btn.dataset.apoStep, 10 ) || 1;
				const min = input.min !== '' ? parseInt( input.min, 10 ) : 0;
				const max = input.max !== '' ? parseInt( input.max, 10 ) : Infinity;
				const next = Math.min( max, Math.max( min, ( parseInt( input.value, 10 ) || 0 ) + step ) );
				input.value = String( next );
				input.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			} );
		} );
		const optionsEl = node.closest( '.apo-options' );
		const base = optionsEl ? parseFloat( optionsEl.dataset.apoBase ) || 0 : 0;
		// Scope the total to THIS group's block — with several priced groups on
		// one form, a form-wide query would write every subtotal into group 1.
		const totalEl = optionsEl && optionsEl.querySelector( '.apo-options-total__value' );
		wirePrices( form, fields, totalEl, base );
	} );
}

if ( document.readyState !== 'loading' ) {
	init();
} else {
	document.addEventListener( 'DOMContentLoaded', init );
}
