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

/**
 * Mutable base-price holder: WooCommerce variation events move it, every
 * breakdown update reads it (spec §9 variable products).
 *
 * @param {number} initial base from data-apo-base.
 */
export function createBaseTracker( initial ) {
	const start = parseFloat( initial ) || 0;
	let current = start;
	return {
		get: () => current,
		set: ( v ) => {
			const n = parseFloat( v );
			if ( ! isNaN( n ) ) {
				current = n;
			}
		},
		reset: () => {
			current = start;
		},
	};
}

/**
 * Pure row model for the breakdown box (spec §9, design B). Empty array when
 * nothing priced is engaged — the box stays hidden.
 *
 * @param {Array<{fieldId:string,label:string,amount:number}>} breakdown engaged rows (all groups merged)
 * @param {number} base   product base price
 * @param {Object} labels { base, total } localized strings
 * @return {Array<{label:string, amount:number, total?:boolean}>}
 */
export function renderBreakdownRows( breakdown, base, labels ) {
	if ( ! breakdown.length ) {
		return [];
	}
	const sum = breakdown.reduce( ( t, r ) => t + r.amount, 0 );
	return [
		{ label: labels.base, amount: base },
		...breakdown.map( ( r ) => ( { label: r.label, amount: r.amount } ) ),
		{ label: labels.total, amount: base + sum, total: true },
	];
}

/**
 * One shared breakdown box per product form: merges every group's engaged
 * rows, re-renders on any change. `getBase` is read per update so the
 * variation tracker (spec §9) can move the base under us.
 *
 * @param {HTMLElement} formEl
 * @param {Array<{fields: Array}>} groups
 * @param {HTMLElement} boxEl the .apo-breakdown container
 * @param {Function} getBase () => number
 */
export function wireBreakdown( formEl, groups, boxEl, getBase ) {
	const list = boxEl && boxEl.querySelector( 'ul' );
	if ( ! list ) {
		return;
	}
	const cfg = ( typeof window !== 'undefined' && window.CLPO_FE ) || {};
	const labels = ( cfg.i18n ) || { base: 'Base price', total: 'Total' };

	const update = () => {
		const base = getBase();
		const merged = [];
		groups.forEach( ( g ) => {
			merged.push( ...computeBreakdown( g.fields, readValues( formEl, g.fields ), base ) );
		} );
		const rows = renderBreakdownRows( merged, base, labels );
		boxEl.hidden = rows.length === 0;
		list.textContent = '';
		rows.forEach( ( r ) => {
			const li = document.createElement( 'li' );
			li.className = r.total ? 'apo-breakdown__row apo-breakdown__row--total' : 'apo-breakdown__row';
			const name = document.createElement( 'span' );
			name.textContent = r.label;
			const amount = document.createElement( 'span' );
			amount.textContent = r.total || r.label === labels.base ? formatMoney( r.amount ) : '+' + formatMoney( r.amount );
			li.append( name, amount );
			list.append( li );
		} );
	};
	formEl.addEventListener( 'change', update );
	formEl.addEventListener( 'input', update );
	update();
	return update;
}
