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

		// Subsystems are wired here in later tasks
		// (Admin\RestController, Admin\BuilderAssets, Frontend\*, Cart\CartIntegration).
	}
}
