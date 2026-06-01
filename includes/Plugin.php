<?php
declare( strict_types=1 );

namespace APO;

defined( 'ABSPATH' ) || exit;

/**
 * Plugin bootstrap singleton. `boot()` wires subsystems and is idempotent.
 */
final class Plugin {

	private static ?Plugin $instance = null;

	private bool $booted = false;

	public static function instance(): Plugin {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public function boot(): void {
		if ( $this->booted ) {
			return;
		}
		$this->booted = true;

		( new \APO\Pro\ProModule() )->register();
		( new \APO\Admin\RestController() )->register();
		( new \APO\Admin\BuilderAssets() )->register();
		( new \APO\Frontend\ProductFormRenderer() )->register();
		( new \APO\Frontend\FrontendAssets() )->register();
		( new \APO\Cart\CartIntegration() )->register();
	}
}
