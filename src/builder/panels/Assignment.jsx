import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { RadioControl, ComboboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from '../store';

const T = 'corelabs-product-options';

/** Async search-select against a clpo/v1 picker route; picked items become chips. */
function Picker( { route, ids, labels, onAdd, onRemove, placeholder } ) {
	const [ options, setOptions ] = useState( [] );

	const search = ( q ) => {
		if ( ! q || q.length < 2 ) {
			setOptions( [] );
			return;
		}
		apiFetch( { path: `clpo/v1/${ route }?q=${ encodeURIComponent( q ) }` } )
			.then( ( items ) => setOptions(
				items
					.filter( ( i ) => ! ids.includes( i.id ) )
					.map( ( i ) => ( { value: String( i.id ), label: i.path || i.name } ) )
			) )
			.catch( () => setOptions( [] ) );
	};

	return (
		<>
			<ComboboxControl
				label={ placeholder }
				options={ options }
				onFilterValueChange={ search }
				onChange={ ( v ) => {
					const opt = options.find( ( o ) => o.value === v );
					if ( opt ) {
						onAdd( parseInt( opt.value, 10 ), opt.label );
						setOptions( [] );
					}
				} }
				value=""
			/>
			<div className="clpo-chips" style={ { marginTop: 6 } }>
				{ ids.map( ( id ) => (
					<span key={ id } className="clpo-chip is-static">
						{ labels[ id ] || `#${ id }` }
						<button aria-label={ __( 'Remove', T ) } onClick={ () => onRemove( id ) }>✕</button>
					</span>
				) ) }
			</div>
		</>
	);
}

/**
 * Group-level settings: where the group applies + priority. Shown in the
 * settings column whenever NO field is selected.
 */
export default function Assignment() {
	const { assignment, priority } = useSelect( ( select ) => ( {
		assignment: select( STORE ).getAssignment(),
		priority: select( STORE ).getPriority(),
	} ), [] );
	const { setAssignment, setPriority } = useDispatch( STORE );
	const [ labels, setLabels ] = useState( {} );

	const addId = ( id, label ) => {
		setLabels( ( l ) => ( { ...l, [ id ]: label } ) );
		setAssignment( { mode: assignment.mode, ids: [ ...assignment.ids, id ] } );
	};
	const removeId = ( id ) => {
		setAssignment( { mode: assignment.mode, ids: assignment.ids.filter( ( x ) => x !== id ) } );
	};

	return (
		<div className="clpo-assignment">
			<h4 className="clpo-sp-subtitle">{ __( 'Where does this group apply?', T ) }</h4>
			<RadioControl
				selected={ assignment.mode }
				options={ [
					{ label: __( 'All products', T ), value: 'all' },
					{ label: __( 'Products in categories…', T ), value: 'categories' },
					{ label: __( 'Specific products…', T ), value: 'products' },
				] }
				onChange={ ( mode ) => setAssignment( { mode, ids: [] } ) }
			/>

			{ assignment.mode === 'categories' && (
				<Picker
					route="categories/search"
					ids={ assignment.ids }
					labels={ labels }
					onAdd={ addId }
					onRemove={ removeId }
					placeholder={ __( 'Search categories…', T ) }
				/>
			) }
			{ assignment.mode === 'products' && (
				<Picker
					route="products/search"
					ids={ assignment.ids }
					labels={ labels }
					onAdd={ addId }
					onRemove={ removeId }
					placeholder={ __( 'Search products…', T ) }
				/>
			) }

			<TextControl
				type="number"
				min={ 0 }
				label={ __( 'Priority', T ) }
				help={ __( 'Lower renders first when several groups apply to the same product.', T ) }
				value={ priority }
				onChange={ ( v ) => setPriority( v ) }
			/>
		</div>
	);
}
