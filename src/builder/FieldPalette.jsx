import { useDispatch } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const FALLBACK = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price' ];

export default function FieldPalette() {
	const { addField } = useDispatch( STORE );
	const types = ( window.APO_BUILDER && window.APO_BUILDER.fieldTypes ) || FALLBACK;

	return (
		<div className="apo-palette" aria-label={ __( 'Field types', 'advanced-product-options' ) }>
			<h3>{ __( 'Add field', 'advanced-product-options' ) }</h3>
			{ types.map( ( type ) => (
				<Button
					key={ type }
					variant="secondary"
					className="apo-palette__btn"
					draggable
					onClick={ () => addField( type ) }
				>
					{ type }
				</Button>
			) ) }
		</div>
	);
}
