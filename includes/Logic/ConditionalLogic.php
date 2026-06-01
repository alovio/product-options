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

	/**
	 * Resolve active state for every field in a group, transitively: a field is
	 * active only if its own condition passes AND its controller field is also
	 * active. Cycle-safe. This prevents charging/validating a field whose
	 * controller is itself hidden.
	 *
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $values
	 * @return array<string,bool> field id => active
	 */
	public static function active_map( array $group, array $values ): array {
		$by_id = array();
		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$by_id[ (string) ( $f['id'] ?? '' ) ] = $f;
		}

		$cache    = array();
		$in_stack = array();

		$resolve = function ( string $id ) use ( &$resolve, $by_id, $values, &$cache, &$in_stack ): bool {
			if ( array_key_exists( $id, $cache ) ) {
				return $cache[ $id ];
			}
			if ( ! isset( $by_id[ $id ] ) || isset( $in_stack[ $id ] ) ) {
				return true; // unknown field or cycle: do not block.
			}
			$in_stack[ $id ] = true;
			$field           = $by_id[ $id ];
			$cond            = $field['condition'] ?? null;

			$self = self::is_active( $field, $values );
			if ( is_array( $cond ) && isset( $cond['field'] ) ) {
				$self = $self && $resolve( (string) $cond['field'] );
			}

			unset( $in_stack[ $id ] );
			$cache[ $id ] = $self;
			return $self;
		};

		$map = array();
		foreach ( array_keys( $by_id ) as $id ) {
			$map[ $id ] = $resolve( $id );
		}
		return $map;
	}
}
