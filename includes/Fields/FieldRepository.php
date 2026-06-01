<?php
declare( strict_types=1 );

namespace APO\Fields;

defined( 'ABSPATH' ) || exit;

/**
 * Read/write a product's field-group definition (stored as JSON in product
 * meta). Product meta is unaffected by HPOS (orders only), so post meta is
 * correct here. Always normalized through FieldSchema on the way in and out.
 */
final class FieldRepository {

	private const META_KEY = '_apo_field_group';

	/** @return array<string,mixed> */
	public function get( int $product_id ): array {
		$raw = get_post_meta( $product_id, self::META_KEY, true );

		if ( is_string( $raw ) && '' !== $raw ) {
			$decoded = json_decode( $raw, true );
		} elseif ( is_array( $raw ) ) {
			$decoded = $raw;
		} else {
			$decoded = array();
		}
		if ( ! is_array( $decoded ) ) {
			$decoded = array();
		}

		return FieldSchema::normalize( $decoded );
	}

	/**
	 * @param array<string,mixed> $group
	 * @return array<string,mixed> The normalized group that was stored.
	 */
	public function save( int $product_id, array $group ): array {
		$normalized = FieldSchema::normalize( $group );
		update_post_meta( $product_id, self::META_KEY, wp_slash( wp_json_encode( $normalized ) ) );

		return $normalized;
	}
}
