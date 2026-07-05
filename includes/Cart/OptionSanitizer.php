<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

defined( 'ABSPATH' ) || exit;

/**
 * Type-aware sanitization of submitted option values. Single input source used
 * by both add-to-cart validation and the cart-item data hook.
 */
final class OptionSanitizer {

	/**
	 * @param array<string,mixed> $group
	 * @param array<string,mixed> $raw
	 * @return array<string,mixed> Map keyed by field id (only engaged/valid values).
	 */
	public static function sanitize( array $group, array $raw ): array {
		$out = array();

		foreach ( (array) ( $group['fields'] ?? array() ) as $f ) {
			$id = (string) ( $f['id'] ?? '' );
			if ( '' === $id ) {
				continue;
			}
			$type = (string) ( $f['type'] ?? '' );
			$val  = $raw[ $id ] ?? null;

			switch ( $type ) {
				case 'text':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = sanitize_text_field( (string) $val );
					}
					break;
				case 'textarea':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = sanitize_textarea_field( (string) $val );
					}
					break;
				case 'date':
				case 'time':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = trim( sanitize_text_field( (string) $val ) );
					}
					break;
				case 'email':
					if ( null !== $val && '' !== $val ) {
						$email = sanitize_email( (string) $val );
						if ( '' !== $email ) {
							$out[ $id ] = $email;
						}
					}
					break;
				case 'url':
					if ( null !== $val && '' !== $val ) {
						$url = esc_url_raw( (string) $val );
						if ( '' !== $url && preg_match( '#^https?://#i', $url ) ) {
							$out[ $id ] = $url;
						}
					}
					break;
				case 'phone':
					if ( null !== $val && '' !== $val ) {
						$phone = trim( (string) preg_replace( '/[^0-9+\-() ]/', '', (string) $val ) );
						if ( preg_match( '/[0-9]/', $phone ) ) {
							$out[ $id ] = $phone;
						}
					}
					break;
				case 'number':
					if ( null !== $val && '' !== $val ) {
						$out[ $id ] = (float) $val;
					}
					break;
				case 'checkbox':
					if ( ! empty( $val ) ) {
						$out[ $id ] = 'yes';
					}
					break;
				case 'select':
				case 'radio':
					$opts = array_map( 'strval', (array) ( $f['options'] ?? array() ) );
					if ( null !== $val && in_array( (string) $val, $opts, true ) ) {
						$out[ $id ] = (string) $val;
					}
					break;
				case 'swatch':
					$labels = array_map(
						static fn( $o ) => (string) ( is_array( $o ) ? ( $o['label'] ?? '' ) : $o ),
						(array) ( $f['options'] ?? array() )
					);
					if ( null !== $val && in_array( (string) $val, $labels, true ) ) {
						$out[ $id ] = (string) $val;
					}
					break;
				// 'price' = auto surcharge field; no user-submitted value.
			}
		}

		return $out;
	}
}
