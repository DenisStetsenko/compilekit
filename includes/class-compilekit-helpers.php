<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class CompileKit_Helpers {
	
	/**
	 * Run a command and capture output + exit code.
	 *
	 * Priority:
	 * 1) exec()      (output + exit code)
	 * 2) shell_exec() (output only, no exit code - unreliable)
	 * • success === false = definitely failed
	 * • success === true  = definitely succeeded
	 * • success === null  = unknown (shell_exec), not a failure signal
	 *
	 * @param string $command Command to execute
	 * @return array
	 */
	public static function process_runner( string $command ) : array {
		$command = trim( $command );
		if ( $command === '' ) {
			return array(
				'success'   => false,
				'method'    => 'none',
				'exit_code' => 1,
				'output'    => 'Empty command.',
			);
		}
		
		// 1) exec() - good enough, has exit code
		// ======================================
		if ( is_callable( 'exec' ) ) {
			$lines = array();
			$code  = 0;
			exec( $command . ' 2>&1', $lines, $code );
			
			return array(
				'success'   => ( $code === 0 ),
				'method'    => 'exec',
				'exit_code' => $code,
				'output'    => trim( implode( "\n", $lines ) ),
			);
		}
		
		
		// 2) shell_exec() - last resort, unreliable, no exit code, better than nothing.
		// =======================================================
		if ( is_callable( 'shell_exec' ) ) {
			$out = shell_exec( $command . ' 2>&1' );
			
			// false = error
			if ( $out === false ) {
				return array(
					'success'   => null,
					'method'    => 'shell_exec',
					'exit_code' => null,
					'output'    => 'shell_exec() failed (pipe could not be established).',
				);
			}
			
			// null = error OR no output
			if ( $out === null ) {
				return array(
					'success'   => null,
					'method'    => 'shell_exec',
					'exit_code' => null,
					'output'    => '',
				);
			}
			
			$out = trim( (string) $out );
			
			return array(
				'success'   => null,
				'method'    => 'shell_exec',
				'exit_code' => null,
				'output'    => $out,
			);
		}
		
		return array(
			'success'   => false,
			'method'    => 'none',
			'exit_code' => 1,
			'output'    => 'No process runner functions available.',
		);
	}
	
	
	/**
	 * Checks if the system has the ability to execute external processes via `exec` or `shell_exec`.
	 */
	public static function process_runner_init() : bool {
		return is_callable( 'exec' ) || is_callable( 'shell_exec' );
	}
	
	
	/**
	 * Retrieves the directory path for storing CompileKit modules within the WordPress uploads directory.
	 */
	public static function compilekit_modules_dir() : string {
		$upload_dir = wp_upload_dir();
		return trailingslashit( $upload_dir['basedir'] ) . 'compilekit/';
	}
	
	
	/**
	 * Detects the operating system family of the server running the PHP script.
	 */
	public static function detect_os_family() : string {
		// PHP_OS_FAMILY returns: 'Windows', 'BSD', 'Darwin', 'Solaris', 'Linux', 'Unknown'
		return PHP_OS_FAMILY;
	}
	
	
	/**
	 * Detects the architecture of the machine running the PHP script.
	 */
	public static function detect_arch() : string {
		// Common: arm64, aarch64, x86_64, amd64
		return strtolower( php_uname( 'm' ) );
	}
	
	
	/**
	 * Detect Linux libc: 'musl' (Alpine) or 'glibc' (default).
	 */
	public static function detect_linux_libc() : string {
		if ( self::detect_os_family() !== 'Linux' ) {
			return '';
		}
		
		// Alpine Linux marker
		if ( file_exists( '/etc/alpine-release' ) ) {
			return 'musl';
		}
		
		// musl loader existence (common)
		foreach ( glob( '/lib/ld-musl-*.so.1' ) ?: array() as $f ) {
			if ( is_string( $f ) && $f !== '' ) {
				return 'musl';
			}
		}
		
		return 'glibc';
	}
	
	
	/**
	 * Returns standalone Tailwind binary filename for current OS/arch.
	 * Example: tailwindcss-macos-arm64, tailwindcss-linux-x64, tailwindcss-windows-x64.exe
	 */
	public static function tailwind_standalone_filename() : string {
		$os   = self::detect_os_family();
		$arch = self::detect_arch();
		
		$is_arm = in_array( $arch, array( 'arm64', 'aarch64' ), true );
		
		if ( $os === 'Darwin' ) {
			return $is_arm ? 'tailwindcss-macos-arm64' : 'tailwindcss-macos-x64';
		}
		
		if ( $os === 'Linux' ) {
			$is_musl = ( self::detect_linux_libc() === 'musl' );
			
			if ( $is_arm ) {
				return $is_musl ? 'tailwindcss-linux-arm64-musl' : 'tailwindcss-linux-arm64';
			}
			
			return $is_musl ? 'tailwindcss-linux-x64-musl' : 'tailwindcss-linux-x64';
		}
		
		if ( $os === 'Windows' ) {
			// Tailwind standalone uses .exe on Windows
			return 'tailwindcss-windows-x64.exe';
		}
		
		return '';
	}
	
	
	/**
	 * Generates the download URL for a specified Tailwind CSS standalone executable file.
	 */
	public static function tailwind_standalone_download_url( string $filename ) : string {
		if ( $filename === '' ) {
			return '';
		}
		
		// Official "latest/download" endpoint
		return 'https://github.com/tailwindlabs/tailwindcss/releases/latest/download/' . $filename;
	}
	
	
	/**
	 * Retrieves the expected SHA-256 hash for a given asset file from the TailwindCSS `sha256sums.txt` file.
	 * This method fetches the latest `sha256sums.txt` file from the official TailwindCSS GitHub releases page,
	 * parses its content, and matches the provided filename to return the corresponding hash.
	 *
	 * @param string $asset_filename The name of the asset file to retrieve the SHA-256 hash for.
	 *                                This should match the filename as listed in the `sha256sums.txt` file.
	 *                                Example: 'tailwindcss-linux-x64'.
	 * @return string The 64-character SHA-256 hash for the asset file if found, or an empty string on failure or mismatch.
	 */
	public static function tailwind_expected_sha256_from_sums( string $asset_filename ) : string {
		$asset_filename = trim( $asset_filename );
		if ( $asset_filename === '' ) {
			return '';
		}
		
		$url = 'https://github.com/tailwindlabs/tailwindcss/releases/latest/download/sha256sums.txt';
		$response = wp_remote_get( $url, array(
				'timeout'    => 30,
				'user-agent' => 'WordPress/compilekit',
			)
		);
		
		if ( is_wp_error( $response ) ) {
			return '';
		}
		
		if ( (int) wp_remote_retrieve_response_code( $response ) !== 200 ) {
			return '';
		}
		
		$body = wp_remote_retrieve_body( $response );
		if ( $body === '' ) {
			return '';
		}
		
		$lines = preg_split( "/\r\n|\n|\r/", $body );
		if ( ! is_array( $lines ) ) {
			return '';
		}
		
		foreach ( $lines as $line ) {
			$line = trim( (string) $line );
			if ( $line === '' ) {
				continue;
			}
			
			// Expected: "<64hex><space(s)>./filename" OR "<64hex><space(s)>filename"
			if ( preg_match( '/^([a-f0-9]{64})\s+(.+)$/i', $line, $m ) ) {
				$hash = strtolower( $m[1] );
				$file = trim( $m[2] );
				
				// Normalize "./tailwindcss-linux-x64" => "tailwindcss-linux-x64"
				$file = preg_replace( '#^\./#', '', $file );
				
				if ( $file === $asset_filename ) {
					return $hash;
				}
			}
		}
		
		return '';
	}
	
	
	/**
	 * Verifies the SHA-256 hash of a file against an expected value.
	 */
	public static function verify_file_sha256( string $path, string $expected_sha256 ) : bool {
		$expected = strtolower( trim( $expected_sha256 ) );
		
		if ( ! preg_match( '/^[a-f0-9]{64}$/', $expected ) ) {
			return false;
		}
		
		if ( ! file_exists( $path ) || ! is_readable( $path ) ) {
			return false;
		}
		
		$actual = hash_file( 'sha256', $path );
		if ( ! is_string( $actual ) ) {
			return false;
		}
		
		$actual = strtolower( $actual );
		
		return hash_equals( $expected, $actual );
	}
	
	
	/**
	 * Retrieves the global WordPress filesystem object, initializing it if necessary.
	 *
	 * This method checks whether the global `$wp_filesystem` object is already available.
	 * If it's not, it attempts to load the necessary WordPress file handling functions
	 * and initializes the `$wp_filesystem` object.
	 *
	 * @return object|null The WordPress filesystem object if successfully initialized, or null otherwise.
	 * @global object $wp_filesystem The WordPress filesystem object.
	 */
	public static function fs() {
		global $wp_filesystem;
		
		if ( $wp_filesystem ) {
			return $wp_filesystem;
		}
		
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}
		
		WP_Filesystem();
		
		return $wp_filesystem;
	}
	
}