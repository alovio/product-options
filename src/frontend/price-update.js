/**
 * Display-only add-on total on the product page. The authoritative price is
 * computed server-side (CoreLabs\ProductOptions\Cart\PriceCalculator); this mirrors it for UX.
 */
import { activeMap, readValues } from './conditional-logic';
import { evaluateFormula } from '../shared/formula';

/**
 * Per-field engaged amounts — the JS twin of PriceCalculator::breakdown.
 *
 * @return {Array<{fieldId: string, label: string, amount: number}>}
 */
export function computeBreakdown( fields, values, base = 0 ) {
	const rows = [];
	const map = activeMap( fields, values );
	const numeric = {};
	fields.forEach( ( f ) => {
		if ( map[ f.id ] && values[ f.id ] !== undefined && values[ f.id ] !== '' && ! isNaN( parseFloat( values[ f.id ] ) ) ) {
			numeric[ f.id ] = parseFloat( values[ f.id ] );
		}
	} );
	fields.forEach( ( f ) => {
		if ( ! map[ f.id ] ) {
			return;
		}
		const price = parseFloat( f.price ) || 0;
		if ( price <= 0 && f.priceMode !== 'formula' ) {
			return;
		}
		const v = values[ f.id ];
		const num = parseFloat( v );
		let engaged;
		if ( f.type === 'price' ) {
			engaged = true;
		} else if ( f.type === 'checkbox' ) {
			engaged = !! v && v !== '0';
		} else if ( f.type === 'number' || f.type === 'quantity' ) {
			engaged = v !== undefined && v !== '' && ! isNaN( num ) && num !== 0;
		} else {
			engaged = v !== undefined && v !== null && v !== '';
		}
		if ( ! engaged ) {
			return;
		}
		let amount;
		if ( f.priceMode === 'formula' ) {
			amount = evaluateFormula( f.formula || '', numeric );
		} else if ( f.priceMode === 'per_unit' && ( f.type === 'number' || f.type === 'quantity' ) && ! isNaN( num ) ) {
			amount = price * num;
		} else if ( f.priceMode === 'per_char' && ( f.type === 'text' || f.type === 'textarea' ) ) {
			amount = price * String( v ).trim().length;
		} else if ( f.priceMode === 'percent' ) {
			amount = ( base * price ) / 100;
		} else {
			amount = price;
		}
		if ( amount > 0 ) {
			rows.push( { fieldId: f.id, label: f.label || f.id, amount } );
		}
	} );
	return rows;
}

export function computeAddonTotal( fields, values, base = 0 ) {
	return computeBreakdown( fields, values, base ).reduce( ( t, r ) => t + r.amount, 0 );
}

export function formatMoney( amount ) {
	const c = ( typeof window !== 'undefined' && window.CLPO_FE ) || {};
	const decimals = typeof c.decimals === 'number' ? c.decimals : 2;
	const sym = c.symbol || '';
	const dsep = c.decimalSep || '.';
	const tsep = c.thousandSep || ',';
	const parts = Math.abs( amount ).toFixed( decimals ).split( '.' );
	parts[ 0 ] = parts[ 0 ].replace( /\B(?=(\d{3})+(?!\d))/g, tsep );
	const num = parts.length > 1 ? parts[ 0 ] + dsep + parts[ 1 ] : parts[ 0 ];
	switch ( c.position ) {
		case 'left_space':
			return sym + ' ' + num;
		case 'right':
			return num + sym;
		case 'right_space':
			return num + ' ' + sym;
		default:
			return sym + num;
	}
}

export function wirePrices( formEl, fields, displayEl, base = 0 ) {
	const update = () => {
		const total = computeAddonTotal( fields, readValues( formEl, fields ), base );
		if ( displayEl ) {
			displayEl.textContent = '+' + formatMoney( total );
		}
	};
	formEl.addEventListener( 'change', update );
	formEl.addEventListener( 'input', update );
	update();
}
