<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Validate and normalize a decoded field-group definition. Pure logic: drops
 * unknown types, coerces price to a non-negative float, de-duplicates ids, and
 * strips conditions that reference a missing field or the field itself.
 */
final class FieldSchema {

	public static function normalize( array $raw ): array {
		$fields = ( isset( $raw['fields'] ) && is_array( $raw['fields'] ) ) ? $raw['fields'] : array();

		$ops     = (array) apply_filters( 'clpo_allowed_operators', array( 'is', 'is_not', 'contains', 'gt', 'lt' ) );
		$actions = (array) apply_filters( 'clpo_allowed_actions', array( 'show', 'hide', 'require' ) );
		$multi   = (bool) apply_filters( 'clpo_multi_conditions', true );
		$modes   = (array) apply_filters( 'clpo_price_modes', array( 'fixed', 'per_unit', 'percent', 'per_char', 'formula' ) );

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
				'id'          => (string) $f['id'],
				'type'        => (string) $f['type'],
				'label'       => isset( $f['label'] ) ? sanitize_text_field( (string) $f['label'] ) : '',
				'required'    => ! empty( $f['required'] ),
				'price'       => $price < 0 ? 0.0 : $price,
				'priceMode'   => self::normalize_price_mode( $pm, (string) $f['type'], $modes, (string) ( $f['formula'] ?? '' ) ),
				'formula'     => substr( sanitize_text_field( (string) ( $f['formula'] ?? '' ) ), 0, \CoreLabs\ProductOptions\Formula\FormulaPrice::MAX_LENGTH ),
				'placeholder' => isset( $f['placeholder'] ) ? sanitize_text_field( (string) $f['placeholder'] ) : '',
				'description' => isset( $f['description'] ) ? sanitize_text_field( (string) $f['description'] ) : '',
				'default'     => isset( $f['default'] ) ? sanitize_text_field( (string) $f['default'] ) : '',
				'options'     => self::normalize_options( $f ),
			);
			$out[] = array_merge(
				$entry,
				self::normalize_conditional( $f, $ids, (string) $f['id'], $ops, $actions, $multi ),
				self::normalize_constraints( $f )
			);
		}

		return array(
			'version' => 1,
			'fields'  => $out,
		);
	}

	/**
	 * Price modes are type-constrained: per_char needs text, per_unit needs a
	 * numeric input. Anything else falls back to fixed.
	 *
	 * @param string[] $modes allowed mode list (filterable).
	 */
	private static function normalize_price_mode( string $pm, string $type, array $modes, string $formula = '' ): string {
		if ( ! in_array( $pm, $modes, true ) ) {
			return 'fixed';
		}
		if ( 'per_char' === $pm && ! in_array( $type, array( 'text', 'textarea' ), true ) ) {
			return 'fixed';
		}
		if ( 'per_unit' === $pm && ! in_array( $type, array( 'number', 'quantity' ), true ) ) {
			return 'fixed';
		}
		if ( 'formula' === $pm && null !== \CoreLabs\ProductOptions\Formula\FormulaPrice::validate( $formula ) ) {
			return 'fixed'; // invalid/empty expression never ships live.
		}
		return $pm;
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
	 * `conditions` array with match/action (Pro, when `clpo_multi_conditions` is on).
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
	/**
	 * Type-specific constraints. Date fields get optional min/max bounds.
	 *
	 * @param array<string,mixed> $f
	 * @return array<string,mixed>
	 */
	private static function normalize_constraints( array $f ): array {
		$type = (string) ( $f['type'] ?? '' );

		if ( 'date' === $type ) {
			return array(
				'min' => sanitize_text_field( (string) ( $f['min'] ?? '' ) ),
				'max' => sanitize_text_field( (string) ( $f['max'] ?? '' ) ),
			);
		}

		if ( 'number' === $type || 'quantity' === $type ) {
			$num = static fn( $k ) => ( isset( $f[ $k ] ) && '' !== $f[ $k ] && is_numeric( $f[ $k ] ) ) ? (string) ( $f[ $k ] + 0 ) : '';
			return array(
				'min'  => $num( 'min' ),
				'max'  => $num( 'max' ),
				'step' => $num( 'step' ),
			);
		}

		if ( in_array( $type, array( 'text', 'textarea' ), true ) ) {
			$maxlen = ( isset( $f['maxLength'] ) && is_numeric( $f['maxLength'] ) ) ? (int) $f['maxLength'] : 0;
			return $maxlen > 0 ? array( 'maxLength' => $maxlen ) : array();
		}

		return array();
	}

	private static function normalize_options( array $f ): array {
		$raw = ( isset( $f['options'] ) && is_array( $f['options'] ) ) ? $f['options'] : array();

		if ( 'image_swatch' === ( $f['type'] ?? '' ) ) {
			$out = array();
			foreach ( $raw as $o ) {
				$label = is_array( $o ) ? sanitize_text_field( (string) ( $o['label'] ?? '' ) ) : sanitize_text_field( (string) $o );
				if ( '' === $label ) {
					continue;
				}
				$image = is_array( $o ) ? esc_url_raw( (string) ( $o['image'] ?? '' ) ) : '';
				$out[] = array(
					'label' => $label,
					'image' => $image,
				);
			}
			return $out;
		}

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
