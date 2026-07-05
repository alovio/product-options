<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\PriceCalculator;
use CoreLabs\ProductOptions\Fields\FieldSchema;
use CoreLabs\ProductOptions\Logic\ConditionalLogic;
use Brain\Monkey;

/**
 * 2.0: everything ships free by default; the clpo_* filters remain as public
 * extension points. These tests pin the full defaults AND prove the filters
 * can still restrict/extend them.
 */
class ExtensionFiltersTest extends TestCase {

	public function test_contains_operator(): void {
		$field = array( 'condition' => array( 'field' => 't', 'operator' => 'contains', 'value' => 'abc', 'action' => 'show' ) );
		$this->assertTrue( ConditionalLogic::is_active( $field, array( 't' => 'x abc y' ) ) );
		$this->assertFalse( ConditionalLogic::is_active( $field, array( 't' => 'nope' ) ) );
	}

	public function test_gt_and_lt_operators(): void {
		$gt = array( 'condition' => array( 'field' => 'n', 'operator' => 'gt', 'value' => '5', 'action' => 'show' ) );
		$this->assertTrue( ConditionalLogic::is_active( $gt, array( 'n' => '7' ) ) );
		$this->assertFalse( ConditionalLogic::is_active( $gt, array( 'n' => '3' ) ) );

		$lt = array( 'condition' => array( 'field' => 'n', 'operator' => 'lt', 'value' => '5', 'action' => 'show' ) );
		$this->assertTrue( ConditionalLogic::is_active( $lt, array( 'n' => '3' ) ) );
		$this->assertFalse( ConditionalLogic::is_active( $lt, array( 'n' => '9' ) ) );
	}

	private function multi( string $match, string $action = 'show' ): array {
		return array(
			'conditions'      => array(
				array( 'field' => 'a', 'operator' => 'is', 'value' => 'x' ),
				array( 'field' => 'b', 'operator' => 'is', 'value' => 'y' ),
			),
			'conditionMatch'  => $match,
			'conditionAction' => $action,
		);
	}

	public function test_multi_all_requires_every_rule(): void {
		$f = $this->multi( 'all' );
		$this->assertTrue( ConditionalLogic::is_active( $f, array( 'a' => 'x', 'b' => 'y' ) ) );
		$this->assertFalse( ConditionalLogic::is_active( $f, array( 'a' => 'x', 'b' => 'z' ) ) );
	}

	public function test_multi_any_needs_one_rule(): void {
		$f = $this->multi( 'any' );
		$this->assertTrue( ConditionalLogic::is_active( $f, array( 'a' => 'x', 'b' => 'z' ) ) );
		$this->assertFalse( ConditionalLogic::is_active( $f, array( 'a' => 'p', 'b' => 'q' ) ) );
	}

	public function test_multi_require_action(): void {
		$f = $this->multi( 'all', 'require' );
		$this->assertTrue( ConditionalLogic::is_active( $f, array() ) ); // require never hides
		$this->assertTrue( ConditionalLogic::requires( $f, array( 'a' => 'x', 'b' => 'y' ) ) );
		$this->assertFalse( ConditionalLogic::requires( $f, array( 'a' => 'x' ) ) );
	}

	public function test_schema_keeps_multi_conditions_by_default(): void {
		// Base stub = passthrough filters -> the inlined full defaults apply.
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'checkbox' ),
					array( 'id' => 'b', 'type' => 'number' ),
					array(
						'id'              => 'c',
						'type'            => 'text',
						'conditions'      => array(
							array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes' ),
							array( 'field' => 'b', 'operator' => 'gt', 'value' => '2' ),
						),
						'conditionMatch'  => 'any',
						'conditionAction' => 'show',
					),
				),
			)
		);
		$c = $out['fields'][2];
		$this->assertCount( 2, $c['conditions'] );
		$this->assertSame( 'any', $c['conditionMatch'] );
		$this->assertSame( 'gt', $c['conditions'][1]['operator'] );
	}

	public function test_filter_can_restrict_multi_conditions(): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return 'clpo_multi_conditions' === $tag ? false : $value;
			}
		);
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'checkbox' ),
					array(
						'id'         => 'c',
						'type'       => 'text',
						'conditions' => array( array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes' ) ),
					),
				),
			)
		);
		$c = $out['fields'][1];
		$this->assertArrayNotHasKey( 'conditions', $c );
		$this->assertNull( $c['condition'] );
	}

	public function test_per_unit_pricing_multiplies_by_quantity(): void {
		$g = array( 'version' => 1, 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => 0.5, 'priceMode' => 'per_unit', 'condition' => null ) ) );
		$this->assertSame( 1.5, PriceCalculator::addon_total( $g, array( 'n' => '3' ), 2 ) );
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array( 'n' => '0' ), 2 ) ); // 0 not engaged
	}

	public function test_percent_pricing_uses_base(): void {
		$g = array( 'version' => 1, 'fields' => array( array( 'id' => 'c', 'type' => 'checkbox', 'price' => 10, 'priceMode' => 'percent', 'condition' => null ) ) );
		$this->assertSame( 20.0, PriceCalculator::addon_total( $g, array( 'c' => 'yes' ), 2, 200.0 ) ); // 10% of 200
		$this->assertSame( 0.0, PriceCalculator::addon_total( $g, array(), 2, 200.0 ) ); // not engaged
	}

	public function test_fixed_mode_ignores_quantity(): void {
		$g = array( 'version' => 1, 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => 2.0, 'priceMode' => 'fixed', 'condition' => null ) ) );
		$this->assertSame( 2.0, PriceCalculator::addon_total( $g, array( 'n' => '5' ), 2 ) );
	}

	public function test_swatch_valid_by_default_and_options_normalized(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'      => 's',
						'type'    => 'swatch',
						'options' => array(
							array( 'label' => 'Red', 'color' => '#ff0000' ),
							array( 'label' => '', 'color' => '#000000' ),
						),
					),
				),
			)
		);
		$this->assertCount( 1, $out['fields'] );
		$this->assertCount( 1, $out['fields'][0]['options'] );
		$this->assertSame( 'Red', $out['fields'][0]['options'][0]['label'] );
		$this->assertSame( '#ff0000', $out['fields'][0]['options'][0]['color'] );
	}

	public function test_date_valid_by_default_with_constraints(): void {
		$out = FieldSchema::normalize( array( 'fields' => array( array( 'id' => 'd', 'type' => 'date', 'min' => '2026-01-01', 'max' => '2026-12-31' ) ) ) );
		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( '2026-01-01', $out['fields'][0]['min'] );
		$this->assertSame( '2026-12-31', $out['fields'][0]['max'] );
	}

	public function test_filter_can_remove_field_types(): void {
		Monkey\Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return 'clpo_field_types' === $tag
					? array( 'text', 'textarea', 'number', 'checkbox', 'radio', 'select', 'price', 'heading' )
					: $value;
			}
		);
		$out = FieldSchema::normalize( array( 'fields' => array( array( 'id' => 's', 'type' => 'swatch' ), array( 'id' => 't', 'type' => 'text' ) ) ) );
		$this->assertCount( 1, $out['fields'] );
		$this->assertSame( 'text', $out['fields'][0]['type'] );
	}

	public function test_price_mode_defaults_full_and_filter_can_restrict(): void {
		// Default: per_unit is allowed.
		$full = FieldSchema::normalize( array( 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => 1, 'priceMode' => 'per_unit' ) ) ) );
		$this->assertSame( 'per_unit', $full['fields'][0]['priceMode'] );

		// Unknown mode coerces to fixed.
		$bogus = FieldSchema::normalize( array( 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => 1, 'priceMode' => 'bogus' ) ) ) );
		$this->assertSame( 'fixed', $bogus['fields'][0]['priceMode'] );

		// A filter can restrict the mode list.
		Monkey\Functions\when( 'apply_filters' )->alias(
			function ( $tag, $value ) {
				return 'clpo_price_modes' === $tag ? array( 'fixed' ) : $value;
			}
		);
		$restricted = FieldSchema::normalize( array( 'fields' => array( array( 'id' => 'n', 'type' => 'number', 'price' => 1, 'priceMode' => 'per_unit' ) ) ) );
		$this->assertSame( 'fixed', $restricted['fields'][0]['priceMode'] );
	}
}
