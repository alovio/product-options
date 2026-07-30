/**
 * Choice-option shape helpers — the JS twin of PHP FieldOptions.
 *
 * An option is either a plain string ("Large") or an object that carries
 * extras: {label, price?, color?, image?}. Both forms are valid everywhere.
 */

export function optionLabel( option ) {
	return option && typeof option === 'object' ? String( option.label || '' ) : String( option ?? '' );
}

/** The option's own add-on price; 0 means "use the field price". */
export function optionPrice( option ) {
	if ( ! option || typeof option !== 'object' ) {
		return 0;
	}
	const price = parseFloat( option.price );
	return isNaN( price ) || price <= 0 ? 0 : price;
}

export function optionLabels( field ) {
	return ( ( field && field.options ) || [] ).map( optionLabel );
}

/** Price attached to the option matching this value, else 0. */
export function optionPriceForValue( field, value ) {
	if ( value === undefined || value === null || value === '' ) {
		return 0;
	}
	const match = ( ( field && field.options ) || [] ).find( ( o ) => optionLabel( o ) === String( value ) );
	return match === undefined ? 0 : optionPrice( match );
}

export function hasPricedOptions( field ) {
	return ( ( field && field.options ) || [] ).some( ( o ) => optionPrice( o ) > 0 );
}

/**
 * What a field charges for this value: the picked option's own price when it
 * has one, else the field-level price. Mirrors PriceCalculator::effective_price.
 */
export function effectivePrice( field, value ) {
	const own = optionPriceForValue( field, value );
	return own > 0 ? own : parseFloat( field.price ) || 0;
}
