/**
 * Iterates the shared PHP↔JS fixture — the same file FormulaTest.php runs.
 * evaluateFormula never throws (errors -> 0); validateFormula flags compile
 * errors only.
 */
import { evaluateFormula, validateFormula } from '../../src/shared/formula';
import cases from '../fixtures/formula-cases.json';

describe( 'formula fixture parity', () => {
	cases.forEach( ( c ) => {
		it( c.name, () => {
			const result = evaluateFormula( c.expr, c.values );
			if ( typeof c.expected === 'object' ) {
				expect( result ).toBe( 0 );
				if ( c.expected.error === 'compile' ) {
					expect( typeof validateFormula( c.expr ) ).toBe( 'string' );
				} else {
					expect( validateFormula( c.expr ) ).toBeNull();
				}
				return;
			}
			expect( result ).toBe( c.expected );
			expect( validateFormula( c.expr ) ).toBeNull();
		} );
	} );
} );
