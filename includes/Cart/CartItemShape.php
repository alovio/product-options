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
}
