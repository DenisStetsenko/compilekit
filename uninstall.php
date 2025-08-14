<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'ck_input_path' );
delete_option( 'ck_output_path' );
delete_option( 'ck_additional_flags' );
delete_option( 'ck_compile_on_reload' );
delete_transient( 'ck_compile_notice' );