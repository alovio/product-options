import { parseRoute } from '../../src/hub/router';

describe( 'hub router', () => {
	it( 'parses the builder route with id', () => {
		expect( parseRoute( '#/groups/12' ) ).toEqual( { name: 'builder', id: 12 } );
	} );
	it( 'defaults to the list', () => {
		expect( parseRoute( '' ) ).toEqual( { name: 'list' } );
		expect( parseRoute( '#/groups' ) ).toEqual( { name: 'list' } );
		expect( parseRoute( '#/nonsense' ) ).toEqual( { name: 'list' } );
		expect( parseRoute( '#/groups/abc' ) ).toEqual( { name: 'list' } );
	} );
	it( 'parses templates and settings', () => {
		expect( parseRoute( '#/templates' ) ).toEqual( { name: 'templates' } );
		expect( parseRoute( '#/settings' ) ).toEqual( { name: 'settings' } );
	} );
} );
