/**
 * Pure reducer tests for the ported group-editor store (no @wordpress/data
 * needed). Extends the CF suite with group meta: title/status/assignment/
 * priority + sim map.
 */
import { reducer, actions, initialState, DEFAULTS, remapTemplate } from '../../src/builder/reducer';

describe( 'builder reducer', () => {
	it( 'adds a field with type defaults and selects it', () => {
		const s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.fields[ 0 ] ).toMatchObject( { id: 'a', type: 'text', priceMode: 'fixed' } );
		expect( s.selectedId ).toBe( 'a' );
	} );

	it( 'swatch defaults carry {label,color} options', () => {
		const s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'swatch', id: 'sw' } );
		expect( s.fields[ 0 ].options[ 0 ] ).toMatchObject( { label: 'Red', color: '#dc2626' } );
		// deep-cloned, not shared with DEFAULTS
		s.fields[ 0 ].options[ 0 ].label = 'Changed';
		expect( DEFAULTS.swatch.options[ 0 ].label ).toBe( 'Red' );
	} );

	it( 'updates a field by id', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, actions.updateField( 'a', { label: 'Engraving', required: true } ) );
		expect( s.fields[ 0 ].label ).toBe( 'Engraving' );
		expect( s.fields[ 0 ].required ).toBe( true );
	} );

	it( 'removes a field and strips rules that referenced it', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'checkbox', id: 'gift' } );
		s = reducer( s, { type: 'ADD_FIELD', fieldType: 'text', id: 'msg' } );
		s = reducer( s, actions.updateField( 'msg', {
			conditions: [ { field: 'gift', operator: 'is', value: 'yes' } ],
			conditionMatch: 'all',
			conditionAction: 'show',
		} ) );
		s = reducer( s, actions.removeField( 'gift' ) );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.fields[ 0 ].conditions ).toHaveLength( 0 );
	} );

	it( 'reorders fields', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, { type: 'ADD_FIELD', fieldType: 'text', id: 'b' } );
		s = reducer( s, actions.reorder( 0, 1 ) );
		expect( s.fields.map( ( f ) => f.id ) ).toEqual( [ 'b', 'a' ] );
	} );

	it( 'duplicates a field right after the source with a new id', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, { type: 'DUPLICATE_FIELD', id: 'a', newId: 'a2' } );
		expect( s.fields.map( ( f ) => f.id ) ).toEqual( [ 'a', 'a2' ] );
		expect( s.fields[ 1 ].label ).toMatch( /copy/ );
	} );

	it( 'undo restores the previous fields snapshot', () => {
		let s = reducer( initialState, { type: 'ADD_FIELD', fieldType: 'text', id: 'a' } );
		s = reducer( s, actions.updateField( 'a', { label: 'X' } ) );
		s = reducer( s, actions.undo() );
		expect( s.fields[ 0 ].label ).toBe( 'Text field' );
	} );

	it( 'hydrate loads the whole group and resets history + sim', () => {
		let s = reducer( initialState, actions.setSim( { a: 'yes' } ) );
		s = reducer( s, actions.hydrate( {
			title: 'Gift options',
			status: 'publish',
			fields: [ { id: 'x', type: 'text', label: 'X' } ],
			assignment: { mode: 'products', ids: [ 15 ] },
			priority: 5,
		} ) );
		expect( s.title ).toBe( 'Gift options' );
		expect( s.status ).toBe( 'publish' );
		expect( s.fields ).toHaveLength( 1 );
		expect( s.assignment ).toEqual( { mode: 'products', ids: [ 15 ] } );
		expect( s.priority ).toBe( 5 );
		expect( s.sim ).toEqual( {} );
		expect( s.past ).toEqual( [] );
	} );

	it( 'setAssignment normalizes mode and ids; all clears ids', () => {
		let s = reducer( initialState, actions.setAssignment( { mode: 'categories', ids: [ '5', 5, -1 ] } ) );
		expect( s.assignment ).toEqual( { mode: 'categories', ids: [ 5 ] } );
		s = reducer( s, actions.setAssignment( { mode: 'all', ids: [ 1, 2 ] } ) );
		expect( s.assignment ).toEqual( { mode: 'all', ids: [] } );
		s = reducer( s, actions.setAssignment( { mode: 'bogus', ids: [ 1 ] } ) );
		expect( s.assignment.mode ).toBe( 'all' );
	} );

	it( 'setPriority clamps to non-negative int', () => {
		let s = reducer( initialState, actions.setPriority( '7' ) );
		expect( s.priority ).toBe( 7 );
		s = reducer( s, actions.setPriority( -3 ) );
		expect( s.priority ).toBe( 0 );
	} );

	it( 'setStatus only accepts publish/draft', () => {
		let s = reducer( initialState, actions.setStatus( 'publish' ) );
		expect( s.status ).toBe( 'publish' );
		s = reducer( s, actions.setStatus( 'weird' ) );
		expect( s.status ).toBe( 'draft' );
	} );

	it( 'sim: set + reset', () => {
		let s = reducer( initialState, actions.setSim( { a: 'yes' } ) );
		s = reducer( s, actions.setSim( { b: '3' } ) );
		expect( s.sim ).toEqual( { a: 'yes', b: '3' } );
		s = reducer( s, actions.resetSim() );
		expect( s.sim ).toEqual( {} );
	} );

	it( 'remapTemplate rewrites sibling condition references to new ids', () => {
		const out = remapTemplate( [
			{ id: 'gift', type: 'checkbox', label: 'Gift' },
			{ id: 'msg', type: 'text', label: 'Msg', conditions: [ { field: 'gift', operator: 'is', value: 'yes' } ] },
		] );
		expect( out[ 0 ].id ).not.toBe( 'gift' );
		expect( out[ 1 ].conditions[ 0 ].field ).toBe( out[ 0 ].id );
	} );
} );
