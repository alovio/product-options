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

	const readValues = () => {
		const values = {};
		fields.forEach( ( f ) => {
			const input = formEl.querySelector( `[name="apo[${ f.id }]"]` );
			if ( ! input ) {
				return;
			}
			if ( input.type === 'checkbox' ) {
				values[ f.id ] = input.checked ? input.value || 'yes' : '';
			} else {
				values[ f.id ] = input.value;
			}
		} );
		return values;
	};

	const apply = () => {
		const values = readValues();
		fields.forEach( ( f ) => {
			if ( ! f.condition ) {
				return;
			}
			const wrap = formEl.querySelector( `[data-apo-field="${ f.id }"]` );
			if ( ! wrap ) {
				return;
			}
			const active = evaluate( f.condition, values );
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
