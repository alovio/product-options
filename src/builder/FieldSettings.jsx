import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import ConditionEditor from './ConditionEditor';
import SwatchOptions from './SwatchOptions';

const HAS_OPTIONS = [ 'radio', 'select' ];

export default function FieldSettings() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const { updateField } = useDispatch( STORE );

	if ( ! field ) {
		return <div className="apo-settings apo-settings--empty">{ __( 'Select a field to edit its settings.', 'conditional-product-options' ) }</div>;
	}

	const set = ( patch ) => updateField( field.id, patch );

	return (
		<div className="apo-settings">
			<TextControl
				label={ __( 'Label', 'conditional-product-options' ) }
				value={ field.label }
				onChange={ ( label ) => set( { label } ) }
			/>
			<ToggleControl
				label={ __( 'Required', 'conditional-product-options' ) }
				checked={ !! field.required }
				onChange={ ( required ) => set( { required } ) }
			/>
			<TextControl
				type="number"
				label={
					field.priceMode === 'per_unit'
						? __( 'Unit price', 'conditional-product-options' )
						: __( 'Add-on price', 'conditional-product-options' )
				}
				value={ field.price }
				onChange={ ( v ) => set( { price: parseFloat( v ) || 0 } ) }
			/>
			{ !! ( window.APO_BUILDER && window.APO_BUILDER.isPro ) && field.type === 'number' && (
				<SelectControl
					label={ __( 'Pricing', 'conditional-product-options' ) }
					value={ field.priceMode || 'fixed' }
					options={ [
						{ label: __( 'Fixed fee', 'conditional-product-options' ), value: 'fixed' },
						{ label: __( 'Per unit (× quantity entered)', 'conditional-product-options' ), value: 'per_unit' },
					] }
					onChange={ ( v ) => set( { priceMode: v } ) }
				/>
			) }
			{ HAS_OPTIONS.includes( field.type ) && (
				<TextareaControl
					label={ __( 'Options (one per line)', 'conditional-product-options' ) }
					value={ ( field.options || [] ).join( '\n' ) }
					onChange={ ( v ) => set( { options: v.split( '\n' ).map( ( s ) => s.trim() ).filter( Boolean ) } ) }
				/>
			) }
			{ field.type === 'swatch' && (
				<SwatchOptions value={ field.options } onChange={ ( opts ) => set( { options: opts } ) } />
			) }
			{ field.type === 'date' && (
				<>
					<TextControl
						type="date"
						label={ __( 'Earliest date (optional)', 'conditional-product-options' ) }
						value={ field.min || '' }
						onChange={ ( v ) => set( { min: v } ) }
					/>
					<TextControl
						type="date"
						label={ __( 'Latest date (optional)', 'conditional-product-options' ) }
						value={ field.max || '' }
						onChange={ ( v ) => set( { max: v } ) }
					/>
				</>
			) }
			<ConditionEditor field={ field } />
		</div>
	);
}
