import { useState, useEffect } from '@wordpress/element';
import { ToggleControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';

const T = 'corelabs-product-options';

export default function Settings() {
	const [ remove, setRemove ] = useState( null ); // null = loading

	useEffect( () => {
		apiFetch( { path: 'clpo/v1/settings' } )
			.then( ( s ) => setRemove( !! s.removeDataOnUninstall ) )
			.catch( () => setRemove( false ) );
	}, [] );

	const toggle = async ( on ) => {
		setRemove( on );
		await apiFetch( { path: 'clpo/v1/settings', method: 'POST', data: { removeDataOnUninstall: on } } );
	};

	return (
		<div className="clpo-app">
			<div className="clpo-hdr">
				<div className="clpo-logo">
					<span className="clpo-mark">▲</span>
					Alovio <span className="clpo-sub">{ __( 'Product Options', T ) }</span>
				</div>
				<nav className="clpo-hubnav">
					<a href="#/groups">{ __( 'Groups', T ) }</a>
					<a href="#/templates">{ __( 'Templates', T ) }</a>
					<a className="is-on" href="#/settings">{ __( 'Settings', T ) }</a>
				</nav>
				<div className="clpo-grow"></div>
			</div>
			<div className="clpo-list-wrap" style={ { maxWidth: 560 } }>
				<h2 className="clpo-h2">{ __( 'Settings', T ) }</h2>
				{ remove === null ? (
					<p className="clpo-list-note">{ __( 'Loading…', T ) }</p>
				) : (
					<ToggleControl
						label={ __( 'Remove all data on uninstall', T ) }
						help={ __( 'When enabled, deleting the plugin also deletes every option group and legacy field definition. Off by default so a reinstall picks up where you left off.', T ) }
						checked={ remove }
						onChange={ toggle }
					/>
				) }
			</div>
		</div>
	);
}
