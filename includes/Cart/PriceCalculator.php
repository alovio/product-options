<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

use CoreLabs\ProductOptions\Fields\FieldOptions;
use CoreLabs\ProductOptions\Formula\FormulaPrice;
use CoreLabs\ProductOptions\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Sum add-ons for the engaged, active fields of a group. Modes: fixed,
 * per_unit (number/quantity), per_char (text), percent (of base), formula
 * (decimal-safe expression over sibling numeric values). Decimals are
 * injected (not read from WC) to keep this unit-testable.
 * `clpo_addon_total` remains a public extension point.
 */
final class PriceCalculator {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $submitted
	 */
	public static function addon_total( array $group, array $submitted, int $decimals = 2, float $base = 0.0 ): float {
		$total = 0.0;
		foreach ( self::breakdown( $group, $submitted, $decimals, $base ) as $row ) {
			$total += $row['amount'];
		}

		$total = round( $total, $decimals );
		$total = (float) apply_filters( 'clpo_addon_total', $total, $group, $submitted );

		return round( $total, $decimals );
	}

	/**
	 * Per-field amounts for the breakdown box + cart display (spec §7/§9).
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $submitted
	 * @return array<int,array{field_id:string, label:string, amount:float}> engaged priced rows only.
	 */
	public static function breakdown( array $group, array $submitted, int $decimals = 2, float $base = 0.0 ): array {
		$rows    = array();
		$active  = ConditionalLogic::active_map( $group, $submitted );
		$numeric = self::numeric_values( $group, $submitted, $active );

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( empty( $active[ $id ] ) ) {
				continue;
			}
			$mode  = (string) ( $f['priceMode'] ?? 'fixed' );
			$type  = (string) ( $f['type'] ?? '' );
			$value = $submitted[ $id ] ?? null;
			// A priced option lifts a field that carries no price of its own.
			$price = self::effective_price( $f, $value );
			if ( $price <= 0 && 'formula' !== $mode ) {
				continue;
			}
			if ( ! self::is_engaged( $type, $value ) ) {
				continue;
			}

			$amount = self::field_amount( $f, $value, $base, $numeric );
			if ( $amount <= 0 ) {
				continue;
			}
			$rows[] = array(
				'field_id' => $id,
				'label'    => ( '' !== (string) ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id,
				'amount'   => round( $amount, $decimals ),
			);
		}

		return $rows;
	}

	/**
	 * @param array<string,mixed> $f
	 * @param mixed               $value
	 * @param array<string,float> $numeric sibling numeric values (inactive = absent -> 0 in formulas).
	 */
	private static function field_amount( array $f, $value, float $base, array $numeric ): float {
		$mode  = (string) ( $f['priceMode'] ?? 'fixed' );
		$price = self::effective_price( $f, $value );
		$type  = (string) ( $f['type'] ?? '' );

		if ( 'formula' === $mode ) {
			return FormulaPrice::evaluate( (string) ( $f['formula'] ?? '' ), $numeric );
		}
		if ( 'per_unit' === $mode && in_array( $type, array( 'number', 'quantity' ), true ) && is_numeric( $value ) ) {
			return $price * (float) $value;
		}
		if ( 'per_char' === $mode && in_array( $type, array( 'text', 'textarea' ), true ) ) {
			return $price * mb_strlen( trim( (string) $value ) );
		}
		if ( 'percent' === $mode ) {
			return $base * $price / 100;
		}
		return $price;
	}

	/**
	 * The price a field charges for THIS value: the picked option's own price
	 * when it has one, else the field-level price. The chosen amount still
	 * runs through the field's price mode, so "10% for Express / 20% for
	 * Overnight" works as naturally as flat per-option amounts.
	 *
	 * @param array<string,mixed> $f
	 * @param mixed               $value
	 */
	private static function effective_price( array $f, $value ): float {
		$option_price = FieldOptions::price_for_value( $f, $value );
		if ( $option_price > 0 ) {
			return $option_price;
		}
		return isset( $f['price'] ) ? (float) $f['price'] : 0.0;
	}

	/**
	 * Numeric view of the submitted values for formula tokens: engaged+active
	 * numeric fields only — everything else resolves to 0 inside the engine.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $submitted
	 * @param array<string,bool>  $active
	 * @return array<string,float>
	 */
	private static function numeric_values( array $group, array $submitted, array $active ): array {
		$out = array();
		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id || empty( $active[ $id ] ) ) {
				continue;
			}
			$v = $submitted[ $id ] ?? null;
			if ( is_numeric( $v ) ) {
				$out[ $id ] = (float) $v;
			}
		}
		return $out;
	}

	/** @param mixed $value */
	private static function is_engaged( string $type, $value ): bool {
		if ( 'price' === $type ) {
			return true; // flat surcharge field.
		}
		if ( 'checkbox' === $type ) {
			return ! empty( $value ) && '0' !== $value;
		}
		if ( 'number' === $type || 'quantity' === $type ) {
			// A literal 0 (or empty) does not engage the fee.
			return is_numeric( $value ) && 0.0 !== (float) $value;
		}
		return null !== $value && '' !== $value;
	}
}
