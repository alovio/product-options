<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions\Groups;

defined( 'ABSPATH' ) || exit;

/** Hidden storage CPT for option groups (spec §3.1). */
final class OptionGroupCpt {

	public const TYPE = 'alovio_option_group';

	public function register(): void {
		add_action( 'init', array( $this, 'register_type' ) );
	}

	public function register_type(): void {
		// Deliberate deviation from spec §3.1's "capabilities manage_woocommerce":
		// the CPT is invisible (no UI, no REST) and is only reached through the
		// clpo/v1 REST layer, where every route checks manage_woocommerce.
		register_post_type(
			self::TYPE,
			array(
				'public'          => false,
				'show_ui'         => false,
				'show_in_rest'    => false,
				'supports'        => array( 'title' ),
				'capability_type' => 'post',
				'map_meta_cap'    => true,
			)
		);
	}
}
