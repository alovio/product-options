/**
 * Preview values for the canvas. Pure — the SimulationBar writes per-field
 * `sim` overrides to the store; merged over these defaults they feed the SAME
 * activeMap/computeAddonTotal engines the live product page runs.
 */
import { hasPricedOptions } from '../shared/options';

/**
 * Default submitted values for preview: each field's default (checkbox default
 * 'yes' when set), so conditions on sibling fields evaluate sensibly.
 *
 * @param {Array} fields
 * @return {Object} field id -> value
 */
export function previewFieldValues( fields ) {
	const out = {};
	( fields || [] ).forEach( ( f ) => {
		if ( f.type === 'heading' ) {
			return;
		}
		out[ f.id ] = f.default !== undefined && f.default !== null ? String( f.default ) : '';
	} );
	return out;
}

/**
 * Merged engine input: defaults overridden by explicit sim values.
 *
 * @param {Array}  fields
 * @param {Object} sim field id -> simulated value
 * @return {Object} field id -> value
 */
export function previewValues( fields, sim ) {
	return { ...previewFieldValues( fields ), ...( sim || {} ) };
}

/**
 * Fields worth simulating in the bar: controllers (referenced by another
 * field's conditions) and priced fields (they move the running total).
 *
 * @param {Array} fields
 * @return {Array} subset of fields
 */
export function simTargets( fields ) {
	const all = fields || [];
	const controllers = new Set();
	all.forEach( ( f ) => {
		( Array.isArray( f.conditions ) ? f.conditions : [] ).forEach( ( r ) => {
			if ( r.field ) {
				controllers.add( r.field );
			}
		} );
	} );
	return all.filter(
		( f ) =>
			f.type !== 'heading' &&
			( controllers.has( f.id ) || parseFloat( f.price ) > 0 || hasPricedOptions( f ) )
	);
}
