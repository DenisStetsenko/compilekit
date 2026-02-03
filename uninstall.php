<?php
/**
 * CompileKit Uninstall Handler
 * Removes all plugin data, settings, and files when the plugin is deleted
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove all plugin options from the database
delete_option('compilekit_input_css');
delete_option('compilekit_output_css');
delete_option('compilekit_environment');
delete_option('compilekit_run_on_refresh');
delete_option('compilekit_compiler_mode');
delete_option('compilekit_worker_threads');

// Remove all plugin transients
delete_transient( 'compilekit_standalone_cli_version' );
delete_transient( 'compilekit_tailwindcss_cli_version' );

// Initialize WordPress Filesystem API
global $wp_filesystem;
if ( !function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}
WP_Filesystem();

// Remove the compilekit directory from uploads including node_modules
$compilekit_target_path = wp_upload_dir()['basedir'] . '/compilekit';

if ( $wp_filesystem && $wp_filesystem->is_dir( $compilekit_target_path ) ) {
	$wp_filesystem->delete( $compilekit_target_path, true );
}