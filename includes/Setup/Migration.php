<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Setup;

use CoreLabs\ProductOptions\Fields\FieldSchema;
use CoreLabs\ProductOptions\Groups\GroupRepository;

defined( 'ABSPATH' ) || exit;

/**
 * One-time 1.x -> 2.0 migration (spec §3.5): every product's legacy
 * `_clpo_field_group` meta becomes a product-assigned CPT group. Idempotent
 * (migrated products are marked), batched (≤200 rows per admin load), and the
 * original meta is left untouched for rollback safety.
 */
final class Migration {

	public const OPTION       = 'clpo_version';
	public const LEGACY_META  = '_clpo_field_group';
	public const MIGRATED_KEY = '_clpo_migrated_to';
	public const BATCH        = 200;

	public function register(): void {
		add_action( 'admin_init', array( $this, 'maybe_run' ) );
	}

	public function maybe_run(): void {
		if ( version_compare( (string) get_option( self::OPTION, '1.0.0' ), '2.0.0', '>=' ) ) {
			return;
		}
		$remaining = $this->run(); // one batch per admin load
		if ( 0 === $remaining ) {
			// Literal on purpose: CLPO_VERSION stays 1.0.0 until the release bump.
			update_option( self::OPTION, '2.0.0' );
		}
	}

	/** @return int rows still unmigrated after this batch. */
	public function run(): int {
		$repo = new GroupRepository();
		foreach ( $this->query_batch( self::BATCH ) as $row ) {
			$planned = self::plan( array( $row ) );
			if ( array() === $planned ) {
				// Unmigratable (empty/invalid legacy meta) — mark so we don't re-scan it forever.
				update_post_meta( (int) $row['product_id'], self::MIGRATED_KEY, '0' );
				continue;
			}
			$p     = $planned[0];
			$group = $repo->save(
				0,
				array(
					'title'      => $p['title'],
					'status'     => 'publish',
					'fields'     => $p['fields'],
					'assignment' => $p['assignment'],
					'priority'   => $p['priority'],
				)
			);
			update_post_meta( $p['product_id'], self::MIGRATED_KEY, (string) $group['id'] );
		}
		return count( $this->query_batch( 1 ) ); // 0 or 1 -> "any left?"
	}

	/**
	 * Pure planner: legacy meta rows -> group definitions.
	 *
	 * @param array<int,array{product_id:int|string, product_title:string, fields_json:string}> $rows
	 * @return array<int,array{product_id:int, title:string, fields:array, assignment:array, priority:int}>
	 */
	public static function plan( array $rows ): array {
		$out = array();
		foreach ( $rows as $row ) {
			$decoded = json_decode( (string) ( $row['fields_json'] ?? '' ), true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$normalized = FieldSchema::normalize( $decoded );
			if ( empty( $normalized['fields'] ) ) {
				continue;
			}
			$pid   = (int) $row['product_id'];
			$out[] = array(
				'product_id' => $pid,
				'title'      => trim( (string) ( $row['product_title'] ?? '' ) ) . ' — Options',
				'fields'     => $normalized['fields'],
				'assignment' => array( 'mode' => 'products', 'ids' => array( $pid ) ),
				'priority'   => 10,
			);
		}
		return $out;
	}

	/**
	 * WP glue: unmigrated legacy rows.
	 *
	 * @return array<int,array{product_id:string, product_title:string, fields_json:string}>
	 */
	private function query_batch( int $limit ): array {
		global $wpdb;
		// phpcs:disable WordPress.DB.DirectDatabaseQuery -- one-time migration scan.
		$sql = $wpdb->prepare(
			"SELECT pm.post_id AS product_id, p.post_title AS product_title, pm.meta_value AS fields_json
			 FROM {$wpdb->postmeta} pm
			 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
			 WHERE pm.meta_key = %s
			   AND pm.meta_value <> ''
			   AND NOT EXISTS (
			       SELECT 1 FROM {$wpdb->postmeta} done
			       WHERE done.post_id = pm.post_id AND done.meta_key = %s
			   )
			 LIMIT %d",
			self::LEGACY_META,
			self::MIGRATED_KEY,
			$limit
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A );
		// phpcs:enable WordPress.DB.DirectDatabaseQuery
		return is_array( $rows ) ? $rows : array();
	}
}
