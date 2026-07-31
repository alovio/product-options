import { useState, useEffect, useCallback, useRef } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { navigate } from '../router';
import FlameMark from '../../shared/FlameMark';
import SupportButton from '../../shared/SupportButton';

const T = 'corelabs-product-options';

export default function GroupsList() {
	const [ groups, setGroups ] = useState( null ); // null = loading
	const [ busy, setBusy ] = useState( false );
	const [ notice, setNotice ] = useState( null ); // { type, text }
	const fileRef = useRef( null );

	const refresh = useCallback( () => {
		apiFetch( { path: 'clpo/v1/groups' } )
			.then( setGroups )
			.catch( () => setGroups( [] ) );
	}, [] );

	useEffect( refresh, [ refresh ] );

	const createGroup = async () => {
		setBusy( true );
		try {
			const g = await apiFetch( { path: 'clpo/v1/groups', method: 'POST', data: { title: __( 'Untitled group', T ) } } );
			navigate( `#/groups/${ g.id }` );
		} finally {
			setBusy( false );
		}
	};

	const duplicate = async ( id ) => {
		setBusy( true );
		try {
			await apiFetch( { path: `clpo/v1/groups/${ id }/duplicate`, method: 'POST' } );
			refresh();
		} finally {
			setBusy( false );
		}
	};

	const download = ( data, filename ) => {
		const blob = new Blob( [ JSON.stringify( data, null, '\t' ) ], { type: 'application/json' } );
		const url = URL.createObjectURL( blob );
		const a = document.createElement( 'a' );
		a.href = url;
		a.download = filename;
		a.click();
		URL.revokeObjectURL( url );
	};

	const exportGroups = async ( ids ) => {
		setBusy( true );
		try {
			const pkg = await apiFetch( { path: `clpo/v1/export${ ids ? `?ids=${ ids.join( ',' ) }` : '' }` } );
			download( pkg, ids ? `product-options-group-${ ids[ 0 ] }.json` : 'product-options-export.json' );
		} finally {
			setBusy( false );
		}
	};

	const importFile = async ( file ) => {
		if ( ! file ) {
			return;
		}
		setBusy( true );
		try {
			const text = await file.text();
			const res = await apiFetch( { path: 'clpo/v1/import', method: 'POST', data: JSON.parse( text ) } );
			const created = ( res.created || [] ).length;
			const warn = ( res.warnings || [] ).length;
			setNotice( {
				type: warn ? 'warning' : 'success',
				text: `${ created } ${ __( 'group(s) imported as drafts.', T ) }${ warn ? ` ${ res.warnings.join( ' ' ) }` : '' }`,
			} );
			refresh();
		} catch ( e ) {
			setNotice( { type: 'error', text: __( 'Import failed — is this a Product Options export file?', T ) } );
		} finally {
			setBusy( false );
			if ( fileRef.current ) {
				fileRef.current.value = '';
			}
		}
	};

	const remove = async ( g ) => {
		// eslint-disable-next-line no-alert
		if ( ! window.confirm( `${ __( 'Delete', T ) } “${ g.title }”?` ) ) {
			return;
		}
		setBusy( true );
		try {
			await apiFetch( { path: `clpo/v1/groups/${ g.id }`, method: 'DELETE' } );
			refresh();
		} finally {
			setBusy( false );
		}
	};

	return (
		<div className="clpo-app">
			<div className="clpo-hdr">
				<div className="clpo-logo">
					<span className="clpo-mark"><FlameMark /></span>
					Alovio <span className="clpo-sub">{ __( 'Product Options', T ) }</span>
				</div>
				<nav className="clpo-hubnav">
					<a className="is-on" href="#/groups">{ __( 'Groups', T ) }</a>
					<a href="#/templates">{ __( 'Templates', T ) }</a>
					<a href="#/settings">{ __( 'Settings', T ) }</a>
				</nav>
				<div className="clpo-grow"></div>
				<button className="clpo-btn-ghost" disabled={ busy } onClick={ () => exportGroups( null ) }>
					{ __( 'Export all', T ) }
				</button>
				<button className="clpo-btn-ghost" disabled={ busy } onClick={ () => fileRef.current && fileRef.current.click() }>
					{ __( 'Import', T ) }
				</button>
				<input ref={ fileRef } type="file" accept="application/json" hidden onChange={ ( e ) => importFile( e.target.files[ 0 ] ) } />
				<button className="clpo-btn-primary" disabled={ busy } onClick={ createGroup }>
					＋ { __( 'New group', T ) }
				</button>
				<SupportButton />
			</div>

			<div className="clpo-list-wrap">
				{ notice && (
					<p className={ `clpo-notice is-${ notice.type }` }>
						{ notice.text } <button className="clpo-linkbtn" onClick={ () => setNotice( null ) }>✕</button>
					</p>
				) }
				{ groups === null && <p className="clpo-list-note">{ __( 'Loading…', T ) }</p> }

				{ groups !== null && groups.length === 0 && (
					<div className="clpo-empty" style={ { margin: '60px auto', maxWidth: 420 } }>
						<div className="clpo-empty-ic">＋</div>
						<h3>{ __( 'No option groups yet', T ) }</h3>
						<p>{ __( 'Build fields once, then show them on all products, a category, or hand-picked products.', T ) }</p>
						<button className="clpo-btn-primary" disabled={ busy } onClick={ createGroup }>
							＋ { __( 'Create your first group', T ) }
						</button>
					</div>
				) }

				{ groups !== null && groups.length > 0 && (
					<table className="clpo-table">
						<thead>
							<tr>
								<th>{ __( 'Group', T ) }</th>
								<th>{ __( 'Status', T ) }</th>
								<th>{ __( 'Fields', T ) }</th>
								<th>{ __( 'Priced', T ) }</th>
								<th>{ __( 'Applies to', T ) }</th>
								<th></th>
							</tr>
						</thead>
						<tbody>
							{ groups.map( ( g ) => (
								<tr key={ g.id }>
									<td>
										<a className="clpo-row-title" href={ `#/groups/${ g.id }` }>{ g.title || __( '(untitled)', T ) }</a>
									</td>
									<td>
										<span className={ `clpo-pill${ g.status === 'publish' ? ' is-live' : '' }` }>
											{ g.status === 'publish' ? __( 'Active', T ) : __( 'Draft', T ) }
										</span>
									</td>
									<td>{ g.field_count }</td>
									<td>{ g.priced_count }</td>
									<td className="clpo-muted">{ g.assignment_summary }</td>
									<td className="clpo-row-ops">
										<button className="clpo-linkbtn" disabled={ busy } onClick={ () => exportGroups( [ g.id ] ) }>{ __( 'Export', T ) }</button>
										<button className="clpo-linkbtn" disabled={ busy } onClick={ () => duplicate( g.id ) }>{ __( 'Duplicate', T ) }</button>
										<button className="clpo-linkbtn is-danger" disabled={ busy } onClick={ () => remove( g ) }>{ __( 'Delete', T ) }</button>
									</td>
								</tr>
							) ) }
						</tbody>
					</table>
				) }
			</div>
		</div>
	);
}
