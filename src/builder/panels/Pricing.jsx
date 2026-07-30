import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, TextareaControl, SelectControl, Button, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';
import { validateFormula } from '../../shared/formula';
import { hasPricedOptions } from '../../shared/options';

const T = 'corelabs-product-options';

/**
 * Add-on pricing for a field: fixed / per-unit (number fields) / percent of
 * the product price. (per_char and formula modes arrive with the new field
 * types — Chunk 3.)
 */
export default function Pricing( { field } ) {
	const { updateField } = useDispatch( STORE );
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const set = ( patch ) => updateField( field.id, patch );
	const mode = field.priceMode || 'fixed';
	const price = field.price || 0;
	const formula = field.formula || '';
	const formulaError = mode === 'formula' && formula !== '' ? validateFormula( formula ) : null;
	const numericSiblings = fields.filter( ( f ) => f.id !== field.id && [ 'number', 'quantity' ].includes( f.type ) );

	const modeOptions = [
		{ label: __( 'Fixed amount', T ), value: 'fixed' },
		...( [ 'number', 'quantity' ].includes( field.type ) ? [ { label: __( 'Per unit (× number entered)', T ), value: 'per_unit' } ] : [] ),
		...( [ 'text', 'textarea' ].includes( field.type ) ? [ { label: __( 'Per character (× text length)', T ), value: 'per_char' } ] : [] ),
		{ label: __( 'Percentage of product price', T ), value: 'percent' },
		{ label: __( 'Formula (advanced)', T ), value: 'formula' },
	];

	const amountLabel =
		mode === 'percent' ? __( 'Add-on — percentage (%)', T )
			: mode === 'per_unit' ? __( 'Add-on — price per unit', T )
				: mode === 'per_char' ? __( 'Add-on — price per character', T )
					: __( 'Add-on price', T );

	if ( mode === 'formula' ) {
		return (
			<>
				<SelectControl
					label={ __( 'Pricing type', T ) }
					value={ mode }
					options={ modeOptions }
					onChange={ ( v ) => set( { priceMode: v } ) }
				/>
				<TextareaControl
					label={ __( 'Formula', T ) }
					help={ __( 'Arithmetic over number/quantity fields: + − × ÷, parentheses, min(), max(), round(). Example: {width} * {height} * 0.85', T ) }
					value={ formula }
					onChange={ ( v ) => set( { formula: v } ) }
				/>
				{ numericSiblings.length > 0 && (
					<div className="clpo-chips" style={ { marginBottom: 8 } }>
						{ numericSiblings.map( ( f ) => (
							<button
								key={ f.id }
								type="button"
								className="clpo-chip"
								onClick={ () => set( { formula: `${ formula }{${ f.id }}` } ) }
							>
								{ f.label || f.id }
							</button>
						) ) }
					</div>
				) }
				{ formulaError && (
					<Notice status="error" isDismissible={ false }>{ formulaError }</Notice>
				) }
				{ ! formulaError && formula !== '' && (
					<p style={ { fontSize: 12, color: '#15803d' } }>✓ { __( 'Formula is valid.', T ) }</p>
				) }
				<p style={ { fontSize: 12, color: '#57606a' } }>
					{ __( 'Invalid or empty formulas are saved as a fixed price of 0 — fix the error before publishing.', T ) }
				</p>
			</>
		);
	}

	const priced = hasPricedOptions( field );

	return (
		<>
			{ priced && (
				<Notice status="info" isDismissible={ false }>
					{ __( 'This field prices each option separately (set in the Options tab). The amount below only applies to options with no price of their own.', T ) }
				</Notice>
			) }
			<TextControl
				type="number"
				min={ 0 }
				step="0.01"
				label={ amountLabel }
				help={ __( 'Added to the product price when this field is filled in or selected. Leave 0 for no charge.', T ) }
				value={ price }
				onChange={ ( v ) => set( { price: Math.max( 0, parseFloat( v ) || 0 ) } ) }
			/>
			<SelectControl
				label={ __( 'Pricing type', T ) }
				value={ mode }
				options={ modeOptions }
				onChange={ ( v ) => set( { priceMode: v } ) }
			/>
			{ price > 0 && ! priced && (
				<p style={ { fontSize: 12, color: '#57606a' } }>
					{ mode === 'percent'
						? `${ __( 'Charges', T ) } ${ price }% ${ __( 'of the product price.', T ) }`
						: mode === 'per_unit'
							? `${ __( 'Charges', T ) } ${ price } ${ __( 'for each unit entered.', T ) }`
							: mode === 'per_char'
								? `${ __( 'Charges', T ) } ${ price } ${ __( 'for each character typed.', T ) }`
								: `${ __( 'Charges a flat', T ) } ${ price }.` }
				</p>
			) }
		</>
	);
}
