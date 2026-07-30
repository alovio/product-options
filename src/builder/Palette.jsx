import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import FieldIcon from '../shared/FieldIcon';

const T = 'corelabs-product-options';
const FALLBACK = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price', 'heading', 'swatch', 'date', 'email', 'phone', 'url', 'time', 'quantity', 'buttons', 'image_swatch', 'file' ];

const LABELS = {
	text: __( 'Text', T ),
	textarea: __( 'Text area', T ),
	number: __( 'Number', T ),
	checkbox: __( 'Checkbox', T ),
	radio: __( 'Radio', T ),
	select: __( 'Dropdown', T ),
	heading: __( 'Heading', T ),
	price: __( 'Surcharge', T ),
	swatch: __( 'Colour swatch', T ),
	email: __( 'Email', T ),
	phone: __( 'Phone', T ),
	url: __( 'URL', T ),
	date: __( 'Date', T ),
	time: __( 'Time', T ),
	file: __( 'File', T ),
	quantity: __( 'Quantity', T ),
	buttons: __( 'Buttons', T ),
	image_swatch: __( 'Image swatch', T ),
};

export default function Palette() {
	const { addField } = useDispatch( STORE );
	const types = ( window.CLPO_HUB && window.CLPO_HUB.fieldTypes ) || FALLBACK;

	return (
		<div className="clpo-palette">
			<div className="clpo-sec-label">{ __( 'Add a field', T ) }</div>
			<div className="clpo-ptypes">
				{ types.map( ( type ) => (
					<button key={ type } className="clpo-ptype" onClick={ () => addField( type ) }>
						<span className="clpo-ic"><FieldIcon type={ type } /></span>
						{ LABELS[ type ] || type }
					</button>
				) ) }
			</div>

		</div>
	);
}
