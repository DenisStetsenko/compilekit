<?php
/**
 * Plugin Name: CompileKit - Tailwind CSS Compiler
 * Description: Compile Tailwind CSS with a server-side compiler. Provides an admin UI, auto-compilation mode, and environment-aware output.
 * Version: 3.0.2
 * Author: Denis Stetsenko
 * Author URI: https://github.com/DenisStetsenko/
 * Plugin URI: https://github.com/DenisStetsenko/compilekit
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Tested up to: 7.0
 * Requires PHP: 7.4
 * Text Domain: compilekit
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMPILEKIT_VERSION', '3.0.2' );
define( 'COMPILEKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'COMPILEKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'COMPILEKIT_WORKER_THREADS_DEFAULT', 33 );
define( 'COMPILEKIT_CSS_SCAN_MAX_DEPTH', 3 );
define( 'COMPILEKIT_COMPILE_DEBOUNCE', 5 );
define( 'COMPILEKIT_STATUS_TTL', 30 );

// Transient keys.
// NOTE: uninstall.php runs in a separate process without these constants and mirrors these literal strings — keep both in sync.
define( 'COMPILEKIT_TRANSIENT_PREFLIGHT', 'compilekit_cli_preflight_ok' );
define( 'COMPILEKIT_TRANSIENT_CLI_VERSION', 'compilekit_standalone_cli_version' );
define( 'COMPILEKIT_TRANSIENT_NODE_VERSION', 'compilekit_tailwindcss_cli_version' );
define( 'COMPILEKIT_TRANSIENT_STATUS_PREFIX', 'compilekit_compilation_status_' );

require_once COMPILEKIT_PATH . 'includes/class-compilekit-helpers.php';
require_once COMPILEKIT_PATH . 'includes/class-compilekit-compiler.php';
require_once COMPILEKIT_PATH . 'includes/class-compilekit-environment.php';
require_once COMPILEKIT_PATH . 'admin/class-compilekit-admin.php';