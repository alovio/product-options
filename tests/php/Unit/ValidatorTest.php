<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\Validator;

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

	public function test_date_range_validation(): void {
		$g = $this->group( array( array( 'id' => 'd', 'type' => 'date', 'label' => 'Date', 'min' => '2026-06-01', 'max' => '2026-06-30', 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'd' => '2026-06-15' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'd' => '2026-07-15' ) ) ); // after max
		$this->assertCount( 1, Validator::validate( $g, array( 'd' => 'not-a-date' ) ) );
	}

	public function test_happy_path_no_errors(): void {
		$g = $this->group( array( array( 'id' => 'a', 'type' => 'text', 'label' => 'Name', 'required' => true, 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'a' => 'Tahir' ) ) );
	}

	public function test_email_invalid_nonempty_errors(): void {
		$g = array( 'fields' => array( array( 'id' => 'e', 'type' => 'email', 'label' => 'Your email', 'required' => false, 'condition' => null ) ) );
		$errors = Validator::validate( $g, array( 'e' => 'nope' ) );
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( 'Your email', $errors[0] );
		$this->assertSame( array(), Validator::validate( $g, array( 'e' => 'a@b.co' ) ) );
	}

	public function test_time_format_enforced(): void {
		$g = array( 'fields' => array( array( 'id' => 't', 'type' => 'time', 'label' => 'Pickup time', 'required' => false, 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 't' => '09:30' ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 't' => '23:59' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 't' => '24:00' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 't' => '9:30' ) ) );
	}

	public function test_phone_needs_five_digits(): void {
		$g = array( 'fields' => array( array( 'id' => 'p', 'type' => 'phone', 'label' => 'Phone', 'required' => false, 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'p' => '+1 555 00' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'p' => '+1 5' ) ) );
	}

	public function test_url_scheme_required(): void {
		$g = array( 'fields' => array( array( 'id' => 'u', 'type' => 'url', 'label' => 'Link', 'required' => false, 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'u' => 'https://x.test' ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'u' => 'x.test' ) ) );
	}

	public function test_quantity_min_max_enforced(): void {
		$g = array( 'fields' => array( array( 'id' => 'q', 'type' => 'quantity', 'label' => 'Qty', 'required' => false, 'min' => '2', 'max' => '5', 'condition' => null ) ) );
		$this->assertSame( array(), Validator::validate( $g, array( 'q' => 3 ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'q' => 1 ) ) );
		$this->assertCount( 1, Validator::validate( $g, array( 'q' => 9 ) ) );
	}

	public function test_buttons_and_image_swatch_selection_validated(): void {
		$g = array( 'fields' => array(
			array( 'id' => 'b', 'type' => 'buttons', 'label' => 'Style', 'required' => false, 'options' => array( 'Classic' ), 'condition' => null ),
			array( 'id' => 'i', 'type' => 'image_swatch', 'label' => 'Material', 'required' => false, 'options' => array( array( 'label' => 'Oak', 'image' => '' ) ), 'condition' => null ),
		) );
		$this->assertSame( array(), Validator::validate( $g, array( 'b' => 'Classic', 'i' => 'Oak' ) ) );
		$this->assertCount( 2, Validator::validate( $g, array( 'b' => 'X', 'i' => 'Y' ) ) );
	}
}
