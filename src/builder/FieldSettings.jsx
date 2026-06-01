import { useDispatch, useSelect } from '@wordpress/data';
import { TextControl, ToggleControl, TextareaControl, SelectControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import ConditionEditor from './ConditionEditor';
import SwatchOptions from './SwatchOptions';

const T = 'conditional-product-options';
const HAS_OPTIONS = [ 'radio', 'select' ];
const HAS_PLACEHOLDER = [ 'text', 'textarea', 'number' ];
const HAS_MAXLEN = [ 'text', 'textarea' ];

function DefaultControl( { field, set } ) {
	const t = field.type;
	if ( HAS_PLACEHOLDER.includes( t ) ) {
		return <TextControl label={ __( 'Default value', T ) } value={ field.default || '' } onChange={ ( v ) => set( { default: v } ) } />;
	}
	if ( t === 'checkbox' ) {
		return <ToggleControl label={ __( 'Checked by default', T ) } checked={ field.default === 'yes' } onChange={ ( on ) => set( { default: on ? 'yes' : '' } ) } />;
	}
	if ( HAS_OPTIONS.includes( t ) ) {
		const opts = [ { label: __( '— none —', T ), value: '' }, ...( field.options || [] ).map( ( o ) => ( { label: o, value: o } ) ) ];
		return <SelectControl label={ __( 'Default selection', T ) } value={ field.default || '' } options={ opts } onChange={ ( v ) => set( { default: v } ) } />;
	}
	return null;
}

export default function FieldSettings() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const { updateField } = useDispatch( STORE );

	if ( ! field ) {
		return <div className="apo-settings apo-settings--empty">{ __( 'Select a field to edit its settings.', T ) }</div>;
	}

	const set = ( patch ) => updateField( field.id, patch );
	const isPro = !! ( window.APO_BUILDER && window.APO_BUILDER.isPro );

	// Heading is a display-only field: just a label, description, and optional condition.
	if ( field.type === 'heading' ) {
		return (
			<div className="apo-settings">
				<TextControl label={ __( 'Heading text', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />
				<TextareaControl label={ __( 'Description', T ) } value={ field.description || '' } onChange={ ( v ) => set( { description: v } ) } />
				<ConditionEditor field={ field } />
			</div>
		);
	}

	const optionsEmpty = HAS_OPTIONS.includes( field.type ) && ! ( field.options || [] ).length;

	return (
		<div className="apo-settings">
			<TextControl label={ __( 'Label', T ) } value={ field.label } onChange={ ( label ) => set( { label } ) } />

			<TextControl
				label={ __( 'Description / help text', T ) }
				value={ field.description || '' }
				onChange={ ( v ) => set( { description: v } ) }
			/>

			{ field.type !== 'price' && (
				<ToggleControl label={ __( 'Required', T ) } checked={ !! field.required } onChange={ ( required ) => set( { required } ) } />
			) }

			{ HAS_PLACEHOLDER.includes( field.type ) && (
				<TextControl label={ __( 'Placeholder', T ) } value={ field.placeholder || '' } onChange={ ( v ) => set( { placeholder: v } ) } />
			) }

			<DefaultControl field={ field } set={ set } />

			<TextControl
				type="number"
				min={ 0 }
				label={
					field.priceMode === 'per_unit'
						? __( 'Unit price', T )
						: field.priceMode === 'percent'
							? __( 'Percentage (%)', T )
							: __( 'Add-on price', T )
				}
				help={
					field.priceMode === 'percent'
						? __( 'Adds this % of the product price when the field is filled in.', T )
						: __( 'Flat fee added when this field is filled in or selected. (Per-option pricing is in Pro.)', T )
				}
				value={ field.price }
				onChange={ ( v ) => set( { price: Math.max( 0, parseFloat( v ) || 0 ) } ) }
			/>

			{ isPro && (
				<SelectControl
					label={ __( 'Pricing', T ) }
					value={ field.priceMode || 'fixed' }
					options={ [
						{ label: __( 'Fixed fee', T ), value: 'fixed' },
						...( field.type === 'number' ? [ { label: __( 'Per unit (× quantity entered)', T ), value: 'per_unit' } ] : [] ),
						{ label: __( 'Percentage of product price', T ), value: 'percent' },
					] }
					onChange={ ( v ) => set( { priceMode: v } ) }
				/>
			) }

			{ field.type === 'number' && (
				<div className="apo-row3">
					<TextControl type="number" label={ __( 'Min', T ) } value={ field.min || '' } onChange={ ( v ) => set( { min: v } ) } />
					<TextControl type="number" label={ __( 'Max', T ) } value={ field.max || '' } onChange={ ( v ) => set( { max: v } ) } />
					<TextControl type="number" label={ __( 'Step', T ) } value={ field.step || '' } onChange={ ( v ) => set( { step: v } ) } />
				</div>
			) }

			{ HAS_MAXLEN.includes( field.type ) && (
				<TextControl type="number" min={ 0 } label={ __( 'Max length (characters)', T ) } value={ field.maxLength || '' } onChange={ ( v ) => set( { maxLength: parseInt( v, 10 ) || 0 } ) } />
			) }

			{ HAS_OPTIONS.includes( field.type ) && (
				<TextareaControl
					label={ __( 'Options (one per line)', T ) }
					value={ ( field.options || [] ).join( '\n' ) }
					onChange={ ( v ) => set( { options: v.split( '\n' ).map( ( s ) => s.trim() ).filter( Boolean ) } ) }
				/>
			) }
			{ optionsEmpty && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', T ) }</Notice>
			) }

			{ field.type === 'swatch' && (
				<SwatchOptions value={ field.options } onChange={ ( opts ) => set( { options: opts } ) } />
			) }

			{ field.type === 'date' && (
				<>
					<TextControl type="date" label={ __( 'Earliest date (optional)', T ) } value={ field.min || '' } onChange={ ( v ) => set( { min: v } ) } />
					<TextControl type="date" label={ __( 'Latest date (optional)', T ) } value={ field.max || '' } onChange={ ( v ) => set( { max: v } ) } />
				</>
			) }

			<ConditionEditor field={ field } />
		</div>
	);
}
