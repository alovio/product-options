import { useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { navigate } from '../router';

const T = 'corelabs-product-options';

export default function Templates() {
	const [ templates, setTemplates ] = useState( null );
	const [ busy, setBusy ] = useState( false );

	useEffect( () => {
		apiFetch( { path: 'clpo/v1/templates' } )
			.then( setTemplates )
			.catch( () => setTemplates( [] ) );
	}, [] );

	const useTemplate = async ( id ) => {
		setBusy( true );
		try {
			const group = await apiFetch( { path: `clpo/v1/templates/${ id }/use`, method: 'POST' } );
			navigate( `#/groups/${ group.id }` );
		} finally {
			setBusy( false );
		}
	};

	return (
		<div className="clpo-app">
			<div className="clpo-hdr">
				<div className="clpo-logo"><span className="clpo-mark">▲</span> Alovio <span className="clpo-sub">{ __( 'Product Options', T ) }</span></div>
				<nav className="clpo-hubnav">
					<a href="#/groups">{ __( 'Groups', T ) }</a>
					<a className="is-on" href="#/templates">{ __( 'Templates', T ) }</a>
					<a href="#/settings">{ __( 'Settings', T ) }</a>
				</nav>
				<div className="clpo-grow"></div>
			</div>
			<div className="clpo-list-wrap">
				<h2 className="clpo-h2">{ __( 'Start from a template', T ) }</h2>
				<p className="clpo-list-note">{ __( 'Each template creates a DRAFT group seeded with fields, pricing and conditional logic — tweak it, assign it, publish.', T ) }</p>
				{ templates === null && <p className="clpo-list-note">{ __( 'Loading…', T ) }</p> }
				<div className="clpo-tplgrid">
					{ ( templates || [] ).map( ( tpl ) => (
						<div key={ tpl.id } className="clpo-tplcard">
							<h3>{ tpl.name }</h3>
							<p>{ tpl.description }</p>
							<div className="clpo-chips">
								{ tpl.types.map( ( t ) => (
									<span key={ t } className="clpo-typechip">{ t.replace( '_', ' ' ) }</span>
								) ) }
							</div>
							<button className="clpo-btn-primary" disabled={ busy } onClick={ () => useTemplate( tpl.id ) }>
								{ __( 'Use template', T ) }
							</button>
						</div>
					) ) }
				</div>
			</div>
		</div>
	);
}
