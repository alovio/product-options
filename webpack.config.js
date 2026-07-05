/**
 * Two entry points: the admin hub SPA (hub) and the product-page runtime
 * (frontend). Extends the default @wordpress/scripts config so dependency
 * extraction (per-entry *.asset.php) and externals are preserved.
 */
const path = require( 'path' );
const defaultConfig = require( '@wordpress/scripts/config/webpack.config' );

module.exports = {
	...defaultConfig,
	entry: {
		hub: path.resolve( process.cwd(), 'src', 'hub', 'index.js' ),
		frontend: path.resolve( process.cwd(), 'src', 'frontend.js' ),
	},
};
