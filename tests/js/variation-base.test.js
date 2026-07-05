import { createBaseTracker, renderBreakdownRows, computeBreakdown } from '../../src/frontend/price-update';

describe( 'variation base tracker', () => {
	it( 'tracks set/reset around the initial base', () => {
		const t = createBaseTracker( 50 );
		expect( t.get() ).toBe( 50 );
		t.set( 75 );
		expect( t.get() ).toBe( 75 );
		t.reset();
		expect( t.get() ).toBe( 50 );
	} );

	it( 'ignores non-numeric sets', () => {
		const t = createBaseTracker( 50 );
		t.set( NaN );
		expect( t.get() ).toBe( 50 );
	} );

	it( 'percent amounts + total follow the tracked base', () => {
		const fields = [ { id: 'p', type: 'checkbox', label: 'Rush', price: 10, priceMode: 'percent' } ];
		const t = createBaseTracker( 50 );
		t.set( 75 );
		const rows = renderBreakdownRows(
			computeBreakdown( fields, { p: 'yes' }, t.get() ),
			t.get(),
			{ base: 'Base price', total: 'Total' }
		);
		expect( rows[ 0 ].amount ).toBe( 75 );
		expect( rows[ 1 ].amount ).toBe( 7.5 ); // 10% of 75
		expect( rows[ 2 ].amount ).toBe( 82.5 );
	} );
} );
