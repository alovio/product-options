import { useDispatch, useSelect } from '@wordpress/data';
import { Button } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';

export default function Canvas() {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const selectedId = useSelect( ( select ) => select( STORE ).getSelectedId(), [] );
	const { selectField, removeField, reorder } = useDispatch( STORE );

	if ( ! fields.length ) {
		return <div className="apo-canvas apo-canvas--empty">{ __( 'Add a field from the left to get started.', 'advanced-product-options' ) }</div>;
	}

	return (
		<ul className="apo-canvas">
			{ fields.map( ( f, i ) => (
				<li
					key={ f.id }
					className={ f.id === selectedId ? 'is-selected' : '' }
					onClick={ () => selectField( f.id ) }
				>
					<span className="apo-canvas__label">
						{ f.label || f.type } <em>({ f.type })</em>
					</span>
					<span className="apo-canvas__ops">
						<Button size="small" disabled={ i === 0 } onClick={ ( e ) => { e.stopPropagation(); reorder( i, i - 1 ); } } aria-label={ __( 'Move up', 'advanced-product-options' ) }>↑</Button>
						<Button size="small" disabled={ i === fields.length - 1 } onClick={ ( e ) => { e.stopPropagation(); reorder( i, i + 1 ); } } aria-label={ __( 'Move down', 'advanced-product-options' ) }>↓</Button>
						<Button size="small" isDestructive onClick={ ( e ) => { e.stopPropagation(); removeField( f.id ); } } aria-label={ __( 'Delete', 'advanced-product-options' ) }>✕</Button>
					</span>
				</li>
			) ) }
		</ul>
	);
}
