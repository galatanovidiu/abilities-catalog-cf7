<?php
/**
 * Plugin Name:       Abilities Catalog — Contact Form 7
 * Plugin URI:        https://github.com/galatanovidiu/abilities-catalog-cf7
 * Description:       Registers Contact Form 7 forms as Abilities API abilities (list, read, create, update, duplicate, delete). An add-on for Abilities Catalog: it works standalone on the core Abilities API, and when the Abilities Catalog MCP server is active it contributes a "forms" domain tool and a setup skill.
 * Version:           0.1.0
 * Requires at least: 7.0
 * Requires PHP:      8.1
 * Requires Plugins:  contact-form-7
 * Author:            Ovidiu Galatan
 * Author URI:        https://github.com/galatanovidiu
 * License:           MIT
 * License URI:       https://opensource.org/license/mit
 * Text Domain:       abilities-catalog-cf7
 *
 * @package AbilitiesCatalogCf7
 */

declare(strict_types=1);

namespace GalatanOvidiu\AbilitiesCatalogCf7;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'ABILITIES_CATALOG_CF7_VERSION', '0.1.0' );
define( 'ABILITIES_CATALOG_CF7_FILE', __FILE__ );
define( 'ABILITIES_CATALOG_CF7_DIR', plugin_dir_path( __FILE__ ) );

/**
 * No-build PSR-4 autoloader for the `GalatanOvidiu\AbilitiesCatalogCf7\` namespace.
 *
 * Maps the namespace root to the `includes/` directory, mirroring the Abilities
 * Catalog's no-build ethos (no Composer step for runtime code). Registered before
 * the bootstrap so the Registry and ability classes load on demand.
 */
spl_autoload_register(
	static function ( string $class_name ): void {
		$prefix = __NAMESPACE__ . '\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}

		$relative = substr( $class_name, strlen( $prefix ) );
		$path     = ABILITIES_CATALOG_CF7_DIR . 'includes/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( ! is_readable( $path ) ) {
			return;
		}

		// phpcs:ignore WordPressVIPMinimum.Files.IncludingFile.UsingVariable -- PSR-4 path built from a plugin constant and an internal class name, not user input.
		require_once $path;
	}
);

add_action(
	'plugins_loaded',
	static function (): void {
		// The CF7 abilities register on the core Abilities API directly, so the
		// add-on works without Abilities Catalog present. A ConditionalAbility stays
		// absent while Contact Form 7 is inactive.
		( new Registry() )->register();

		// The Abilities API ships with WordPress 7.0; without it there is nothing to
		// expose, so the optional MCP integration below has no hooks to attach to.
		if ( ! function_exists( 'wp_register_ability' ) ) {
			return;
		}

		// Plug the CF7 "forms" domain tool and the setup skill into the Abilities
		// Catalog MCP server through its public filters. The filters no-op when the
		// catalog (or its server) is absent, so this stays safe standalone.
		Mcp\Integration::register();
	}
);
