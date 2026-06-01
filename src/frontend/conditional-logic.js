/**
 * Front-end conditional logic. `evaluate` mirrors the PHP
 * APO\Logic\ConditionalLogic and is kept in lockstep via
 * tests/fixtures/conditional-cases.json.
 */

/**
 * @param {Object|null} condition {field, operator, value, action}
 * @param {Object} values map of fieldId -> submitted value
 * @return {boolean} whether the field is active/shown
 */
export function evaluate( condition, values ) {
	if ( ! condition || typeof condition !== 'object' ) {
		return true;
	}
	const left =
		values[ condition.field ] !== undefined && values[ condition.field ] !== null
			? String( values[ condition.field ] )
			: '';
	const right = condition.value !== undefined && condition.value !== null ? String( condition.value ) : '';
	const op = condition.operator || 'is';
	const match = op === 'is_not' ? left !== right : left === right;
	const action = condition.action || 'show';

	if ( action === 'require' ) {
		return true;
	}
	if ( action === 'hide' ) {
		return ! match;
	}
	return match; // show
}

/**
 * Resolve active state for all fields, transitively (mirrors PHP
 * ConditionalLogic::active_map): a field is active only if its own condition
 * passes AND its controller is also active. Cycle-safe.
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
		let active = evaluate( f.condition, values );
		if ( f.condition && f.condition.field ) {
			active = active && resolve( f.condition.field );
		}
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
 * @return {Object} values map
 */
export function readValues( formEl, fields ) {
	const values = {};
	fields.forEach( ( f ) => {
		if ( f.type === 'radio' ) {
			const checked = formEl.querySelector( `[name="apo[${ f.id }]"]:checked` );
			values[ f.id ] = checked ? checked.value : '';
			return;
		}
		const input = formEl.querySelector( `[name="apo[${ f.id }]"]` );
		if ( ! input ) {
			values[ f.id ] = '';
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
 * Wire a product form: re-evaluate rules on input/change and toggle
 * visibility + the `required` attribute on dependent fields.
 *
 * @param {HTMLElement} formEl
 * @param {Array} fields normalized field definitions
 */
export function wire( formEl, fields ) {
	if ( ! formEl || ! Array.isArray( fields ) ) {
		return;
	}

	const apply = () => {
		const values = readValues( formEl, fields );
		const map = activeMap( fields, values );
		fields.forEach( ( f ) => {
			if ( ! f.condition ) {
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
