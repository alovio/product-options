import { useDispatch } from '@wordpress/data';
import { Button, TextControl, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from '../store';

const T = 'corelabs-product-options';

/** Types whose options are plain strings vs {label,…} objects. */
export const PLAIN_OPTION_TYPES = [ 'radio', 'select', 'buttons' ];
export const OBJECT_OPTION_TYPES = [ 'swatch', 'image_swatch' ];
export const HAS_OPTIONS_TYPES = [ ...PLAIN_OPTION_TYPES, ...OBJECT_OPTION_TYPES ];

/** Open the WP media modal (classic wp.media — no extra bundle dep). */
function pickImage( onSelect ) {
	if ( ! window.wp || ! window.wp.media ) {
		return;
	}
	const frame = window.wp.media( {
		title: __( 'Choose an option image', T ),
		multiple: false,
		library: { type: 'image' },
	} );
	frame.on( 'select', () => {
		const att = frame.state().get( 'selection' ).first().toJSON();
		const url = ( att.sizes && att.sizes.thumbnail && att.sizes.thumbnail.url ) || att.url;
		onSelect( url );
	} );
	frame.open();
}

/**
 * Shared options editor for every choice type: plain rows for
 * radio/select/buttons, label+colour rows for swatch, label+image rows for
 * image swatch.
 */
/** Options are strings until they carry extras; edit them as objects either way. */
const asObject = ( o ) => ( o && typeof o === 'object' ? { ...o } : { label: String( o ?? '' ) } );
const priceOf = ( o ) => {
	const p = parseFloat( asObject( o ).price );
	return isNaN( p ) || p <= 0 ? '' : String( p );
};

export default function Options( { field } ) {
	const { updateField } = useDispatch( STORE );
	const opts = field.options || [];
	const set = ( options ) => updateField( field.id, { options } );

	const plain = PLAIN_OPTION_TYPES.includes( field.type );

	const row = ( i, patch ) => {
		const next = opts.map( ( o, j ) => {
			if ( j !== i ) {
				return o;
			}
			const merged = { ...asObject( o ), ...patch };
			const price = parseFloat( merged.price );
			if ( isNaN( price ) || price <= 0 ) {
				delete merged.price;
				// A plain choice with nothing extra collapses back to a string.
				return plain ? merged.label : merged;
			}
			merged.price = price;
			return merged;
		} );
		set( next );
	};
	const removeRow = ( i ) => set( opts.filter( ( _, j ) => j !== i ) );
	const addRow = () => {
		if ( plain ) {
			set( [ ...opts, `${ __( 'Option', T ) } ${ opts.length + 1 }` ] );
		} else if ( field.type === 'swatch' ) {
			set( [ ...opts, { label: `${ __( 'Option', T ) } ${ opts.length + 1 }`, color: '#cccccc' } ] );
		} else {
			set( [ ...opts, { label: `${ __( 'Option', T ) } ${ opts.length + 1 }`, image: '' } ] );
		}
	};

	return (
		<>
			{ opts.map( ( o, i ) => (
				<div key={ i } className="clpo-optrow">
					<TextControl
						label={ 0 === i ? __( 'Options', T ) : undefined }
						value={ asObject( o ).label }
						onChange={ ( v ) => row( i, { label: v } ) }
					/>
					{ field.type === 'swatch' && (
						<input
							type="color"
							className="clpo-optcolor"
							value={ asObject( o ).color || '#cccccc' }
							aria-label={ __( 'Colour', T ) }
							onChange={ ( e ) => row( i, { color: e.target.value } ) }
						/>
					) }
					{ field.type === 'image_swatch' && (
						<Button
							variant="secondary"
							className="clpo-optimg"
							onClick={ () => pickImage( ( url ) => row( i, { image: url } ) ) }
						>
							{ asObject( o ).image ? <img src={ asObject( o ).image } alt="" /> : __( 'Image…', T ) }
						</Button>
					) }
					<div className="clpo-optprice">
						<TextControl
							type="number"
							min={ 0 }
							step="any"
							label={ 0 === i ? __( 'Price', T ) : undefined }
							placeholder="0"
							value={ priceOf( o ) }
							aria-label={ __( 'Price for this option', T ) }
							onChange={ ( v ) => row( i, { price: v } ) }
						/>
					</div>
					<Button
						isDestructive
						variant="tertiary"
						aria-label={ __( 'Remove option', T ) }
						onClick={ () => removeRow( i ) }
					>
						✕
					</Button>
				</div>
			) ) }
			{ field.type === 'image_swatch' && (
				<TextControl
					label={ __( 'Or paste an image URL for the last option', T ) }
					value={ asObject( opts[ opts.length - 1 ] ).image || '' }
					onChange={ ( v ) => opts.length && row( opts.length - 1, { image: v } ) }
				/>
			) }
			<Button variant="secondary" onClick={ addRow }>＋ { __( 'Add option', T ) }</Button>
			{ ! opts.length && (
				<Notice status="warning" isDismissible={ false }>{ __( 'Add at least one option.', T ) }</Notice>
			) }
			{ !! opts.length && (
				<p className="clpo-opthelp">
					{ __( 'Give an option its own price to charge a different amount per choice — e.g. 21x30 +399, 50x70 +799. Leave it empty to fall back to the field price in the Pricing tab.', T ) }
				</p>
			) }
		</>
	);
}
