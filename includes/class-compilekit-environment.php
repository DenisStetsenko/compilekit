<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Handles environment-specific operations like downloading node_modules for IDE support
 * and cleaning up packages when switching to production environments
 */
class CompileKit_Environment {
	
	/**
	 * Check if node_modules directory exists and contains Tailwind CSS
	 */
	public static function node_modules_exists() : bool {
		$compilekit_dir   = CompileKit_Helpers::compilekit_modules_dir();
		$node_modules_dir = $compilekit_dir . 'node_modules/';
		$tailwind_path    = $node_modules_dir . 'tailwindcss/';
		
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return false;
		}
		
		return $fs->is_dir( $node_modules_dir ) && $fs->is_dir( $tailwind_path );
	}
	
	
	/**
	 * Retrieves the version of the Tailwind Node.js package from a cached transient.
	 */
	public static function get_node_modules_version() : string {
		$ver = get_transient( 'compilekit_tailwindcss_cli_version' );
		return is_string( $ver ) ? $ver : '';
	}
	
	
	/**
	 * Detects the version of a Tailwind Node.js package by checking `package.json` files in the provided `node_modules` directory.
	 */
	private static function detect_node_modules_version( string $node_modules_dir = '' ) : string {
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return '';
		}
		
		$candidates = array(
			$node_modules_dir . 'tailwindcss/package.json',
			$node_modules_dir . '@tailwindcss/cli/package.json',
		);
		
		foreach ( $candidates as $path ) {
			$path = wp_normalize_path( $path );
			
			if ( ! $fs->exists( $path ) || ! $fs->is_file( $path ) || ! $fs->is_readable( $path ) ) {
				continue;
			}
			
			$raw = $fs->get_contents( $path );
			if ( ! is_string( $raw ) || $raw === '' ) {
				continue;
			}
			
			try {
				$data = json_decode( $raw, true, 512, JSON_THROW_ON_ERROR );
			} catch ( \JsonException $e ) {
				continue;
			}
			
			if ( ! is_array( $data ) ) {
				continue;
			}
			
			$ver = isset( $data['version'] ) ? trim( (string) $data['version'] ) : '';
			if ( $ver === '' ) {
				continue;
			}
			
			// UI expects something like "v4.x.y"
			return 'v' . $ver;
		}
		
		return '';
	}
	
	
	/**
	 * Downloads Tailwind CSS npm packages to wp-content/uploads/compilekit/node_modules
	 *
	 * This allows IDEs like PHPStorm to read the package files and provide autocomplete
	 */
	public static function download_node_modules() : array {
		if ( ! CompileKit_Helpers::process_runner_init() ) {
			return array(
				'success' => false,
				'message' => __( 'PHP cannot run system commands (exec/shell_exec disabled).', 'compilekit' ),
			);
		}
		
		/**
		 * Ensure Node/npm can be found.
		 * WordPress often runs with a minimal PATH (especially in admin / cron).
		 */
		$old_path = getenv( 'PATH' );
		$old_path = is_string( $old_path ) ? $old_path : '';
		
		try {
			// Detect OS/arch (so we can set PATH correctly)
			$os = CompileKit_Helpers::detect_os_family();
			
			if ( $os === 'Darwin' ) {
				// Apple Silicon + Intel Homebrew common locations
				putenv( 'PATH=/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin' . ( $old_path !== '' ? ':' . $old_path : '' ) );
				
			} elseif ( $os === 'Linux' ) {
				// Typical server paths
				putenv( 'PATH=/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin' . ( $old_path !== '' ? ':' . $old_path : '' ) );
				
			} elseif ( $os !== 'Windows' ) {
				return array(
					'success' => false,
					'message' => __( 'Unsupported OS for Node.js/npm automation.', 'compilekit' ),
				);
			}
			
			// Verify npm exists (via process_runner)
			$npm_check = CompileKit_Helpers::process_runner( 'npm --version' );
			if ( $npm_check['success'] === false ) {
				return array(
					'success' => false,
					'message' => sprintf( "%s\n%s",
						__( 'Node.js or npm is not installed, or is not available in PATH.', 'compilekit' ),
						$npm_check['output']
					));
			}
			
			$compilekit_dir   = CompileKit_Helpers::compilekit_modules_dir();
			$node_modules_dir = $compilekit_dir . 'node_modules/';
			
			// WP Filesystem
			$fs = CompileKit_Helpers::fs();
			if ( ! $fs ) {
				return array(
					'success' => false,
					'message' => __( 'WP Filesystem is not available.', 'compilekit' ),
				);
			}
			
			// Create directory if missing
			if ( ! wp_mkdir_p( $compilekit_dir ) ) {
				return array(
					'success' => false,
					'message' => __( 'Failed to create CompileKit directory.', 'compilekit' ),
				);
			}
			
			// Check directory permissions
			if ( ! $fs->is_writable( $compilekit_dir ) ) {
				return array(
					'success' => false,
					'message' => __( 'Directory is not writable. Check permissions.', 'compilekit' ),
				);
			}
			
			$package_json = $compilekit_dir . 'package.json';
			
			if ( ! $fs->exists( $package_json ) ) {
				$package_content = json_encode( [
					'name'         		=> 'compilekit-tailwind',
					'version'      		=> '1.0.0',
					'private' 				=> true,
					'license'					=> 'ISC',
					'devDependencies' => array(),
				], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT );
				
				$written = $fs->put_contents( $package_json, $package_content, FS_CHMOD_FILE );
				
				if ( ! $written ) {
					return array(
						'success' => false,
						'message' => __( 'Failed to write to package.json', 'compilekit' ),
					);
				}
			}
			
			// Get current dir
			$old_cwd   = getcwd();
			$did_chdir = false;
			
			if ( $old_cwd !== false && chdir( $compilekit_dir ) ) {
				$did_chdir = true;
			}
			
			// run NPM install
			$command = 'npm install -D tailwindcss @tailwindcss/cli @tailwindcss/forms @tailwindcss/typography --no-audit --no-fund';
			$result  = CompileKit_Helpers::process_runner( $command );
			
			// Restore original directory
			if ( $did_chdir && $old_cwd !== false ) {
				chdir( $old_cwd );
			}
			
			// Check IF node_modules/tailwindcss/ exists and $result is NOT FALSE.
			if ( ( $result['success'] === true || $result['success'] === null ) && self::node_modules_exists() ) {
				
				// Detect + Save npx version
				$tailwindcss_ver = self::detect_node_modules_version( $node_modules_dir );
				if ( $tailwindcss_ver !== '' ) {
					set_transient( 'compilekit_tailwindcss_cli_version', $tailwindcss_ver );
				}
				
				return array(
					'success' => true,
					'message' => __( 'Tailwind Node.js packages were installed successfully!', 'compilekit' ),
					'path'    => $node_modules_dir,
				);
			}
			
			return array(
				'success' => false,
				// translators: %s: List of package names that failed to install.
				'message' => sprintf( __( "Failed to install packages:\n%s", 'compilekit' ), (string) $result['output'] ),
			);
			
			
		} finally {
			if ( $old_path !== false && $old_path !== '' ) {
				putenv( 'PATH=' . $old_path );
			} else {
				putenv( 'PATH' );
			}
		}
	}
	
	
	/**
	 * Removes the node_modules directory completely using WordPress filesystem API
	 */
	public static function remove_node_modules() : array {
		$compilekit_dir = CompileKit_Helpers::compilekit_modules_dir();
		$node_modules   = $compilekit_dir . 'node_modules/';
		$package_json   = $compilekit_dir . 'package.json';
		$package_lock   = $compilekit_dir . 'package-lock.json';
		
		// Initialize WordPress Filesystem API
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return array(
				'success' => false,
				'message' => __( 'WP Filesystem is not available.', 'compilekit' ),
			);
		}
		
		$had_any = false;
		$ok      = true;
		
		// node_modules/
		if ( $fs->is_dir( $node_modules ) ) {
			$had_any = true;
			$deleted = $fs->delete( $node_modules, true );
			$ok      = $ok && $deleted;
		}
		
		// package-lock.json
		if ( $fs->exists( $package_lock ) ) {
			$had_any = true;
			$deleted = $fs->delete( $package_lock, false );
			$ok      = $ok && $deleted;
		}
		
		// package.json
		if ( $fs->exists( $package_json ) ) {
			$had_any = true;
			$deleted = $fs->delete( $package_json, false );
			$ok      = $ok && $deleted;
		}
		
		if ( ! $had_any ) {
			return array(
				'success' => true,
				'message' => __( 'Nothing to remove.', 'compilekit' ),
			);
		}
		
		if ( $ok ) {
			// clear persisted version
			delete_transient( 'compilekit_tailwindcss_cli_version' );
			
			return array(
				'success' => true,
				'message' => __( 'Successfully removed Tailwind npm packages (node_modules and package files).', 'compilekit' ),
			);
		}
		
		return array(
			'success' => false,
			'message' => __( 'Failed to remove one or more items. Check file permissions.', 'compilekit' ),
		);
	}
	
	
	/**
	 * Check if a standalone executable CLI file exists and is readable.
	 */
	public static function standalone_executable_cli_exists() : bool {
		$compilekit_dir = CompileKit_Helpers::compilekit_modules_dir();
		$binary_unix    = $compilekit_dir . 'tailwindcli';
		$binary_win     = $compilekit_dir . 'tailwindcli.exe';
		
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return false;
		}
		
		return ( $fs->is_file( $binary_unix ) && $fs->is_readable( $binary_unix ) )
		       || ( $fs->is_file( $binary_win ) && $fs->is_readable( $binary_win ) );
	}
	
	
	/**
	 * Retrieves the cached version of the standalone executable CLI, if available.
	 */
	public static function get_standalone_executable_cli_version() : string {
		$ver = get_transient( 'compilekit_standalone_cli_version' );
		return is_string( $ver ) ? $ver : '';
	}
	
	
	/**
	 * Detects the version of a standalone executable CLI by running the binary with the `--version` flag.
	 */
	private static function detect_standalone_executable_cli_version( string $binary_path ) : string {
		if ( ! CompileKit_Helpers::process_runner_init() ) {
			return '';
		}
		
		$cmd = escapeshellarg( $binary_path ) . ' --version';
		
		$os = CompileKit_Helpers::detect_os_family();
		if ( $os === 'Windows' ) {
			$cmd = 'cmd /c ' . $cmd;
		}
		
		$run = CompileKit_Helpers::process_runner( $cmd );
		$out = isset( $run['output'] ) ? trim( (string) $run['output'] ) : '';
		
		if ( $out === '' ) {
			return '';
		}
		
		// Expected: "tailwindcss v4.x.x" (or similar). Extract first version-like token.
		if ( preg_match( '/\bv?\d+\.\d+\.\d+\b/', $out, $m ) ) {
			$ver = (string) $m[0];
			return ( $ver !== '' && $ver[0] !== 'v' ) ? ( 'v' . $ver ) : $ver;
		}
		
		return '';
	}
	
	
	/**
	 * Downloads a standalone executable for the command-line interface.
	 */
	public static function download_standalone_executable_cli( bool $force = false ) : array {
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return array(
				'success' => false,
				'message' => __( 'WP Filesystem is not available.', 'compilekit' ),
			);
		}
		
		$compilekit_dir = CompileKit_Helpers::compilekit_modules_dir();
		
		// Create directory if missing
		if ( ! wp_mkdir_p( $compilekit_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create CompileKit directory.', 'compilekit' ),
			);
		}
		
		if ( ! $fs->is_writable( $compilekit_dir ) ) {
			return array(
				'success' => false,
				'message' => __( 'CompileKit directory is not writable. Check permissions.', 'compilekit' ),
			);
		}
		
		$os = CompileKit_Helpers::detect_os_family();
		
		// Stable renamed executable name
		$final_name = ( $os === 'Windows' ) ? 'tailwindcli.exe' : 'tailwindcli';
		$final_path = $compilekit_dir . $final_name;
		
		// If already exists and not forcing
		if ( ! $force && $fs->is_file( $final_path ) && $fs->is_readable( $final_path ) && (int) $fs->size( $final_path ) > 0 ) {
			return array(
				'success' => true,
				'message' => __( 'Tailwind CLI is already installed.', 'compilekit' ),
				'path'    => $final_path,
			);
		}
		
		// Force cleanup old file
		if ( $force && $fs->exists( $final_path ) ) {
			$fs->delete( $final_path );
		}
		
		// Detect correct upstream asset filename for this OS/arch/libc
		$upstream_filename = CompileKit_Helpers::tailwind_standalone_filename();
		if ( $upstream_filename === '' ) {
			return array(
				'success' => false,
				'message' => __( 'Unsupported OS/architecture for Tailwind Standalone CLI.', 'compilekit' ),
			);
		}
		
		$url = CompileKit_Helpers::tailwind_standalone_download_url( $upstream_filename );
		if ( $url === '' ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to build Tailwind CLI download URL.', 'compilekit' ),
			);
		}
		
		$temp_file = wp_tempnam( $upstream_filename );
		if ( ! $temp_file ) {
			return array(
				'success' => false,
				'message' => __( 'Failed to create a temporary file for download.', 'compilekit' ),
			);
		}
		
		$response = wp_remote_get( $url, array(
			'timeout'    => 300,
			'stream'     => true,
			'filename'   => $temp_file,
			'user-agent' => 'WordPress/compilekit',
		) );
		
		$cleanup_temp = static function() use ( $fs, $temp_file ) : void {
			if ( $fs && $fs->exists( $temp_file ) ) {
				$fs->delete( $temp_file );
			}
		};
		
		if ( is_wp_error( $response ) ) {
			$cleanup_temp();
			
			return array(
				'success' => false,
				'message' => __( 'Tailwind CLI download failed.', 'compilekit' ),
			);
		}
		
		$code = (int) wp_remote_retrieve_response_code( $response );
		
		if ( $code === 403 ) {
			$headers        = wp_remote_retrieve_headers($response);
			$rate_limit     = isset($headers['x-ratelimit-limit']) ? (int) $headers['x-ratelimit-limit'] : null;
			$rate_reset     = isset($headers['x-ratelimit-reset']) ? gmdate('F j, Y H:i:s', (int) $headers['x-ratelimit-reset']) : null;
			$cleanup_temp();
			
			return array(
				'success' => false,
				'message' => sprintf(
				/* translators: %1$s: rate limit, %2$s: rate reset */
					__('Error: GitHub API rate limit exceeded. %1$s', 'compilekit'),
					$rate_limit && $rate_reset ? sprintf(
					/* translators: %1$d: rate limit, %2$s: rate reset time */
						__('Limit: %1$d requests. Resets at %2$s UTC.', 'compilekit'),
						$rate_limit,
						$rate_reset
					) : __('Please try again later.', 'compilekit')
				),
			);
		}
		
		if ( $code === 404 ) {
			$cleanup_temp();
			
			return array(
				'success' => false,
				'message' => __( 'Error: GitHub Repository Not Found.', 'compilekit' ),
			);
		}
		
		if ( $code !== 200 ) {
			$cleanup_temp();
			
			return array(
				'success' => false,
				'message' => sprintf(
				/* translators: %d: HTTP code */
					__( 'Tailwind CLI download failed (HTTP %d).', 'compilekit' ),
					$code
			) );
		}
		
		// Validate temp file
		if ( ! $fs->exists( $temp_file ) || (int) $fs->size( $temp_file ) === 0 ) {
			$cleanup_temp();
			return array(
				'success' => false,
				'message' => __( 'Downloaded file is empty or corrupt.', 'compilekit' ),
			);
		}
		
		// Move to final stable name
		if ( ! $fs->move( $temp_file, $final_path, true ) ) {
			$cleanup_temp();
			return array(
				'success' => false,
				'message' => __( 'Failed to move Tailwind CLI into place.', 'compilekit' ),
			);
		}
		
		$expected_sha256 = CompileKit_Helpers::tailwind_expected_sha256_from_sums( $upstream_filename );
		
		if ( $expected_sha256 === '' ) {
			$fs->delete( $final_path );
			
			return array(
				'success' => false,
				'message' => __( 'Failed to verify Tailwind CLI integrity (missing sha256 for asset).', 'compilekit' ),
			);
		}
		
		if ( ! CompileKit_Helpers::verify_file_sha256( $final_path, $expected_sha256 ) ) {
			$fs->delete( $final_path );
			
			return array(
				'success' => false,
				'message' => __( 'Downloaded Tailwind CLI failed integrity check (sha256 mismatch).', 'compilekit' ),
			);
		}
		
		// Make executable on Unix
		if ( $os !== 'Windows' ) {
			$fs->chmod( $final_path, 0755 );
		}
		
		// Final sanity check
		if ( ! $fs->is_readable( $final_path ) || (int) $fs->size( $final_path ) === 0 ) {
			return array(
				'success' => false,
				'message' => __( 'Tailwind CLI was downloaded but is not readable. Check permissions.', 'compilekit' ),
			);
		}
		
		// Detect + Save binary version
		$binary_ver = self::detect_standalone_executable_cli_version( $final_path );
		if ( $binary_ver !== '' ) {
			set_transient( 'compilekit_standalone_cli_version', $binary_ver );
		}
		
		return array(
			'success'  => true,
			'message'  => __( 'Tailwind Standalone Executable Binary was downloaded successfully!', 'compilekit' ),
			'path'     => $final_path,
			'filename' => $final_name,
			'url'      => $url,
		);
	
	}
	
	
	/**
	 * Removes standalone CLI executable files (Unix and Windows) from the compilekit directory.
	 */
	public static function remove_standalone_executable_cli() : array {
		$compilekit_dir = CompileKit_Helpers::compilekit_modules_dir();
		$binary_unix    = $compilekit_dir . 'tailwindcli';
		$binary_win     = $compilekit_dir . 'tailwindcli.exe';
		
		// Initialize WordPress Filesystem API
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return array(
				'success' => false,
				'message' => __( 'WP Filesystem is not available.', 'compilekit' ),
			);
		}
		
		$had_any = false;
		$ok      = true;
		
		// Remove Unix/macOS binary
		if ( $fs->exists( $binary_unix ) ) {
			$had_any = true;
			$deleted = $fs->delete( $binary_unix, false );
			$ok      = $ok && $deleted;
		}
		
		// Remove Windows binary
		if ( $fs->exists( $binary_win ) ) {
			$had_any = true;
			$deleted = $fs->delete( $binary_win, false );
			$ok      = $ok && $deleted;
		}
		
		if ( ! $had_any ) {
			return array(
				'success' => true,
				'message' => __( 'No Tailwind Standalone Executable Binary found to remove.', 'compilekit' ),
			);
		}
		
		if ( $ok ) {
			// clear persisted binary version
			delete_transient( 'compilekit_standalone_cli_version' );
			
			return array(
				'success' => true,
				'message' => __( 'Successfully removed Tailwind Standalone Executable Binary.', 'compilekit' ),
			);
		}
		
		return array(
			'success' => false,
			'message' => __( 'Failed to remove Tailwind Standalone Executable Binary. Check file permissions.', 'compilekit' ),
		);
	}
	
	
	/**
	 * Get the current environment setting
	 *
	 * Returns 'local', 'staging', or 'live'
	 * Default: 'local'
	 */
	public static function get_environment() {
		return get_option( 'compilekit_environment', 'local' );
	}
	
	
	/**
	 * Get the active compiler backend.
	 *
	 * Returns: 'cli', 'node', or 'auto'
	 * - local -> prefer Node/npm (for IDE support + predictable dev workflow)
	 * - staging / live -> prefer Standalone Tailwind CLI binary (less dependencies, more reliable)
	 * - Default: 'auto'
	 *
	 * MANUAL mode respects user choice but returns 'none' if the chosen backend is unavailable.
	 * AUTO prefers cli over node, and returns 'none' if nothing exists.
	 */
	public static function get_active_compiler() : string {
		$cli  = self::standalone_executable_cli_exists();
		$node = self::node_modules_exists();
		$mode = get_option( 'compilekit_compiler_mode', 'auto' );
		
		if ( $mode === 'cli' ) {
			return $cli ? 'cli' : '';
		}
		
		if ( $mode === 'node' ) {
			return $node ? 'node' : '';
		}
		
		// auto:
		if ( $cli ) {
			return 'cli';
		}
		
		if ( $node ) {
			return 'node';
		}
		
		return 'none';
	}
	
	
	/**
	 * Determine if CSS should be minified based on environment
	 *
	 * Local, Staging: not minified for easier debugging
	 * Live: minified for performance
	 */
	public static function should_minify() : bool {
		$env = self::get_environment();
		return $env === 'live';
	}
	
	
	/**
	 * Determine whether a sourcemap should be generated based on the environment.
	 * true if the environment is 'local', otherwise false.
	 */
	public static function should_generate_sourcemap() : bool {
		$env = self::get_environment();
		return $env === 'local';
	}
	
}