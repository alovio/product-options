import { reducer, actions } from '../../src/builder/reducer';

describe( 'builder reducer', () => {
	it( 'addField appends with defaults and a unique id', () => {
		const s = reducer( undefined, actions.addField( 'text' ) );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.fields[ 0 ].type ).toBe( 'text' );
		expect( s.fields[ 0 ].label ).toBe( 'Text field' );
		expect( s.fields[ 0 ].id ).toMatch( /^fld_/ );
		expect( s.selectedId ).toBe( s.fields[ 0 ].id );
	} );

	it( 'updateField merges a patch', () => {
		let s = reducer( undefined, actions.addField( 'text' ) );
		const id = s.fields[ 0 ].id;
		s = reducer( s, actions.updateField( id, { label: 'New', price: 5 } ) );
		expect( s.fields[ 0 ].label ).toBe( 'New' );
		expect( s.fields[ 0 ].price ).toBe( 5 );
	} );

	it( 'removeField deletes and clears dependent conditions', () => {
		let s = reducer( undefined, actions.addField( 'checkbox' ) );
		const a = s.fields[ 0 ].id;
		s = reducer( s, actions.addField( 'text' ) );
		const b = s.fields[ 1 ].id;
		s = reducer( s, actions.updateField( b, { condition: { field: a, operator: 'is', value: 'yes', action: 'show' } } ) );
		s = reducer( s, actions.removeField( a ) );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.fields[ 0 ].id ).toBe( b );
		expect( s.fields[ 0 ].condition ).toBeNull();
	} );

	it( 'reorder moves a field and ignores out-of-range', () => {
		let s = reducer( undefined, actions.addField( 'text' ) );
		s = reducer( s, actions.addField( 'number' ) );
		const firstId = s.fields[ 0 ].id;
		s = reducer( s, actions.reorder( 0, 1 ) );
		expect( s.fields[ 1 ].id ).toBe( firstId );
		const same = reducer( s, actions.reorder( 0, 9 ) );
		expect( same ).toBe( s );
	} );

	it( 'hydrate replaces fields', () => {
		const s = reducer( undefined, actions.hydrate( [ { id: 'x', type: 'text' } ] ) );
		expect( s.fields ).toEqual( [ { id: 'x', type: 'text' } ] );
	} );
} );
