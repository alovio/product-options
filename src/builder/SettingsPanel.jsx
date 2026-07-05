import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import General from './panels/General';
import Logic from './panels/Logic';
import Pricing from './panels/Pricing';
import Options, { HAS_OPTIONS_TYPES } from './panels/Options';
import Assignment from './panels/Assignment';

const T = 'corelabs-product-options';

const TYPE_IC = { text: 'Aa', textarea: '¶', number: '#', checkbox: '☑', radio: '◉', select: '▾', heading: 'H', price: '＄', swatch: '🎨', quantity: '±', buttons: '⬚', image_swatch: '🖼', email: '@', phone: '☎', url: '🔗', date: '📅', time: '🕐', file: '📎' };

export default function SettingsPanel() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const [ tab, setTab ] = useState( 'general' );

	// Reset to General when switching fields.
	useEffect( () => {
		setTab( 'general' );
	}, [ field && field.id ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! field ) {
		// No field selected: group-level settings (assignment + priority).
		return (
			<div className="clpo-settings">
				<div className="clpo-sp-head">
					<div className="clpo-sp-title">
						<span className="clpo-ic">⚙</span>
						<div>
							<h3>{ __( 'Group settings', T ) }</h3>
							<small>{ __( 'select a field to edit it', T ) }</small>
						</div>
					</div>
				</div>
				<div className="clpo-sp-body">
					<Assignment />
				</div>
			</div>
		);
	}

	const meta = [ field.type, field.required ? __( 'required', T ) : null ].filter( Boolean ).join( ' · ' );

	return (
		<div className="clpo-settings">
			<div className="clpo-sp-head">
				<div className="clpo-sp-title">
					<span className="clpo-ic">{ TYPE_IC[ field.type ] || '·' }</span>
					<div>
						<h3>{ field.label || field.type }</h3>
						<small>{ meta }</small>
					</div>
				</div>
				<div className="clpo-tabs">
					<button className={ `clpo-tab${ tab === 'general' ? ' is-on' : '' }` } onClick={ () => setTab( 'general' ) }>{ __( 'General', T ) }</button>
					{ HAS_OPTIONS_TYPES.includes( field.type ) && (
						<button className={ `clpo-tab${ tab === 'options' ? ' is-on' : '' }` } onClick={ () => setTab( 'options' ) }>{ __( 'Options', T ) }</button>
					) }
					<button className={ `clpo-tab${ tab === 'logic' ? ' is-on' : '' }` } onClick={ () => setTab( 'logic' ) }>{ __( 'Logic', T ) }</button>
					{ field.type !== 'heading' && (
						<button className={ `clpo-tab${ tab === 'pricing' ? ' is-on' : '' }` } onClick={ () => setTab( 'pricing' ) }>{ __( 'Pricing', T ) }</button>
					) }
				</div>
			</div>
			<div className="clpo-sp-body">
				{ tab === 'general' && <General field={ field } /> }
				{ tab === 'options' && HAS_OPTIONS_TYPES.includes( field.type ) && <Options field={ field } /> }
				{ tab === 'logic' && <Logic field={ field } /> }
				{ tab === 'pricing' && field.type !== 'heading' && <Pricing field={ field } /> }
			</div>
			<div className="clpo-sp-foot">
				{ __( 'Changes preview instantly', T ) } · <span className="clpo-kbd">⌘S</span> { __( 'saves', T ) }
			</div>
		</div>
	);
}
