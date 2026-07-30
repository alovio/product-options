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

	/** @var string[] Field types whose value is one of a fixed list of options. */
	public const CHOICE_TYPES = array( 'select', 'radio', 'buttons', 'swatch', 'image_swatch' );

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
		if ( ! is_scalar( $value ) || '' === $value ) {
			return 0.0;
		}
		if ( ! in_array( (string) ( $field['type'] ?? '' ), self::CHOICE_TYPES, true ) ) {
			return 0.0; // only a real choice field can price by option.
		}
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			if ( self::label( $option ) === (string) $value ) {
				return self::price( $option );
			}
		}
		return 0.0;
	}

	/**
	 * What the field charges for THIS value — the single source of truth for
	 * both the amount billed (PriceCalculator) and the amount advertised
	 * (ProductFormRenderer). An option's own price wins; otherwise the
	 * field-level price applies. The caller still applies the price mode.
	 *
	 * Duplicate labels resolve to the FIRST match, exactly as pricing does, so
	 * a repeated label can never advertise one amount and charge another.
	 *
	 * @param array<string,mixed> $field
	 * @param mixed               $value
	 */
	public static function effective_price( array $field, $value ): float {
		$own = self::price_for_value( $field, $value );
		if ( $own > 0 ) {
			return $own;
		}
		return isset( $field['price'] ) ? (float) $field['price'] : 0.0;
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
	 * Min/max of what this field can actually charge, for the summary pill.
	 * Every option contributes what picking it would cost — its own price, or
	 * the field price it falls back to — so a field that mixes priced and
	 * unpriced options summarises honestly.
	 *
	 * @param array<string,mixed> $field
	 * @return array{0:float,1:float} [min, max]; [0,0] when nothing can charge.
	 */
	public static function price_range( array $field ): array {
		$prices = array();
		foreach ( (array) ( $field['options'] ?? array() ) as $option ) {
			$price = self::effective_price( $field, self::label( $option ) );
			if ( $price > 0 ) {
				$prices[] = $price;
			}
		}
		return array() === $prices ? array( 0.0, 0.0 ) : array( min( $prices ), max( $prices ) );
	}
}
