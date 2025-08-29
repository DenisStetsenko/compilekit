<?php
/**
 * Plugin Name: CompileKit for Tailwind CSS
 * Description: Integrates Tailwind CSS Standalone CLI with WordPress for streamlined builds and asset compilation.
 * Version: 2.1.4
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
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Download Tailwind Standalone CLI
 *
 * @param bool $force Whether to force download even if file exists
 */
function compilekit_download_tailwind_cli( $force = false ) {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();

	$upload_dir 	= wp_upload_dir();
	$home_dir 		= trailingslashit( $upload_dir['basedir'] ) . 'compilekit/';
	$base_dir 		= trailingslashit( $upload_dir['basedir'] ) . 'compilekit/bin/';
	$dest     		= $base_dir . 'tw';

	// Check if PHP exec() is available
	if ( ! function_exists( 'exec' ) ) {
		return __( 'PHP exec() function is required for this plugin but not available or disabled on the server.', 'compilekit' );
	}

	// Only check if file exists when not forcing reinstall
	if ( ! $force && $wp_filesystem->is_file( $dest ) ) {
		return __( 'Current Tailwind Standalone CLI is the latest version.', 'compilekit' );
	}

	// If forcing and file exists, delete it first
	if ( $force && $wp_filesystem->is_dir( $home_dir ) ) {
		$wp_filesystem->delete( $home_dir, true ); // recursive **/*.* delete
	}

	// Recursively create directories
	if ( ! $wp_filesystem->is_dir( $base_dir ) && ! wp_mkdir_p( $base_dir ) ) {
		return __( 'Failed to create bin/ directory for Tailwind Standalone CLI', 'compilekit' );
	}

	// Check directory permissions
	if ( ! $wp_filesystem->is_writable( $base_dir ) ) {
		return __( 'Directory is not writable. Check permissions.', 'compilekit' );
	}

	$os   = PHP_OS_FAMILY;
	$arch = php_uname( 'm' );

	if ( $os === 'Darwin' ) {
		$filename = $arch === 'arm64' ? 'tailwindcss-macos-arm64' : 'tailwindcss-macos-x64';
	} elseif ( $os === 'Linux' ) {
		$filename = ( $arch === 'aarch64' || $arch === 'arm64' ) ? 'tailwindcss-linux-arm64' : 'tailwindcss-linux-x64';
	} else {
		return __( 'Unsupported OS for Tailwind Standalone CLI', 'compilekit' );
	}

	$url = 'https://github.com/tailwindlabs/tailwindcss/releases/latest/download/' . $filename;

	// NEW: Create temporary file for streaming download using WP_Filesystem
	$temp_file = wp_tempnam();
	if ( ! $temp_file ) {
		return __( 'Failed to create temporary file for download', 'compilekit' );
	}

	// NEW: Stream download with increased timeout to prevent timeouts on large files
	$response = wp_remote_get( $url, [
			'timeout' 		=> 300, 				// NEW: Increased from default 5 seconds to 5 minutes
			'stream' 			=> true, 				// NEW: Stream to file instead of loading into memory
			'filename' 		=> $temp_file, 	// NEW: Stream directly to temp file
			'user-agent' 	=> 'WordPress/compilekit',
	] );

	$response_code 	= wp_remote_retrieve_response_code( $response );
	$headers        = wp_remote_retrieve_headers($response);
	$rate_limit     = isset($headers['x-ratelimit-limit']) ? (int) $headers['x-ratelimit-limit'] : null;
	$rate_reset     = isset($headers['x-ratelimit-reset']) ? gmdate('F j, Y H:i:s', (int) $headers['x-ratelimit-reset']) : null;

	$delete_temp_file = function() use ( $wp_filesystem, $temp_file ) {
		if ( $wp_filesystem->exists( $temp_file ) ) {
			$wp_filesystem->delete( $temp_file );
		}
	};

	if ( is_wp_error( $response ) ) {
		$delete_temp_file();
		return __( 'Tailwind Standalone CLI download failed.', 'compilekit' );
	}

	if ( $response_code === 403 ) {
		$delete_temp_file();
		return sprintf(
		/* translators: %1$s: rate limit, %2$s: rate reset */
				__('Error: GitHub API rate limit exceeded. %1$s', 'compilekit'),
				$rate_limit && $rate_reset ? sprintf(
				/* translators: %1$d: rate limit, %2$s: rate reset time */
						__('Limit: %1$d requests. Resets at %2$s UTC.', 'compilekit'),
						$rate_limit,
						$rate_reset
				) : __('Please try again later.', 'compilekit')
		);
	}

	if ( $response_code === 404 ) {
		$delete_temp_file();
		return __( 'Error: GitHub Repository Not Found.', 'compilekit' );
	}

	if ( wp_remote_retrieve_response_code( $response ) !== 200 ) {
		$delete_temp_file();
		return __( 'Error: Tailwind Standalone CLI download failed.', 'compilekit' );
	}

	// NEW: Verify temp file exists and has content using WP_Filesystem
	if ( ! $wp_filesystem->exists( $temp_file ) || $wp_filesystem->size( $temp_file ) === 0 ) {
		$delete_temp_file();
		return __( 'Error: Downloaded file is empty or corrupt.', 'compilekit' );
	}

	// NEW: Move temp file to final destination using WP_Filesystem
	if ( ! $wp_filesystem->move( $temp_file, $dest ) ) {
		$delete_temp_file();
		return __( 'Failed to save Tailwind Standalone CLI', 'compilekit' );
	}

	// Set permissions
	$wp_filesystem->chmod( $dest, 0755 );

	// NEW: Verify final 'tw' binary file is accessible using WP_Filesystem
	if ( ! $wp_filesystem->is_readable( $dest ) || $wp_filesystem->size( $dest ) === 0 ) {
		return __( 'Warning: Tailwind CLI was downloaded successfully but may not be readable. Check file permissions.', 'compilekit' );
	}

	// PHASE 2: install NPM Modules (fallback) //
	$output = [];
	putenv( 'PATH=/usr/local/bin:' . getenv( 'PATH' ) );

	exec( 'node --version 2>&1', $output, $exit_code );

	if ( $exit_code !== 0 ) {
		return __( 'Node.js is not installed or not available in PATH.', 'compilekit' );
	}

	// Create package.json if missing
	$package_json = $base_dir . 'package.json';
	if ( ! $wp_filesystem->exists( $package_json ) ) {
		$package_content = json_encode( [
				'name'         		=> 'compilekit-tailwind',
				'version'      		=> '1.0.0',
				'private' 				=> true,
				'license'					=> 'ISC',
				'devDependencies' => [],
		], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT );
		$wp_filesystem->put_contents( $package_json, $package_content );
	}

	$old_cwd = getcwd();
	if ( $old_cwd === false ) {
		return __( 'Failed to get current directory.', 'compilekit' ) ;
	}

	// Change to the CompileKit directory and install NPM
	if ( ! chdir( $base_dir ) ) {
		return __( 'Failed to change to CompileKit directory.', 'compilekit' );
	}

	// run NPM install
	$exec_output = [];
	$install_cmd = 'npm install -D tailwindcss @tailwindcss/cli @tailwindcss/forms tailwind-clamp 2>&1';
	exec( $install_cmd, $exec_output, $install_exit_code );

	// Restore original directory
	chdir( $old_cwd );

	return $force
			? __( 'Tailwind Standalone CLI was successfully reinstalled.', 'compilekit' )
			: __( 'The latest version of Tailwind Standalone CLI was installed successfully.', 'compilekit' );
}


/**
 * Get binary CLI path
 * https://tailwindcss.com/blog/standalone-cli
 */
function compilekit_get_binary_path() : string {
	$upload_dir = wp_upload_dir();
	return trailingslashit( $upload_dir['basedir'] ) . 'compilekit/bin/tw';
}


/**
 * Check if CLI binary exists and is readable - CONSISTENT WP_FILESYSTEM VERSION
 */
function compilekit_cli_exists() : bool {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();

	$binary = compilekit_get_binary_path();
	return $wp_filesystem->is_file( $binary ) && $wp_filesystem->is_readable( $binary );
}


/**
 * Small helper to display notices
 * @return string
 */
function compilekit_compiler_admin_notice( $message, $type = 'error' ) {
	if ( ! is_admin() ) {
		return '';
	}

	return '<div class="notice notice-'.esc_attr($type).' is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
}


/**
 * Run Compiler
 * @return void
 */
function compilekit_run_compiler() {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();

	if ( ! function_exists( 'exec' ) ) {
		echo wp_kses_post( compilekit_compiler_admin_notice( __( 'PHP exec() function not available or disabled.', 'compilekit' ) ) );
		return;
	}

	$input  = get_option( 'compilekit_input_path', '' );
	$output = get_option( 'compilekit_output_path', '' );
	$flags  = get_option( 'compilekit_additional_flags', '--minify' );

	if ( ! $input || ! $output ) {
		$missing = ! $input ? __( 'Input path', 'compilekit' ) : __( 'Output path', 'compilekit' );
		echo wp_kses_post( compilekit_compiler_admin_notice( sprintf(
				/* translators: %s: Type of path */
						__( '%s not set.', 'compilekit' ), $missing )
		) );
		return;
	}

	$theme_dir   = get_stylesheet_directory();
	$input_path  = realpath( $theme_dir . '/' . ltrim( trim( $input ), '/' ) );
	$output_path = $theme_dir . '/' . ltrim( trim( $output ), '/' );

	if ( ! $input_path || ! str_starts_with( $input_path, realpath( $theme_dir ) ) ) {
		echo wp_kses_post( compilekit_compiler_admin_notice( __( 'Invalid input path.', 'compilekit' ) ) );
		return;
	}

	$binary = compilekit_get_binary_path();

	if ( ! $wp_filesystem->is_file( $binary ) || ! $wp_filesystem->is_readable( $binary ) ) {
		echo wp_kses_post( compilekit_compiler_admin_notice( __( 'Tailwind binary file not exists or not executable.', 'compilekit' ) ) );
		return;
	}

	if ( ! $wp_filesystem->is_file( $input_path ) ) {
		echo wp_kses_post( compilekit_compiler_admin_notice( sprintf(
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

	exec( $cmd, $output_lines, $exit_code );

	set_transient( 'compilekit_compile_notice', $exit_code, 30 );

	if ( $exit_code === 0 ) {

		$success_notice = __( 'Tailwind CLI compiled styles successfully!', 'compilekit' );
		set_transient( 'compilekit_compile_notice', $success_notice, 30 );
		echo wp_kses_post( compilekit_compiler_admin_notice( $success_notice, 'success' ) );

	} else {
		// Fallback to npx @tailwindcss/cli
		$bin_dir 						= dirname( $binary );
		$node_modules_path 	= trailingslashit( $bin_dir ) . 'node_modules';

		// Set NODE_PATH for this process
		putenv('NODE_PATH=' . $node_modules_path);

		$fallback_cmd = 'npx @tailwindcss/cli' .
										' --input ' . escapeshellarg( $input_path ) .
										' --output ' . escapeshellarg( $output_path ) .
										' --cwd ' . escapeshellarg( $cwd ) .
										' ' . escapeshellarg( $flags ) . ' 2>&1';
		$output_lines = [];

		exec( $fallback_cmd, $output_lines, $exit_code );

		set_transient( 'compilekit_compile_notice', $exit_code, 30 );

		if ( $exit_code === 0 ) {

			$success_notice = __( 'Tailwind CLI compiled styles successfully!', 'compilekit' );
			set_transient( 'compilekit_compile_notice', $success_notice, 30 );
			echo wp_kses_post( compilekit_compiler_admin_notice( $success_notice, 'success' ) );

		} else {
			$error_notice = sprintf(
			/* translators: %s: list of errors */
					__( 'Compilation failed: %1$s (exit code %2$s)', 'compilekit' ),
					implode( ' | ', array_map( 'esc_html', $output_lines ) ),
					$exit_code,
			);
			set_transient( 'compilekit_compile_notice', $error_notice, 30 );
			echo wp_kses_post( compilekit_compiler_admin_notice( $error_notice ) );

		}
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
	$enabled = get_option( 'compilekit_compile_on_reload', false );
	if ( ! $enabled ) {
		return;
	}

	// 4. Run compiler
	compilekit_run_compiler();
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
			'compilekit_render_page',
			plugin_dir_url( __FILE__ ) . 'assets/icon.png'
	);

	add_submenu_page(
			'compilekit',
			'Updates',
			'Updates',
			'manage_options',
			'compilekit-updates',
			'compilekit_render_updates_page'
	);

} );


/**
 * Main Tailwind CLI Page
 * @return void
 */
function compilekit_render_page() {
	$input             = get_option( 'compilekit_input_path', '' );
	$output            = get_option( 'compilekit_output_path', '' );
	$flags             = get_option( 'compilekit_additional_flags', '--minify' );
	$compile_on_reload = get_option( 'compilekit_compile_on_reload', false );
	$cli_installed     = compilekit_cli_exists(); // NEW: Use consistent function

	// STEP 1: Manual CLI download trigger
	if ( isset( $_POST['compilekit_download_cli'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'compilekit_settings_action' );
		$result = compilekit_download_tailwind_cli();

		echo '<div class="notice notice-info"><p>' . esc_html( $result ) . '</p></div>';
		$cli_installed = compilekit_cli_exists(); // NEW: Recheck with consistent function
	}

	// STEP 2: Save settings
	if ( isset( $_POST['compilekit_save_settings'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'compilekit_settings_action' );

		$input             = isset( $_POST['compilekit_input'] ) 	? sanitize_text_field( wp_unslash( $_POST['compilekit_input'] ) )  : '';
		$output            = isset( $_POST['compilekit_output'] ) ? sanitize_text_field( wp_unslash( $_POST['compilekit_output'] ) ) : '';
		$flags             = isset( $_POST['compilekit_flags'] ) 	? sanitize_text_field( wp_unslash( $_POST['compilekit_flags'] ) )  : '--minify';
		$compile_on_reload = isset( $_POST['compilekit_compile_on_reload'] ) ? 1 : 0;

		update_option( 'compilekit_input_path', $input );
		update_option( 'compilekit_output_path', $output );
		update_option( 'compilekit_additional_flags', $flags );
		update_option( 'compilekit_compile_on_reload', $compile_on_reload );

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Settings saved.', 'compilekit' ) . '</p></div>';
	}

	// STEP 2.1: Manually compile
	if ( isset( $_POST['compilekit_compile_now'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'compilekit_settings_action' );
		compilekit_run_compiler();
	}

	if ( $compile_on_reload ) {
		echo '<div class="notice notice-warning"><p>' . esc_html__( 'Warning: Compile on reload is enabled. Make sure to DISABLE it when done!', 'compilekit' ) . '</p></div>';
	}
	?>
	<div class="wrap">
		<h1><?php esc_html_e('CompileKit Settings', 'compilekit') ?></h1>
		<p><?php esc_html_e('Integrates Tailwind CSS Standalone CLI with WordPress for streamlined builds and asset compilation.', 'compilekit') ?></p>

		<form method="post">
			<?php wp_nonce_field('compilekit_settings_action'); ?>
			<table class="form-table">
				<?php if ( ! $cli_installed ) : ?>
					<h3><?php esc_html_e('Step 1:', 'compilekit') ?>
						<span style="font-weight: 400"><?php esc_html_e('Download Tailwind CSS CLI binary', 'compilekit') ?></span>
					</h3>
					<?php submit_button( esc_html__( 'Download Tailwind CLI', 'compilekit' ), 'primary', 'compilekit_download_cli' ); ?>
				<?php else : ?>
					<tr>
						<th scope="row">
							<label for="compilekit_input" style="white-space: nowrap">
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
						<td><input type="text" name="compilekit_input" id="compilekit_input" value="<?php echo esc_attr($input); ?>"
											 class="regular-text" placeholder="./assets/styles/src/input.css" required></td>
					</tr>
					<tr>
						<th scope="row">
							<label for="compilekit_output" style="white-space: nowrap">
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
						<td><input type="text" name="compilekit_output" id="compilekit_output" value="<?php echo esc_attr($output); ?>"
											 class="regular-text" placeholder="./assets/styles/css/output.css" required></td>
					</tr>
					<tr>
						<th scope="row"><label for="compilekit_flags"><?php esc_html_e('Compiling Mode', 'compilekit') ?></label></th>
						<td>
							<label>
								<input type="radio" name="compilekit_flags" value="--minify" <?php checked($flags, '--minify'); ?>>
								<?php esc_html_e('Compressed', 'compilekit') ?>
							</label>
							<span style="padding: 0 10px"></span>
							<label>
								<input type="radio" name="compilekit_flags" value="--optimize" <?php checked($flags, '--optimize'); ?>>
								<?php esc_html_e('Expanded', 'compilekit') ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row" style="white-space: nowrap">
							<?php
							echo wp_kses_post( sprintf(
							/* translators: %1$s is the main label, %2$s is the additional context in parentheses */
									__( '%1$s <span style="font-weight: normal">%2$s</span>', 'compilekit' ),
									esc_html( 'Always Recompile' ),
									esc_html( '(use with caution)' )
							) );
							?>
						</th>
						<td><label><input type="checkbox" name="compilekit_compile_on_reload" <?php checked($compile_on_reload); ?>>
								<?php esc_html_e('Run Tailwind CLI compiler on every page reload', 'compilekit') ?></label></td>
					</tr>
				<?php endif; ?>
			</table>

			<?php if ( $cli_installed ) : ?>
				<p class="submit" style="display: flex; align-items: center; gap: 16px;">
					<?php submit_button( esc_html__( 'Save Settings', 'compilekit' ), 'primary', 'compilekit_save_settings', false ); ?>
					<?php submit_button( esc_html__( 'Compile CSS Manually', 'compilekit' ), 'secondary', 'compilekit_compile_now', false ); ?>
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
function compilekit_render_updates_page() {
	global $wp_filesystem;

	if ( ! function_exists( 'WP_Filesystem' ) ) {
		require_once ABSPATH . 'wp-admin/includes/file.php';
	}
	WP_Filesystem();

	$current_version 	= get_transient( 'compilekit_current_version' );
	$latest_version  	= get_transient( 'compilekit_latest_version' );
	$na_notice				= __( 'Not available', 'compilekit' );

	if ( false === $current_version ) {
		$current_version = $na_notice;
	}
	if ( false === $latest_version ) {
		$latest_version = $na_notice;
	}

	$binary 			= compilekit_get_binary_path();
	$get_version 	= [];

	if ( isset( $_POST['compilekit_check_updates'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'compilekit_update_action' );

		$get_version 			= compilekit_check_version($binary);
		$notice 					= $get_version['notices'] 				?? '';
		$current_version 	= $get_version['current_version'] ?? $na_notice;
		$latest_version 	= $get_version['latest_version'] 	?? $na_notice;

		if ( $notice ) {
			echo '<div class="notice notice-info is-dismissible"><p>' . esc_html( $notice ) . '</p></div>';
		}

		// Check if binary exists properly
		if ( ! $wp_filesystem->is_file( $binary ) || ! $wp_filesystem->is_readable( $binary ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Binary missing. Please reinstall Tailwind Standalone CLI.', 'compilekit' ) . '</p></div>';
		} elseif ( empty( $current_version ) || $current_version === $na_notice || $get_version['current_version'] === '' ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Could not determine current version. Binary may be corrupted.', 'compilekit' ) . '</p></div>';
		}

	}

	if ( isset( $_POST['compilekit_reinstall_cli'] ) ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'compilekit' ) );
		}

		check_admin_referer( 'compilekit_update_action' );

		// Force reinstall by passing true
		$result = compilekit_download_tailwind_cli( true );
		if ( $result ) {
			echo '<div class="notice notice-info"><p>' . esc_html( $result ) . '</p></div>';
		}

		$get_version     = compilekit_check_version( $binary );
		$current_version = $get_version['current_version'] ?? $na_notice;
		$latest_version  = $get_version['latest_version']  ?? $na_notice;
	}

	?>
	<div class="wrap">
		<h1><?php esc_html_e( 'CompileKit Updates', 'compilekit' ); ?></h1>
		<form method="post">
			<?php wp_nonce_field( 'compilekit_update_action' ); ?>
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
				<?php submit_button( __( 'Check for Updates', 'compilekit' ), 'primary', 'compilekit_check_updates', false ); ?>
				<?php submit_button( __( 'Reinstall Tailwind CLI', 'compilekit' ), 'secondary', 'compilekit_reinstall_cli', false ); ?>
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
function compilekit_check_version( $binary ) {
	$notice = $current_version = $latest_version = '';

	if ( ! function_exists( 'exec' ) ) {
		return [
				'notices' 				=> __( 'PHP exec function not exists or disabled.', 'compilekit' ),
				'current_version' => $current_version,
				'latest_version' 	=> $latest_version,
		];
	}

	// Step 1: Check current version
	if ( compilekit_cli_exists() ) {

		// ATTEMPT 1: Try to get version from Binary
		$output_lines = [];
		$cwd 					= get_stylesheet_directory();
		$cmd 					= escapeshellarg( $binary ) . ' --cwd ' . escapeshellarg( $cwd ) . ' --version 2>&1';
		exec( $cmd, $output_lines, $exit_code );

		if ( $exit_code !== 0 ) {
			// ATTEMPT 2: First command failed, try fallback.
			$old_cwd = getcwd();
			if ( $old_cwd === false ) {
				$notice = __( 'Failed to get current directory.', 'compilekit' );
				return [
						'notices' 				=> $notice,
						'current_version' => 'N/A',
						'latest_version' 	=> 'N/A',
				];
			}

			$bin_dir = dirname( $binary );

			if ( ! chdir( $bin_dir ) ) {
				// Restore original directory
				chdir( $old_cwd );

				$notice = __( 'Failed to change to NPM directory for version check.', 'compilekit' );
				return [
						'notices' 				=> $notice,
						'current_version' => 'N/A',
						'latest_version' 	=> 'N/A',
				];
			}

			$output_lines = []; // reset before fallback
			$fallback_cmd	= 'npx @tailwindcss/cli --version 2>&1';
			exec( $fallback_cmd, $output_lines, $exit_code );

			// Restore directory
			chdir( $old_cwd );

			if ( $exit_code !== 0 ) {
				if ( $exit_code === 134 ) {
					$error_notice = sprintf(
					/* translators: %d: exit code */
							__( 'Binary compatibility error (exit code %d). Try reinstalling the CLI for your system architecture.', 'compilekit' ),
							$exit_code
					);
				} elseif ( $exit_code === 127 ) {
					$error_notice = __( 'Binary not found or not executable.', 'compilekit' );
				} else {
					$error_notice = sprintf(
					/* translators: %1$d: exit code, %2$s: output lines */
							__( 'Both primary and fallback commands failed. Last exit code %1$d: %2$s', 'compilekit' ),
							$exit_code,
							implode( ' | ', $output_lines )
					);
				}
				$notice = $error_notice;
			} else {
				$notice = __( 'Fallback command succeeded. Successfully fetched the latest version.', 'compilekit' );
				if ( ! empty( $output_lines[0] ) ) {
					$current_version = sanitize_text_field( $output_lines[0] );
				}
			}

		} else {
			$notice = __( 'Successfully fetched Tailwind CLI version info.', 'compilekit' );
			if ( ! empty( $output_lines[0] ) ) {
				$current_version = sanitize_text_field( $output_lines[0] );
			}
		}

	}

	// Step 2: Check the latest version
	$response = wp_remote_get( 'https://api.github.com/repos/tailwindlabs/tailwindcss/releases/latest', [
			'user-agent' => 'WordPress/compilekit',
			'timeout'    => 30,
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

	set_transient( 'compilekit_current_version', $current_version, MONTH_IN_SECONDS );
	set_transient( 'compilekit_latest_version', $latest_version, MONTH_IN_SECONDS );

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

	if ( ! get_option( 'compilekit_compile_on_reload', false ) ) {
		return;
	}

	// Run compiler and capture result
	$message = get_transient( 'compilekit_compile_notice' );

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

	delete_transient( 'compilekit_compile_notice' ); // finally clear

}, 100 );