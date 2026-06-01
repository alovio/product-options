<?php
declare( strict_types=1 );

namespace APO\Cart;

use APO\Logic\ConditionalLogic;

defined( 'ABSPATH' ) || exit;

/**
 * Validate submitted option values against a normalized field group.
 * Pure logic — returns a list of human-readable error strings.
 */
final class Validator {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $submitted
	 * @return string[]
	 */
	public static function validate( array $group, array $submitted ): array {
		$errors = array();
		$active = ConditionalLogic::active_map( $group, $submitted );

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			if ( empty( $active[ $f['id'] ?? '' ] ) ) {
				continue; // hidden fields are not validated.
			}

			$id   = (string) ( $f['id'] ?? '' );
			$type = (string) ( $f['type'] ?? '' );
			if ( 'heading' === $type ) {
				continue; // display-only, no value to validate.
			}
			$label = ( '' !== ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id;
			$value = $submitted[ $id ] ?? null;

			$required = ! empty( $f['required'] ) || ConditionalLogic::requires( $f, $submitted );

			$is_empty = ( null === $value || '' === $value || array() === $value || false === $value );

			if ( $required && $is_empty ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” is required.', 'conditional-product-options' ), $label );
				continue;
			}
			if ( $is_empty ) {
				continue; // optional + empty: nothing more to check.
			}

			if ( 'number' === $type && ! is_numeric( $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a number.', 'conditional-product-options' ), $label );
			}

			if ( in_array( $type, array( 'select', 'radio', 'swatch' ), true ) && ! empty( $f['options'] ) ) {
				$valid = ( 'swatch' === $type )
					? array_map( static fn( $o ) => (string) ( $o['label'] ?? '' ), (array) $f['options'] )
					: array_map( 'strval', (array) $f['options'] );
				if ( ! in_array( (string) $value, $valid, true ) ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '“%s” has an invalid selection.', 'conditional-product-options' ), $label );
				}
			}

			if ( 'date' === $type ) {
				$ts = strtotime( (string) $value );
				if ( false === $ts ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '“%s” is not a valid date.', 'conditional-product-options' ), $label );
				} else {
					if ( ! empty( $f['min'] ) && $ts < (int) strtotime( (string) $f['min'] ) ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '“%s” is before the earliest allowed date.', 'conditional-product-options' ), $label );
					}
					if ( ! empty( $f['max'] ) && $ts > (int) strtotime( (string) $f['max'] ) ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '“%s” is after the latest allowed date.', 'conditional-product-options' ), $label );
					}
				}
			}
		}

		return $errors;
	}
}
