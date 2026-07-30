<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Choice-option shape helpers.
 *
 * An option is either a plain string ("Large") or an object carrying extras:
 * {label, price?, color?, image?}. Both forms are valid and both ship in the
 * stored JSON — a priced option only becomes an object when it needs to, so
 * groups written before per-option pricing stay byte-identical.
 *
 * Every reader (sanitizer, price calculator, renderer, REST) goes through
 * these two functions instead of poking at the raw value.
 */
final class FieldOptions {

	/** @param mixed $option */
	public static function label( $option ): string {
		return is_array( $option ) ? (string) ( $option['label'] ?? '' ) : (string) $option;
	}

	/**
	 * The option's own add-on price. 0 means "no price of its own" — the
	 * field-level price applies instead.
	 *
	 * @param mixed $option
	 */
	public static function price( $option ): float {
		if ( ! is_array( $option ) || ! isset( $option['price'] ) || ! is_numeric( $option['price'] ) ) {
			return 0.0;
		}
		$price = (float) $option['price'];
		return $price > 0 ? $price : 0.0;
	}

	/**
	 * Labels of a field's options, in order.
	 *
	 * @param array<string,mixed> $field
	 * @return string[]
	 */
	public static function labels( array $field ): array {
		return array_map( array( self::class, 'label' ), (array) ( $field['options'] ?? array() ) );
	}

	/**
	 * The price attached to the option a customer picked, or 0 when that
	 * option carries none (or the value matches nothing).
	 *
	 * @param array<string,mixed> $field
	 * @param mixed               $value submitted label.
	 */
	public static function price_for_value( array $field, $value ): float {
		if ( null === $value || '' === $value ) {
			return 0.0;
		}
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( self::label( $option ) === (string) $value ) {
				return self::price( $option );
			}
		}
		return 0.0;
	}

	/**
	 * Does any option in this field carry its own price? Drives the "prices
	 * live on the options" branches in pricing and rendering.
	 *
	 * @param array<string,mixed> $field
	 */
	public static function has_priced_options( array $field ): bool {
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( self::price( $option ) > 0 ) {
				return true;
			}
		}
		return false;
	}

	/**
	 * Min/max of the priced options, for a "from … to …" summary pill.
	 *
	 * @param array<string,mixed> $field
	 * @return array{0:float,1:float} [min, max]; [0,0] when nothing is priced.
	 */
	public static function price_range( array $field ): array {
		$prices = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			$price = self::price( $option );
			if ( $price > 0 ) {
				$prices[] = $price;
			}
		}
		return array() === $prices ? array( 0.0, 0.0 ) : array( min( $prices ), max( $prices ) );
	}
}
