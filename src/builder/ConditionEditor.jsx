import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl, SelectControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

export default function ConditionEditor( { field } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );
	const others = fields.filter( ( f ) => f.id !== field.id );
	const c = field.condition;
	const enabled = !! c;

	const toggle = ( on ) =>
		updateField( field.id, {
			condition: on && others.length
				? { field: others[ 0 ].id, operator: 'is', value: '', action: 'show' }
				: null,
		} );

	const setCond = ( patch ) => updateField( field.id, { condition: { ...c, ...patch } } );

	return (
		<div className="apo-condition">
			<ToggleControl
				label={ __( 'Show / require based on another field', 'advanced-product-options' ) }
				checked={ enabled }
				disabled={ ! others.length }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<SelectControl
						label={ __( 'When field', 'advanced-product-options' ) }
						value={ c.field }
						options={ others.map( ( o ) => ( { label: o.label || o.type, value: o.id } ) ) }
						onChange={ ( v ) => setCond( { field: v } ) }
					/>
					<SelectControl
						label={ __( 'Operator', 'advanced-product-options' ) }
						value={ c.operator }
						options={ [ { label: __( 'is', 'advanced-product-options' ), value: 'is' }, { label: __( 'is not', 'advanced-product-options' ), value: 'is_not' } ] }
						onChange={ ( v ) => setCond( { operator: v } ) }
					/>
					<TextControl
						label={ __( 'Value', 'advanced-product-options' ) }
						value={ c.value }
						onChange={ ( v ) => setCond( { value: v } ) }
					/>
					<SelectControl
						label={ __( 'Then', 'advanced-product-options' ) }
						value={ c.action }
						options={ [
							{ label: __( 'Show this field', 'advanced-product-options' ), value: 'show' },
							{ label: __( 'Hide this field', 'advanced-product-options' ), value: 'hide' },
							{ label: __( 'Require this field', 'advanced-product-options' ), value: 'require' },
						] }
						onChange={ ( v ) => setCond( { action: v } ) }
					/>
				</>
			) }
		</div>
	);
}
