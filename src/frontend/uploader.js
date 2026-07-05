/**
 * File pickers: upload on selection to clpo/v1/upload, write the returned
 * token into the field's hidden input (the raw file never rides the
 * add-to-cart submit). Ported from Alovio Checkout Fields.
 */
export function wireUploads() {
	const cfg = ( window.CLPO_FE && window.CLPO_FE.upload ) || null;
	if ( ! cfg ) {
		return;
	}
	document.addEventListener( 'change', async ( e ) => {
		const picker = e.target;
		if ( ! picker.classList || ! picker.classList.contains( 'apo-file-picker' ) ) {
			return;
		}
		const wrap = picker.closest( '.apo-file' );
		const hidden = wrap.querySelector( 'input[type="hidden"]' );
		const status = wrap.querySelector( '.apo-file-status' );
		const setStatus = ( t ) => {
			if ( status ) {
				status.textContent = t;
			}
		};
		const file = picker.files && picker.files[ 0 ];
		if ( ! file ) {
			hidden.value = '';
			setStatus( '' );
			return;
		}
		if ( file.size > cfg.maxMb * 1048576 ) {
			picker.value = '';
			hidden.value = '';
			setStatus( `File is too large (max ${ cfg.maxMb } MB).` );
			return;
		}
		setStatus( 'Uploading…' );
		try {
			const form = new FormData();
			form.append( 'file', file );
			const headers = { 'X-CLPO-Nonce': cfg.nonce };
			if ( cfg.restNonce ) {
				headers[ 'X-WP-Nonce' ] = cfg.restNonce;
			}
			const resp = await fetch( cfg.url, { method: 'POST', headers, body: form } );
			const data = await resp.json();
			if ( ! resp.ok || ! data.token ) {
				throw new Error( ( data && data.message ) || 'upload failed' );
			}
			hidden.value = data.token;
			hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );
			setStatus( `✓ ${ data.name }` );
		} catch ( err ) {
			picker.value = '';
			hidden.value = '';
			setStatus( err.message || 'Upload failed — please try again.' );
		}
	} );
}
