import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { previewValues, simTargets } from './simulation';
import { computeAddonTotal, formatMoney } from '../frontend/price-update';

const T = 'corelabs-product-options';

/** Value picker for one simulated field, shaped by the field type. */
function Pick( { field, value, onChange } ) {
	const label = field.label || field.type;
	const isActive = value !== undefined && value !== '';

	if ( field.type === 'checkbox' || field.type === 'price' ) {
		const opts = [
			{ value: '', label: __( 'Off', T ) },
			{ value: 'yes', label: __( 'On', T ) },
		];
		return (
			<span className={ `clpo-sim${ isActive ? ' is-active' : '' }` }>
				{ label }: { value === 'yes' ? __( 'On', T ) : __( 'Off', T ) } <span className="clpo-car"></span>
				<select value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } aria-label={ label }>
					{ opts.map( ( o ) => (
						<option key={ o.value } value={ o.value }>{ o.label }</option>
					) ) }
				</select>
			</span>
		);
	}

	if ( [ 'radio', 'select', 'swatch' ].includes( field.type ) ) {
		const opts = ( field.options || [] ).map( ( o ) => {
			const v = typeof o === 'object' ? o.label : o;
			return { value: v, label: v };
		} );
		const current = opts.find( ( o ) => o.value === value );
		return (
			<span className={ `clpo-sim${ isActive ? ' is-active' : '' }` }>
				{ label }: { current ? current.label : __( 'Any', T ) } <span className="clpo-car"></span>
				<select value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } aria-label={ label }>
					<option value="">{ __( 'Any', T ) }</option>
					{ opts.map( ( o ) => (
						<option key={ o.value } value={ o.value }>{ o.label }</option>
					) ) }
				</select>
			</span>
		);
	}

	// text / textarea / number / date: inline input chip.
	return (
		<span className={ `clpo-sim${ isActive ? ' is-active' : '' }` }>
			{ label }:{ ' ' }
			<input
				className="clpo-sim-input"
				type={ field.type === 'number' ? 'number' : 'text' }
				value={ value || '' }
				placeholder={ __( 'any', T ) }
				aria-label={ label }
				onChange={ ( e ) => onChange( e.target.value ) }
			/>
		</span>
	);
}

export default function SimulationBar() {
	const { fields, sim } = useSelect( ( select ) => ( {
		fields: select( STORE ).getFields(),
		sim: select( STORE ).getSim(),
	} ), [] );
	const { setSim, resetSim } = useDispatch( STORE );

	const targets = simTargets( fields );
	const values = previewValues( fields, sim );
	const total = computeAddonTotal( fields, values, 0 );
	const hasSim = Object.keys( sim ).length > 0;

	return (
		<div className="clpo-simbar">
			<span className="clpo-lbl"><span className="clpo-eye"></span>{ __( 'PREVIEW AS', T ) }</span>
			{ targets.length === 0 && (
				<span className="clpo-hint">{ __( 'Add a priced or condition-controlling field to simulate customer choices', T ) }</span>
			) }
			{ targets.map( ( f ) => (
				<Pick key={ f.id } field={ f } value={ sim[ f.id ] } onChange={ ( v ) => setSim( { [ f.id ]: v } ) } />
			) ) }
			{ hasSim && (
				<button className="clpo-chip" onClick={ resetSim }>{ __( 'Reset', T ) }</button>
			) }
			<span className="clpo-grow"></span>
			<span className="clpo-hint">
				{ __( 'Options total:', T ) } <strong>+{ formatMoney( total ) }</strong>
			</span>
		</div>
	);
}
