/**
 * Swatch → product-gallery image swap (display-only).
 *
 * When a shopper selects a colour/image swatch that carries a product image
 * (emitted by ProductFormRenderer as data-apo-gallery-image on the radio), swap
 * the WooCommerce product gallery's currently-shown image to it. Selecting a
 * swatch without one — or a different one — restores the original first.
 *
 * The gallery lives OUTSIDE the plugin's form, so we query the document. Nothing
 * happens when the standard gallery is absent (custom themes) — we degrade to a
 * no-op rather than guess at foreign markup. We key off the checked element's
 * data attribute, never off the label text, so duplicate labels can't confuse it.
 */

/** The image the shopper actually sees: the active flex slide, else the first gallery image. */
function currentMainImage() {
	const gallery = document.querySelector( '.woocommerce-product-gallery' );
	if ( ! gallery ) {
		return null;
	}
	return (
		gallery.querySelector( '.flex-active-slide img' ) ||
		gallery.querySelector( '.woocommerce-product-gallery__image img' ) ||
		gallery.querySelector( 'img' )
	);
}

function setOrRemove( el, attr, value ) {
	if ( value === null || value === undefined ) {
		el.removeAttribute( attr );
	} else {
		el.setAttribute( attr, value );
	}
}

const READ = [ 'src', 'srcset', 'sizes', 'data-src', 'data-large_image' ];

export function createGallerySwap() {
	let swapped = null; // { img, anchor, orig }

	const restore = () => {
		if ( ! swapped ) {
			return;
		}
		const { img, anchor, orig } = swapped;
		READ.forEach( ( a ) => setOrRemove( img, a, orig[ a ] ) );
		if ( anchor ) {
			setOrRemove( anchor, 'href', orig.href );
		}
		swapped = null;
	};

	const swap = ( url, srcset ) => {
		// Revert any prior swap first (the active image may have changed).
		restore();
		const img = currentMainImage();
		if ( ! img ) {
			return;
		}
		const anchor = img.closest( 'a' );
		const orig = { href: anchor ? anchor.getAttribute( 'href' ) : null };
		READ.forEach( ( a ) => ( orig[ a ] = img.getAttribute( a ) ) );
		swapped = { img, anchor, orig };

		setOrRemove( img, 'src', url );
		setOrRemove( img, 'srcset', srcset || null );
		img.removeAttribute( 'sizes' );
		img.removeAttribute( 'data-src' ); // defeat lazy-load libraries
		setOrRemove( img, 'data-large_image', url );
		if ( anchor ) {
			setOrRemove( anchor, 'href', url );
		}
	};

	/** Apply the swatch a radio represents (swap when it has an image, else revert). */
	const applyFrom = ( radio ) => {
		if ( ! radio ) {
			return;
		}
		const url = radio.dataset.apoGalleryImage;
		if ( url ) {
			swap( url, radio.dataset.apoGallerySrcset || '' );
		} else {
			restore();
		}
	};

	return { swap, restore, applyFrom };
}

/** Wire one product form: react to swatch selections and honour any default. */
export function wireGallerySwap( form, controller ) {
	const ctrl = controller || createGallerySwap();

	form.addEventListener( 'change', ( e ) => {
		const radio = e.target;
		if ( ! radio || radio.type !== 'radio' || ! radio.closest( '.apo-swatch' ) ) {
			return; // only swatch selections drive the gallery
		}
		ctrl.applyFrom( radio );
	} );

	// A swatch checked by default should show its image on load.
	const checked = form.querySelector( '.apo-swatch input[type="radio"]:checked' );
	if ( checked && checked.dataset.apoGalleryImage ) {
		ctrl.applyFrom( checked );
	}

	return ctrl;
}
