import { useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

const T = 'corelabs-product-options';
const FALLBACK = [ 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price', 'heading', 'swatch', 'date', 'email', 'phone', 'url', 'time', 'quantity', 'buttons', 'image_swatch' ];

const META = {
	text: { label: __( 'Text', T ), ic: 'Aa' },
	textarea: { label: __( 'Text area', T ), ic: '¶' },
	number: { label: __( 'Number', T ), ic: '#' },
	checkbox: { label: __( 'Checkbox', T ), ic: '☑' },
	radio: { label: __( 'Radio', T ), ic: '◉' },
	select: { label: __( 'Dropdown', T ), ic: '▾' },
	heading: { label: __( 'Heading', T ), ic: 'H' },
	price: { label: __( 'Surcharge', T ), ic: '＄' },
	swatch: { label: __( 'Colour swatch', T ), ic: '🎨' },
	email: { label: __( 'Email', T ), ic: '@' },
	phone: { label: __( 'Phone', T ), ic: '☎' },
	url: { label: __( 'URL', T ), ic: '🔗' },
	date: { label: __( 'Date', T ), ic: '📅' },
	time: { label: __( 'Time', T ), ic: '🕐' },
	file: { label: __( 'File', T ), ic: '📎' },
	quantity: { label: __( 'Quantity', T ), ic: '±' },
	buttons: { label: __( 'Buttons', T ), ic: '⬚' },
	image_swatch: { label: __( 'Image swatch', T ), ic: '🖼' },
};

export default function Palette() {
	const { addField } = useDispatch( STORE );
	const types = ( window.CLPO_HUB && window.CLPO_HUB.fieldTypes ) || FALLBACK;

	return (
		<div className="clpo-palette">
			<div className="clpo-sec-label">{ __( 'Add a field', T ) }</div>
			<div className="clpo-ptypes">
				{ types.map( ( type ) => {
					const m = META[ type ] || { label: type, ic: '·' };
					return (
						<button key={ type } className="clpo-ptype" onClick={ () => addField( type ) }>
							<span className="clpo-ic">{ m.ic }</span>
							{ m.label }
						</button>
					);
				} ) }
			</div>

		</div>
	);
}
