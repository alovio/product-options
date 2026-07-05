<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\CartItemShape;

final class CartItemShapeTest extends TestCase {

	private function group( int $id, string $field_id, string $label ): array {
		return array(
			'id'         => $id,
			'title'      => "G{$id}",
			'status'     => 'publish',
			'fields'     => array(
				array(
					'id'        => $field_id,
					'type'      => 'text',
					'label'     => $label,
					'required'  => true,
					'price'     => 0,
					'priceMode' => 'fixed',
					'condition' => null,
				),
			),
			'assignment' => array( 'mode' => 'all', 'ids' => array() ),
			'priority'   => 10,
		);
	}

	public function test_collect_errors_iterates_all_groups(): void {
		$groups = array(
			$this->group( 1, 'g1_note', 'First note' ),
			$this->group( 2, 'g2_note', 'Second note' ),
		);
		$errors = CartItemShape::collect_errors( $groups, array( 'g1_note' => 'hello' ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'Second note', $errors[0] );
	}

	public function test_collect_errors_empty_when_all_valid(): void {
		$groups = array( $this->group( 1, 'g1_note', 'First note' ) );
		$this->assertSame( array(), CartItemShape::collect_errors( $groups, array( 'g1_note' => 'ok' ) ) );
	}

	public function test_legacy_single_map_becomes_group0_list(): void {
		$legacy = array( 'options' => array( 'a' => 'x' ), 'base_price' => 10.0, 'unique_key' => 'k' );
		$out    = CartItemShape::normalize_apo( $legacy );
		$this->assertCount( 1, $out );
		$this->assertSame( 0, $out[0]['group_id'] );
		$this->assertSame( array( 'a' => 'x' ), $out[0]['options'] );
		$this->assertSame( 10.0, $out[0]['base_price'] );
		$this->assertSame( 0.0, $out[0]['addon_total'] ); // missing -> default 0
		$this->assertSame( 'k', $out[0]['unique_key'] );
	}

	public function test_new_shape_passes_through(): void {
		$list = array(
			array( 'group_id' => 5, 'options' => array(), 'base_price' => 1.0, 'addon_total' => 2.5, 'unique_key' => 'k' ),
		);
		$this->assertSame( $list, CartItemShape::normalize_apo( $list ) );
	}

	public function test_normalize_garbage_returns_empty_list(): void {
		$this->assertSame( array(), CartItemShape::normalize_apo( 'garbage' ) );
		$this->assertSame( array(), CartItemShape::normalize_apo( array() ) );
	}

	public function test_pick_group_for_entry_prefers_id_then_any_for_legacy(): void {
		$groups = array(
			$this->group( 5, 'a', 'A' ),
			$this->group( 9, 'b', 'B' ),
		);
		$this->assertSame( 5, CartItemShape::pick_group( $groups, 5 )['id'] );
		$this->assertSame( 9, CartItemShape::pick_group( $groups, 9 )['id'] );
		$this->assertSame( 5, CartItemShape::pick_group( $groups, 0 )['id'] ); // legacy: first resolved
		$this->assertNull( CartItemShape::pick_group( $groups, 77 ) );          // deleted mid-cart
		$this->assertNull( CartItemShape::pick_group( array(), 0 ) );
	}
}
