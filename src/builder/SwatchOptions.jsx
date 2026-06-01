import { Button, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';

const D = 'conditional-product-options';

export default function SwatchOptions( { value, onChange } ) {
	const options = Array.isArray( value ) ? value : [];
	const update = ( i, patch ) => onChange( options.map( ( o, idx ) => ( idx === i ? { ...o, ...patch } : o ) ) );
	const add = () => onChange( [ ...options, { label: '', color: '#cccccc' } ] );
	const remove = ( i ) => onChange( options.filter( ( _, idx ) => idx !== i ) );

	return (
		<div className="apo-swatch-options">
			<span className="apo-swatch-options__title">{ __( 'Swatches', D ) }</span>
			{ options.map( ( o, i ) => (
				<div className="apo-swatch-row" key={ i }>
					<input
						type="color"
						value={ o.color || '#cccccc' }
						onChange={ ( e ) => update( i, { color: e.target.value } ) }
						aria-label={ __( 'Colour', D ) }
					/>
					<TextControl
						value={ o.label || '' }
						placeholder={ __( 'Label', D ) }
						onChange={ ( v ) => update( i, { label: v } ) }
					/>
					<Button isDestructive variant="link" onClick={ () => remove( i ) } aria-label={ __( 'Remove', D ) }>✕</Button>
				</div>
			) ) }
			<Button variant="secondary" onClick={ add }>{ __( '+ Add swatch', D ) }</Button>
		</div>
	);
}
