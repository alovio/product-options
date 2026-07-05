<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Groups\GroupRepository;

final class GroupRepositoryTest extends TestCase {

	public function test_normalize_assignment_defaults_and_clamps(): void {
		$a = GroupRepository::normalize_assignment( array( 'mode' => 'bogus', 'ids' => array( '3', -1, 'x' ) ) );
		$this->assertSame( array( 'mode' => 'all', 'ids' => array() ), $a );

		$b = GroupRepository::normalize_assignment( array( 'mode' => 'products', 'ids' => array( '3', 7, 7 ) ) );
		$this->assertSame( array( 'mode' => 'products', 'ids' => array( 3, 7 ) ), $b );
	}

	public function test_normalize_assignment_drops_invalid_ids_and_non_arrays(): void {
		$a = GroupRepository::normalize_assignment( 'not-an-array' );
		$this->assertSame( array( 'mode' => 'all', 'ids' => array() ), $a );

		$b = GroupRepository::normalize_assignment( array( 'mode' => 'categories', 'ids' => array( 0, -5, '12', 'abc' ) ) );
		$this->assertSame( array( 'mode' => 'categories', 'ids' => array( 12 ) ), $b );
	}

	public function test_all_mode_always_has_empty_ids(): void {
		$a = GroupRepository::normalize_assignment( array( 'mode' => 'all', 'ids' => array( 1, 2 ) ) );
		$this->assertSame( array( 'mode' => 'all', 'ids' => array() ), $a );
	}

	public function test_group_to_array_shape(): void {
		$g = GroupRepository::to_array(
			12,
			'Gift options',
			'publish',
			array( 'fields' => array() ),
			array( 'mode' => 'all', 'ids' => array() ),
			10
		);
		$this->assertSame( array( 'id', 'title', 'status', 'fields', 'assignment', 'priority' ), array_keys( $g ) );
		$this->assertSame( 12, $g['id'] );
		$this->assertSame( 'Gift options', $g['title'] );
		$this->assertSame( 'publish', $g['status'] );
		$this->assertSame( array(), $g['fields'] );
		$this->assertSame( 10, $g['priority'] );
	}
}
