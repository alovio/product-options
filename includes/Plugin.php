<?php
declare( strict_types=1 );

namespace CoreLabs\ProductOptions;

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

		( new \CoreLabs\ProductOptions\Groups\OptionGroupCpt() )->register();
		( new \CoreLabs\ProductOptions\Admin\RestController() )->register();
		( new \CoreLabs\ProductOptions\Admin\BuilderAssets() )->register();
		( new \CoreLabs\ProductOptions\Frontend\ProductFormRenderer() )->register();
		( new \CoreLabs\ProductOptions\Frontend\FrontendAssets() )->register();
		( new \CoreLabs\ProductOptions\Cart\CartIntegration() )->register();
	}
}
