<?php
declare( strict_types=1 );

namespace APO\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Registry of supported field types. Free set is fixed; Pro extends via the
 * `apo_field_types` filter.
 */
final class FieldTypes {

	/** @var string[] Free-tier field types. */
	public const FREE = array( 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price', 'heading' );

	/** @return string[] */
	public static function all(): array {
		return (array) apply_filters( 'apo_field_types', self::FREE );
	}

	public static function is_valid( string $type ): bool {
		return in_array( $type, self::all(), true );
	}
}
