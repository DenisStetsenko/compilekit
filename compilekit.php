<?php
/**
 * Plugin Name: CompileKit for Tailwind CSS
 * Description: Integrates Tailwind CSS Standalone CLI with WordPress for streamlined builds and asset compilation.
 * Version: 2.1.0
 * Author: Denis Stetsenko
 * Author URI: https://github.com/DenisStetsenko/
 * Plugin URI: https://github.com/DenisStetsenko/compilekit
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Tested up to: 6.8
 * Requires PHP: 8.0
 * Text Domain: compilekit
 */


/**
 * Download Tailwind standalone CLI
 */
function ck_download_tailwind_cli() {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();

	$base_dir = plugin_dir_path( __FILE__ ) . 'bin/';
	$dest     = $base_dir . 'tailwindcss';

	if ( $wp_filesystem->is_file( $dest ) ) {
		return __( 'Tailwind standalone CLI already exists.', 'compilekit' );
	}

	if ( ! $wp_filesystem->is_dir( $base_dir ) && ! $wp_filesystem->mkdir( $base_dir, FS_CHMOD_DIR ) ) {
		return __( 'Failed to create directory for Tailwind standalone CLI', 'compilekit' );
	}
	
	$os   = PHP_OS_FAMILY;
	$arch = php_uname( 'm' );

	if ( $os === 'Darwin' ) {
		$filename = $arch === 'arm64' ? 'tailwindcss-macos-arm64' : 'tailwindcss-macos-x64';
	} elseif ( $os === 'Linux' ) {
		$filename = ( $arch === 'aarch64' || $arch === 'arm64' ) ? 'tailwindcss-linux-arm64' : 'tailwindcss-linux-x64';
	} else {
		return __( 'Unsupported OS for Tailwind standalone CLI', 'compilekit' );
	}

	$url      = 'https://github.com/tailwindlabs/tailwindcss/releases/latest/download/' . $filename;
	$response = wp_remote_get( $url );

	if ( is_wp_error( $response ) || wp_remote_retrieve_response_code( $response ) !== 200 ) {
		return __( 'Tailwind standalone CLI download failed.', 'compilekit' );
	}

	$binary = wp_remote_retrieve_body( $response );
	if ( ! $wp_filesystem->put_contents( $dest, $binary, FS_CHMOD_FILE ) ) {
		return __( 'Failed to save Tailwind standalone CLI', 'compilekit' );
	}

	// Set permissions
	$wp_filesystem->chmod( $dest, 0755 );

	return __( 'The latest version of Tailwind Standalone CLI was installed successfully.', 'compilekit' );
}


/**
 * Get binary CLI path
 * https://tailwindcss.com/blog/standalone-cli
 */
function ck_get_binary_path() : string {
	return plugin_dir_path( __FILE__ ) . 'bin/tailwindcss';
}


/**
 * Small helper to display notices
 * @return string
 */
function ck_compiler_admin_notice( $message, $type = 'error' ) {
	if ( ! is_admin() ) {
		return '';
	}
	
	return '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}


/**
 * Run Compiler
 * @return void
 */
function ck_run_compiler() {
	$input  = get_option( 'ck_input_path', '' );
	$output = get_option( 'ck_output_path', '' );
	$flags  = get_option( 'ck_additional_flags', '--minify' );

	if ( ! $input ) {
		echo wp_kses_post( ck_compiler_admin_notice( __( 'Input path not set.', 'compilekit' ) ) );
		return;
	}

	if ( ! $output ) {
		echo wp_kses_post( ck_compiler_admin_notice( __( 'Output path not set.', 'compilekit' ) ) );
		return;
	}

	$theme_dir   = get_stylesheet_directory();
	$input_path  = realpath( $theme_dir . '/' . ltrim( trim( $input ), '/' ) );
	$output_path = $theme_dir . '/' . ltrim( trim( $output ), '/' );

	if ( ! $input_path || ! str_starts_with( $input_path, realpath( $theme_dir ) ) ) {
		echo wp_kses_post( ck_compiler_admin_notice( __( 'Invalid input path.', 'compilekit' ) ) );
		return;
	}

	$binary = ck_get_binary_path();

	if ( ! file_exists( $binary ) || ! is_executable( $binary ) ) {
		echo wp_kses_post( ck_compiler_admin_notice( __( 'Tailwind binary file not exists or not executable.', 'compilekit' ) ) );
		return;
	}

	if ( ! file_exists( $input_path ) ) {
		echo wp_kses_post( ck_compiler_admin_notice( sprintf(
					/* translators: %s: input filename.css */
					__( 'Tailwind %s file is broken or does not exist.', 'compilekit' ),
					basename( $input_path )
		) ) );
		return;
	}
	
	// run compiler command
	$cwd = get_stylesheet_directory();
	$cmd = escapeshellarg( $binary ) .
				' --input ' . escapeshellarg( $input_path ) .
				' --output ' . escapeshellarg( $output_path ) .
				' --cwd ' . escapeshellarg( $cwd ) .
				' ' . escapeshellarg( $flags ) . ' 2>&1';
	$output_lines = [];
	
	if ( function_exists( 'exec' ) ) {
		exec( $cmd, $output_lines, $exit_code );
		
		set_transient( 'ck_compile_notice', $exit_code, 30 );
		
		if ( $exit_code === 0 ) {
			$success_notice = __( 'Tailwind CLI compiled styles successfully!', 'compilekit' );
			set_transient( 'ck_compile_notice', $success_notice, 30 );
			echo wp_kses_post( ck_compiler_admin_notice( $success_notice, 'success' ) );
		}
		else {
			$error_notice = sprintf(
				/* translators: %s: list of errors */
				__( 'Compilation failed: %s', 'compilekit' ),
				implode( ' | ', $output_lines )
			);
			set_transient( 'ck_compile_notice', $error_notice, 30 );
			echo wp_kses_post( ck_compiler_admin_notice( $error_notice ) );
		}
		
	} else {
		echo wp_kses_post( ck_compiler_admin_notice( __( 'PHP exec() function not exists or disabled.', 'compilekit' ) ) );
	}
	
}


/**
 * Actions on init()
 * This runs exec() command on every frontend page load when enabled - should only be used during development, not production.
 */
add_action('init', function () {
	// 1. Compile styles on front end page reload.
	if ( is_admin() ) {
		return;
	}

	// 2. Require admin user to trigger compile
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
		return;
	}
	
	// 3. check if checkbox it enabled
	$enabled = get_option( 'ck_compile_on_reload', false );
	if ( ! $enabled ) {
		return;
	}
	
	// 4. Run compiler
	ck_run_compiler();
});


/**
 * Render wp-admin menu page
 * @return void
 */
add_action( 'admin_menu', function () {

	add_menu_page(
		'CompileKit',
		'CompileKit',
		'manage_options',
		'compilekit',
		'ck_render_page',
		plugin_dir_url( __FILE__ ) . 'assets/icon.png'
	);

	add_submenu_page(
		'compilekit',
		'Updates',
		'Updates',
		'manage_options',
		'compilekit-updates',
		'ck_render_updates_page'
	);
} );


/**
 * Main Tailwind CLI Page
 * @return void
 */
function ck_render_page() {
	$input             = get_option( 'ck_input_path', '' );
	$output            = get_option( 'ck_output_path', '' );
	$flags             = get_option( 'ck_additional_flags', '--minify' );
	$compile_on_reload = get_option( 'ck_compile_on_reload', false );
	$cli_installed     = file_exists( ck_get_binary_path() );
	
	
	// STEP 1: Manual CLI download trigger
	if ( isset( $_POST['ck_download_cli'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'ck_settings_action' );
		$message = ck_download_tailwind_cli();

		echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
		$cli_installed = file_exists( ck_get_binary_path() ); // recheck
	}
	
	
	// STEP 2: Save settings
	if ( isset( $_POST['ck_save_settings'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'ck_settings_action' );

		$input             = isset( $_POST['ck_input'] ) 	? sanitize_text_field( wp_unslash( $_POST['ck_input'] ) )  : '';
		$output            = isset( $_POST['ck_output'] ) ? sanitize_text_field( wp_unslash( $_POST['ck_output'] ) ) : '';
		$flags             = isset( $_POST['ck_flags'] ) 	? sanitize_text_field( wp_unslash( $_POST['ck_flags'] ) )  : '--minify';
		$compile_on_reload = isset( $_POST['ck_compile_on_reload'] ) ? 1 : 0;

		update_option( 'ck_input_path', $input );
		update_option( 'ck_output_path', $output );
		update_option( 'ck_additional_flags', $flags );
		update_option( 'ck_compile_on_reload', $compile_on_reload );

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'compilekit' ) . '</p></div>';
	}
	
	// STEP 2.1: Manually compile
	if ( isset( $_POST['ck_compile_now'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'ck_settings_action' );
		
		ck_run_compiler();
	}

	if ( $compile_on_reload ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Warning: Compile on reload is enabled. Make sure to disable it when done.', 'compilekit' ) . '</p></div>';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e('CompileKit Settings', 'compilekit') ?></h1>
		<p><?php esc_html_e('Integrates Tailwind CSS Standalone CLI with WordPress for streamlined builds and asset compilation.', 'compilekit') ?></p>

		<form method="post">
			<?php wp_nonce_field('ck_settings_action'); ?>
			<table class="form-table">
				<?php if ( ! $cli_installed ) : ?>
					<h3><?php esc_html_e('Step 1:', 'compilekit') ?>
						<span style="font-weight: 400"><?php esc_html_e('Download Tailwind CSS CLI binary', 'compilekit') ?></span>
					</h3>
					<?php submit_button( esc_html__( 'Download Tailwind CLI', 'compilekit' ), 'primary', 'ck_download_cli' ); ?>
				<?php else : ?>
					<tr>
						<th scope="row">
							<label for="ck_input" style="white-space: nowrap">
								<?php
								echo wp_kses_post( sprintf(
									/* translators: %1$s is the main label, %2$s is the additional context in parentheses */
									__( '%1$s <span style="font-weight: normal">%2$s</span>', 'compilekit' ),
									esc_html( 'Input CSS Path' ),
									esc_html( '(relative to theme)' )
								) );
								?>
							</label>
						</th>
						<td><input type="text" name="ck_input" id="ck_input" value="<?php echo esc_attr($input); ?>"
											 class="regular-text" placeholder="./assets/styles/src/input.css" required></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="ck_output" style="white-space: nowrap">
								<?php
								echo wp_kses_post( sprintf(
									/* translators: %1$s is the main label, %2$s is the additional context in parentheses */
									__( '%1$s <span style="font-weight: normal">%2$s</span>', 'compilekit' ),
									esc_html( 'Output CSS Path' ),
									esc_html( '(relative to theme)' )
								) );
								?>
							</label>
						</th>
						<td><input type="text" name="ck_output" id="ck_output" value="<?php echo esc_attr($output); ?>"
											 class="regular-text" placeholder="./assets/styles/css/output.css" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="ck_flags"><?php esc_html_e('Compiling Mode', 'compilekit') ?></label></th>
						<td>
							<label>
								<input type="radio" name="ck_flags" value="--minify" <?php checked($flags, '--minify'); ?>>
								<?php esc_html_e('Compressed', 'compilekit') ?>
							</label>
							<span style="padding: 0 10px"></span>
							<label>
								<input type="radio" name="ck_flags" value="--optimize" <?php checked($flags, '--optimize'); ?>>
								<?php esc_html_e('Expanded', 'compilekit') ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e('Always Recompile', 'compilekit') ?></th>
						<td><label><input type="checkbox" name="ck_compile_on_reload" <?php checked($compile_on_reload); ?>>
								<?php esc_html_e('Run Tailwind CLI compiler on every page reload', 'compilekit') ?></label></td>
					</tr>
				<?php endif; ?>
			</table>
			
			<?php if ( $cli_installed ) : ?>
				<p class="submit" style="display: flex; align-items: center; gap: 16px;">
					<?php submit_button( esc_html__( 'Save Settings', 'compilekit' ), 'primary', 'ck_save_settings', false ); ?>
					<?php submit_button( esc_html__( 'Compile CSS Manually', 'compilekit' ), 'secondary', 'ck_compile_now', false ); ?>
				</p>
			<?php endif; ?>

		</form>

	</div>
	<?php
}


/**
 * Sub page for updates
 * @return void
 */
function ck_render_updates_page() {
	$current_version 	= get_transient( 'ck_current_version' );
	$latest_version  	= get_transient( 'ck_latest_version' );
	$na_notice				= __( 'Not available', 'compilekit' );

	if ( false === $current_version ) {
		$current_version = $na_notice;
	}
	if ( false === $latest_version ) {
		$latest_version = $na_notice;
	}

	$binary 			= ck_get_binary_path();
	$get_version 	= [];

	if ( isset( $_POST['ck_check_updates'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'ck_update_action' );
		
		$get_version 			= ck_check_version($binary);
		$notice 					= $get_version['notices'];
		$current_version 	= $get_version['current_version'] ?: $na_notice;
		$latest_version 	= $get_version['latest_version'] 	?: $na_notice;

		if ( $notice ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}

		if ( empty( $get_version ) || $get_version['current_version'] === '' ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Binary missing. Please reinstall CLI.', 'compilekit' ) . '</p></div>';
		}
	}

	if ( isset( $_POST['ck_reinstall_cli'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'ck_update_action' );

		// Delete and reinstall
		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		WP_Filesystem();

		$bin_dir = plugin_dir_path( __FILE__ ) . 'bin/';
		if ( $wp_filesystem->is_dir( $bin_dir ) ) {
			$wp_filesystem->delete( $bin_dir, true );
		}

		$message = ck_download_tailwind_cli();
		if ( $message ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $message ) . '</p></div>';
		}

		$get_version     = ck_check_version( $binary );
		$current_version = $get_version['current_version'] ?: $na_notice;
		$latest_version  = $get_version['latest_version']  ?: $na_notice;
	}
	
	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CompileKit Updates', 'compilekit' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'ck_update_action' ); ?>
			<table class="form-table">
				<tr>
					<th><?php esc_html_e( 'Current Version', 'compilekit' ); ?></th>
					<td><code><?php echo esc_html( $current_version ?: $na_notice ); ?></code></td>
				</tr>
				<tr>
					<th><?php esc_html_e( 'Latest Available Version', 'compilekit' ); ?></th>
					<td><code><?php echo esc_html( $latest_version ?: $na_notice ); ?></code></td>
				</tr>
			</table>
			<p class="submit" style="display: flex; align-items: center; gap: 16px;">
				<?php submit_button( __( 'Check for Updates', 'compilekit' ), 'primary', 'ck_check_updates', false ); ?>
				<?php submit_button( __( 'Reinstall Tailwind CLI', 'compilekit' ), 'secondary', 'ck_reinstall_cli', false ); ?>
			</p>
		</form>
	</div>
	<?php
}


/**
 * Check CLI version
 * @param $binary
 *
 * @return array
 */
function ck_check_version( $binary ) {
	$notice = $current_version = $latest_version = '';
	
	// Step 1: Check current version
	if ( file_exists( $binary ) && is_executable( $binary ) ) {
		// run compiler command
		$cwd = get_stylesheet_directory();
		$cmd = escapeshellarg( $binary ) .
						' --cwd ' . escapeshellarg( $cwd ) .
						' --version 2>&1';
		$output_lines = [];

		if ( function_exists( 'exec' ) ) {
			exec( $cmd, $output_lines, $exit_code );

			if ( $exit_code !== 0 ) {
				$error_notice = sprintf(
				/* translators: %s: list of errors */
						__( 'Error: %s', 'compilekit' ),
						implode( ' | ', $output_lines )
				);
				$notice = $error_notice;
			} else {
				$notice = __( 'Successfully fetched Tailwind CLI version info.', 'compilekit' );
			}

		} else {
			$notice = __( 'PHP exec function not exists or disabled.', 'compilekit' );
		}

		if ( ! empty( $output_lines[0] ) ) {
			$current_version = sanitize_text_field( $output_lines[0] );
		}

	}
	
	
	// Step 2: Check the latest version
	$response = wp_remote_get( 'https://api.github.com/repos/tailwindlabs/tailwindcss/releases/latest', [
		'user-agent' => 'WordPress/compilekit',
		'timeout'    => 15,
	]);

	$latest_version_raw = '';

	if ( ! is_wp_error( $response ) && wp_remote_retrieve_response_code( $response ) === 200 ) {
		try {
			$body = json_decode( wp_remote_retrieve_body( $response ), true, 512, JSON_THROW_ON_ERROR );
			if ( isset( $body['tag_name'] ) ) {
				$latest_version_raw = ltrim( strtolower( $body['tag_name'] ), 'v' ); // remove leading "v"
				$latest_version = sprintf(
					/* translators: %1$s: version, %2$s for date */
					__( '≈ tailwindcss %1$s released on %2$s', 'compilekit' ),
					sanitize_text_field($body['tag_name']),
					gmdate( 'M j, Y', strtotime( $body['published_at'] ) )
				);
			}
		} catch ( JsonException ) {
			$latest_version = __( 'Failed to parse latest version data.', 'compilekit' );
		}
	} else {
		$latest_version = __( 'Failed to retrieve latest version', 'compilekit' );
	}

	// Compare current vs latest
	if ( $current_version && $latest_version_raw ) {
		if ( preg_match( '/\d+(\.\d+){1,2}/', $current_version, $matches ) ) {
			$current_version_raw = $matches[0];
		} else {
			$current_version_raw = '';
		}
		if ( version_compare( $current_version_raw, $latest_version_raw, '>=' ) ) {
			$notice = __( 'You are running the latest Tailwind CSS CLI version. No updates needed. ', 'compilekit' );
		}
	}
	
	set_transient( 'ck_current_version', $current_version, MONTH_IN_SECONDS );
	set_transient( 'ck_latest_version', $latest_version, MONTH_IN_SECONDS );
	
	return [
		'notices' 				=> $notice,
		'current_version' => $current_version,
		'latest_version' 	=> $latest_version,
	];
}


/**
 * Display notification on admin bar when compile on reload is enabled
 */
add_action( 'admin_bar_menu', function ( $wp_admin_bar ) {
	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || is_admin() ) {
		return;
	}
	
	if ( ! get_option( 'ck_compile_on_reload', false ) ) {
		return;
	}
	
	// Run compiler and capture result
	$message = get_transient( 'ck_compile_notice' );
	
	if ( str_contains( $message, 'successfully' ) ) {
		$title = esc_html__( 'CompileKit is running', 'compilekit' );
	} elseif ( str_contains( $message, 'failed' ) ) {
		$title = esc_html__( 'CompileKit: Compile Error!', 'compilekit' );
	} else {
		$title = esc_html__( 'CompileKit: Setup Error', 'compilekit' );
	}
	
	$icon_url = plugin_dir_url( __FILE__ ) . 'assets/icon.png';
	$title    = '<img src="' . esc_url( $icon_url ) . '" style="display: inline;margin-bottom: 2px;"> ' . esc_html( $title );
	
	$wp_admin_bar->add_node( [
		'id'    => 'ck-compile-status',
		'title' => $title,
		'meta'  => [
			'class' => 'ck-compile-status',
			'style' => 'color:#fff; font-weight: 600;'
		],
	] );
	
	delete_transient( 'ck_compile_notice' ); // finally clear
	
}, 100 );