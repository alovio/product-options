<?php
declare( strict_types=1 );

namespace APO\Cart;

use APO\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Sum fixed-fee add-ons for the engaged, active fields of a group.
 * Decimals are injected (not read from WC) to keep this unit-testable.
 * `apo_addon_total` is the Pro formula/percentage/quantity extension point.
 */
final class PriceCalculator {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $submitted
	 */
	public static function addon_total( array $group, array $submitted, int $decimals = 2 ): float {
		$total  = 0.0;
		$active = ConditionalLogic::active_map( $group, $submitted );

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			if ( empty( $active[ $f['id'] ?? '' ] ) ) {
				continue;
			}
			$price = isset( $f['price'] ) ? (float) $f['price'] : 0.0;
			if ( $price <= 0 ) {
				continue;
			}
			$value = $submitted[ $f['id'] ?? '' ] ?? null;
			if ( self::is_engaged( (string) ( $f['type'] ?? '' ), $value ) ) {
				$total += $price;
			}
		}

		$total = round( $total, $decimals );

		return (float) apply_filters( 'apo_addon_total', $total, $group, $submitted );
	}

	/** @param mixed $value */
	private static function is_engaged( string $type, $value ): bool {
		if ( 'price' === $type ) {
			return true; // flat surcharge field.
		}
		if ( 'checkbox' === $type ) {
			return ! empty( $value ) && '0' !== $value;
		}
		if ( 'number' === $type ) {
			// A literal 0 (or empty) does not engage the fee.
			return is_numeric( $value ) && 0.0 !== (float) $value;
		}
		return null !== $value && '' !== $value;
	}
}
