<?php
declare( strict_types=1 );

namespace APO\Tests\Unit;

use APO\Cart\Validator;

class ValidatorTest extends TestCase {

	private function group( array $fields ): array {
		return array( 'version' => 1, 'fields' => $fields );
	}

	public function test_required_empty_field_errors(): void {
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'Name', 'required' => true, 'condition' => null ) ) );
		$this->assertCount( 1, Validator::validate( $g, array() ) );
	}

	public function test_required_but_inactive_is_skipped(): void {
		$g = $this->group(
			array(
				array( 'id' => 'a', 'type' => 'checkbox', 'label' => 'Engrave?', 'condition' => null ),
				array(
					'id'        => 'b',
					'type'      => 'text',
					'label'     => 'Text',
					'required'  => true,
					'condition' => array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ),
				),
			)
		);
		// 'a' not 'yes' => field b hidden => not required.
		$this->assertSame( array(), Validator::validate( $g, array( 'a' => 'no' ) ) );
	}

	public function test_require_action_condition_enforces_required(): void {
		$g = $this->group(
			array(
				array( 'id' => 'a', 'type' => 'checkbox', 'label' => 'Gift?', 'condition' => null ),
				array(
					'id'        => 'b',
					'type'      => 'text',
					'label'     => 'Message',
					'condition' => array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes', 'action' => 'require' ),
				),
			)
		);
		$this->assertCount( 1, Validator::validate( $g, array( 'a' => 'yes' ) ) ); // required now, empty -> error
		$this->assertSame( array(), Validator::validate( $g, array( 'a' => 'no' ) ) ); // not required
	}

	public function test_number_must_be_numeric(): void {
		$g = $this->group( array( array( 'id' => 'n', 'type' => 'number', 'label' => 'Qty', 'condition' => null ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'n' => 'abc' ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'n' => '12' ) ) );
	}

	public function test_invalid_select_option_errors(): void {
		$g = $this->group( array( array( 'id' => 's', 'type' => 'select', 'label' => 'Size', 'options' => array( 'S', 'M' ), 'condition' => null ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 's' => 'XL' ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 's' => 'M' ) ) );
	}

	public function test_happy_path_no_errors(): void {
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'Name', 'required' => true, 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'a' => 'Tahir' ) ) );
	}
}
