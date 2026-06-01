import { useState, useEffect, useRef } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { TabPanel, Button, Snackbar, Notice } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from './store';
import FieldPalette from './FieldPalette';
import Canvas from './Canvas';
import FieldSettings from './FieldSettings';
import LivePreview from './LivePreview';

const T = 'conditional-product-options';

export default function App( { productId } ) {
	const fields = useSelect( ( select ) => select( STORE ).getFields(), [] );
	const { hydrate } = useDispatch( STORE );
	const [ notice, setNotice ] = useState( null ); // { type, text }
	const [ saving, setSaving ] = useState( false );
	const savedRef = useRef( null ); // JSON snapshot of the last-saved fields

	// Load saved fields once, then record the clean snapshot.
	useEffect( () => {
		apiFetch( { path: `apo/v1/product/${ productId }/fields` } )
			.then( ( group ) => {
				const f = ( group && group.fields ) || [];
				hydrate( f );
				savedRef.current = JSON.stringify( f );
			} )
			.catch( () => {
				savedRef.current = '[]';
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [] );

	const dirty = savedRef.current !== null && JSON.stringify( fields ) !== savedRef.current;

	// Warn before leaving with unsaved builder changes.
	useEffect( () => {
		const handler = ( e ) => {
			if ( dirty ) {
				e.preventDefault();
				e.returnValue = '';
			}
		};
		window.addEventListener( 'beforeunload', handler );
		return () => window.removeEventListener( 'beforeunload', handler );
	}, [ dirty ] );

	const save = async () => {
		setSaving( true );
		try {
			await apiFetch( { path: `apo/v1/product/${ productId }/fields`, method: 'POST', data: { fields } } );
			savedRef.current = JSON.stringify( fields );
			setNotice( { type: 'success', text: __( 'Options saved.', T ) } );
		} catch ( e ) {
			setNotice( { type: 'error', text: __( 'Save failed. Please try again.', T ) } );
		}
		setSaving( false );
	};

	return (
		<div className="apo-app">
			{ notice && notice.type === 'error' && (
				<Notice status="error" onRemove={ () => setNotice( null ) }>{ notice.text }</Notice>
			) }
			<TabPanel
				className="apo-tabs"
				tabs={ [
					{ name: 'build', title: __( 'Build', T ) },
					{ name: 'preview', title: __( 'Preview', T ) },
				] }
			>
				{ ( tab ) =>
					tab.name === 'build' ? (
						<div className="apo-build">
							<FieldPalette />
							<Canvas />
							<FieldSettings />
						</div>
					) : (
						<LivePreview />
					)
				}
			</TabPanel>
			<div className="apo-actions">
				<Button variant="primary" onClick={ save } isBusy={ saving } disabled={ saving }>
					{ dirty ? __( 'Save options •', T ) : __( 'Save options', T ) }
				</Button>
				{ dirty && <span className="apo-unsaved">{ __( 'Unsaved changes', T ) }</span> }
			</div>
			{ notice && notice.type === 'success' && (
				<Snackbar onRemove={ () => setNotice( null ) }>{ notice.text }</Snackbar>
			) }
		</div>
	);
}
