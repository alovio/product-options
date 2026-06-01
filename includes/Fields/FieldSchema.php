<?php
declare( strict_types=1 );

namespace APO\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Validate and normalize a decoded field-group definition. Pure logic: drops
 * unknown types, coerces price to a non-negative float, de-duplicates ids, and
 * strips conditions that reference a missing field or the field itself.
 */
final class FieldSchema {

	public static function normalize( array $raw ): array {
		$fields = ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) ? $raw['fields'] : array();

		$ops     = (array) apply_filters( 'apo_allowed_operators', array( 'is', 'is_not' ) );
		$actions = (array) apply_filters( 'apo_allowed_actions', array( 'show', 'hide', 'require' ) );
		$multi   = (bool) apply_filters( 'apo_multi_conditions', false );
		$modes   = (array) apply_filters( 'apo_price_modes', array( 'fixed' ) );

		// First pass: keep valid-typed fields with unique, non-empty ids.
		$kept = array();
		$seen = array();
		foreach ( $fields as $f ) {
			if ( ! is_array( $f ) ) {
				continue;
			}
			$type = isset( $f['type'] ) ? (string) $f['type'] : '';
			if ( ! FieldTypes::is_valid( $type ) ) {
				continue;
			}
			$id = isset( $f['id'] ) ? (string) $f['id'] : '';
			if ( '' === $id || isset( $seen[ $id ] ) ) {
				continue;
			}
			$seen[ $id ] = true;
			$kept[]      = $f;
		}
		$ids = array_keys( $seen );

		// Second pass: normalize each kept field.
		$out = array();
		foreach ( $kept as $f ) {
			$price = isset( $f['price'] ) ? (float) $f['price'] : 0.0;
			$pm    = (string) ( $f['priceMode'] ?? 'fixed' );
			$entry = array(
				'id'       => (string) $f['id'],
				'type'     => (string) $f['type'],
				'label'    => isset( $f['label'] ) ? sanitize_text_field( (string) $f['label'] ) : '',
				'required'  => ! empty( $f['required'] ),
				'price'     => $price < 0 ? 0.0 : $price,
				'priceMode' => in_array( $pm, $modes, true ) ? $pm : 'fixed',
				'options'   => self::normalize_options( $f ),
			);
			$out[] = array_merge( $entry, self::normalize_conditional( $f, $ids, (string) $f['id'], $ops, $actions, $multi ) );
		}

		return array(
			'version' => 1,
			'fields'  => $out,
		);
	}

	/**
	 * @param mixed    $cond
	 * @param string[] $ids
	 * @param string[] $ops
	 * @param string[] $actions
	 * @return array|null
	 */
	private static function normalize_condition( $cond, array $ids, string $self_id, array $ops, array $actions ) {
		if ( ! is_array( $cond ) ) {
			return null;
		}
		$field = isset( $cond['field'] ) ? (string) $cond['field'] : '';
		// Strip references to a missing field or to the field itself.
		if ( '' === $field || $field === $self_id || ! in_array( $field, $ids, true ) ) {
			return null;
		}
		$op = isset( $cond['operator'] ) ? (string) $cond['operator'] : '';
		if ( ! in_array( $op, $ops, true ) ) {
			$op = $ops[0] ?? 'is';
		}
		$action = isset( $cond['action'] ) ? (string) $cond['action'] : '';
		if ( ! in_array( $action, $actions, true ) ) {
			$action = $actions[0] ?? 'show';
		}
		return array(
			'field'    => $field,
			'operator' => $op,
			'value'    => isset( $cond['value'] ) ? sanitize_text_field( (string) $cond['value'] ) : '',
			'action'   => $action,
		);
	}

	/**
	 * Build the conditional keys for a field: a single `condition` (free) or a
	 * `conditions` array with match/action (Pro, when `apo_multi_conditions` is on).
	 *
	 * @param array<string,mixed> $f
	 * @param string[]            $ids
	 * @param string[]            $ops
	 * @param string[]            $actions
	 * @return array<string,mixed>
	 */
	private static function normalize_conditional( array $f, array $ids, string $self_id, array $ops, array $actions, bool $multi ): array {
		if ( $multi && ! empty( $f['conditions'] ) && is_array( $f['conditions'] ) ) {
			$rules = array();
			foreach ( $f['conditions'] as $rule ) {
				$nr = self::normalize_rule( is_array( $rule ) ? $rule : array(), $ids, $self_id, $ops );
				if ( null !== $nr ) {
					$rules[] = $nr;
				}
			}
			if ( ! empty( $rules ) ) {
				$action = isset( $f['conditionAction'] ) ? (string) $f['conditionAction'] : 'show';
				if ( ! in_array( $action, $actions, true ) ) {
					$action = $actions[0] ?? 'show';
				}
				return array(
					'condition'       => null,
					'conditions'      => $rules,
					'conditionMatch'  => ( 'any' === ( $f['conditionMatch'] ?? 'all' ) ) ? 'any' : 'all',
					'conditionAction' => $action,
				);
			}
		}
		return array( 'condition' => self::normalize_condition( $f['condition'] ?? null, $ids, $self_id, $ops, $actions ) );
	}

	/**
	 * Normalize one multi-condition rule ( {field, operator, value} ), or null if invalid.
	 *
	 * @param array<string,mixed> $rule
	 * @param string[]            $ids
	 * @param string[]            $ops
	 * @return array<string,mixed>|null
	 */
	/**
	 * Normalize a field's options. Swatch options are objects {label, color};
	 * all other option-bearing types are plain strings.
	 *
	 * @param array<string,mixed> $f
	 * @return array<int,mixed>
	 */
	private static function normalize_options( array $f ): array {
		$raw = ( isset( $f['options'] ) && is_array( $f['options'] ) ) ? $f['options'] : array();

		if ( 'swatch' === ( $f['type'] ?? '' ) ) {
			$out = array();
			foreach ( $raw as $o ) {
				$label = is_array( $o ) ? sanitize_text_field( (string) ( $o['label'] ?? '' ) ) : sanitize_text_field( (string) $o );
				if ( '' === $label ) {
					continue;
				}
				$color = is_array( $o ) ? sanitize_hex_color( (string) ( $o['color'] ?? '' ) ) : '';
				$out[] = array(
					'label' => $label,
					'color' => $color ? $color : '#cccccc',
				);
			}
			return $out;
		}

		return array_values( array_map( static fn( $o ) => sanitize_text_field( (string) $o ), $raw ) );
	}

	private static function normalize_rule( array $rule, array $ids, string $self_id, array $ops ): ?array {
		$field = isset( $rule['field'] ) ? (string) $rule['field'] : '';
		if ( '' === $field || $field === $self_id || ! in_array( $field, $ids, true ) ) {
			return null;
		}
		$op = isset( $rule['operator'] ) ? (string) $rule['operator'] : '';
		if ( ! in_array( $op, $ops, true ) ) {
			$op = $ops[0] ?? 'is';
		}
		return array(
			'field'    => $field,
			'operator' => $op,
			'value'    => isset( $rule['value'] ) ? sanitize_text_field( (string) $rule['value'] ) : '',
		);
	}

	public static function is_valid( array $raw ): bool {
		$in_count = ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) ? count( $raw['fields'] ) : 0;
		return count( self::normalize( $raw )['fields'] ) === $in_count;
	}
}
