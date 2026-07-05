<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Fields\FieldSchema;

class FieldSchemaTest extends TestCase {

	public function test_drops_unknown_field_type(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'evil', 'label' => 'x' ),
					array( 'id' => 'b', 'type' => 'text', 'label' => 'ok' ),
				),
			)
		);
		$this->assertSame( array( 'b' ), array_column( $out['fields'], 'id' ) );
	}

	public function test_condition_referencing_missing_field_is_stripped(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'        => 'b',
						'type'      => 'text',
						'label'     => 'ok',
						'condition' => array( 'field' => 'ghost', 'operator' => 'is', 'value' => 'y', 'action' => 'show' ),
					),
				),
			)
		);
		$this->assertNull( $out['fields'][0]['condition'] );
	}

	public function test_valid_condition_is_kept(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'checkbox', 'label' => 'Want it?' ),
					array(
						'id'        => 'b',
						'type'      => 'text',
						'label'     => 'Details',
						'condition' => array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
					),
				),
			)
		);
		$this->assertSame( 'a', $out['fields'][1]['condition']['field'] );
	}

	public function test_self_referencing_condition_is_stripped(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array(
						'id'        => 'a',
						'type'      => 'text',
						'condition' => array( 'field' => 'a', 'operator' => 'is', 'value' => 'x', 'action' => 'show' ),
					),
				),
			)
		);
		$this->assertNull( $out['fields'][0]['condition'] );
	}

	public function test_negative_price_coerced_to_zero(): void {
		$out = FieldSchema::normalize(
			array( 'fields' => array( array( 'id' => 'a', 'type' => 'price', 'price' => -10 ) ) )
		);
		$this->assertSame( 0.0, $out['fields'][0]['price'] );
	}

	public function test_duplicate_ids_deduped(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'text' ),
					array( 'id' => 'a', 'type' => 'text' ),
				),
			)
		);
		$this->assertCount( 1, $out['fields'] );
	}

	public function test_normalizes_free_extras(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 't', 'type' => 'text', 'placeholder' => 'Type here', 'description' => 'help', 'default' => 'hi', 'maxLength' => 10 ),
					array( 'id' => 'n', 'type' => 'number', 'min' => 1, 'max' => 5, 'step' => 0.5 ),
					array( 'id' => 'h', 'type' => 'heading', 'label' => 'Section' ),
				),
			)
		);
		$t = $out['fields'][0];
		$this->assertSame( 'Type here', $t['placeholder'] );
		$this->assertSame( 'help', $t['description'] );
		$this->assertSame( 'hi', $t['default'] );
		$this->assertSame( 10, $t['maxLength'] );

		$n = $out['fields'][1];
		$this->assertSame( '1', $n['min'] );
		$this->assertSame( '5', $n['max'] );
		$this->assertSame( '0.5', $n['step'] );

		$this->assertSame( 'heading', $out['fields'][2]['type'] );
	}

	public function test_invalid_operator_and_action_defaulted(): void {
		$out = FieldSchema::normalize(
			array(
				'fields' => array(
					array( 'id' => 'a', 'type' => 'checkbox' ),
					array(
						'id'        => 'b',
						'type'      => 'text',
						'condition' => array( 'field' => 'a', 'operator' => 'bogus', 'value' => 'y', 'action' => 'bogus' ),
					),
				),
			)
		);
		$this->assertSame( 'is', $out['fields'][1]['condition']['operator'] );
		$this->assertSame( 'show', $out['fields'][1]['condition']['action'] );
	}

	public function test_per_char_only_for_text_types(): void {
		$out = \CoreLabs\ProductOptions\Fields\FieldSchema::normalize( array( 'fields' => array(
			array( 'id' => 't', 'type' => 'text', 'price' => 1, 'priceMode' => 'per_char' ),
			array( 'id' => 'c', 'type' => 'checkbox', 'price' => 1, 'priceMode' => 'per_char' ),
		) ) );
		$this->assertSame( 'per_char', $out['fields'][0]['priceMode'] );
		$this->assertSame( 'fixed', $out['fields'][1]['priceMode'] );
	}
}
