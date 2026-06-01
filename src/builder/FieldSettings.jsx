import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import ConditionEditor from './ConditionEditor';

const HAS_OPTIONS = [ 'radio', 'select' ];

export default function FieldSettings() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const { updateField } = useDispatch( STORE );

	if ( ! field ) {
		return <div className="apo-settings apo-settings--empty">{ __( 'Select a field to edit its settings.', 'advanced-product-options' ) }</div>;
	}

	const set = ( patch ) => updateField( field.id, patch );

	return (
		<div className="apo-settings">
			<TextControl
				label={ __( 'Label', 'advanced-product-options' ) }
				value={ field.label }
				onChange={ ( label ) => set( { label } ) }
			/>
			<ToggleControl
				label={ __( 'Required', 'advanced-product-options' ) }
				checked={ !! field.required }
				onChange={ ( required ) => set( { required } ) }
			/>
			<TextControl
				type="number"
				label={ __( 'Add-on price', 'advanced-product-options' ) }
				value={ field.price }
				onChange={ ( v ) => set( { price: parseFloat( v ) || 0 } ) }
			/>
			{ HAS_OPTIONS.includes( field.type ) && (
				<TextareaControl
					label={ __( 'Options (one per line)', 'advanced-product-options' ) }
					value={ ( field.options || [] ).join( '\n' ) }
					onChange={ ( v ) => set( { options: v.split( '\n' ).map( ( s ) => s.trim() ).filter( Boolean ) } ) }
				/>
			) }
			<ConditionEditor field={ field } />
		</div>
	);
}
