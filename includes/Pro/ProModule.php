<?php
declare( strict_types=1 );

namespace APO\Pro;

defined( 'ABSPATH' ) || exit;

/**
 * Pro gate. When Pro is active (the `apo_is_pro` filter — wired to Freemius'
 * can_use_premium_code() once credentials are configured), it lifts the free
 * restrictions: extended operators and multi-condition rules.
 *
 * Building Pro features behind this gate decouples them from Freemius wiring —
 * Pro can be developed/tested now by returning true from `apo_is_pro`.
 */
final class ProModule {

	public static function is_pro(): bool {
		return (bool) apply_filters( 'apo_is_pro', false );
	}

	public function register(): void {
		if ( ! self::is_pro() ) {
			return;
		}
		add_filter( 'apo_allowed_operators', array( $this, 'operators' ) );
		add_filter( 'apo_multi_conditions', '__return_true' );
		add_filter( 'apo_price_modes', array( $this, 'price_modes' ) );
	}

	/**
	 * @param string[] $operators
	 * @return string[]
	 */
	public function operators( $operators ): array {
		return array( 'is', 'is_not', 'contains', 'gt', 'lt' );
	}

	/**
	 * @param string[] $modes
	 * @return string[]
	 */
	public function price_modes( $modes ): array {
		return array( 'fixed', 'per_unit' );
	}
}
