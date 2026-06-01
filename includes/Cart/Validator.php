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

			$id    = (string) ( $f['id'] ?? '' );
			$type  = (string) ( $f['type'] ?? '' );
			$label = ( '' !== ( $f['label'] ?? '' ) ) ? (string) $f['label'] : $id;
			$value = $submitted[ $id ] ?? null;

			$required = ! empty( $f['required'] ) || ConditionalLogic::requires( $f, $submitted );

			$is_empty = ( null === $value || '' === $value || array() === $value || false === $value );

			if ( $required && $is_empty ) {
				$errors[] = sprintf( '“%s” is required.', $label );
				continue;
			}
			if ( $is_empty ) {
				continue; // optional + empty: nothing more to check.
			}

			if ( 'number' === $type && ! is_numeric( $value ) ) {
				$errors[] = sprintf( '“%s” must be a number.', $label );
			}

			if ( in_array( $type, array( 'select', 'radio' ), true ) && ! empty( $f['options'] ) ) {
				$opts = array_map( 'strval', (array) $f['options'] );
				if ( ! in_array( (string) $value, $opts, true ) ) {
					$errors[] = sprintf( '“%s” has an invalid selection.', $label );
				}
			}
		}

		return $errors;
	}
}
