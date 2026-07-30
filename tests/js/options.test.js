import {
	optionLabel,
	optionPrice,
	optionLabels,
	optionPriceForValue,
	hasPricedOptions,
	effectivePrice,
	priceRange,
} from '../../src/shared/options';
import { computeBreakdown, computeAddonTotal } from '../../src/frontend/price-update';
import fixture from '../fixtures/option-pricing-cases.json';

const SIZES = {
	id: 'size',
	type: 'select',
	label: 'Frame size',
	price: 0,
	priceMode: 'fixed',
	options: [
		{ label: '21x30', price: 399 },
		{ label: '30x40', price: 499 },
		{ label: '50x70', price: 799 },
	],
};

describe( 'option shape helpers', () => {
	it( 'reads labels from both shapes', () => {
		expect( optionLabel( 'Large' ) ).toBe( 'Large' );
		expect( optionLabel( { label: 'Large', price: 5 } ) ).toBe( 'Large' );
		expect( optionLabel( null ) ).toBe( '' );
	} );

	it( 'only counts positive numeric prices', () => {
		expect( optionPrice( { label: 'a', price: 5 } ) ).toBe( 5 );
		expect( optionPrice( 'a' ) ).toBe( 0 );
		expect( optionPrice( { label: 'a' } ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: -5 } ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: 'free' } ) ).toBe( 0 );
	} );

	it( 'lists labels across mixed shapes', () => {
		expect( optionLabels( { options: [ 'S', { label: 'M', price: 3 } ] } ) ).toEqual( [ 'S', 'M' ] );
	} );

	it( 'finds the price of the picked option', () => {
		expect( optionPriceForValue( SIZES, '21x30' ) ).toBe( 399 );
		expect( optionPriceForValue( SIZES, '50x70' ) ).toBe( 799 );
		expect( optionPriceForValue( SIZES, 'A2' ) ).toBe( 0 );
		expect( optionPriceForValue( SIZES, '' ) ).toBe( 0 );
	} );

	it( 'detects priced options', () => {
		expect( hasPricedOptions( SIZES ) ).toBe( true );
		expect( hasPricedOptions( { options: [ 'S', 'M' ] } ) ).toBe( false );
	} );

	it( 'prefers the option price over the field price', () => {
		const field = { type: 'radio', price: 25, options: [ 'Standard', { label: 'Oversized', price: 90 } ] };
		expect( effectivePrice( field, 'Standard' ) ).toBe( 25 );
		expect( effectivePrice( field, 'Oversized' ) ).toBe( 90 );
	} );

	it( 'never lets options price a non-choice field', () => {
		const field = { type: 'text', price: 5, options: [ { label: 'gold', price: 500 } ] };
		expect( optionPriceForValue( field, 'gold' ) ).toBe( 0 );
		expect( effectivePrice( field, 'gold' ) ).toBe( 5 );
	} );

	it( 'ignores a half-numeric price exactly as PHP is_numeric does', () => {
		expect( optionPrice( { label: 'a', price: '399abc' } ) ).toBe( 0 );
		expect( optionPrice( { label: 'a', price: '399' } ) ).toBe( 399 );
		expect( optionPrice( { label: 'a', price: ' ' } ) ).toBe( 0 );
	} );

	it( 'spans what the field can charge, including the fallback', () => {
		const field = { type: 'radio', price: 25, options: [ 'Standard', { label: 'Oversized', price: 90 } ] };
		expect( priceRange( field ) ).toEqual( [ 25, 90 ] );
		expect( priceRange( { type: 'select', options: [ 'A', 'B' ] } ) ).toEqual( [ 0, 0 ] );
	} );
} );

describe( 'per-option pricing in the breakdown (PHP parity)', () => {
	it( 'charges each option its own price', () => {
		expect( computeAddonTotal( [ SIZES ], { size: '21x30' } ) ).toBe( 399 );
		expect( computeAddonTotal( [ SIZES ], { size: '50x70' } ) ).toBe( 799 );
	} );

	it( 'bills a priced option even when the field price is zero', () => {
		const rows = computeBreakdown( [ SIZES ], { size: '30x40' } );
		expect( rows ).toHaveLength( 1 );
		expect( rows[ 0 ] ).toMatchObject( { fieldId: 'size', label: 'Frame size', amount: 499 } );
	} );

	it( 'charges nothing without a selection or for an unknown value', () => {
		expect( computeAddonTotal( [ SIZES ], {} ) ).toBe( 0 );
		expect( computeAddonTotal( [ SIZES ], { size: '' } ) ).toBe( 0 );
		expect( computeAddonTotal( [ SIZES ], { size: 'A2 (+9999)' } ) ).toBe( 0 );
	} );

	it( 'runs option prices through percent mode', () => {
		const speed = {
			id: 'speed',
			type: 'radio',
			label: 'Speed',
			price: 0,
			priceMode: 'percent',
			options: [ { label: 'Express', price: 10 }, { label: 'Overnight', price: 20 } ],
		};
		expect( computeAddonTotal( [ speed ], { speed: 'Express' }, 200 ) ).toBe( 20 );
		expect( computeAddonTotal( [ speed ], { speed: 'Overnight' }, 200 ) ).toBe( 40 );
	} );

	// The same file PriceCalculatorTest.php runs — the two engines must agree.
	describe( 'shared fixtures', () => {
		fixture.cases.forEach( ( c ) => {
			it( c.name, () => {
				expect( computeAddonTotal( [ c.field ], { [ c.field.id ]: c.value }, c.base ) ).toBe( c.expected );
			} );
		} );
	} );

	it( 'falls back to the field price for unpriced options', () => {
		const field = {
			id: 'size',
			type: 'radio',
			label: 'Size',
			price: 25,
			priceMode: 'fixed',
			options: [ 'Standard', { label: 'Oversized', price: 90 } ],
		};
		expect( computeAddonTotal( [ field ], { size: 'Standard' } ) ).toBe( 25 );
		expect( computeAddonTotal( [ field ], { size: 'Oversized' } ) ).toBe( 90 );
	} );
} );
