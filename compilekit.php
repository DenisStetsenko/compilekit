<?php
/**
 * Plugin Name: CompileKit - Tailwind CSS Compiler
 * Description: Compile Tailwind CSS with a server-side compiler. Provides an admin UI, auto-compilation mode, and environment-aware output.
 * Version: 3.0.0
 * Author: Denis Stetsenko
 * Author URI: https://github.com/DenisStetsenko/
 * Plugin URI: https://github.com/DenisStetsenko/compilekit
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Tested up to: 6.9
 * Requires PHP: 7.4
 * Text Domain: compilekit
 */
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

define( 'COMPILEKIT_VERSION', '3.0.0' );
define( 'COMPILEKIT_PATH', plugin_dir_path( __FILE__ ) );
define( 'COMPILEKIT_URL', plugin_dir_url( __FILE__ ) );
define( 'COMPILEKIT_WORKER_THREADS_DEFAULT', 33 );

require_once COMPILEKIT_PATH . 'includes/class-compilekit-helpers.php';
require_once COMPILEKIT_PATH . 'includes/class-compilekit-compiler.php';
require_once COMPILEKIT_PATH . 'includes/class-compilekit-environment.php';
require_once COMPILEKIT_PATH . 'admin/class-compilekit-admin.php';