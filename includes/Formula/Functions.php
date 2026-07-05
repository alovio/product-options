<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Formula;

defined( 'ABSPATH' ) || exit;

/**
 * Allowed formula functions (ported from Alovio Calculator, STRIPPED to the
 * 2.0 pricing grammar — no if/ceil/floor/abs, no comparisons).
 */
final class Functions {

	/** @var array<string, array{0:int,1:int}> name => [minArity, maxArity]. */
	public const SPECS = array(
		'min'   => array( 2, 8 ),
		'max'   => array( 2, 8 ),
		'round' => array( 1, 2 ),
	);
}
