/**
 * File pickers: upload on selection to clpo/v1/upload, write the returned
 * token(s) into the field's hidden input (the raw file never rides the
 * add-to-cart submit). Ported from Alovio Checkout Fields.
 *
 * Multi-file fields (maxFiles > 1) keep a comma-joined token list in the
 * hidden input and render one removable list item per uploaded file; a
 * single-file field is the same UI capped at one item.
 */

/** Parse a hidden-input value (single token or comma-joined list) into tokens. */
export function parseTokens( str ) {
	return String( str || '' )
		.split( ',' )
		.map( ( s ) => s.trim() )
		.filter( ( s ) => /^[a-f0-9]{32}$/.test( s ) );
}

/** How many more files a field can take. */
export function remainingSlots( hiddenValue, maxFiles ) {
	return Math.max( 0, ( maxFiles || 1 ) - parseTokens( hiddenValue ).length );
}

function syncHidden( wrap, hidden, picker ) {
	const tokens = Array.from( wrap.querySelectorAll( '.apo-file-item' ) ).map( ( el ) => el.dataset.token );
	hidden.value = tokens.join( ',' );
	// A required picker would block submit once we clear its FileList, so the
	// requirement moves to the picker only while no file is uploaded yet.
	if ( picker.dataset.apoRequired ) {
		if ( tokens.length ) {
			picker.removeAttribute( 'required' );
		} else {
			picker.setAttribute( 'required', 'required' );
		}
	}
	hidden.dispatchEvent( new Event( 'change', { bubbles: true } ) );
}

function addItem( wrap, list, token, name ) {
	const item = document.createElement( 'span' );
	item.className = 'apo-file-item';
	item.dataset.token = token;
	const label = document.createElement( 'span' );
	label.className = 'apo-file-item__name';
	label.textContent = name;
	const remove = document.createElement( 'button' );
	remove.type = 'button';
	remove.className = 'apo-file-remove';
	remove.setAttribute( 'aria-label', `Remove ${ name }` );
	remove.textContent = '×';
	item.append( label, remove );
	list.append( item );
}

export function wireUploads() {
	const cfg = ( window.CLPO_FE && window.CLPO_FE.upload ) || null;
	if ( ! cfg ) {
		return;
	}

	document.addEventListener( 'click', ( e ) => {
		const btn = e.target.closest && e.target.closest( '.apo-file-remove' );
		if ( ! btn ) {
			return;
		}
		e.preventDefault();
		const wrap = btn.closest( '.apo-file' );
		btn.closest( '.apo-file-item' ).remove();
		const status = wrap.querySelector( '.apo-file-status' );
		if ( status ) {
			status.textContent = '';
		}
		syncHidden( wrap, wrap.querySelector( 'input[type="hidden"]' ), wrap.querySelector( '.apo-file-picker' ) );
	} );

	document.addEventListener( 'change', async ( e ) => {
		const picker = e.target;
		if ( ! picker.classList || ! picker.classList.contains( 'apo-file-picker' ) ) {
			return;
		}
		const wrap = picker.closest( '.apo-file' );
		const hidden = wrap.querySelector( 'input[type="hidden"]' );
		const list = wrap.querySelector( '.apo-file-list' );
		const status = wrap.querySelector( '.apo-file-status' );
		const setStatus = ( t ) => {
			if ( status ) {
				status.textContent = t;
			}
		};
		if ( picker.hasAttribute( 'required' ) ) {
			picker.dataset.apoRequired = '1';
		}

		const maxFiles = parseInt( picker.dataset.apoMaxFiles || '1', 10 ) || 1;
		let files = Array.from( picker.files || [] );
		if ( ! files.length ) {
			return;
		}

		// A single-file field replaces its file; a multi-file field appends.
		if ( maxFiles === 1 && list ) {
			list.textContent = '';
			syncHidden( wrap, hidden, picker );
		}
		const room = remainingSlots( hidden.value, maxFiles );
		const overflow = files.length > room;
		files = files.slice( 0, room );

		const oversize = files.filter( ( f ) => f.size > cfg.maxMb * 1048576 );
		files = files.filter( ( f ) => f.size <= cfg.maxMb * 1048576 );

		let failed = '';
		for ( const file of files ) {
			setStatus( `Uploading ${ file.name }…` );
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
				addItem( wrap, list, data.token, data.name );
				syncHidden( wrap, hidden, picker );
			} catch ( err ) {
				failed = err.message || 'Upload failed — please try again.';
			}
		}

		picker.value = '';
		if ( failed ) {
			setStatus( failed );
		} else if ( oversize.length ) {
			setStatus( `File is too large (max ${ cfg.maxMb } MB).` );
		} else if ( overflow ) {
			setStatus( `Only ${ maxFiles } files allowed.` );
		} else {
			setStatus( '' );
		}
	} );
}
