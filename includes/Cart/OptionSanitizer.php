<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Cart;

use CoreLabs\ProductOptions\Fields\FieldOptions;

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
				case 'file':
					$tokens = FileUploads::parse_tokens( $val );
					$tokens = array_slice( $tokens, 0, max( 1, (int) ( $f['maxFiles'] ?? 1 ) ) );
					if ( array() !== $tokens ) {
						$out[ $id ] = implode( ',', $tokens );
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
				case 'quantity':
					if ( null !== $val && '' !== $val && is_numeric( $val ) ) {
						$n = (int) $val;
						if ( '' !== (string) ( $f['min'] ?? '' ) ) {
							$n = max( (int) $f['min'], $n );
						}
						if ( '' !== (string) ( $f['max'] ?? '' ) ) {
							$n = min( (int) $f['max'], $n );
						}
						$out[ $id ] = $n;
					}
					break;
				case 'select':
				case 'radio':
				case 'buttons':
				case 'swatch':
				case 'image_swatch':
					// Options may be bare strings or {label, price, colour, image}.
					if ( null !== $val && in_array( (string) $val, FieldOptions::labels( $f ), true ) ) {
						$out[ $id ] = (string) $val;
					}
					break;
				// 'price' = auto surcharge field; no user-submitted value.
			}
		}

		return $out;
	}
}
