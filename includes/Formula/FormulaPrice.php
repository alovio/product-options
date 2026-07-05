<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Formula;

defined( 'ABSPATH' ) || exit;

/**
 * Money facade over the ported decimal-safe engine (spec §7).
 *
 * evaluate() NEVER throws: compile or runtime errors (divide-by-zero, bad
 * token) contribute 0 and are logged through wc_get_logger when available.
 * Results are clamped to ≥ 0 (no negative-price discounts in 2.0) and
 * rounded to 2 decimals. Referenced-but-missing field tokens resolve to 0.
 *
 * validate() is compile-only (length cap included): it returns an error
 * string for authoring mistakes, null when the expression parses.
 */
final class FormulaPrice {

	public const MAX_LENGTH = 200;

	/**
	 * @param array<string, int|float|string> $values field id => numeric value.
	 */
	public static function evaluate( string $expr, array $values ): float {
		try {
			$ast = self::compile( $expr );

			$scaled = array();
			foreach ( self::references( $ast ) as $ref ) {
				$v              = $values[ $ref ] ?? 0;
				$scaled[ $ref ] = DecimalMath::toScaled( is_numeric( $v ) ? $v : 0 );
			}

			$result = ( new Evaluator( Functions::SPECS ) )->evaluate( $ast, $scaled );
			$float  = (float) DecimalMath::fromScaled( $result );

			return round( max( 0.0, $float ), 2 );
		} catch ( FormulaError $e ) {
			if ( function_exists( 'wc_get_logger' ) ) {
				wc_get_logger()->warning(
					sprintf( 'Formula "%s" failed: %s (%s)', $expr, $e->getMessage(), $e->getErrorCode() ),
					array( 'source' => 'alovio-product-options' )
				);
			}
			return 0.0;
		}
	}

	/** @return string|null error message (compile-only), null when valid. */
	public static function validate( string $expr ): ?string {
		if ( strlen( $expr ) > self::MAX_LENGTH ) {
			return sprintf( 'The formula is too long (max %d characters).', self::MAX_LENGTH );
		}
		try {
			self::compile( $expr );
			return null;
		} catch ( FormulaError $e ) {
			return $e->getMessage();
		}
	}

	/** Compile with the length cap enforced (the shared entry point). */
	public static function compile( string $expr ): array {
		if ( strlen( $expr ) > self::MAX_LENGTH ) {
			throw new FormulaError( 'too_long', 'Expression exceeds the length cap' );
		}
		return ( new Parser( Functions::SPECS ) )->parse( Lexer::tokenize( $expr ) );
	}

	/** @return string[] unique field ids referenced by the AST, first-seen order. */
	public static function references( array $ast ): array {
		$refs = array();
		self::walk( $ast, $refs );
		return array_values( array_unique( $refs ) );
	}

	private static function walk( array $node, array &$refs ): void {
		switch ( $node['type'] ) {
			case 'field':
				$refs[] = $node['id'];
				return;
			case 'neg':
				self::walk( $node['operand'], $refs );
				return;
			case 'bin':
				self::walk( $node['left'], $refs );
				self::walk( $node['right'], $refs );
				return;
			case 'call':
				foreach ( $node['args'] as $arg ) {
					self::walk( $arg, $refs );
				}
				return;
		}
	}
}
