import { useDispatch, useSelect } from '@wordpress/data';
import { ToggleControl, SelectControl, TextControl, Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const D = 'conditional-product-options';
const OP_LABELS = {
	is: __( 'is', D ),
	is_not: __( 'is not', D ),
	contains: __( 'contains', D ),
	gt: __( 'greater than', D ),
	lt: __( 'less than', D ),
};

export default function ConditionEditor( { field } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { updateField } = useDispatch( STORE );
	const others = fields.filter( ( f ) => f.id !== field.id );

	const isPro = !! ( window.APO_BUILDER && window.APO_BUILDER.isPro );
	const operators = ( window.APO_BUILDER && window.APO_BUILDER.operators ) || [ 'is', 'is_not' ];
	const opOptions = operators.map( ( o ) => ( { label: OP_LABELS[ o ] || o, value: o } ) );
	const actionOptions = [
		{ label: __( 'Show this field', D ), value: 'show' },
		{ label: __( 'Hide this field', D ), value: 'hide' },
		{ label: __( 'Require this field', D ), value: 'require' },
	];
	const fieldOptions = others.map( ( o ) => ( { label: o.label || o.type, value: o.id } ) );

	// ---- Pro: multiple conditions (AND/OR) ----
	if ( isPro ) {
		const rules = Array.isArray( field.conditions ) ? field.conditions : [];
		const enabled = rules.length > 0;
		const setRules = ( newRules ) =>
			updateField( field.id, {
				condition: null,
				conditions: newRules,
				conditionMatch: field.conditionMatch || 'all',
				conditionAction: field.conditionAction || 'show',
			} );
		const toggle = ( on ) =>
			on && others.length
				? setRules( [ { field: others[ 0 ].id, operator: operators[ 0 ], value: '' } ] )
				: updateField( field.id, { conditions: [] } );
		const updateRule = ( i, patch ) => setRules( rules.map( ( r, idx ) => ( idx === i ? { ...r, ...patch } : r ) ) );
		const addRule = () => setRules( [ ...rules, { field: others[ 0 ].id, operator: operators[ 0 ], value: '' } ] );
		const removeRule = ( i ) => setRules( rules.filter( ( _, idx ) => idx !== i ) );

		return (
			<div className="apo-condition">
				<ToggleControl
					label={ __( 'Conditional rules', D ) }
					checked={ enabled }
					disabled={ ! others.length }
					onChange={ toggle }
				/>
				{ enabled && (
					<>
						<SelectControl
							label={ __( 'Match', D ) }
							value={ field.conditionMatch || 'all' }
							options={ [
								{ label: __( 'All rules (AND)', D ), value: 'all' },
								{ label: __( 'Any rule (OR)', D ), value: 'any' },
							] }
							onChange={ ( v ) => updateField( field.id, { conditionMatch: v } ) }
						/>
						{ rules.map( ( r, i ) => (
							<div className="apo-rule" key={ i }>
								<SelectControl label={ __( 'When field', D ) } value={ r.field } options={ fieldOptions } onChange={ ( v ) => updateRule( i, { field: v } ) } />
								<SelectControl label={ __( 'Operator', D ) } value={ r.operator } options={ opOptions } onChange={ ( v ) => updateRule( i, { operator: v } ) } />
								<TextControl label={ __( 'Value', D ) } value={ r.value } onChange={ ( v ) => updateRule( i, { value: v } ) } />
								{ rules.length > 1 && (
									<Button isDestructive variant="link" onClick={ () => removeRule( i ) }>{ __( 'Remove rule', D ) }</Button>
								) }
							</div>
						) ) }
						<Button variant="secondary" onClick={ addRule }>{ __( '+ Add rule', D ) }</Button>
						<SelectControl
							label={ __( 'Then', D ) }
							value={ field.conditionAction || 'show' }
							options={ actionOptions }
							onChange={ ( v ) => updateField( field.id, { conditionAction: v } ) }
						/>
					</>
				) }
			</div>
		);
	}

	// ---- Free: a single condition ----
	const c = field.condition;
	const enabled = !! c;
	const toggle = ( on ) =>
		updateField( field.id, {
			condition: on && others.length ? { field: others[ 0 ].id, operator: 'is', value: '', action: 'show' } : null,
		} );
	const setCond = ( patch ) => updateField( field.id, { condition: { ...c, ...patch } } );

	return (
		<div className="apo-condition">
			<ToggleControl
				label={ __( 'Show / require based on another field', D ) }
				checked={ enabled }
				disabled={ ! others.length }
				onChange={ toggle }
			/>
			{ enabled && (
				<>
					<SelectControl label={ __( 'When field', D ) } value={ c.field } options={ fieldOptions } onChange={ ( v ) => setCond( { field: v } ) } />
					<SelectControl label={ __( 'Operator', D ) } value={ c.operator } options={ opOptions } onChange={ ( v ) => setCond( { operator: v } ) } />
					<TextControl label={ __( 'Value', D ) } value={ c.value } onChange={ ( v ) => setCond( { value: v } ) } />
					<SelectControl label={ __( 'Then', D ) } value={ c.action } options={ actionOptions } onChange={ ( v ) => setCond( { action: v } ) } />
				</>
			) }
			<p className="apo-pro-hint">{ __( 'Pro: combine multiple conditions (AND/OR) and use contains / greater-than / less-than.', D ) }</p>
		</div>
	);
}
