/**
 * @jest-environment jsdom
 *
 * Regression: a hidden field must not keep ANY native `required`, or the browser
 * aborts add-to-cart on a control it cannot focus ("An invalid form control with
 * name='apo[…]' is not focusable" — Firefox). Radio/button/swatch groups render
 * one required input PER OPTION, so clearing just the first was not enough.
 */
import { wire } from '../../src/frontend/conditional-logic';

const FIELDS = [
	{ id: 'inscription', type: 'radio', required: true, condition: null },
	{
		id: 'typo',
		type: 'radio',
		required: true,
		condition: { field: 'inscription', operator: 'is', value: 'Custom text', action: 'show' },
	},
];

function setup() {
	document.body.innerHTML = `
		<form class="cart">
			<div class="apo-field" data-apo-field="inscription">
				<label><input type="radio" name="apo[inscription]" value="None" required /></label>
				<label><input type="radio" name="apo[inscription]" value="Proposed" required /></label>
				<label><input type="radio" name="apo[inscription]" value="Custom text" required /></label>
			</div>
			<div class="apo-field" data-apo-field="typo">
				<label><input type="radio" name="apo[typo]" value="Serif" required /></label>
				<label><input type="radio" name="apo[typo]" value="Script" required /></label>
				<label><input type="radio" name="apo[typo]" value="Block" required /></label>
			</div>
		</form>`;
	return document.querySelector( 'form.cart' );
}

const group = ( id ) => Array.from( document.querySelectorAll( `[name="apo[${ id }]"]` ) );
const wrapOf = ( id ) => document.querySelector( `[data-apo-field="${ id }"]` );
const pick = ( id, value ) => {
	const el = document.querySelector( `[name="apo[${ id }]"][value="${ value }"]` );
	el.checked = true;
	el.dispatchEvent( new Event( 'change', { bubbles: true } ) );
};

describe( 'conditional logic clears required on EVERY control it hides', () => {
	it( 'drops required from all radios of a hidden group', () => {
		const form = setup();
		wire( form, FIELDS );

		pick( 'inscription', 'Custom text' ); // reveals the dependent group
		expect( wrapOf( 'typo' ).hidden ).toBe( false );
		expect( group( 'typo' ).map( ( r ) => r.required ) ).toEqual( [ true, true, true ] );

		pick( 'inscription', 'None' ); // hides it again — the reported bug
		expect( wrapOf( 'typo' ).hidden ).toBe( true );
		expect( group( 'typo' ).map( ( r ) => r.required ) ).toEqual( [ false, false, false ] );
		// Nothing left that native validation could refuse to focus.
		expect( document.querySelectorAll( '[data-apo-field="typo"] [required]' ) ).toHaveLength( 0 );
	} );

	it( 'starts clean when the dependent is hidden on first paint', () => {
		const form = setup();
		wire( form, FIELDS ); // nothing selected ⇒ typo hidden from the start
		expect( wrapOf( 'typo' ).hidden ).toBe( true );
		expect( group( 'typo' ).every( ( r ) => r.required === false ) ).toBe( true );
	} );

	it( 'never marks a hidden value carrier required, and leaves the file picker to the uploader', () => {
		document.body.innerHTML = `
			<form class="cart">
				<div class="apo-field" data-apo-field="gate">
					<input type="checkbox" name="apo[gate]" value="yes" />
				</div>
				<div class="apo-field" data-apo-field="proof">
					<span class="apo-file">
						<input type="hidden" name="apo[proof]" value="" />
						<input type="file" class="apo-file-picker" required />
					</span>
				</div>
			</form>`;
		const form = document.querySelector( 'form.cart' );
		const fields = [
			{ id: 'gate', type: 'checkbox', required: false, condition: null },
			{
				id: 'proof',
				type: 'file',
				required: true,
				condition: { field: 'gate', operator: 'is', value: 'yes', action: 'show' },
			},
		];
		wire( form, fields );

		const cb = form.querySelector( '[name="apo[gate]"]' );
		cb.checked = true;
		cb.dispatchEvent( new Event( 'change', { bubbles: true } ) );

		const token = form.querySelector( 'input[type="hidden"][name="apo[proof]"]' );
		const picker = form.querySelector( '.apo-file-picker' );
		expect( token.required ).toBe( false ); // a hidden input may never be required
		expect( picker.required ).toBe( true ); // nothing uploaded yet ⇒ still required

		token.value = 'tok_123'; // a file has been staged
		cb.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( picker.required ).toBe( false ); // uploader's rule honoured

		cb.checked = false; // hide it again
		cb.dispatchEvent( new Event( 'change', { bubbles: true } ) );
		expect( picker.required ).toBe( false );
		expect( token.required ).toBe( false );
	} );
} );
