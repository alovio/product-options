import { parseTokens, remainingSlots } from '../../src/frontend/uploader';

const T1 = 'a1'.repeat( 16 );
const T2 = 'b2'.repeat( 16 );

describe( 'uploader token helpers', () => {
	it( 'parseTokens handles a single token and a comma list', () => {
		expect( parseTokens( T1 ) ).toEqual( [ T1 ] );
		expect( parseTokens( `${ T1 },${ T2 }` ) ).toEqual( [ T1, T2 ] );
		expect( parseTokens( ` ${ T1 } , ${ T2 } ` ) ).toEqual( [ T1, T2 ] );
	} );

	it( 'parseTokens drops garbage and empty values', () => {
		expect( parseTokens( `${ T1 },nope,,${ T1.toUpperCase() }` ) ).toEqual( [ T1 ] );
		expect( parseTokens( '' ) ).toEqual( [] );
		expect( parseTokens( undefined ) ).toEqual( [] );
		expect( parseTokens( null ) ).toEqual( [] );
	} );

	it( 'remainingSlots counts down from maxFiles', () => {
		expect( remainingSlots( '', 3 ) ).toBe( 3 );
		expect( remainingSlots( T1, 3 ) ).toBe( 2 );
		expect( remainingSlots( `${ T1 },${ T2 }`, 3 ) ).toBe( 1 );
		expect( remainingSlots( `${ T1 },${ T2 }`, 2 ) ).toBe( 0 );
		expect( remainingSlots( `${ T1 },${ T2 }`, 1 ) ).toBe( 0 );
		expect( remainingSlots( '', undefined ) ).toBe( 1 );
	} );
} );
