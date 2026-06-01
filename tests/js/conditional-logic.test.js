/**
 * JS/PHP parity: `evaluate` must match every case in the shared fixture
 * (the same file APO\Logic\ConditionalLogic is tested against).
 */
import { evaluate, activeMap } from '../../src/frontend/conditional-logic';
import cases from '../fixtures/conditional-cases.json';

describe( 'evaluate (parity with PHP ConditionalLogic)', () => {
	cases.forEach( ( c ) => {
		it( c.name, () => {
			expect( evaluate( c.condition, c.values ) ).toBe( c.expectedActive );
		} );
	} );
} );

describe( 'activeMap (transitive, parity with PHP active_map)', () => {
	const fields = [
		{ id: 'gate', type: 'checkbox', condition: null },
		{ id: 'a', type: 'checkbox', condition: { field: 'gate', operator: 'is', value: 'yes', action: 'show' } },
		{ id: 'b', type: 'text', condition: { field: 'a', operator: 'is', value: 'yes', action: 'show' } },
	];

	it( 'hides a dependent whose controller is transitively hidden', () => {
		const map = activeMap( fields, { gate: 'no', a: 'yes' } );
		expect( map.a ).toBe( false );
		expect( map.b ).toBe( false );
	} );

	it( 'activates the chain when controllers pass', () => {
		const map = activeMap( fields, { gate: 'yes', a: 'yes' } );
		expect( map.a ).toBe( true );
		expect( map.b ).toBe( true );
	} );
} );
