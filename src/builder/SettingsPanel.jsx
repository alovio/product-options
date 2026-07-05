import { useState, useEffect } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import General from './panels/General';
import Logic from './panels/Logic';
import Pricing from './panels/Pricing';

const T = 'corelabs-product-options';

const TYPE_IC = { text: 'Aa', textarea: '¶', number: '#', checkbox: '☑', radio: '◉', select: '▾', heading: 'H', price: '＄', swatch: '🎨', email: '@', phone: '☎', url: '🔗', date: '📅', time: '🕐', file: '📎' };

export default function SettingsPanel() {
	const field = useSelect( ( select ) => select( STORE ).getSelected(), [] );
	const [ tab, setTab ] = useState( 'general' );

	// Reset to General when switching fields.
	useEffect( () => {
		setTab( 'general' );
	}, [ field && field.id ] ); // eslint-disable-line react-hooks/exhaustive-deps

	if ( ! field ) {
		return (
			<div className="clpo-settings">
				<div className="clpo-sp-empty">
					{ __( 'Select a field on the canvas to edit its settings — or add one from the left.', T ) }
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
					<button className={ `clpo-tab${ tab === 'logic' ? ' is-on' : '' }` } onClick={ () => setTab( 'logic' ) }>{ __( 'Logic', T ) }</button>
					{ field.type !== 'heading' && (
						<button className={ `clpo-tab${ tab === 'pricing' ? ' is-on' : '' }` } onClick={ () => setTab( 'pricing' ) }>{ __( 'Pricing', T ) }</button>
					) }
				</div>
			</div>
			<div className="clpo-sp-body">
				{ tab === 'general' && <General field={ field } /> }
				{ tab === 'logic' && <Logic field={ field } /> }
				{ tab === 'pricing' && field.type !== 'heading' && <Pricing field={ field } /> }
			</div>
			<div className="clpo-sp-foot">
				{ __( 'Changes preview instantly', T ) } · <span className="clpo-kbd">⌘S</span> { __( 'saves', T ) }
			</div>
		</div>
	);
}
