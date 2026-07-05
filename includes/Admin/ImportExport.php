<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Admin;

use CoreLabs\ProductOptions\Fields\FieldSchema;
use CoreLabs\ProductOptions\Groups\GroupRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Group export/import (spec §8). Pure: package() strips ids for portability,
 * unpack() schema-normalizes and reports what normalization removed as
 * warnings — the same code path powers built-in templates, so template↔schema
 * drift surfaces in tests.
 */
final class ImportExport {

	public const VERSION = '2.0';

	/**
	 * @param array[] $groups canonical group arrays.
	 * @return array{version:string, groups:array[]}
	 */
	public static function package( array $groups ): array {
		$out = array();
		foreach ( $groups as $g ) {
			$out[] = array(
				'title'      => (string) ( $g['title'] ?? '' ),
				'fields'     => isset( $g['fields'] ) && is_array( $g['fields'] ) ? $g['fields'] : array(),
				'assignment' => GroupRepository::normalize_assignment( $g['assignment'] ?? array() ),
				'priority'   => (int) ( $g['priority'] ?? 10 ),
			);
		}
		return array(
			'version' => self::VERSION,
			'groups'  => $out,
		);
	}

	/**
	 * @param mixed $json decoded import payload.
	 * @return array{groups:array[], warnings:string[]}
	 */
	public static function unpack( $json ): array {
		if ( ! is_array( $json ) || ! isset( $json['groups'] ) || ! is_array( $json['groups'] ) ) {
			return array(
				'groups'   => array(),
				'warnings' => array( __( 'The file is not a valid Product Options export.', 'corelabs-product-options' ) ),
			);
		}

		$groups   = array();
		$warnings = array();

		foreach ( $json['groups'] as $i => $raw ) {
			if ( ! is_array( $raw ) ) {
				/* translators: %d: group position in the file */
				$warnings[] = sprintf( __( 'Group #%d was skipped (not an object).', 'corelabs-product-options' ), $i + 1 );
				continue;
			}
			$title      = '' !== trim( (string) ( $raw['title'] ?? '' ) ) ? sanitize_text_field( (string) $raw['title'] ) : 'Untitled group';
			$fields_in  = isset( $raw['fields'] ) && is_array( $raw['fields'] ) ? $raw['fields'] : array();
			$normalized = FieldSchema::normalize( array( 'fields' => $fields_in ) );

			// Report what normalization dropped/coerced.
			$kept_ids = array_column( $normalized['fields'], 'id' );
			foreach ( $fields_in as $f ) {
				if ( ! is_array( $f ) ) {
					continue;
				}
				$fid = (string) ( $f['id'] ?? '' );
				if ( '' === $fid || ! in_array( $fid, $kept_ids, true ) ) {
					$warnings[] = sprintf(
						/* translators: 1: field id, 2: group title, 3: field type */
						__( 'Field “%1$s” in “%2$s” was dropped (unknown type “%3$s”).', 'corelabs-product-options' ),
						'' !== $fid ? $fid : '?',
						$title,
						(string) ( $f['type'] ?? '' )
					);
				}
			}

			$groups[] = array(
				'title'      => $title,
				'fields'     => $normalized['fields'],
				'assignment' => GroupRepository::normalize_assignment( $raw['assignment'] ?? array() ),
				'priority'   => max( 0, (int) ( $raw['priority'] ?? 10 ) ),
			);
		}

		return array(
			'groups'   => $groups,
			'warnings' => $warnings,
		);
	}
}
