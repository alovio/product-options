<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

use CoreLabs\ProductOptions\Logic\ConditionalLogic;

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
				$errors[] = sprintf( __( '“%s” is required.', 'corelabs-product-options' ), $label );
				continue;
			}
			if ( $is_empty ) {
				continue; // optional + empty: nothing more to check.
			}

			if ( 'number' === $type && ! is_numeric( $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a number.', 'corelabs-product-options' ), $label );
			}

			if ( 'email' === $type && ! filter_var( (string) $value, FILTER_VALIDATE_EMAIL ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a valid email address.', 'corelabs-product-options' ), $label );
			}

			if ( 'time' === $type && ! preg_match( '/^([01]\d|2[0-3]):[0-5]\d$/', (string) $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a valid time (HH:MM).', 'corelabs-product-options' ), $label );
			}

			if ( 'phone' === $type && preg_match_all( '/[0-9]/', (string) $value ) < 5 ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a valid phone number.', 'corelabs-product-options' ), $label );
			}

			if ( 'url' === $type && ! preg_match( '#^https?://#i', (string) $value ) ) {
				/* translators: %s: field label */
				$errors[] = sprintf( __( '“%s” must be a valid link (https://…).', 'corelabs-product-options' ), $label );
			}

			if ( 'quantity' === $type ) {
				if ( ! is_numeric( $value ) ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '“%s” must be a number.', 'corelabs-product-options' ), $label );
				} else {
					if ( '' !== (string) ( $f['min'] ?? '' ) && (float) $value < (float) $f['min'] ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '“%s” is below the minimum quantity.', 'corelabs-product-options' ), $label );
					}
					if ( '' !== (string) ( $f['max'] ?? '' ) && (float) $value > (float) $f['max'] ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '“%s” is above the maximum quantity.', 'corelabs-product-options' ), $label );
					}
				}
			}

			if ( in_array( $type, array( 'select', 'radio', 'buttons', 'swatch', 'image_swatch' ), true ) && ! empty( $f['options'] ) ) {
				$valid = in_array( $type, array( 'swatch', 'image_swatch' ), true )
					? array_map( static fn( $o ) => (string) ( $o['label'] ?? '' ), (array) $f['options'] )
					: array_map( 'strval', (array) $f['options'] );
				if ( ! in_array( (string) $value, $valid, true ) ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '“%s” has an invalid selection.', 'corelabs-product-options' ), $label );
				}
			}

			if ( 'date' === $type ) {
				$ts = strtotime( (string) $value );
				if ( false === $ts ) {
					/* translators: %s: field label */
					$errors[] = sprintf( __( '“%s” is not a valid date.', 'corelabs-product-options' ), $label );
				} else {
					if ( ! empty( $f['min'] ) && $ts < (int) strtotime( (string) $f['min'] ) ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '“%s” is before the earliest allowed date.', 'corelabs-product-options' ), $label );
					}
					if ( ! empty( $f['max'] ) && $ts > (int) strtotime( (string) $f['max'] ) ) {
						/* translators: %s: field label */
						$errors[] = sprintf( __( '“%s” is after the latest allowed date.', 'corelabs-product-options' ), $label );
					}
				}
			}
		}

		return $errors;
	}
}
