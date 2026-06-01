/**
 * JS/PHP parity: `evaluate` must match every case in the shared fixture
 * (the same file APO\Logic\ConditionalLogic is tested against).
 */
import { evaluate } from '../../src/frontend/conditional-logic';
import cases from '../fixtures/conditional-cases.json';

describe( 'evaluate (parity with PHP ConditionalLogic)', () => {
	cases.forEach( ( c ) => {
		it( c.name, () => {
			expect( evaluate( c.condition, c.values ) ).toBe( c.expectedActive );
		} );
	} );
} );
