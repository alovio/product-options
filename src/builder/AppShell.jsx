import { useState, useEffect, useRef, useCallback } from '@wordpress/element';
import { useSelect, useDispatch } from '@wordpress/data';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { STORE } from './store';
import Palette from './Palette';
import SimulationBar from './SimulationBar';
import PreviewCanvas from './PreviewCanvas';
import SettingsPanel from './SettingsPanel';
import FlameMark from '../shared/FlameMark';
import UiIcon from '../shared/UiIcon';
import SupportButton from '../shared/SupportButton';

const T = 'corelabs-product-options';

/** The group editor: loads one group, saves it back through PUT /groups/{id}. */
export default function AppShell( { groupId, onBack } ) {
	const { fields, canUndo, title, status, assignment, priority } = useSelect( ( select ) => ( {
		fields: select( STORE ).getFields(),
		canUndo: select( STORE ).canUndo(),
		title: select( STORE ).getTitle(),
		status: select( STORE ).getStatus(),
		assignment: select( STORE ).getAssignment(),
		priority: select( STORE ).getPriority(),
	} ), [] );
	const { hydrate, undo, setTitle } = useDispatch( STORE );
	const [ saving, setSaving ] = useState( false );
	const [ flash, setFlash ] = useState( null ); // 'saved' | 'error' | null
	const savedRef = useRef( null );

	const payload = { title, status, fields, assignment, priority };
	const snapshot = ( g ) => JSON.stringify( {
		title: g.title,
		status: g.status,
		fields: g.fields || [],
		assignment: g.assignment,
		priority: g.priority,
	} );

	useEffect( () => {
		apiFetch( { path: `clpo/v1/groups/${ groupId }` } )
			.then( ( group ) => {
				hydrate( group );
				savedRef.current = snapshot( group );
			} )
			.catch( () => {
				savedRef.current = JSON.stringify( payload );
			} );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ groupId ] );

	const dirty = savedRef.current !== null && JSON.stringify( payload ) !== savedRef.current;

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

	const save = useCallback( async ( publish ) => {
		setSaving( true );
		try {
			const body = { ...payload };
			if ( publish ) {
				body.status = 'publish';
			}
			const group = await apiFetch( { path: `clpo/v1/groups/${ groupId }`, method: 'PUT', data: body } );
			hydrate( group );
			savedRef.current = snapshot( group );
			setFlash( 'saved' );
			setTimeout( () => setFlash( null ), 2500 );
		} catch ( e ) {
			setFlash( 'error' );
		}
		setSaving( false );
		// eslint-disable-next-line react-hooks/exhaustive-deps
	}, [ groupId, title, status, fields, assignment, priority, hydrate ] );

	// ⌘S / Ctrl+S saves (keeps current status).
	useEffect( () => {
		const onKey = ( e ) => {
			if ( ( e.metaKey || e.ctrlKey ) && e.key.toLowerCase() === 's' ) {
				e.preventDefault();
				save( false );
			}
		};
		window.addEventListener( 'keydown', onKey );
		return () => window.removeEventListener( 'keydown', onKey );
	}, [ save ] );

	let statusCls = 'clpo-status';
	let statusTxt = __( 'All changes saved', T );
	if ( flash === 'error' ) {
		statusCls += ' is-error';
		statusTxt = __( 'Save failed — try again', T );
	} else if ( dirty ) {
		statusCls += ' is-dirty';
		statusTxt = __( 'Unsaved changes', T );
	} else if ( flash === 'saved' ) {
		statusCls += ' is-saved';
		statusTxt = __( 'Saved', T );
	}

	const isDraft = status !== 'publish';

	return (
		<div className="clpo-app">
			<div className="clpo-hdr">
				<button className="clpo-btn-ghost" onClick={ onBack } aria-label={ __( 'Back to groups', T ) }>
					<UiIcon name="back" />
					<span className="clpo-lbl-wide">{ __( 'Groups', T ) }</span>
				</button>
				<div className="clpo-logo">
					<span className="clpo-mark"><FlameMark /></span>
					Alovio <span className="clpo-sub">{ __( 'Product Options', T ) }</span>
				</div>
				<input
					className="clpo-title-input"
					value={ title }
					placeholder={ __( 'Group name…', T ) }
					aria-label={ __( 'Group name', T ) }
					onChange={ ( e ) => setTitle( e.target.value ) }
				/>
				{ isDraft && <span className="clpo-draft-pill">{ __( 'Draft', T ) }</span> }
				<div className="clpo-grow"></div>
				<span className={ statusCls } title={ statusTxt }>
					<span className="clpo-dot"></span>
					<span className="clpo-lbl-wide">{ statusTxt }</span>
				</span>
				<button className="clpo-btn-ghost" disabled={ ! canUndo } onClick={ undo } aria-label={ __( 'Undo', T ) }>
					<UiIcon name="undo" />
					<span className="clpo-lbl-wide">{ __( 'Undo', T ) }</span>
				</button>
				{ isDraft ? (
					<>
						<button className="clpo-btn-ghost" disabled={ saving } onClick={ () => save( false ) }>
							{ __( 'Save draft', T ) }
						</button>
						<button className="clpo-btn-primary" disabled={ saving } onClick={ () => save( true ) }>
							{ saving ? __( 'Saving…', T ) : __( 'Save & publish', T ) }
						</button>
					</>
				) : (
					<button className="clpo-btn-primary" disabled={ saving } onClick={ () => save( false ) }>
						{ saving ? __( 'Saving…', T ) : __( 'Save', T ) }
					</button>
				) }
				<SupportButton />
			</div>
			<div className="clpo-work">
				<Palette />
				<div className="clpo-canvas-col">
					<SimulationBar />
					<PreviewCanvas />
				</div>
				<SettingsPanel />
			</div>
		</div>
	);
}
