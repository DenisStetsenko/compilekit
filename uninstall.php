<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'compilekit_input_path' );
delete_option( 'compilekit_output_path' );
delete_option( 'compilekit_additional_flags' );
delete_option( 'compilekit_compile_on_reload' );
delete_transient( 'compilekit_compile_notice' );