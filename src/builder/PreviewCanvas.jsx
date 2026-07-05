import { useState } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import { STORE } from './store';
import FieldPreview from './FieldPreview';
import { previewValues } from './simulation';
import { activeMap } from '../frontend/conditional-logic';
import { describeCondition } from './describe';

const T = 'corelabs-product-options';

export default function PreviewCanvas() {
	const { fields, selectedId, sim } = useSelect( ( select ) => ( {
		fields: select( STORE ).getFields(),
		selectedId: select( STORE ).getSelectedId(),
		sim: select( STORE ).getSim(),
	} ), [] );
	const { selectField, removeField, duplicateField, reorder } = useDispatch( STORE );
	const [ drag, setDrag ] = useState( { from: null, over: null } );

	// The exact live-product-page engine drives the preview.
	const values = previewValues( fields, sim );
	const map = activeMap( fields, values );

	const visible = fields.filter( ( f ) => map[ f.id ] !== false );
	const hidden = fields.filter( ( f ) => map[ f.id ] === false );

	const idx = ( id ) => fields.findIndex( ( f ) => f.id === id );

	const dragHandlersFor = ( field ) => ( {
		isDragging: drag.from === field.id,
		isDropTarget: drag.over === field.id && drag.from !== field.id,
		props: {
			draggable: true,
			onDragStart: () => setDrag( { from: field.id, over: null } ),
			onDragOver: ( e ) => { e.preventDefault(); setDrag( ( d ) => ( { ...d, over: field.id } ) ); },
			onDrop: () => {
				if ( drag.from && drag.from !== field.id ) {
					reorder( idx( drag.from ), idx( field.id ) );
				}
				setDrag( { from: null, over: null } );
			},
			onDragEnd: () => setDrag( { from: null, over: null } ),
		},
	} );

	return (
		<div className="clpo-canvas">
			<div className="clpo-sheet">
				<div className="clpo-shead">
					<h2>{ __( 'Product preview', T ) }</h2>
					<span className="clpo-region-tag">
						{ __( 'Shown before Add to cart', T ) }
					</span>
				</div>

				{ /* Product-page context placeholders: title/price + gallery. */ }
				<div className="clpo-wcrow" aria-hidden="true">
					<div className="clpo-wcph"><div className="clpo-cap"></div><div className="clpo-box"></div></div>
					<div className="clpo-wcph"><div className="clpo-cap" style={ { width: '46%' } }></div><div className="clpo-box"></div></div>
				</div>

				{ fields.length === 0 ? (
					<div className="clpo-empty">
						<div className="clpo-empty-ic">＋</div>
						<h3>{ __( 'No fields yet', T ) }</h3>
						<p>{ __( 'Add a field from the left — conditional logic and add-on pricing are built in.', T ) }</p>
					</div>
				) : (
					<div className="clpo-fgrid">
						{ visible.map( ( f ) => (
							<FieldPreview
								key={ f.id }
								field={ f }
								fields={ fields }
								selected={ f.id === selectedId }
								onSelect={ () => selectField( f.id ) }
								onDuplicate={ () => duplicateField( f.id ) }
								onRemove={ () => removeField( f.id ) }
								dragHandlers={ dragHandlersFor( f ) }
							/>
						) ) }
					</div>
				) }

				{ hidden.length > 0 && (
					<>
						<div className="clpo-hiddenbar">
							<span>
								{ hidden.length === 1
									? __( '1 field hidden by current preview', T )
									: `${ hidden.length } ${ __( 'fields hidden by current preview', T ) }` }
							</span>
							<span className="clpo-line"></span>
						</div>
						<div className="clpo-fgrid" style={ { marginTop: 12 } }>
							{ hidden.map( ( f ) => (
								<FieldPreview
									key={ f.id }
									field={ f }
									fields={ fields }
									ghost
									reason={ `${ __( 'Shows when', T ) } ${ describeCondition( f, [], fields ) }` }
									selected={ f.id === selectedId }
									onSelect={ () => selectField( f.id ) }
									onDuplicate={ () => duplicateField( f.id ) }
									onRemove={ () => removeField( f.id ) }
								/>
							) ) }
						</div>
					</>
				) }
			</div>
		</div>
	);
}
