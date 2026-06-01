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

	it( 'fixed fee added once for a non-number engaged field', () => {
		const fields = [ { id: 'c', type: 'checkbox', price: 4, condition: null } ];
		expect( computeAddonTotal( fields, { c: 'yes' } ) ).toBe( 4 );
		expect( computeAddonTotal( fields, {} ) ).toBe( 0 );
	} );
} );
