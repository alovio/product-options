/**
 * Display-only add-on total on the product page. The authoritative price is
 * computed server-side (APO\Cart\PriceCalculator); this mirrors it for UX.
 */
import { activeMap, readValues } from './conditional-logic';

export function computeAddonTotal( fields, values ) {
	let total = 0;
	const map = activeMap( fields, values );
	fields.forEach( ( f ) => {
		if ( ! map[ f.id ] ) {
			return;
		}
		const price = parseFloat( f.price ) || 0;
		if ( price <= 0 ) {
			return;
		}
		const v = values[ f.id ];
		let engaged;
		if ( f.type === 'price' ) {
			engaged = true;
		} else if ( f.type === 'checkbox' ) {
			engaged = !! v && v !== '0';
		} else {
			engaged = v !== undefined && v !== null && v !== '';
		}
		if ( engaged ) {
			total += price;
		}
	} );
	return total;
}

export function wirePrices( formEl, fields, displayEl ) {
	const update = () => {
		const total = computeAddonTotal( fields, readValues( formEl, fields ) );
		if ( displayEl ) {
			displayEl.textContent = '+' + total.toFixed( 2 );
		}
	};
	formEl.addEventListener( 'change', update );
	formEl.addEventListener( 'input', update );
	update();
}
