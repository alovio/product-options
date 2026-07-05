<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Templates;

defined( 'ABSPATH' ) || exit;

/**
 * Built-in starter templates (spec §8): each is a single-group export package
 * shipped as JSON beside this class. "Use template" imports it as a draft.
 */
final class Templates {

	/** @var array<string,array{name:string, description:string}> id => meta. */
	private const CATALOG = array(
		'gift-options'    => array(
			'name'        => 'Gift options',
			'description' => 'Gift wrap with a conditional ribbon colour and gift message.',
		),
		'engraving'       => array(
			'name'        => 'Engraving',
			'description' => 'Per-character priced engraving text with a font picker.',
		),
		'tshirt-designer' => array(
			'name'        => 'T-shirt designer',
			'description' => 'Size buttons, print-style image swatches, custom text and artwork upload.',
		),
		'made-to-order'   => array(
			'name'        => 'Made-to-order dimensions',
			'description' => 'Width × height formula pricing plus per-unit extra panels.',
		),
		'delivery-date'   => array(
			'name'        => 'Delivery date',
			'description' => 'Date and time pickers with a percentage rush fee.',
		),
		'donation'        => array(
			'name'        => 'Donation / tip',
			'description' => 'An optional tip amount added to the price.',
		),
	);

	/** @return array<int,array{id:string, name:string, description:string, package:array}> */
	public static function all(): array {
		$out = array();
		foreach ( self::CATALOG as $id => $meta ) {
			$tpl = self::get( $id );
			if ( null !== $tpl ) {
				$out[] = $tpl;
			}
		}
		return $out;
	}

	/** @return array{id:string, name:string, description:string, package:array}|null */
	public static function get( string $id ): ?array {
		if ( ! isset( self::CATALOG[ $id ] ) ) {
			return null;
		}
		$file = __DIR__ . '/' . $id . '.json';
		if ( ! is_readable( $file ) ) {
			return null;
		}
		$package = json_decode( (string) file_get_contents( $file ), true ); // phpcs:ignore WordPress.WP.AlternativeFunctions
		if ( ! is_array( $package ) ) {
			return null;
		}
		return array(
			'id'          => $id,
			'name'        => self::CATALOG[ $id ]['name'],
			'description' => self::CATALOG[ $id ]['description'],
			'package'     => $package,
		);
	}
}
