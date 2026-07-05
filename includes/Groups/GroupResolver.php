<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Groups;

defined( 'ABSPATH' ) || exit;

/**
 * Resolve which option groups apply to a product (spec §3.2). The matcher is
 * pure and unit-tested; for_product() is thin WP glue with a per-request
 * static cache (no persistent object cache — one small CPT query).
 */
final class GroupResolver {

	/**
	 * Pure: filter + sort canonical group arrays for a product.
	 *
	 * @param array[] $groups       canonical shapes (GroupRepository::to_array).
	 * @param int     $product_id
	 * @param int[]   $category_ids product's category ids INCLUDING ancestors.
	 * @return array[]
	 */
	public static function filter_groups( array $groups, int $product_id, array $category_ids ): array {
		$hit = array_values(
			array_filter(
				$groups,
				static function ( $g ) use ( $product_id, $category_ids ) {
					if ( 'publish' !== ( $g['status'] ?? '' ) ) {
						return false;
					}
					$a = isset( $g['assignment'] ) && is_array( $g['assignment'] ) ? $g['assignment'] : array( 'mode' => 'all', 'ids' => array() );
					if ( 'all' === $a['mode'] ) {
						return true;
					}
					if ( 'products' === $a['mode'] ) {
						return in_array( $product_id, $a['ids'], true );
					}
					return (bool) array_intersect( $a['ids'], $category_ids );
				}
			)
		);
		usort(
			$hit,
			static function ( $x, $y ) {
				$p = ( $x['priority'] <=> $y['priority'] );
				return 0 !== $p ? $p : strcasecmp( (string) $x['title'], (string) $y['title'] );
			}
		);
		return $hit;
	}

	/** WP glue: resolved groups for a product, cached per request. */
	public static function for_product( int $product_id ): array {
		static $cache = array();
		if ( ! isset( $cache[ $product_id ] ) ) {
			$cats = array();
			foreach ( (array) wc_get_product_term_ids( $product_id, 'product_cat' ) as $cid ) {
				$cid    = (int) $cid;
				$cats[] = $cid;
				$cats   = array_merge( $cats, array_map( 'intval', get_ancestors( $cid, 'product_cat' ) ) );
			}
			// filter_groups re-checks status, so feeding all() keeps one code path
			// for both the storefront and the hub.
			$cache[ $product_id ] = self::filter_groups( ( new GroupRepository() )->all(), $product_id, array_values( array_unique( $cats ) ) );
		}
		return $cache[ $product_id ];
	}
}
