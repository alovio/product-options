<?php
declare( strict_types=1 );

namespace APO\Tests\Unit;

use APO\Fields\FieldSchema;

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
}
