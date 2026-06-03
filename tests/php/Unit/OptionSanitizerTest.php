<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\OptionSanitizer;
use Brain\Monkey;

class OptionSanitizerTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'sanitize_text_field' )->returnArg( 1 );
		Monkey\Functions\when( 'sanitize_textarea_field' )->returnArg( 1 );
	}

	private function group( array $fields ): array {
		return array( 'version' => 1, 'fields' => $fields );
	}

	public function test_text_kept_and_empty_dropped(): void {
		$g = $this->group(
			array(
				array( 'id' => 'a', 'type' => 'text' ),
				array( 'id' => 'b', 'type' => 'text' ),
			)
		);
		$out = OptionSanitizer::sanitize( $g, array( 'a' => 'hi', 'b' => '' ) );
		$this->assertSame( array( 'a' => 'hi' ), $out );
	}

	public function test_number_cast_to_float(): void {
		$g   = $this->group( array( array( 'id' => 'n', 'type' => 'number' ) ) );
		$out = OptionSanitizer::sanitize( $g, array( 'n' => '12.5' ) );
		$this->assertSame( 12.5, $out['n'] );
	}

	public function test_checkbox_presence_normalized(): void {
		$g = $this->group( array( array( 'id' => 'c', 'type' => 'checkbox' ) ) );
		$this->assertSame( array( 'c' => 'yes' ), OptionSanitizer::sanitize( $g, array( 'c' => '1' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array() ) );
	}

	public function test_invalid_select_dropped_valid_kept(): void {
		$g = $this->group( array( array( 'id' => 's', 'type' => 'select', 'options' => array( 'S', 'M' ) ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 's' => 'XL' ) ) );
		$this->assertSame( array( 's' => 'M' ), OptionSanitizer::sanitize( $g, array( 's' => 'M' ) ) );
	}

	public function test_date_value_kept(): void {
		$g = $this->group( array( array( 'id' => 'd', 'type' => 'date' ) ) );
		$this->assertSame( array( 'd' => '2026-06-20' ), OptionSanitizer::sanitize( $g, array( 'd' => '2026-06-20' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'd' => '' ) ) );
	}

	public function test_swatch_value_validated_against_labels(): void {
		$g = $this->group( array( array( 'id' => 'sw', 'type' => 'swatch', 'options' => array( array( 'label' => 'Red', 'color' => '#f00' ), array( 'label' => 'Blue', 'color' => '#00f' ) ) ) ) );
		$this->assertSame( array( 'sw' => 'Blue' ), OptionSanitizer::sanitize( $g, array( 'sw' => 'Blue' ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'sw' => 'Purple' ) ) );
	}

	public function test_price_type_has_no_user_value(): void {
		$g = $this->group( array( array( 'id' => 'p', 'type' => 'price', 'price' => 5.0 ) ) );
		$this->assertSame( array(), OptionSanitizer::sanitize( $g, array( 'p' => 'whatever' ) ) );
	}
}
