<?php
namespace CoreLabs\ProductOptions\Formula;

defined( 'ABSPATH' ) || exit;

final class Evaluator {

	/** @var array<string, array{0:int,1:int}> */
	private $functions;

	public function __construct( array $functions ) {
		$this->functions = $functions;
	}

	/**
	 * @param array $ast    Node from Parser.
	 * @param array $values Map field-id => scaled int. Callers must pre-resolve
	 *                      inactive fields to 0 (spec §6/§8).
	 */
	public function evaluate( array $ast, array $values ): int {
		switch ( $ast['type'] ) {
			case 'num':
				return $ast['value'];

			case 'field':
				if ( ! array_key_exists( $ast['id'], $values ) ) {
					throw new FormulaError( 'unknown_field', 'Unknown field' );
				}
				return $values[ $ast['id'] ];

			case 'neg':
				return -$this->evaluate( $ast['operand'], $values );

			case 'bin':
				$l = $this->evaluate( $ast['left'], $values );
				$r = $this->evaluate( $ast['right'], $values );
				switch ( $ast['op'] ) {
					case '+':
						return DecimalMath::add( $l, $r );
					case '-':
						return DecimalMath::sub( $l, $r );
					case '*':
						return DecimalMath::mul( $l, $r );
					case '/':
						return DecimalMath::div( $l, $r );
				}
				break;

			case 'call':
				return $this->call( $ast['name'], $ast['args'], $values );
		}

		throw new FormulaError( 'syntax', 'Malformed AST node' );
	}

	private function call( string $name, array $args, array $values ): int {
		if ( ! isset( $this->functions[ $name ] ) ) {
			throw new FormulaError( 'unknown_function', 'Unknown function' );
		}

		$vals = array_map( fn( $a ) => $this->evaluate( $a, $values ), $args );

		switch ( $name ) {
			case 'min':
				return min( $vals );
			case 'max':
				return max( $vals );
			case 'round':
				$n = isset( $vals[1] ) ? intdiv( $vals[1], DecimalMath::SCALE ) : 0;
				return DecimalMath::roundToDecimals( $vals[0], $n );
		}

		throw new FormulaError( 'unknown_function', 'No evaluator for this function' );
	}
}
