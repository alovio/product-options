<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Tests\Unit;

use CoreLabs\ProductOptions\Cart\CartIntegration;
use CoreLabs\ProductOptions\Cart\PriceCalculator;
use Brain\Monkey;

final class CartIntegrationTest extends TestCase {

	private const CHIP = '<span class="apo-cart-fee">+%s</span>';

	protected function setUp(): void {
		parent::setUp();
		Monkey\Functions\when( 'esc_html' )->returnArg( 1 );
	}

	public function test_with_price_appends_amount_chip_to_value(): void {
		// No wc_price in the unit context -> number_format fallback.
		$this->assertSame( 'Oak ' . sprintf( self::CHIP, '5.00' ), CartIntegration::with_price( 'Oak', 5.0 ) );
		$this->assertSame( 'x.png, y.webp ' . sprintf( self::CHIP, '2.00' ), CartIntegration::with_price( 'x.png, y.webp', 2.0 ) );
	}

	public function test_with_price_zero_or_negative_leaves_value_untouched(): void {
		$this->assertSame( 'Oak', CartIntegration::with_price( 'Oak', 0.0 ) );
		$this->assertSame( 'Oak', CartIntegration::with_price( 'Oak', -1.0 ) );
	}

	public function test_with_price_surcharge_row_is_a_bare_chip(): void {
		$this->assertSame( sprintf( self::CHIP, '48.00' ), CartIntegration::with_price( '', 48.0 ) );
	}

	public function test_with_price_uses_store_currency_format_when_available(): void {
		Monkey\Functions\when( 'wc_price' )->alias( static fn( $a ) => '<span>&#36;' . number_format( (float) $a, 2 ) . '</span>' );
		Monkey\Functions\when( 'wp_strip_all_tags' )->alias( static fn( $s ) => strip_tags( (string) $s ) );
		$this->assertSame( 'Oak ' . sprintf( self::CHIP, '$5.50' ), CartIntegration::with_price( 'Oak', 5.5 ) );
	}

	/** @param mixed ...$args */
	private function call( string $method, ...$args ) {
		$m = new \ReflectionMethod( CartIntegration::class, $method );
		$m->setAccessible( true );
		return $m->invoke( null, ...$args );
	}

	/** The fabric/colour group from the forum report. */
	private function fabric_group(): array {
		$show = static fn( string $value ): array => array( 'field' => 'fabric', 'operator' => 'is', 'value' => $value, 'action' => 'show' );
		return array(
			'id'     => 7,
			'fields' => array(
				array( 'id' => 'fabric', 'type' => 'select', 'condition' => null ),
				array( 'id' => 'gauze_color', 'type' => 'select', 'condition' => $show( 'Double gauze' ) ),
				array( 'id' => 'minky_color', 'type' => 'select', 'condition' => $show( 'Minky' ) ),
			),
		);
	}

	public function test_values_of_conditionally_hidden_fields_never_reach_the_cart_line(): void {
		// Reported: pick Double gauze + a colour, switch to Minky, pick a colour —
		// the stale gauze colour was still submitted and showed up in the cart.
		$submitted = array(
			'fabric'      => 'Minky',
			'gauze_color' => 'Mushroom pattern', // hidden by the switch
			'minky_color' => 'Pink',
		);

		$visible = $this->call( 'visible_only', $this->fabric_group(), $submitted );

		$this->assertSame( array( 'fabric' => 'Minky', 'minky_color' => 'Pink' ), $visible );
		$this->assertArrayNotHasKey( 'gauze_color', $visible );
	}

	public function test_drops_a_whole_chain_when_the_controller_is_itself_hidden(): void {
		$group = array(
			'id'     => 9,
			'fields' => array(
				array( 'id' => 'a', 'type' => 'select', 'condition' => null ),
				array( 'id' => 'b', 'type' => 'select', 'condition' => array( 'field' => 'a', 'operator' => 'is', 'value' => 'yes', 'action' => 'show' ) ),
				array( 'id' => 'c', 'type' => 'select', 'condition' => array( 'field' => 'b', 'operator' => 'is', 'value' => 'go', 'action' => 'show' ) ),
			),
		);

		$visible = $this->call( 'visible_only', $group, array( 'a' => 'no', 'b' => 'go', 'c' => 'deep' ) );

		$this->assertSame( array( 'a' => 'no' ), $visible );
	}

	public function test_dropping_hidden_values_does_not_change_what_is_charged(): void {
		// Price both colours: a pricing regression would surface as money here.
		$group                        = $this->fabric_group();
		$group['fields'][1]['price']  = 4.0; // hidden gauze colour
		$group['fields'][2]['price']  = 6.0; // visible minky colour
		$submitted                    = array( 'fabric' => 'Minky', 'gauze_color' => 'Mushroom pattern', 'minky_color' => 'Pink' );

		$before = PriceCalculator::addon_total( $group, $submitted, 2, 0.0 );
		$after  = PriceCalculator::addon_total( $group, $this->call( 'visible_only', $group, $submitted ), 2, 0.0 );

		$this->assertSame( 6.0, $before ); // the hidden colour was never charged
		$this->assertSame( $before, $after );
	}
}
