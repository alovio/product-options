import domReady from '@wordpress/dom-ready';
import { createRoot, useState, useEffect } from '@wordpress/element';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import '../builder/store';
import AppShell from '../builder/AppShell';
import GroupsList from './screens/GroupsList';
import Templates from './screens/Templates';
import Settings from './screens/Settings';
import { currentRoute, navigate } from './router';
import '../../assets/css/builder.css';

const T = 'corelabs-product-options';

function Hub() {
	const [ route, setRoute ] = useState( currentRoute() );

	useEffect( () => {
		const onHash = () => setRoute( currentRoute() );
		window.addEventListener( 'hashchange', onHash );
		return () => window.removeEventListener( 'hashchange', onHash );
	}, [] );

	if ( route.name === 'builder' ) {
		return <AppShell key={ route.id } groupId={ route.id } onBack={ () => navigate( '#/groups' ) } />;
	}
	if ( route.name === 'templates' ) {
		return <Templates />;
	}
	if ( route.name === 'settings' ) {
		return <Settings />;
	}
	return <GroupsList />;
}

domReady( () => {
	const el = document.getElementById( 'clpo-hub-root' );
	if ( ! el ) {
		return;
	}
	if ( window.CLPO_HUB ) {
		apiFetch.use( apiFetch.createRootURLMiddleware( window.CLPO_HUB.root ) );
		apiFetch.use( apiFetch.createNonceMiddleware( window.CLPO_HUB.nonce ) );
	}
	createRoot( el ).render( <Hub /> );
} );
