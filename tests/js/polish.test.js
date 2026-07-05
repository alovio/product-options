import { counterText, inlineErrorFor } from '../../src/frontend/polish';

describe( 'polish helpers', () => {
	it( 'counterText formats value / max', () => {
		expect( counterText( 'Hello sailor', 40 ) ).toBe( '12 / 40' );
		expect( counterText( '', 40 ) ).toBe( '0 / 40' );
	} );

	it( 'inlineErrorFor mirrors the PHP validator required message', () => {
		const messages = { required: '“%s” is required.', number: '“%s” must be a number.' };
		const req = { id: 'a', type: 'text', label: 'Note', required: true };
		expect( inlineErrorFor( req, '', messages ) ).toBe( '“Note” is required.' );
		expect( inlineErrorFor( req, 'ok', messages ) ).toBeNull();
		const num = { id: 'n', type: 'number', label: 'Qty', required: false };
		expect( inlineErrorFor( num, 'abc', messages ) ).toBe( '“Qty” must be a number.' );
		expect( inlineErrorFor( num, '', messages ) ).toBeNull(); // optional + empty
	} );
} );
