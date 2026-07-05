import { computeAddonTotal } from '../../src/frontend/price-update';

describe( 'computeAddonTotal', () => {
	it( 'per-unit number multiplies by the quantity entered (Pro)', () => {
		const fields = [ { id: 'n', type: 'number', price: 0.5, priceMode: 'per_unit', condition: null } ];
		expect( computeAddonTotal( fields, { n: '3' } ) ).toBe( 1.5 );
		expect( computeAddonTotal( fields, { n: '0' } ) ).toBe( 0 ); // 0 does not engage
	} );

	it( 'fixed mode charges a flat fee regardless of quantity', () => {
		const fields = [ { id: 'n', type: 'number', price: 2, priceMode: 'fixed', condition: null } ];
		expect( computeAddonTotal( fields, { n: '5' } ) ).toBe( 2 );
	} );

	it( 'percentage mode uses the product base price', () => {
		const fields = [ { id: 'c', type: 'checkbox', price: 10, priceMode: 'percent', condition: null } ];
		expect( computeAddonTotal( fields, { c: 'yes' }, 200 ) ).toBe( 20 ); // 10% of 200
		expect( computeAddonTotal( fields, {}, 200 ) ).toBe( 0 );
	} );

	it( 'fixed fee added once for a non-number engaged field', () => {
		const fields = [ { id: 'c', type: 'checkbox', price: 4, condition: null } ];
		expect( computeAddonTotal( fields, { c: 'yes' } ) ).toBe( 4 );
		expect( computeAddonTotal( fields, {} ) ).toBe( 0 );
	} );
} );

describe( 'quantity pricing gates', () => {
	const { computeAddonTotal } = require( '../../src/frontend/price-update' );

	it( 'quantity × per_unit multiplies', () => {
		const fields = [ { id: 'q', type: 'quantity', price: 2, priceMode: 'per_unit' } ];
		expect( computeAddonTotal( fields, { q: '3' } ) ).toBe( 6 );
	} );

	it( 'quantity 0 is not engaged', () => {
		const fields = [ { id: 'q', type: 'quantity', price: 2, priceMode: 'per_unit' } ];
		expect( computeAddonTotal( fields, { q: '0' } ) ).toBe( 0 );
	} );
} );

describe( 'per_char pricing', () => {
	const { computeAddonTotal } = require( '../../src/frontend/price-update' );

	it( 'multiplies price by trimmed length', () => {
		const fields = [ { id: 't', type: 'text', price: 0.5, priceMode: 'per_char' } ];
		expect( computeAddonTotal( fields, { t: 'Hello world' } ) ).toBe( 5.5 );
		expect( computeAddonTotal( fields, { t: '  ab  ' } ) ).toBe( 1 );
		expect( computeAddonTotal( fields, { t: '' } ) ).toBe( 0 );
	} );
} );
