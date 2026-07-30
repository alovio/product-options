<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Admin\GroupsRestController;

final class GroupsRestShapeTest extends TestCase {

	private function group( array $overrides = array() ): array {
		return array_merge(
			array(
				'id'         => 7,
				'title'      => 'Gift options',
				'status'     => 'publish',
				'fields'     => array(
					array( 'id' => 'a', 'type' => 'checkbox', 'label' => 'Wrap', 'price' => 8, 'priceMode' => 'fixed' ),
					array( 'id' => 'b', 'type' => 'text', 'label' => 'Note', 'price' => 0, 'priceMode' => 'fixed' ),
					array( 'id' => 'c', 'type' => 'price', 'label' => 'Fee', 'price' => 0, 'priceMode' => 'fixed' ),
				),
				'assignment' => array( 'mode' => 'all', 'ids' => array() ),
				'priority'   => 10,
			),
			$overrides
		);
	}

	public function test_summarize_all_mode(): void {
		$s = GroupsRestController::summarize( $this->group() );
		$this->assertSame( 7, $s['id'] );
		$this->assertSame( 'Gift options', $s['title'] );
		$this->assertSame( 'publish', $s['status'] );
		$this->assertSame( 3, $s['field_count'] );
		$this->assertSame( 2, $s['priced_count'] ); // price>0 (a) + type price (c)
		$this->assertSame( 'All products', $s['assignment_summary'] );
		$this->assertSame( 10, $s['priority'] );
	}

	public function test_priced_count_includes_option_priced_and_formula_fields(): void {
		$s = GroupsRestController::summarize(
			array(
				'fields' => array(
					array( 'id' => 'plain', 'type' => 'select', 'price' => 0, 'options' => array( 'S', 'M' ) ),
					array( 'id' => 'sizes', 'type' => 'select', 'price' => 0, 'options' => array( array( 'label' => '50x70', 'price' => 799 ) ) ),
					array( 'id' => 'calc', 'type' => 'number', 'price' => 0, 'priceMode' => 'formula', 'formula' => '{a}*2' ),
				),
			)
		);
		// 'plain' has neither a field price nor priced options; the other two do.
		$this->assertSame( 2, $s['priced_count'] );
	}

	public function test_summarize_categories_and_products(): void {
		$cats = GroupsRestController::summarize( $this->group( array( 'assignment' => array( 'mode' => 'categories', 'ids' => array( 1, 2, 3 ) ) ) ) );
		$this->assertSame( '3 categories', $cats['assignment_summary'] );

		$one = GroupsRestController::summarize( $this->group( array( 'assignment' => array( 'mode' => 'products', 'ids' => array( 9 ) ) ) ) );
		$this->assertSame( '1 product', $one['assignment_summary'] );
	}
}
