/**
 * @jest-environment jsdom
 */
import { wireGallerySwap } from '../../src/frontend/gallery-swap';

function setupDOM() {
	document.body.innerHTML = `
		<div class="woocommerce-product-gallery">
			<div class="woocommerce-product-gallery__image">
				<a href="/orig-large.jpg"><img class="wp-post-image" src="/orig.jpg" srcset="/orig.jpg 1x" sizes="100vw" data-large_image="/orig-large.jpg" /></a>
			</div>
		</div>
		<form class="cart">
			<div class="apo-swatches">
				<label class="apo-swatch"><input type="radio" name="apo[c]" value="Red" data-apo-gallery-image="/red.jpg" data-apo-gallery-srcset="/red.jpg 2x" /></label>
				<label class="apo-swatch"><input type="radio" name="apo[c]" value="Blue" /></label>
			</div>
			<input type="text" name="apo[note]" />
		</form>
	`;
	return {
		form: document.querySelector( 'form.cart' ),
		img: document.querySelector( '.woocommerce-product-gallery img' ),
		anchor: document.querySelector( '.woocommerce-product-gallery a' ),
		red: document.querySelector( 'input[value="Red"]' ),
		blue: document.querySelector( 'input[value="Blue"]' ),
	};
}

const change = ( el ) => el.dispatchEvent( new Event( 'change', { bubbles: true } ) );

describe( 'gallery swap', () => {
	it( 'swaps the gallery image on a swatch with an image, and reverts on one without', () => {
		const { form, img, anchor, red, blue } = setupDOM();
		wireGallerySwap( form );

		red.checked = true;
		change( red );
		expect( img.getAttribute( 'src' ) ).toBe( '/red.jpg' );
		expect( img.getAttribute( 'srcset' ) ).toBe( '/red.jpg 2x' );
		expect( img.getAttribute( 'data-large_image' ) ).toBe( '/red.jpg' );
		expect( anchor.getAttribute( 'href' ) ).toBe( '/red.jpg' );

		red.checked = false;
		blue.checked = true;
		change( blue );
		expect( img.getAttribute( 'src' ) ).toBe( '/orig.jpg' );
		expect( img.getAttribute( 'srcset' ) ).toBe( '/orig.jpg 1x' );
		expect( img.getAttribute( 'sizes' ) ).toBe( '100vw' );
		expect( img.getAttribute( 'data-large_image' ) ).toBe( '/orig-large.jpg' );
		expect( anchor.getAttribute( 'href' ) ).toBe( '/orig-large.jpg' );
	} );

	it( 'honours a default-checked swatch when wired', () => {
		const { form, img, red } = setupDOM();
		red.checked = true; // pre-selected before wiring
		wireGallerySwap( form );
		expect( img.getAttribute( 'src' ) ).toBe( '/red.jpg' );
	} );

	it( 'ignores non-swatch input changes', () => {
		const { form, img } = setupDOM();
		wireGallerySwap( form );
		const note = form.querySelector( 'input[name="apo[note]"]' );
		note.value = 'hello';
		change( note );
		expect( img.getAttribute( 'src' ) ).toBe( '/orig.jpg' );
	} );

	it( 'no-ops when the product gallery is absent', () => {
		document.body.innerHTML = `<form class="cart"><label class="apo-swatch"><input type="radio" value="Red" data-apo-gallery-image="/red.jpg" /></label></form>`;
		const form = document.querySelector( 'form.cart' );
		const red = document.querySelector( 'input' );
		expect( () => {
			wireGallerySwap( form );
			red.checked = true;
			change( red );
		} ).not.toThrow();
	} );
} );
