<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Formula\FormulaPrice;

/**
 * Iterates the shared PHP↔JS fixture (tests/fixtures/formula-cases.json).
 * evaluate() never throws: BOTH error kinds yield 0.0. validate() is
 * compile-only (incl. the ≤200 length cap): it flags compile errors, but NOT
 * runtime errors like divide-by-zero.
 */
final class FormulaTest extends TestCase {

	/** @return array<string,array{0:array}> */
	public function caseProvider(): array {
		$raw   = json_decode( (string) file_get_contents( dirname( __DIR__, 2 ) . '/fixtures/formula-cases.json' ), true );
		$cases = array();
		foreach ( $raw as $case ) {
			$cases[ $case['name'] ] = array( $case );
		}
		return $cases;
	}

	/** @dataProvider caseProvider */
	public function test_fixture_case( array $case ): void {
		$expected = $case['expected'];
		$result   = FormulaPrice::evaluate( (string) $case['expr'], (array) $case['values'] );

		if ( is_array( $expected ) ) {
			// Any error kind -> contribution 0.0 on the storefront/server.
			$this->assertSame( 0.0, $result, 'errors must yield 0.0' );
			$validation = FormulaPrice::validate( (string) $case['expr'] );
			if ( 'compile' === $expected['error'] ) {
				$this->assertIsString( $validation, 'compile errors must be flagged by validate()' );
			} else {
				$this->assertNull( $validation, 'runtime errors are invisible to compile-only validate()' );
			}
			return;
		}

		$this->assertSame( (float) $expected, $result );
		$this->assertNull( FormulaPrice::validate( (string) $case['expr'] ) );
	}
}
