/**
 * Money facade over the decimal-safe formula engine — the JS mirror of
 * includes/Formula/FormulaPrice.php, kept in lockstep via
 * tests/fixtures/formula-cases.json.
 */
import { tokenize } from './lexer';
import { parse } from './parser';
import { evaluate as rawEvaluate } from './evaluator';
import { FormulaError, toScaled, fromScaled } from './decimal';

export { FormulaError } from './decimal';

export const MAX_LENGTH = 200;

// Must stay identical to includes/Formula/Functions.php SPECS.
export const FUNCTION_SPECS = {
	min: [ 2, 8 ],
	max: [ 2, 8 ],
	round: [ 1, 2 ],
};

export function compile( expr ) {
	if ( String( expr ).length > MAX_LENGTH ) {
		throw new FormulaError( 'too_long', 'Expression exceeds the length cap' );
	}
	return parse( tokenize( String( expr ) ), FUNCTION_SPECS );
}

export function references( ast ) {
	const refs = [];
	const walk = ( node ) => {
		if ( node.type === 'field' ) {
			refs.push( node.id );
		} else if ( node.type === 'neg' ) {
			walk( node.operand );
		} else if ( node.type === 'bin' ) {
			walk( node.left );
			walk( node.right );
		} else if ( node.type === 'call' ) {
			node.args.forEach( walk );
		}
	};
	walk( ast );
	return [ ...new Set( refs ) ];
}

/**
 * Never throws: any error yields 0. Missing tokens resolve to 0. Result is
 * clamped ≥ 0 and rounded to 2 decimals — identical to FormulaPrice::evaluate.
 *
 * @param {string} expr
 * @param {Object} values field id -> numeric value
 * @return {number}
 */
export function evaluateFormula( expr, values ) {
	try {
		const ast = compile( expr );
		const scaled = {};
		references( ast ).forEach( ( ref ) => {
			const v = values && values[ ref ] !== undefined && values[ ref ] !== null && values[ ref ] !== '' ? values[ ref ] : 0;
			scaled[ ref ] = toScaled( isNaN( Number( v ) ) ? 0 : Number( v ) );
		} );
		const result = rawEvaluate( ast, scaled, FUNCTION_SPECS );
		const float = Number( fromScaled( result ) );
		return Math.round( Math.max( 0, float ) * 100 ) / 100;
	} catch ( e ) {
		if ( e instanceof FormulaError ) {
			return 0;
		}
		throw e;
	}
}

/**
 * Compile-only validation (length cap included) — the builder's inline error
 * source. Returns null when valid, an error message string otherwise.
 *
 * @param {string} expr
 * @return {string|null}
 */
export function validateFormula( expr ) {
	try {
		compile( expr );
		return null;
	} catch ( e ) {
		if ( e instanceof FormulaError ) {
			return e.message;
		}
		throw e;
	}
}
