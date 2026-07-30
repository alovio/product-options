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

	/* ── per-option pricing ─────────────────────────────────────────────── */

	private function sizes( float $field_price = 0.0 ): array {
		return $this->group(
			array(
				array(
					'id'        => 'size',
					'type'      => 'select',
					'label'     => 'Frame size',
					'price'     => $field_price,
					'priceMode' => 'fixed',
					'condition' => null,
					'options'   => array(
						array( 'label' => '21x30', 'price' => 399 ),
						array( 'label' => '30x40', 'price' => 499 ),
						array( 'label' => '50x70', 'price' => 799 ),
					),
				),
			)
		);
	}

	public function test_each_option_charges_its_own_price(): void {
		$g = $this->sizes();
		$this->assertSame( 399.0, PriceCalculator::addon_total( $g, array( 'size' => '21x30' ), 2 ) );
		$this->assertSame( 499.0, PriceCalculator::addon_total( $g, array( 'size' => '30x40' ), 2 ) );
		$this->assertSame( 799.0, PriceCalculator::addon_total( $g, array( 'size' => '50x70' ), 2 ) );
	}

	public function test_option_price_works_with_a_zero_field_price(): void {
		// The field itself charges nothing — the option must still be billed.
		$rows = PriceCalculator::breakdown( $this->sizes(), array( 'size' => '50x70' ), 2 );
		$this->assertCount( 1, $rows );
		$this->assertSame( 'Frame size', $rows[0]['label'] );
		$this->assertSame( 799.0, $rows[0]['amount'] );
	}

	public function test_unpriced_option_falls_back_to_the_field_price(): void {
		$g = $this->group(
			array(
				array(
					'id'        => 'size',
					'type'      => 'radio',
					'price'     => 25,
					'priceMode' => 'fixed',
					'condition' => null,
					'options'   => array( 'Standard', array( 'label' => 'Oversized', 'price' => 90 ) ),
				),
			)
		);
		$this->assertSame( 25.0, PriceCalculator::addon_total( $g, array( 'size' => 'Standard' ), 2 ) );
		$this->assertSame( 90.0, PriceCalculator::addon_total( $g, array( 'size' => 'Oversized' ), 2 ) );
	}

	public function test_nothing_charged_when_no_option_is_picked(): void {
		$this->assertSame( 0.0, PriceCalculator::addon_total( $this->sizes(), array(), 2 ) );
		$this->assertSame( 0.0, PriceCalculator::addon_total( $this->sizes(), array( 'size' => '' ), 2 ) );
	}

	public function test_unknown_value_never_charges_an_option_price(): void {
		$this->assertSame( 0.0, PriceCalculator::addon_total( $this->sizes(), array( 'size' => 'A2 (+9999)' ), 2 ) );
	}

	public function test_option_price_respects_percent_mode(): void {
		$g = $this->group(
			array(
				array(
					'id'        => 'speed',
					'type'      => 'radio',
					'price'     => 0,
					'priceMode' => 'percent',
					'condition' => null,
					'options'   => array(
						array( 'label' => 'Express', 'price' => 10 ),
						array( 'label' => 'Overnight', 'price' => 20 ),
					),
				),
			)
		);
		$this->assertSame( 20.0, PriceCalculator::addon_total( $g, array( 'speed' => 'Express' ), 2, 200.0 ) );
		$this->assertSame( 40.0, PriceCalculator::addon_total( $g, array( 'speed' => 'Overnight' ), 2, 200.0 ) );
	}

	public function test_priced_options_still_obey_conditional_logic(): void {
		$g = $this->group(
			array(
				array( 'id' => 'framed', 'type' => 'checkbox', 'price' => 0, 'condition' => null ),
				array(
					'id'        => 'size',
					'type'      => 'select',
					'price'     => 0,
					'priceMode' => 'fixed',
					'condition' => array( 'field' => 'framed', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
					'options'   => array( array( 'label' => '50x70', 'price' => 799 ) ),
				),
			)
		);
		// Controller off -> the size field is inactive, so its option cannot charge.
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'size' => '50x70' ), 2 ) );
		$this->assertSame( 799.0, PriceCalculator::addon_total( $g, array( 'framed' => 'yes', 'size' => '50x70' ), 2 ) );
	}
}
