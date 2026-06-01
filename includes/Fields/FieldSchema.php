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
			$out[] = array(
				'id'        => (string) $f['id'],
				'type'      => (string) $f['type'],
				'label'     => isset( $f['label'] ) ? sanitize_text_field( (string) $f['label'] ) : '',
				'required'  => ! empty( $f['required'] ),
				'price'     => $price < 0 ? 0.0 : $price,
				'options'   => ( isset( $f['options'] ) && is_array( $f['options'] ) )
					? array_values( array_map( static fn( $o ) => sanitize_text_field( (string) $o ), $f['options'] ) )
					: array(),
				'condition' => self::normalize_condition( $f['condition'] ?? null, $ids, (string) $f['id'], $ops, $actions ),
			);
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

	public static function is_valid( array $raw ): bool {
		$in_count = ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) ? count( $raw['fields'] ) : 0;
		return count( self::normalize( $raw )['fields'] ) === $in_count;
	}
}
