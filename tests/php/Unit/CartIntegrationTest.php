<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\CartIntegration;
use Brain\Monkey;

final class CartIntegrationTest extends TestCase {

	public function test_with_price_appends_amount_to_value(): void {
		// No wc_price in the unit context -> number_format fallback.
		$this->assertSame( 'Oak (+5.00)', CartIntegration::with_price( 'Oak', 5.0 ) );
		$this->assertSame( 'x.png, y.webp (+2.00)', CartIntegration::with_price( 'x.png, y.webp', 2.0 ) );
	}

	public function test_with_price_zero_or_negative_leaves_value_untouched(): void {
		$this->assertSame( 'Oak', CartIntegration::with_price( 'Oak', 0.0 ) );
		$this->assertSame( 'Oak', CartIntegration::with_price( 'Oak', -1.0 ) );
	}

	public function test_with_price_surcharge_row_has_bare_amount(): void {
		$this->assertSame( '+48.00', CartIntegration::with_price( '', 48.0 ) );
	}

	public function test_with_price_uses_store_currency_format_when_available(): void {
		Monkey\Functions\when( 'wc_price' )->alias( static fn( $a ) => '<span>&#36;' . number_format( (float) $a, 2 ) . '</span>' );
		Monkey\Functions\when( 'wp_strip_all_tags' )->alias( static fn( $s ) => strip_tags( (string) $s ) );
		$this->assertSame( 'Oak (+$5.50)', CartIntegration::with_price( 'Oak', 5.5 ) );
	}
}
