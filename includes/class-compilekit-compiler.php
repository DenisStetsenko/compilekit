<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CompileKit_Compiler {
	
	public function __construct() {
		add_action( 'init', array($this, 'run_compiler_on_refresh') );
	}
	
	/**
	 * Resolves and validates the input and output CSS file paths for the Tailwind compilation process.
	 *
	 * This method ensures that the specified paths are provided, point to valid locations within the active theme directory,
	 * and adhere to the expected file constraints, such as file extensions and writability. It creates necessary directories
	 * if they do not exist and verifies that all file paths are properly normalized and accessible.
	 */
	public static function resolve_compile_paths() : array {
		$input_rel  = trim( (string) get_option( 'compilekit_input_css', '' ) );
		$output_rel = trim( (string) get_option( 'compilekit_output_css', '' ) );
		
		if ( $input_rel === '' || $output_rel === '' ) {
			return array(
				'success'  => false,
				'message'  => __( 'Input/Output CSS paths must be set.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		// Check writable via WP_Filesystem.
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return array(
				'success'  => false,
				'message'  => __( 'WP Filesystem is not available.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		if ( strtolower( pathinfo( $input_rel, PATHINFO_EXTENSION ) ) !== 'css' ) {
			return array(
				'success'  => false,
				'message'  => __( 'Input file must be a .css file.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		if ( strtolower( pathinfo( $output_rel, PATHINFO_EXTENSION ) ) !== 'css' ) {
			return array(
				'success'  => false,
				'message'  => __( 'Output file must be a .css file.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		$theme_dir  = get_stylesheet_directory();
		$theme_real = realpath( $theme_dir );
		
		if ( ! is_string( $theme_real ) || $theme_real === '' ) {
			return array(
				'success'  => false,
				'message'  => __( 'Failed to resolve theme directory path.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		$theme_root = trailingslashit( wp_normalize_path( $theme_real ) );
		
		// Normalize user-provided relative paths.
		$input_rel  = ltrim( $input_rel, "/\\ \t\n\r\0\x0B" );
		$output_rel = ltrim( $output_rel, "/\\ \t\n\r\0\x0B" );
		
		// Build absolute paths.
		$input_abs  = wp_normalize_path( $theme_root . $input_rel );
		$output_abs = wp_normalize_path( $theme_root . $output_rel );
		
		// INPUT must exist, be real file, and be inside theme.
		$input_real = realpath( $input_abs );
		
		if ( $input_real === false || $input_real === '' ) {
			return array(
				'success'  => false,
				'message'  => __( 'Input CSS file does not exist.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		$input_real_norm = wp_normalize_path( $input_real );
		if ( strpos( $input_real_norm, $theme_root ) !== 0 ) {
			return array(
				'success'  => false,
				'message'  => __( 'Input CSS path must be inside the active theme directory.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		// OUTPUT must be inside theme (file may not exist yet).
		if ( strpos( $output_abs, $theme_root ) !== 0 ) {
			return array(
				'success'  => false,
				'message'  => __( 'Output CSS path must be inside the active theme directory.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		// Ensure output directory exists (create if needed).
		$output_dir = wp_normalize_path( dirname( $output_abs ) );
		
		if ( ! wp_mkdir_p( $output_dir ) ) {
			return array(
				'success'  => false,
				'message'  => __( 'Failed to create output directory.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		// Verify directory actually exists and is writable
		if ( ! $fs->is_writable( $output_dir ) ) {
			return array(
				'success'  => false,
				'message'  => __( 'Output directory is not writable.', 'compilekit' ),
				'resolved' => array(),
			);
		}
		
		return array(
			'success'  => true,
			'message'  => __( 'Successfully resolved system paths.', 'compilekit' ),
			'resolved' => array(
				'input_css'  => $input_real_norm,
				'output_css' => $output_abs,
				'cwd'        => untrailingslashit( $theme_root ),
			),
		);
	}
	
	
	/**
	 * Compiles Tailwind CSS using the Standalone CLI binary
	 *
	 * @return array
	 */
	public static function compile_via_cli() : array {
		// 1) Process runner must exist (otherwise we cannot run the binary anyway)
		if ( ! CompileKit_Helpers::process_runner_init() ) {
			return array(
				'success' => false,
				'message' => __( 'PHP cannot run system commands (exec/shell_exec disabled).', 'compilekit' ),
			);
		}
		
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return array(
				'success' => false,
				'message' => __( 'WP Filesystem is not available.', 'compilekit' ),
			);
		}
		
		$compilekit_dir = CompileKit_Helpers::compilekit_modules_dir();
		$os             = CompileKit_Helpers::detect_os_family();
		$binary_name    = ( $os === 'Windows' ) ? 'tailwindcli.exe' : 'tailwindcli';
		$binary_path    = $compilekit_dir . $binary_name;
		
		// 2) Binary must exist
		if ( ! $fs->is_file( $binary_path ) || ! $fs->is_readable( $binary_path ) ) {
			return array(
				'success' => false,
				'message' => __( 'Tailwind Standalone CLI is not installed. Download it first.', 'compilekit' ),
			);
		}
		
		// Make executable on Unix just in case
		if ( $os !== 'Windows' ) {
			$needs_chmod = false;
			
			if ( is_object( $fs ) && isset( $fs->method ) && $fs->method === 'direct' ) {
				$needs_chmod = ! is_executable( $binary_path );
			}
			
			if ( $needs_chmod && ! $fs->chmod( $binary_path, 0755 ) ) {
				return array(
					'success' => false,
					'message' => __( 'Tailwind CLI binary is not executable and permission update (chmod) failed. Please set permissions to 0755 manually.', 'compilekit' ),
				);
			}
		}
		
		
		// 3) FUNCTIONAL PRE-FLIGHT CHECK [exec/ only since it returns exit code]
		// We run the binary with --help. If it crashes, hangs, or errors, the environment is incompatible.
		if ( is_callable( 'exec' ) ) {
			$dry_run_cmd = escapeshellarg( $binary_path ) . ' --help';
			$dry_run     = CompileKit_Helpers::process_runner( $dry_run_cmd );
			
			if ( $dry_run['exit_code'] !== 0 ) {
				// Capture the error output to give the user a clue (e.g., "kernel too old", "error while loading shared libraries")
				$error_detail = is_array( $dry_run['output'] ) ? implode( ' | ', $dry_run['output'] ?? [] ) : (string) $dry_run['output'];
				$error_detail = trim( $error_detail );
				
				$lines   = array();
				
				$lines[] = __( 'Tailwind Standalone CLI failed to start.', 'compilekit' );
				
				$lines[] = sprintf(
				/* translators: 1: /proc, 2: /sys */
					__( 'This host may block executables or restrict access to %1$s and %2$s.', 'compilekit' ),
					'<code>/proc</code>',
					'<code>/sys</code>'
				);
				
				if ( ! empty( $error_detail ) ) {
					$lines[] = sprintf(
					/* translators: %s: error detail */
						__( 'Error: %s', 'compilekit' ),
						esc_html( $error_detail )
					);
				}
				
				if ( CompileKit_Environment::node_modules_exists() ) {
					$lines[] = '<br>' . __( 'Switch to "Node.js (npm)" compiler mode.', 'compilekit' );
				} else {
					$lines[] = '<br>' . __( 'Install Tailwind Node.js packages, then switch to "Node.js (npm)" mode.', 'compilekit' );
				}
				
				return array(
					'success'   => false,
					'exit_code' => (int) $dry_run['exit_code'],
					'message'   => implode( '<br>', $lines ),
				);
			}
		}
		
		
		// 4) Save env vars (best-effort) to restore them later.
		$old_rayon      = getenv( 'RAYON_NUM_THREADS' );
		$worker_threads = max( 1, (int) get_option( 'compilekit_worker_threads', COMPILEKIT_WORKER_THREADS_DEFAULT ) );
		
		try{
			putenv( 'RAYON_NUM_THREADS=' . $worker_threads );
			
			// 5) Resolve paths
			$resolved = self::resolve_compile_paths();
			
			if ( ! $resolved['success'] ) {
				return array(
					'success' => false,
					'message' => isset( $resolved['message'] ) ? (string) $resolved['message'] : __( 'Failed to resolve compile paths.', 'compilekit' ),
				);
			}
			
			$resolved    = (array) $resolved['resolved'];
			$input_css   = (string) $resolved['input_css'];
			$output_css  = (string) $resolved['output_css'];
			$cwd         = (string) $resolved['cwd'];
			
			if ( ! $fs->is_file( $input_css ) || ! $fs->is_readable( $input_css ) || (int) $fs->size( $input_css ) === 0 ) {
				return array(
					'success' => false,
					'message' => __( 'Tailwind input CSS file was not found or empty. Check input path.', 'compilekit' ),
				);
			}
			
			// 6) Build/Run command
			$cmd = escapeshellarg( $binary_path )
			       . ' --input ' . escapeshellarg( $input_css )
			       . ' --output ' . escapeshellarg( $output_css )
			       . ' --cwd ' . escapeshellarg( $cwd );
			
			// Should minify?
			$minify = CompileKit_Environment::should_minify();
			if ( $minify ) {
				$cmd .= ' --minify';
			}
			
			// Should generate source map?
			$sourcemap = CompileKit_Environment::should_generate_sourcemap();
			if ( $sourcemap ) {
				$cmd .= ' --map';
			}
			
			$before = $fs->exists( $output_css ) ? (int) $fs->mtime( $output_css ) : 0;
			$run = CompileKit_Helpers::process_runner( $cmd );
			
			// exit code is only reliable when returned as a real value (exec path)
			$has_exit_code = array_key_exists( 'exit_code', $run ) && $run['exit_code'] !== null;
			$exit_code     = $has_exit_code ? (int) $run['exit_code'] : 0;
			
			if ( $has_exit_code && $exit_code !== 0 ) {
				
				if ( $run['exit_code'] === 134 ) {
					$error_notice = sprintf(
					/* translators: %d: exit code */
						__( 'Standalone CLI Binary compatibility error (exit code %d).', 'compilekit' ),
						$run['exit_code']
					);
					
				} elseif ( $run['exit_code'] === 127 ) {
					$error_notice = __( 'Standalone CLI Binary not found or not executable.', 'compilekit' );
					
				} else {
					$output_raw   = $run['output'] ?? array();
					$output_lines = is_array( $output_raw ) ? $output_raw : array( (string) $output_raw );
					$output_text  = trim( implode( ' | ', array_filter( array_map( 'trim', $output_lines ) ) ) );
					
					$error_notice = sprintf(
						/* translators: %1$d: exit code, %2$s: output lines */
						__( 'Compilation via Standalone CLI has failed. Last exit code %1$d: %2$s', 'compilekit' ),
						(int) ( $run['exit_code'] ?? 0 ),
						$output_text
					);
				}
				
				return array(
					'success' => false,
					'message' => $error_notice,
				);
			}
			
			$after = $fs->exists( $output_css ) ? (int) $fs->mtime( $output_css ) : 0;
			
			// 7) Validate output file exists
			$output_ok = $fs->exists( $output_css )
			             && $fs->is_file( $output_css )
			             && (int) $fs->size( $output_css ) > 0
			             && $after >= $before;
			
			if ( ( $run['success'] === true || $run['success'] === null ) && $output_ok ) {
				$success = $run['success'] ?? null;
				
				// enforce: only true|null here
				if ( $success !== true ) {
					$success = null;
				}
				
				return array(
					'success' => $success,
					'message' => __( 'Successfully compiled Tailwind CSS styles using Standalone CLI.', 'compilekit' ),
					'method'  => $run['method'] ?? '',
					'output'  => $run['output'] ?? '',
					'path'    => $output_css,
				);
			}
			
			
			$error_output = isset( $run['output'] ) ? trim( (string) $run['output'] ) : '';
			$message = __( 'Tailwind CSS compilation via Standalone CLI has failed.', 'compilekit' );
			
			if ( $error_output !== '' ) {
				$message .= '<br>' . esc_html( $error_output );
			}
			
			return array(
				'success' => false,
				'message' => $message,
				'method'  => $run['method'] ?? '',
				'output'  => $run['output'] ?? '',
			);
			
		} finally {
			// Restore env.
			if ( $old_rayon !== false && $old_rayon !== '' ) {
				putenv( 'RAYON_NUM_THREADS=' . $old_rayon );
			} else {
				putenv( 'RAYON_NUM_THREADS' );
			}
		}
		
	}
	
	
	/**
	 * Compiles Tailwind CSS using the Node.js
	 *
	 * @return array
	 */
	public static function compile_via_node() : array {
		// 1) Process runner must exist
		if ( ! CompileKit_Helpers::process_runner_init() ) {
			return array(
				'success' => false,
				'message' => __( 'PHP cannot run system commands (exec/shell_exec disabled).', 'compilekit' ),
			);
		}
		
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs ) {
			return array(
				'success' => false,
				'message' => __( 'WP Filesystem is not available.', 'compilekit' ),
			);
		}
		
		// 2) Node modules must exist.
		if ( ! CompileKit_Environment::node_modules_exists() ) {
			return array(
				'success' => false,
				'message' => __( 'Tailwind Node.js packages are not installed. Download them first.', 'compilekit' ),
			);
		}
		
		// 3) Prepare paths (local .bin is the most reliable).
		$os               = CompileKit_Helpers::detect_os_family();
		$compilekit_dir   = CompileKit_Helpers::compilekit_modules_dir();
		$node_modules     = trailingslashit( wp_normalize_path( $compilekit_dir ) ) . 'node_modules';
		$node_modules_bin = trailingslashit( wp_normalize_path( $node_modules ) ) . '.bin';
		
		$tailwind_bin     = wp_normalize_path( $node_modules_bin . '/tailwindcss' );
		
		if ( $os === 'Windows' ) {
			$candidates = array(
				wp_normalize_path( $node_modules_bin . '/tailwindcss.cmd' ),
				wp_normalize_path( $node_modules_bin . '/tailwindcss.ps1' ),
				wp_normalize_path( $node_modules_bin . '/tailwindcss.exe' ),
			);
			
			$tailwind_bin = '';
			foreach ( $candidates as $candidate ) {
				if ( $fs->exists( $candidate ) && $fs->is_file( $candidate ) && $fs->is_readable( $candidate ) ) {
					$tailwind_bin = $candidate;
					break;
				}
			}
			
			if ( $tailwind_bin === '' ) {
				return array(
					'success' => false,
					'message' => __( 'Tailwind CLI executable was not found in node_modules/.bin. Reinstall Node.js packages.', 'compilekit' ),
				);
			}
			
		} else {
			if ( ! $fs->exists( $tailwind_bin ) || ! $fs->is_file( $tailwind_bin ) || ! $fs->is_readable( $tailwind_bin ) ) {
				return array(
					'success' => false,
					'message' => __( 'Tailwind CLI executable was not found in node_modules/.bin. Reinstall Node.js packages.', 'compilekit' ),
				);
			}
		}
		
		// 4) Save env vars (best-effort) to restore them later.
		$old_path       = getenv( 'PATH' );
		$old_node_path  = getenv( 'NODE_PATH' );
		$old_rayon      = getenv( 'RAYON_NUM_THREADS' );
		
		$worker_threads = max( 1, (int) get_option( 'compilekit_worker_threads', COMPILEKIT_WORKER_THREADS_DEFAULT ) );
		
		if ( $old_path === false ) {
			$old_path = '';
		}
		
		try {
			if ( $os === 'Darwin' ) {
				putenv( 'PATH=/usr/local/bin:/opt/homebrew/bin:/usr/bin:/bin' . ( $old_path !== '' ? ':' . $old_path : '' ) );
			} elseif ( $os === 'Linux' ) {
				putenv( 'PATH=/usr/local/bin:/usr/bin:/bin:/usr/sbin:/sbin' . ( $old_path !== '' ? ':' . $old_path : '' ) );
			}
			
			putenv( 'NODE_PATH=' . $node_modules );
			putenv( 'RAYON_NUM_THREADS=' . $worker_threads );
			
			// 5) Ensure Node.js is available to exectue.
			$node_check = CompileKit_Helpers::process_runner( 'node --version' );
			if ( empty( $node_check['output'] ) || ( isset( $node_check['success'] ) && $node_check['success'] === false ) ) {
				$error_output = isset( $node_check['output'] ) ? trim( (string) $node_check['output'] ) : '';
				$message      = __( 'Node.js is not installed or not available in PATH.', 'compilekit' );
				
				if ( $error_output !== '' ) {
					$message .= '<br>' . esc_html( $error_output );
				}
				
				return array(
					'success' => false,
					'message' => $message,
					'method'  => $node_check['method'] ?? '',
					'output'  => $node_check['output'] ?? '',
				);
			}
			
			// 6) Resolve paths.
			$resolved = self::resolve_compile_paths();
			if ( empty( $resolved['success'] ) ) {
				return array(
					'success' => false,
					'message' => isset( $resolved['message'] ) ? (string) $resolved['message'] : __( 'Failed to resolve compile paths.', 'compilekit' ),
				);
			}
			
			$resolved   = (array) $resolved['resolved'];
			$input_css  = (string) $resolved['input_css'];
			$output_css = (string) $resolved['output_css'];
			$cwd        = (string) $resolved['cwd'];
			
			if ( ! $fs->is_file( $input_css ) || ! $fs->is_readable( $input_css ) || (int) $fs->size( $input_css ) === 0 ) {
				return array(
					'success' => false,
					'message' => __( 'Tailwind input CSS file was not found or empty. Check input path.', 'compilekit' ),
				);
			}
			
			// 7) Build command.
			$cmd = escapeshellarg( $tailwind_bin )
			       . ' --input ' . escapeshellarg( $input_css )
			       . ' --output ' . escapeshellarg( $output_css )
			       . ' --cwd ' . escapeshellarg( $cwd );
			
			if ( CompileKit_Environment::should_minify() ) {
				$cmd .= ' --minify';
			}
			
			if ( CompileKit_Environment::should_generate_sourcemap() ) {
				$cmd .= ' --map';
			}
			
			// Windows: ensure the .cmd/.ps1 runs consistently.
			if ( $os === 'Windows' ) {
				$cmd = 'cmd /c ' . $cmd;
			}
			
			$before = $fs->exists( $output_css ) ? (int) $fs->mtime( $output_css ) : 0;
			$run    = CompileKit_Helpers::process_runner( $cmd );
			$after  = $fs->exists( $output_css ) ? (int) $fs->mtime( $output_css ) : 0;
			
			// 8) Validate output.
			$output_ok = $fs->exists( $output_css )
			             && $fs->is_file( $output_css )
			             && (int) $fs->size( $output_css ) > 0
			             && $after >= $before;
			
			
			
			if ( ( $run['success'] === true || $run['success'] === null ) && $output_ok ) {
				$success = $run['success'] ?? null;
				
				// enforce: only true|null here
				if ( $success !== true ) {
					$success = null;
				}
				
				return array(
					'success' => $success, // true|null
					'message' => __( 'Successfully compiled Tailwind CSS styles using Node.js.', 'compilekit' ),
					'method'  => $run['method'] ?? '',
					'output'  => $run['output'] ?? '',
					'path'    => $output_css,
				);
			}
			
			$error_output = isset( $run['output'] ) ? trim( (string) $run['output'] ) : '';
			$message      = __( 'Tailwind CSS compilation via Node.js has failed.', 'compilekit' );
			
			if ( $error_output !== '' ) {
				$message .= '<br>' . esc_html( $error_output );
			}
			
			return array(
				'success' => false,
				'message' => $message,
				'method'  => $run['method'] ?? '',
				'output'  => $run['output'] ?? '',
			);
			
		} finally {
			// Restore env.
			if ( $old_path !== false && $old_path !== '' ) {
				putenv( 'PATH=' . $old_path );
			} else {
				putenv( 'PATH' );
			}
			
			if ( $old_node_path !== false && $old_node_path !== '' ) {
				putenv( 'NODE_PATH=' . $old_node_path );
			} else {
				putenv( 'NODE_PATH' );
			}
			
			if ( $old_rayon !== false && $old_rayon !== '' ) {
				putenv( 'RAYON_NUM_THREADS=' . $old_rayon );
			} else {
				putenv( 'RAYON_NUM_THREADS' );
			}
		}
		
	}
	
	
	/**
	 * Triggers a Auto-Compile process on a front-end page refresh
	 *
	 * Uses the active compiler resolved by CompileKit_Environment::get_active_compiler():
	 *  - 'cli'  -> CompileKit_Compiler::compile_via_cli()
	 *  - 'node' -> CompileKit_Compiler::compile_via_node()
	 *
	 * Stores a per-user compilation status transient for Admin Bar display.
	 *
	 * @return void
	 */
	public function run_compiler_on_refresh() : void {
		// Frontend only
		if ( is_admin() ) {
			return;
		}
		
		// Skip non-page loads
		if ( ( defined( 'REST_REQUEST' ) && REST_REQUEST )
		     || ( defined( 'DOING_CRON' ) && DOING_CRON )
		     || ( defined( 'WP_CLI' ) && WP_CLI )
		     || wp_doing_ajax() ) {
			return;
		}
		
		// Admin-only trigger
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		
		if ( (int) get_option( 'compilekit_run_on_refresh', 0 ) !== 1 ) {
			return;
		}
		
		// Throttle to avoid repeated compiles on asset requests / multiple hits
		$lock_key = 'compilekit_compile_lock_' . $user_id;
		if ( get_transient( $lock_key ) ) {
			return;
		}
		set_transient( $lock_key, 1, 5 );
		
		$active_compiler = CompileKit_Environment::get_active_compiler();
		
		if ( $active_compiler === 'cli' ) {
			$result = self::compile_via_cli();
		} elseif ( $active_compiler === 'node' ) {
			$result = self::compile_via_node();
		} else {
			$result = array(
				'success' => false,
				'message' => __( 'Required compiler not detected.', 'compilekit' ),
			);
		}
		
		// Set the status for Admin Bar
		$success = array_key_exists( 'success', $result ) ? $result['success'] : false; // true|false|null
		
		if ( $success === true ) {
			$status = 'success';
		} elseif ( $success === false ) {
			$status = 'error';
		} else {
			$status = 'unknown';
		}
		
		set_transient( 'compilekit_compilation_status_' . $user_id, $status, 30 );
	}
	
}

new CompileKit_Compiler();