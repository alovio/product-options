/**
 * Product-page runtime: hydrate option groups printed by ProductFormRenderer,
 * then wire conditional visibility and the display-only options total.
 */
import { wire } from './frontend/conditional-logic';
import { wireBreakdown } from './frontend/price-update';
import { wireUploads } from './frontend/uploader';
import '../assets/css/frontend.css';

function init() {
	const byForm = new Map(); // form -> { groups: [{fields}], base }

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

		// Quantity steppers: −/＋ buttons nudge the input and fire change.
		optionsEl.querySelectorAll( '.apo-qty__btn' ).forEach( ( btn ) => {
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

		if ( ! byForm.has( form ) ) {
			byForm.set( form, { groups: [], base: parseFloat( optionsEl.dataset.apoBase ) || 0 } );
		}
		byForm.get( form ).groups.push( { fields } );
	} );

	// One shared breakdown box per form, merging every group (spec §9).
	byForm.forEach( ( { groups, base }, form ) => {
		const boxEl = form.querySelector( '.apo-breakdown' );
		if ( ! boxEl ) {
			return;
		}
		wireBreakdown( form, groups, boxEl, () => base );
	} );
}

wireUploads();

if ( document.readyState !== 'loading' ) {
	init();
} else {
	document.addEventListener( 'DOMContentLoaded', init );
}
