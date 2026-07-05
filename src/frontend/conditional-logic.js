/**
 * Front-end conditional logic. Mirrors PHP CoreLabs\ProductOptions\Logic\ConditionalLogic and is
 * kept in lockstep via tests/fixtures/conditional-cases.json.
 *
 * Free: a single `condition` {field, operator, value, action} (operators is/is_not).
 * Pro:  `conditions` [{field, operator, value}] + conditionMatch (all|any) +
 *       conditionAction (show|hide|require), and operators contains/gt/lt.
 */

function isNum( v ) {
	return v !== '' && ! isNaN( parseFloat( v ) ) && isFinite( v );
}

function matchRule( rule, values ) {
	if ( ! rule || typeof rule !== 'object' ) {
		return false;
	}
	const left = values[ rule.field ] !== undefined && values[ rule.field ] !== null ? String( values[ rule.field ] ) : '';
	const right = rule.value !== undefined && rule.value !== null ? String( rule.value ) : '';
	switch ( rule.operator ) {
		case 'is_not':
			return left !== right;
		case 'contains':
			return right !== '' && left.indexOf( right ) !== -1;
		case 'gt':
			return isNum( left ) && isNum( right ) && parseFloat( left ) > parseFloat( right );
		case 'lt':
			return isNum( left ) && isNum( right ) && parseFloat( left ) < parseFloat( right );
		case 'is':
		default:
			return left === right;
	}
}

/**
 * Evaluate a single condition (free). Kept for fixture parity.
 *
 * @param {Object|null} condition
 * @param {Object} values
 * @return {boolean}
 */
export function evaluate( condition, values ) {
	if ( ! condition || typeof condition !== 'object' ) {
		return true;
	}
	const action = condition.action || 'show';
	if ( action === 'require' ) {
		return true;
	}
	const match = matchRule( condition, values );
	if ( action === 'hide' ) {
		return ! match;
	}
	return match;
}

/**
 * Whether a field is active given submitted values (single or multi rules).
 *
 * @param {Object} field
 * @param {Object} values
 * @return {boolean}
 */
export function fieldActive( field, values ) {
	if ( Array.isArray( field.conditions ) && field.conditions.length ) {
		const action = field.conditionAction || 'show';
		if ( action === 'require' ) {
			return true;
		}
		const results = field.conditions.map( ( r ) => matchRule( r, values ) );
		const combined =
			field.conditionMatch === 'any' ? results.indexOf( true ) !== -1 : results.indexOf( false ) === -1;
		if ( action === 'hide' ) {
			return ! combined;
		}
		return combined;
	}
	return evaluate( field.condition, values );
}

function controllers( field ) {
	if ( Array.isArray( field.conditions ) && field.conditions.length ) {
		return field.conditions.map( ( r ) => r.field ).filter( Boolean );
	}
	if ( field.condition && field.condition.field ) {
		return [ field.condition.field ];
	}
	return [];
}

/**
 * Transitive active map (mirrors PHP active_map): a field is active only if its
 * own rules pass AND every referenced controller is active. Cycle-safe.
 *
 * @param {Array} fields
 * @param {Object} values
 * @return {Object} id -> boolean
 */
export function activeMap( fields, values ) {
	const byId = {};
	fields.forEach( ( f ) => {
		byId[ f.id ] = f;
	} );
	const cache = {};
	const inStack = {};
	const resolve = ( id ) => {
		if ( id in cache ) {
			return cache[ id ];
		}
		if ( ! byId[ id ] || inStack[ id ] ) {
			return true;
		}
		inStack[ id ] = true;
		const f = byId[ id ];
		let active = fieldActive( f, values );
		controllers( f ).forEach( ( cid ) => {
			active = active && resolve( cid );
		} );
		delete inStack[ id ];
		cache[ id ] = active;
		return active;
	};
	const map = {};
	fields.forEach( ( f ) => {
		map[ f.id ] = resolve( f.id );
	} );
	return map;
}

/**
 * Read current submitted values from a product form, keyed by field id.
 *
 * @param {HTMLElement} formEl
 * @param {Array} fields
 * @return {Object}
 */
export function readValues( formEl, fields ) {
	const values = {};
	fields.forEach( ( f ) => {
		const input = formEl.querySelector( `[name="apo[${ f.id }]"]` );
		if ( ! input ) {
			values[ f.id ] = '';
			return;
		}
		// Keyed on INPUT SHAPE, not field type: buttons/swatch/image_swatch all
		// render as radios — the generic branch would read the FIRST radio's
		// value regardless of :checked.
		if ( input.type === 'radio' ) {
			const checked = formEl.querySelector( `[name="apo[${ f.id }]"]:checked` );
			values[ f.id ] = checked ? checked.value : '';
			return;
		}
		if ( input.type === 'checkbox' ) {
			values[ f.id ] = input.checked ? input.value || 'yes' : '';
		} else {
			values[ f.id ] = input.value;
		}
	} );
	return values;
}

/**
 * Wire a product form: re-evaluate rules on input/change and toggle visibility +
 * the `required` attribute on dependent fields.
 *
 * @param {HTMLElement} formEl
 * @param {Array} fields
 */
export function wire( formEl, fields ) {
	if ( ! formEl || ! Array.isArray( fields ) ) {
		return;
	}
	const hasRule = ( f ) => ( Array.isArray( f.conditions ) && f.conditions.length ) || f.condition;
	const apply = () => {
		const values = readValues( formEl, fields );
		const map = activeMap( fields, values );
		fields.forEach( ( f ) => {
			if ( ! hasRule( f ) ) {
				return;
			}
			const wrap = formEl.querySelector( `[data-apo-field="${ f.id }"]` );
			if ( ! wrap ) {
				return;
			}
			const active = map[ f.id ];
			wrap.hidden = ! active;
			const input = wrap.querySelector( 'input, select, textarea' );
			if ( input ) {
				input.required = active && !! f.required;
			}
		} );
	};
	formEl.addEventListener( 'change', apply );
	formEl.addEventListener( 'input', apply );
	apply();
}
