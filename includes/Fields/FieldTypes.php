<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of supported field types. All types ship free; the
 * `clpo_field_types` filter remains as a public extension point.
 */
final class FieldTypes {

	/** @var string[] Built-in field types. */
	public const TYPES = array( 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price', 'heading', 'swatch', 'date', 'email', 'phone', 'url', 'time' );

	/** @return string[] */
	public static function all(): array {
		return (array) apply_filters( 'clpo_field_types', self::TYPES );
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
