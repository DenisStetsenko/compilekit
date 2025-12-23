<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'compilekit_input_path' );
delete_option( 'compilekit_output_path' );
delete_option( 'compilekit_additional_flags' );
delete_option( 'compilekit_compile_on_reload' );
delete_transient( 'compilekit_compile_notice' );

// Remove uploaded compilekit folder recursively
if ( ! function_exists( 'WP_Filesystem' ) ) {
	require_once ABSPATH . 'wp-admin/includes/file.php';
}

WP_Filesystem();

global $wp_filesystem;

$compilekit_upload_dir = wp_upload_dir();
$compilekit_dir        = trailingslashit( $compilekit_upload_dir['basedir'] ) . 'compilekit/';

if ( $wp_filesystem && $wp_filesystem->is_dir( $compilekit_dir ) ) {
	$wp_filesystem->delete( $compilekit_dir, true ); // recursive delete
}