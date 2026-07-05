<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Pure helpers for the cart-item `apo` payload (spec §3.5.4, §4). No hooks,
 * no WP state — CartIntegration delegates here so it stays hooks-only.
 */
final class CartItemShape {

	/**
	 * Sanitize + validate the posted values against every resolved group and
	 * merge the error messages.
	 *
	 * @param array[] $groups canonical group arrays (fields inside).
	 * @param array<string,mixed> $posted raw posted apo[] values.
	 * @return string[] error messages.
	 */
	public static function collect_errors( array $groups, array $posted ): array {
		$errors = array();
		foreach ( $groups as $group ) {
			$opts   = OptionSanitizer::sanitize( $group, $posted );
			$errors = array_merge( $errors, Validator::validate( $group, $opts ) );
		}
		return $errors;
	}

	/**
	 * Normalize the cart-item `apo` payload to the 2.0 per-group LIST shape.
	 * Legacy 1.x single maps ({options, base_price, unique_key}) become a
	 * single entry with group_id 0 (spec §3.5.4); missing keys are filled.
	 *
	 * @param mixed $apo
	 * @return array<int,array{group_id:int, options:array, base_price:float, addon_total:float, unique_key:string}>
	 */
	public static function normalize_apo( $apo ): array {
		if ( ! is_array( $apo ) || array() === $apo ) {
			return array();
		}
		// Legacy single map: has an 'options' key instead of integer-indexed entries.
		if ( isset( $apo['options'] ) ) {
			$apo = array( $apo );
		}
		$out = array();
		foreach ( $apo as $entry ) {
			if ( ! is_array( $entry ) ) {
				continue;
			}
			$out[] = array(
				'group_id'    => (int) ( $entry['group_id'] ?? 0 ),
				'options'     => isset( $entry['options'] ) && is_array( $entry['options'] ) ? $entry['options'] : array(),
				'base_price'  => (float) ( $entry['base_price'] ?? 0 ),
				'addon_total' => (float) ( $entry['addon_total'] ?? 0 ),
				'unique_key'  => (string) ( $entry['unique_key'] ?? '' ),
			);
		}
		return $out;
	}

	/**
	 * Find the group an entry belongs to. Legacy entries (group_id 0) match the
	 * first resolved group — migration creates a product-assigned group, so
	 * this covers carts that survived the 1.x -> 2.0 update.
	 *
	 * @param array[] $groups resolved canonical groups.
	 * @return array|null null when the group no longer resolves (deleted mid-cart).
	 */
	public static function pick_group( array $groups, int $group_id ): ?array {
		if ( array() === $groups ) {
			return null;
		}
		if ( 0 === $group_id ) {
			return $groups[0];
		}
		foreach ( $groups as $g ) {
			if ( (int) ( $g['id'] ?? -1 ) === $group_id ) {
				return $g;
			}
		}
		return null;
	}
}
