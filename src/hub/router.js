/**
 * Tiny hash router for the hub. Pure parse function + a subscribe helper.
 * Routes: #/groups (list, default) · #/groups/{id} (builder) · #/templates ·
 * #/settings.
 */

export function parseRoute( hash ) {
	const h = ( hash || '' ).replace( /^#\/?/, '' );
	const parts = h.split( '/' ).filter( Boolean );
	if ( parts[ 0 ] === 'groups' && parts[ 1 ] ) {
		const id = parseInt( parts[ 1 ], 10 );
		if ( id > 0 ) {
			return { name: 'builder', id };
		}
		return { name: 'list' };
	}
	if ( parts[ 0 ] === 'templates' ) {
		return { name: 'templates' };
	}
	if ( parts[ 0 ] === 'settings' ) {
		return { name: 'settings' };
	}
	return { name: 'list' };
}

export function navigate( hash ) {
	window.location.hash = hash;
}

export function currentRoute() {
	return parseRoute( window.location.hash );
}
