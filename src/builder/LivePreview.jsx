import { useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import { activeMap } from '../frontend/conditional-logic';

function renderInput( f, value, onChange ) {
	switch ( f.type ) {
		case 'textarea':
			return <textarea value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } />;
		case 'number':
			return <input type="number" value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } />;
		case 'checkbox':
			return <input type="checkbox" checked={ value === 'yes' } onChange={ ( e ) => onChange( e.target.checked ? 'yes' : '' ) } />;
		case 'radio':
			return ( f.options || [] ).map( ( o ) => (
				<label key={ o } className="apo-preview__radio">
					<input type="radio" name={ f.id } value={ o } checked={ value === o } onChange={ () => onChange( o ) } /> { o }
				</label>
			) );
		case 'select':
			return (
				<select value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) }>
					<option value="">—</option>
					{ ( f.options || [] ).map( ( o ) => <option key={ o } value={ o }>{ o }</option> ) }
				</select>
			);
		case 'swatch':
			return (
				<span className="apo-swatches">
					{ ( f.options || [] ).map( ( o ) => {
						const lbl = typeof o === 'object' ? o.label : o;
						const col = typeof o === 'object' ? o.color : '#cccccc';
						return (
							<label key={ lbl } className="apo-swatch" title={ lbl }>
								<input type="radio" name={ f.id } value={ lbl } checked={ value === lbl } onChange={ () => onChange( lbl ) } />
								<span className="apo-swatch__dot" style={ { backgroundColor: col } } />
							</label>
						);
					} ) }
				</span>
			);
		case 'price':
			return <em className="apo-preview__fee">+{ f.price }</em>;
		default:
			return <input type="text" value={ value || '' } onChange={ ( e ) => onChange( e.target.value ) } />;
	}
}

export default function LivePreview() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const [ values, setValues ] = useState( {} );
	const set = ( id, v ) => setValues( ( prev ) => ( { ...prev, [ id ]: v } ) );

	if ( ! fields.length ) {
		return <p className="apo-preview apo-preview--empty">{ __( 'No fields yet.', 'conditional-product-options' ) }</p>;
	}

	const map = activeMap( fields, values );

	return (
		<form className="apo-preview" onSubmit={ ( e ) => e.preventDefault() }>
			{ fields.map( ( f ) => {
				if ( ! map[ f.id ] ) {
					return null;
				}
				return (
					<div key={ f.id } className="apo-preview__field" data-apo-field={ f.id }>
						<label>{ f.label || f.type }{ f.required ? ' *' : '' }</label>
						{ renderInput( f, values[ f.id ], ( v ) => set( f.id, v ) ) }
					</div>
				);
			} ) }
		</form>
	);
}
