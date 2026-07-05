/**
 * Storefront polish (spec §9): live character counters, tooltip toggles and
 * inline required/number validation (message under the field, focus the first
 * invalid control instead of a silent scroll-to-top). Server-side validation
 * stays authoritative — this only improves the feedback loop.
 */

export function counterText( value, max ) {
	return `${ String( value ).length } / ${ max }`;
}

/**
 * Mirror of the PHP Validator's required/number messages (localized strings
 * arrive via CLPO_FE.messages with %s placeholders).
 *
 * @return {string|null} message, or null when the value is acceptable.
 */
export function inlineErrorFor( field, value, messages ) {
	const label = field.label || field.id;
	const empty = value === undefined || value === null || value === '';
	if ( field.required && empty ) {
		return ( messages.required || '"%s" is required.' ).replace( '%s', label );
	}
	if ( empty ) {
		return null;
	}
	if ( ( field.type === 'number' || field.type === 'quantity' ) && isNaN( parseFloat( value ) ) ) {
		return ( messages.number || '"%s" must be a number.' ).replace( '%s', label );
	}
	return null;
}

function fieldWrap( formEl, id ) {
	return formEl.querySelector( `[data-apo-field="${ id }"]` );
}

function setError( wrap, input, message ) {
	let el = wrap.querySelector( '.apo-error' );
	if ( ! message ) {
		if ( el ) {
			el.remove();
		}
		if ( input ) {
			input.removeAttribute( 'aria-invalid' );
		}
		return;
	}
	if ( ! el ) {
		el = document.createElement( 'span' );
		el.className = 'apo-error';
		el.id = `apo-err-${ Math.random().toString( 36 ).slice( 2, 8 ) }`;
		wrap.append( el );
	}
	el.textContent = message;
	if ( input ) {
		input.setAttribute( 'aria-invalid', 'true' );
		input.setAttribute( 'aria-describedby', `${ input.getAttribute( 'aria-describedby' ) || '' } ${ el.id }`.trim() );
	}
}

/**
 * @param {HTMLElement} formEl
 * @param {Array<{fields: Array}>} groups
 */
export function wirePolish( formEl, groups ) {
	const cfg = ( typeof window !== 'undefined' && window.CLPO_FE ) || {};
	const messages = cfg.messages || {};
	const fields = groups.reduce( ( all, g ) => all.concat( g.fields ), [] );

	// Character counters.
	formEl.querySelectorAll( '[data-apo-counter]' ).forEach( ( counter ) => {
		const wrap = counter.closest( '[data-apo-field]' );
		const input = wrap && wrap.querySelector( 'input[type="text"], textarea' );
		if ( ! input || ! input.maxLength || input.maxLength < 0 ) {
			return;
		}
		const refresh = () => {
			counter.textContent = counterText( input.value, input.maxLength );
		};
		input.addEventListener( 'input', refresh );
		refresh();
	} );

	// Tooltip toggles.
	formEl.querySelectorAll( '.apo-tip-toggle' ).forEach( ( btn ) => {
		btn.addEventListener( 'click', ( e ) => {
			e.preventDefault();
			const wrap = btn.closest( '[data-apo-field]' );
			const tip = wrap && wrap.querySelector( '.apo-tip' );
			if ( ! tip ) {
				return;
			}
			const open = tip.hidden;
			tip.hidden = ! open;
			btn.setAttribute( 'aria-expanded', open ? 'true' : 'false' );
		} );
	} );

	const validateField = ( f ) => {
		const wrap = fieldWrap( formEl, f.id );
		if ( ! wrap || wrap.hidden || f.type === 'heading' || f.type === 'price' ) {
			return null;
		}
		const input = wrap.querySelector( 'input:not([type="hidden"]), select, textarea' ) || wrap.querySelector( 'input' );
		let value;
		if ( input && input.type === 'radio' ) {
			const checked = wrap.querySelector( 'input[type="radio"]:checked' );
			value = checked ? checked.value : '';
		} else if ( input && input.type === 'checkbox' ) {
			value = input.checked ? 'yes' : '';
		} else if ( f.type === 'file' ) {
			const hidden = wrap.querySelector( 'input[type="hidden"]' );
			value = hidden ? hidden.value : '';
		} else {
			value = input ? input.value : '';
		}
		const message = inlineErrorFor( f, value, messages );
		setError( wrap, input, message );
		return message ? wrap : null;
	};

	// Validate on blur (per field).
	fields.forEach( ( f ) => {
		const wrap = fieldWrap( formEl, f.id );
		if ( ! wrap ) {
			return;
		}
		wrap.addEventListener( 'focusout', () => validateField( f ) );
	} );

	// On submit: validate everything visible; block + focus the first invalid.
	formEl.addEventListener( 'submit', ( e ) => {
		let firstInvalid = null;
		fields.forEach( ( f ) => {
			const bad = validateField( f );
			if ( bad && ! firstInvalid ) {
				firstInvalid = bad;
			}
		} );
		if ( firstInvalid ) {
			e.preventDefault();
			const input = firstInvalid.querySelector( 'input:not([type="hidden"]), select, textarea' );
			if ( input ) {
				input.focus();
			} else {
				firstInvalid.scrollIntoView( { block: 'center' } );
			}
		}
	} );
}
