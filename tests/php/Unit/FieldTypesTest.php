<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Fields\FieldTypes;
use Brain\Monkey;

class FieldTypesTest extends TestCase {

	public function test_all_returns_builtin_types(): void {
		$this->assertSame(
			array( 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price', 'heading', 'swatch', 'date', 'email', 'phone', 'url', 'time', 'quantity', 'buttons', 'image_swatch', 'file' ),
			FieldTypes::all()
		);
	}

	public function test_all_is_filterable(): void {
		// Override the base passthrough stub for this test to prove the filtered
		// return value is what FieldTypes::all() surfaces (Pro extension point).
		Monkey\Functions\when( 'apply_filters' )->justReturn( array( 'text', 'custom' ) );

		$this->assertContains( 'custom', FieldTypes::all() );
	}

	public function test_is_valid(): void {
		$this->assertTrue( FieldTypes::is_valid( 'text' ) );
		$this->assertFalse( FieldTypes::is_valid( 'evil' ) );
	}
}
