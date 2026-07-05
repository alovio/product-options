import { useDispatch } from '@wordpress/data';
import { TextControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

const T = 'corelabs-product-options';

/**
 * Add-on pricing for a field: fixed / per-unit (number fields) / percent of
 * the product price. (per_char and formula modes arrive with the new field
 * types — Chunk 3.)
 */
export default function Pricing( { field } ) {
	const { updateField } = useDispatch( STORE );
	const set = ( patch ) => updateField( field.id, patch );
	const mode = field.priceMode || 'fixed';
	const price = field.price || 0;

	const modeOptions = [
		{ label: __( 'Fixed amount', T ), value: 'fixed' },
		...( [ 'number', 'quantity' ].includes( field.type ) ? [ { label: __( 'Per unit (× number entered)', T ), value: 'per_unit' } ] : [] ),
		...( [ 'text', 'textarea' ].includes( field.type ) ? [ { label: __( 'Per character (× text length)', T ), value: 'per_char' } ] : [] ),
		{ label: __( 'Percentage of product price', T ), value: 'percent' },
	];

	const amountLabel =
		mode === 'percent' ? __( 'Add-on — percentage (%)', T )
			: mode === 'per_unit' ? __( 'Add-on — price per unit', T )
				: mode === 'per_char' ? __( 'Add-on — price per character', T )
					: __( 'Add-on price', T );

	return (
		<>
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
			{ price > 0 && (
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
