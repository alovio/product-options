<?php
declare( strict_types=1 );

namespace APO\Logic;

defined( 'ABSPATH' ) || exit;

/**
 * Single source of truth for evaluating a field's single-condition rule.
 * Mirrored in JS (src/frontend/conditional-logic.js); both are kept in lockstep
 * by tests/fixtures/conditional-cases.json.
 */
final class ConditionalLogic {

	/**
	 * Raw operator match (no action applied).
	 *
	 * @param array<string,mixed>|null $cond
	 * @param array<string,mixed>      $values
	 */
	public static function matches( ?array $cond, array $values ): bool {
		if ( ! is_array( $cond ) ) {
			return false;
		}
		$field = $cond['field'] ?? '';
		$left  = isset( $values[ $field ] ) ? (string) $values[ $field ] : '';
		$right = isset( $cond['value'] ) ? (string) $cond['value'] : '';
		$op    = $cond['operator'] ?? 'is';

		return 'is_not' === $op ? ( $left !== $right ) : ( $left === $right );
	}

	/**
	 * Whether the field is shown/active given submitted values.
	 *
	 * @param array<string,mixed> $field
	 * @param array<string,mixed> $values
	 */
	public static function is_active( array $field, array $values ): bool {
		$cond = $field['condition'] ?? null;
		if ( ! is_array( $cond ) ) {
			return true;
		}
		$action = $cond['action'] ?? 'show';
		if ( 'require' === $action ) {
			return true; // visibility unaffected; handled by Validator.
		}
		$match = self::matches( $cond, $values );

		return 'hide' === $action ? ! $match : $match;
	}
}
