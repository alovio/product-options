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
}
