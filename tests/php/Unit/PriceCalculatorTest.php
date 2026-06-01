<?php
declare( strict_types=1 );

namespace APO\Tests\Unit;

use APO\Cart\PriceCalculator;
use Brain\Monkey;

class PriceCalculatorTest extends TestCase {

	private function group( array $fields ): array {
		return array( 'version' => 1, 'fields' => $fields );
	}

	public function test_fixed_fee_added_when_engaged(): void {
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'text', 'price' => 5.0, 'condition' => null ) ) );
		$this->assertSame( 5.0, PriceCalculator::addon_total( $g, array( 'a' => 'hello' ), 2 ) );
	}

	public function test_fee_not_added_when_empty(): void {
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'text', 'price' => 5.0, 'condition' => null ) ) );
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'a' => '' ), 2 ) );
	}

	public function test_checkbox_fee_only_when_checked(): void {
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'checkbox', 'price' => 3.0, 'condition' => null ) ) );
		$this->assertSame( 3.0, PriceCalculator::addon_total( $g, array( 'a' => 'yes' ), 2 ) );
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array(), 2 ) );
	}

	public function test_inactive_field_fee_excluded(): void {
		$g = $this->group(
			array(
				array( 'id' => 'c', 'type' => 'checkbox', 'condition' => null ),
				array(
					'id'        => 'a',
					'type'      => 'text',
					'price'     => 5.0,
					'condition' => array( 'field' => 'c', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
				),
			)
		);
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'c' => 'no', 'a' => 'hi' ), 2 ) );
	}

	public function test_rounding_to_two_decimals(): void {
		$g = $this->group(
			array(
				array( 'id' => 'a', 'type' => 'price', 'price' => 1.1, 'condition' => null ),
				array( 'id' => 'b', 'type' => 'price', 'price' => 2.2, 'condition' => null ),
			)
		);
		$this->assertSame( 3.3, PriceCalculator::addon_total( $g, array(), 2 ) );
	}

	public function test_filter_can_override_total(): void {
		Monkey\Functions\when( 'apply_filters' )->justReturn( 99.0 );
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'text', 'price' => 5.0, 'condition' => null ) ) );
		$this->assertSame( 99.0, PriceCalculator::addon_total( $g, array( 'a' => 'x' ), 2 ) );
	}
}
