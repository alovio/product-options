<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Groups;

use CoreLabs\ProductOptions\Fields\FieldSchema;

defined( 'ABSPATH' ) || exit;

/**
 * CRUD over the option-group CPT. Fields and assignment are stored as JSON
 * post meta; priority is numeric meta so listings can order in-query.
 * Capability checks live at the REST layer, not here.
 */
final class GroupRepository {

	public const META_FIELDS     = '_clpo_fields';
	public const META_ASSIGNMENT = '_clpo_assignment';
	public const META_PRIORITY   = '_clpo_priority';

	public const MODES = array( 'all', 'categories', 'products' );

	/**
	 * Pure: coerce any input into {mode, ids} with unique positive int ids.
	 *
	 * @param mixed $raw
	 * @return array{mode:string, ids:int[]}
	 */
	public static function normalize_assignment( $raw ): array {
		$mode = is_array( $raw ) && isset( $raw['mode'] ) ? (string) $raw['mode'] : 'all';
		if ( ! in_array( $mode, self::MODES, true ) ) {
			$mode = 'all';
		}
		$ids = array();
		if ( 'all' !== $mode && is_array( $raw ) && isset( $raw['ids'] ) && is_array( $raw['ids'] ) ) {
			foreach ( $raw['ids'] as $id ) {
				$id = (int) $id;
				if ( $id > 0 && ! in_array( $id, $ids, true ) ) {
					$ids[] = $id;
				}
			}
		}
		return array( 'mode' => $mode, 'ids' => $ids );
	}

	/**
	 * Pure: the canonical group array shape used by REST/resolver/renderer.
	 *
	 * @param array<string,mixed> $group      normalized group ({fields: ...}).
	 * @param array{mode:string, ids:int[]} $assignment
	 */
	public static function to_array( int $id, string $title, string $status, array $group, array $assignment, int $priority ): array {
		return array(
			'id'         => $id,
			'title'      => $title,
			'status'     => $status,
			'fields'     => isset( $group['fields'] ) && is_array( $group['fields'] ) ? $group['fields'] : array(),
			'assignment' => $assignment,
			'priority'   => $priority,
		);
	}

	public function get( int $id ): ?array {
		$post = get_post( $id );
		if ( ! $post || OptionGroupCpt::TYPE !== $post->post_type ) {
			return null;
		}
		return $this->from_post( $post );
	}

	/**
	 * Create (id = 0) or update a group. Normalizes fields + assignment.
	 *
	 * @param array<string,mixed> $data {title?, status?, fields?, assignment?, priority?}
	 */
	public function save( int $id, array $data ): array {
		$existing = $id > 0 ? $this->get( $id ) : null;

		$title    = isset( $data['title'] ) ? sanitize_text_field( (string) $data['title'] ) : ( $existing['title'] ?? 'Untitled group' );
		$status   = isset( $data['status'] ) && 'publish' === $data['status'] ? 'publish' : ( isset( $data['status'] ) ? 'draft' : ( $existing['status'] ?? 'draft' ) );
		$fields   = isset( $data['fields'] ) && is_array( $data['fields'] ) ? $data['fields'] : ( $existing['fields'] ?? array() );
		$assign   = self::normalize_assignment( $data['assignment'] ?? ( $existing['assignment'] ?? array() ) );
		$priority = isset( $data['priority'] ) ? max( 0, (int) $data['priority'] ) : ( $existing['priority'] ?? 10 );

		$normalized = FieldSchema::normalize( array( 'fields' => $fields ) );

		$postarr = array(
			'post_type'   => OptionGroupCpt::TYPE,
			'post_title'  => '' !== $title ? $title : 'Untitled group',
			'post_status' => $status,
		);
		if ( $id > 0 ) {
			$postarr['ID'] = $id;
			wp_update_post( $postarr );
		} else {
			$id = (int) wp_insert_post( $postarr );
		}

		update_post_meta( $id, self::META_FIELDS, wp_slash( (string) wp_json_encode( $normalized ) ) );
		update_post_meta( $id, self::META_ASSIGNMENT, wp_slash( (string) wp_json_encode( $assign ) ) );
		update_post_meta( $id, self::META_PRIORITY, (string) $priority );

		return self::to_array( $id, $postarr['post_title'], $status, $normalized, $assign, $priority );
	}

	public function delete( int $id ): bool {
		if ( null === $this->get( $id ) ) {
			return false;
		}
		return (bool) wp_delete_post( $id, true );
	}

	public function duplicate( int $id ): ?array {
		$src = $this->get( $id );
		if ( null === $src ) {
			return null;
		}
		return $this->save(
			0,
			array(
				'title'      => $src['title'] . ' (copy)',
				'status'     => 'draft',
				'fields'     => $src['fields'],
				'assignment' => $src['assignment'],
				'priority'   => $src['priority'],
			)
		);
	}

	/** @return array[] every group (publish + draft), priority then title order. */
	public function all(): array {
		$posts = get_posts(
			array(
				'post_type'   => OptionGroupCpt::TYPE,
				'post_status' => array( 'publish', 'draft' ),
				'numberposts' => -1,
				'meta_key'    => self::META_PRIORITY, // phpcs:ignore WordPress.DB.SlowDBQuery
				'orderby'     => array(
					'meta_value_num' => 'ASC',
					'title'          => 'ASC',
				),
			)
		);
		return array_map( array( $this, 'from_post' ), $posts );
	}

	/** @return array[] published groups only. */
	public function published(): array {
		return array_values(
			array_filter(
				$this->all(),
				static function ( $g ) {
					return 'publish' === $g['status'];
				}
			)
		);
	}

	/** @param \WP_Post $post */
	private function from_post( $post ): array {
		$fields_raw = (string) get_post_meta( $post->ID, self::META_FIELDS, true );
		$assign_raw = (string) get_post_meta( $post->ID, self::META_ASSIGNMENT, true );
		$priority   = get_post_meta( $post->ID, self::META_PRIORITY, true );

		$group  = json_decode( $fields_raw, true );
		$group  = is_array( $group ) ? $group : array( 'fields' => array() );
		$assign = self::normalize_assignment( json_decode( $assign_raw, true ) );

		return self::to_array(
			(int) $post->ID,
			(string) $post->post_title,
			(string) $post->post_status,
			$group,
			$assign,
			'' !== (string) $priority ? (int) $priority : 10
		);
	}
}
