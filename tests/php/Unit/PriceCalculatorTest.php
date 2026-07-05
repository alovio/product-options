<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\PriceCalculator;
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

	public function test_number_zero_does_not_engage_fee(): void {
		$g = $this->group( array( array( 'id' => 'n', 'type' => 'number', 'price' => 4.0, 'condition' => null ) ) );
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'n' => '0' ), 2 ) );
		$this->assertSame( 4.0, PriceCalculator::addon_total( $g, array( 'n' => '3' ), 2 ) );
	}

	public function test_fee_excluded_when_controller_is_transitively_hidden(): void {
		$g = $this->group(
			array(
				array( 'id' => 'gate', 'type' => 'checkbox', 'condition' => null ),
				array(
					'id'        => 'a',
					'type'      => 'checkbox',
					'condition' => array( 'field' => 'gate', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
				),
				array(
					'id'        => 'b',
					'type'      => 'text',
					'price'     => 7.0,
					'condition' => array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
				),
			)
		);
		// gate=no hides a, so b is transitively inactive even though a's stale value is 'yes'.
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'gate' => 'no', 'a' => 'yes', 'b' => 'hi' ), 2 ) );
		// gate=yes + a=yes -> b active and engaged.
		$this->assertSame( 7.0, PriceCalculator::addon_total( $g, array( 'gate' => 'yes', 'a' => 'yes', 'b' => 'hi' ), 2 ) );
	}

	public function test_quantity_per_unit_and_zero_not_engaged(): void {
		$g = array( 'version' => 1, 'fields' => array( array( 'id' => 'q', 'type' => 'quantity', 'price' => 2.0, 'priceMode' => 'per_unit', 'condition' => null ) ) );
		$this->assertSame( 6.0, PriceCalculator::addon_total( $g, array( 'q' => 3 ), 2 ) );
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'q' => 0 ), 2 ) );
	}

	public function test_per_char_multiplies_by_trimmed_length(): void {
		$g = array( 'version' => 1, 'fields' => array( array( 'id' => 't', 'type' => 'text', 'price' => 0.5, 'priceMode' => 'per_char', 'condition' => null ) ) );
		$this->assertSame( 5.5, PriceCalculator::addon_total( $g, array( 't' => 'Hello world' ), 2 ) ); // 11 chars
		$this->assertSame( 2.0, PriceCalculator::addon_total( $g, array( 't' => ' həəə ' ), 2 ) ); // 4 mb chars trimmed
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 't' => '' ), 2 ) );
	}

	public function test_formula_mode_evaluates_over_sibling_values(): void {
		$g = array( 'version' => 1, 'fields' => array(
			array( 'id' => 'width', 'type' => 'number', 'price' => 0, 'priceMode' => 'fixed', 'condition' => null ),
			array( 'id' => 'height', 'type' => 'number', 'price' => 0, 'priceMode' => 'fixed', 'condition' => null ),
			array( 'id' => 'fee', 'type' => 'price', 'price' => 0, 'priceMode' => 'formula', 'formula' => '{width} * {height} * 0.85', 'condition' => null ),
		) );
		$this->assertSame( 17.0, PriceCalculator::addon_total( $g, array( 'width' => '4', 'height' => '5' ), 2 ) );
		// missing tokens -> 0 contribution
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array(), 2 ) );
	}

	public function test_breakdown_rows_labels_and_amounts(): void {
		$g = array( 'version' => 1, 'fields' => array(
			array( 'id' => 'wrap', 'type' => 'checkbox', 'label' => 'Gift wrap', 'price' => 8, 'priceMode' => 'fixed', 'condition' => null ),
			array( 'id' => 'note', 'type' => 'text', 'label' => 'Engraving', 'price' => 0.5, 'priceMode' => 'per_char', 'condition' => null ),
		) );
		$rows = PriceCalculator::breakdown( $g, array( 'wrap' => 'yes', 'note' => 'Hello' ), 2 );
		$this->assertCount( 2, $rows );
		$this->assertSame( 'Gift wrap', $rows[0]['label'] );
		$this->assertSame( 8.0, $rows[0]['amount'] );
		$this->assertSame( 2.5, $rows[1]['amount'] );
	}
}
