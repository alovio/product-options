<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Fields\FieldOptions;

final class FieldOptionsTest extends TestCase {

	private function sizes(): array {
		return array(
			'id'      => 'size',
			'type'    => 'select',
			'options' => array(
				array( 'label' => '21x30', 'price' => 399 ),
				array( 'label' => '30x40', 'price' => 499 ),
				array( 'label' => '50x70', 'price' => 799 ),
			),
		);
	}

	public function test_label_reads_both_shapes(): void {
		$this->assertSame( 'Large', FieldOptions::label( 'Large' ) );
		$this->assertSame( 'Large', FieldOptions::label( array( 'label' => 'Large', 'price' => 5 ) ) );
		$this->assertSame( '', FieldOptions::label( array( 'price' => 5 ) ) );
	}

	public function test_price_only_counts_positive_numbers(): void {
		$this->assertSame( 5.0, FieldOptions::price( array( 'label' => 'a', 'price' => 5 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( 'a' ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a' ) ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => 0 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => -5 ) ) );
		$this->assertSame( 0.0, FieldOptions::price( array( 'label' => 'a', 'price' => 'free' ) ) );
	}

	public function test_labels_mixed_shapes(): void {
		$f = array( 'options' => array( 'S', array( 'label' => 'M', 'price' => 3 ), array( 'label' => 'L' ) ) );
		$this->assertSame( array( 'S', 'M', 'L' ), FieldOptions::labels( $f ) );
	}

	public function test_price_for_value_matches_the_picked_option(): void {
		$f = $this->sizes();
		$this->assertSame( 399.0, FieldOptions::price_for_value( $f, '21x30' ) );
		$this->assertSame( 799.0, FieldOptions::price_for_value( $f, '50x70' ) );
		$this->assertSame( 0.0, FieldOptions::price_for_value( $f, 'A2' ) );
		$this->assertSame( 0.0, FieldOptions::price_for_value( $f, '' ) );
		$this->assertSame( 0.0, FieldOptions::price_for_value( $f, null ) );
	}

	public function test_has_priced_options_and_range(): void {
		$this->assertTrue( FieldOptions::has_priced_options( $this->sizes() ) );
		$this->assertSame( array( 399.0, 799.0 ), FieldOptions::price_range( $this->sizes() ) );

		$plain = array( 'options' => array( 'S', 'M', 'L' ) );
		$this->assertFalse( FieldOptions::has_priced_options( $plain ) );
		$this->assertSame( array( 0.0, 0.0 ), FieldOptions::price_range( $plain ) );
	}

	public function test_single_priced_option_range_collapses(): void {
		$f = array( 'options' => array( 'S', array( 'label' => 'M', 'price' => 12 ) ) );
		$this->assertSame( array( 12.0, 12.0 ), FieldOptions::price_range( $f ) );
	}
}
