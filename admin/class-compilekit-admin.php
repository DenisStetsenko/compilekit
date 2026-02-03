<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

class CompileKit_Admin {
	
	public function __construct() {
		add_action( 'admin_menu', array($this, 'add_menu_page') );
		add_action( 'admin_init', array($this, 'register_settings') );
		add_action( 'admin_init', array($this, 'handle_environment_actions') );
		add_action( 'admin_enqueue_scripts', array($this, 'enqueue_admin_assets'), 100 );
		add_action( 'admin_notices', array( $this, 'handle_compile_alerts' ) );
		add_action( 'admin_bar_menu', array($this, 'compilekit_admin_bar'), 100 );
	}
	
	/**
	 * Handles the display of a warning notice in the WordPress admin dashboard if auto-compilation mode is active.
	 *
	 * @return void
	 */
	public function handle_compile_alerts() : void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		if ( (int) get_option( 'compilekit_run_on_refresh', 0 ) !== 1 ) {
			return;
		}
		
		$screen = function_exists( 'get_current_screen' ) ? get_current_screen() : null;
		if ( ! $screen || strpos( $screen->id, 'compilekit' ) === false ) {
			return;
		}
		
		echo '<div class="notice notice-warning"><p><strong>'
		     . esc_html__( 'Auto-Compilation Mode is currently ACTIVE. Turn it OFF when finish making changes.', 'compilekit' )
		     . '</strong></p></div>';
	}
	
	/**
	 * Displays a settings notice in the WordPress admin dashboard.
	 *
	 * @param array $result An associative array containing the message and success status.
	 * @param string $type (optional) The type of notice to display. Defaults to 'success' or 'error' based on the $result['success'] value.
	 * @return void
	 */
	private function add_admin_notice_from_result( array $result, string $type = '' ) : void {
		$message = isset( $result['message'] ) ? (string) $result['message'] : __( 'Unknown result.', 'compilekit' );
		$success = array_key_exists( 'success', $result ) ? $result['success'] : false; // true|false|null
		
		if ( $type === '' ) {
			if ( $success === true ) {
				$type = 'success';
			} elseif ( $success === null ) {
				$type = 'success';
			} else {
				$type = 'error';
			}
		}
		
		add_settings_error(
			'compilekit_alerts',
			'compilekit_alert',
			$message,
			$type
		);
	}
	
	
	/**
	 * Registers a new menu page in the WordPress admin dashboard.
	 */
	public function add_menu_page() : void {
		add_menu_page(
			'CompileKit',
			'CompileKit',
			'manage_options',
			'compilekit',
			array($this, 'compilekit_settings_page'),
			COMPILEKIT_URL . 'assets/img/icon.png'
		);
	}
	
	
	/**
	 * Registers settings for the CompileKit plugin.
	 */
	public function register_settings() : void {
		register_setting( 'compilekit_settings', 'compilekit_input_css', array(
			'sanitize_callback' => 'wp_strip_all_tags',
		) );
		
		register_setting( 'compilekit_settings', 'compilekit_output_css', array(
			'sanitize_callback' => 'wp_strip_all_tags',
		) );
		
		register_setting( 'compilekit_settings', 'compilekit_environment', array(
			'sanitize_callback' => array( $this, 'sanitize_environment' ),
		) );
		
		register_setting( 'compilekit_settings', 'compilekit_run_on_refresh', array(
			'sanitize_callback' => 'rest_sanitize_boolean',
		) );
		
		register_setting( 'compilekit_settings', 'compilekit_worker_threads', array(
			'sanitize_callback' => 'absint',
		) );
	}
	
	
	/**
	 * Sanitize environment option value.
	 *
	 * @param mixed $value
	 * @return string
	 */
	public function sanitize_environment( $value ) : string {
		$allowed = array('local', 'staging', 'live');
		$value   = is_string( $value ) ? sanitize_key( $value ) : '';
		
		if ( ! in_array( $value, $allowed, true ) ) {
			return 'local';
		}
		
		return $value;
	}
	
	
	/**
	 * Enqueue Tailwind CSS and custom admin styles
	 * Loads admin page styles only.
	 */
	public function enqueue_admin_assets($hook) : void {
		if ( $hook !== 'toplevel_page_compilekit' ) {
			return;
		}
		
		wp_enqueue_style( 'compilekit-admin', COMPILEKIT_URL . 'assets/css/admin-styles.css', array(), filemtime( COMPILEKIT_PATH . 'assets/css/admin-styles.css' ) );
		wp_enqueue_style( 'compilekit-output', COMPILEKIT_URL . 'assets/css/output.css', array('common', 'forms'), filemtime( COMPILEKIT_PATH . 'assets/css/output.css' ) );
	}
	
	
	/**
	 * Handle environment-specific actions like downloading or removing node_modules
	 */
	public function handle_environment_actions() : void {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) {
			return;
		}
		
		$page = isset( $_GET['page'] ) ? sanitize_text_field( wp_unslash( $_GET['page'] ) ) : '';
		if ( $page !== 'compilekit' ) {
			return;
		}
		
		// Download Node.js npm packages
		if ( isset( $_POST['compilekit_download_modules'] ) ) {
			check_admin_referer( 'compilekit_download_modules' );
			$result = CompileKit_Environment::download_node_modules();
			$this->add_admin_notice_from_result( $result );
			
		} elseif ( isset( $_POST['compilekit_remove_modules'] ) ) {
			// Remove Node.js npm packages
			check_admin_referer( 'compilekit_remove_modules' );
			$result = CompileKit_Environment::remove_node_modules();
			$this->add_admin_notice_from_result( $result );
			
		} elseif ( isset( $_POST['compilekit_download_cli'] ) ) {
			// Download Tailwind Standalone CLI
			check_admin_referer( 'compilekit_download_cli' );
			$result = CompileKit_Environment::download_standalone_executable_cli();
			$this->add_admin_notice_from_result( $result );
			
		} elseif ( isset( $_POST['compilekit_download_cli_force'] ) ) {
			// Force Download Tailwind Standalone CLI
			check_admin_referer( 'compilekit_download_cli_force' );
			$result = CompileKit_Environment::download_standalone_executable_cli(true);
			$this->add_admin_notice_from_result( $result );
			
		} elseif ( isset( $_POST['compilekit_remove_cli'] ) ) {
			// Remove Tailwind Standalone CLI
			check_admin_referer( 'compilekit_remove_cli' );
			$result = CompileKit_Environment::remove_standalone_executable_cli();
			$this->add_admin_notice_from_result( $result );
			
		} elseif ( isset( $_POST['compilekit_save_compiler_mode'] ) ) {
			// Set Compilation Mode
			check_admin_referer( 'compilekit_compiler_mode' );
			
			$mode = isset( $_POST['compilekit_compiler_mode'] )
				? sanitize_text_field( wp_unslash( $_POST['compilekit_compiler_mode'] ) )
				: 'auto';
			
			if ( ! in_array( $mode, array( 'auto', 'node', 'cli' ), true ) ) {
				$mode = 'auto';
			}
			
			update_option( 'compilekit_compiler_mode', $mode );
			
			if ( $mode === 'node' ) {
				$active_compiler = __('Node.js (npm)', 'compilekit');
			} elseif ( $mode === 'cli' ) {
				$active_compiler = __('Tailwind Standalone CLI', 'compilekit');
			} else {
				$active_compiler = __('Auto', 'compilekit');
			}
			
			$this->add_admin_notice_from_result( array(
				'success' => true,
				/* translators: %s: active compiler mode label (e.g. "Node.js (npm)") */
				'message' => sprintf( __( 'Compiler Mode: %s', 'compilekit' ), $active_compiler ),
			), 'info' );
			
		} elseif ( isset( $_POST['compilekit_run_manually'] ) ) {
			// Compile CSS Manually
			check_admin_referer( 'compilekit_run_manually' );
			
			$active_compiler = CompileKit_Environment::get_active_compiler();
			$mode            = get_option( 'compilekit_compiler_mode', 'auto' );
			
			if ( $active_compiler === 'cli' ) {
				$result = CompileKit_Compiler::compile_via_cli();
				
				// Auto mode: if Standalone CLI fails, try Node.js fallback (if installed).
				$success = array_key_exists( 'success', $result ) ? $result['success'] : false; // true|false|null
				
				if ( $mode === 'auto' && $success === false ) {
					$cli_message = (string) ( $result['message'] ?? '' );
					
					if ( CompileKit_Environment::node_modules_exists() ) {
						$node = CompileKit_Compiler::compile_via_node();
						$node_success = array_key_exists( 'success', $node ) ? $node['success'] : false; // true|false|null
						
						if ( $node_success === true || $node_success === null ) {
							$result = $node;
						} else {
							$node_message = (string) ( $node['message'] ?? '' );
							$result['message'] = sprintf(
							/* translators: 1: original Standalone CLI message, 2: Node.js compiler message */
								__( '%1$s<br><br>Node.js fallback failed:<br>%2$s', 'compilekit' ),
								$cli_message,
								$node_message
							);
						}
					}
				}
				
				$this->add_admin_notice_from_result( $result );
				
			} elseif ( $active_compiler === 'node' ) {
				$result = CompileKit_Compiler::compile_via_node();
				$this->add_admin_notice_from_result( $result );
				
			} else {
				if ( $mode === 'node' ) {
					$required_compiler = __('Install Tailwind Node.js packages first.', 'compilekit');
				} elseif ( $mode === 'cli' ) {
					$required_compiler = __('Download Tailwind Standalone CLI first.', 'compilekit');
				} else {
					$required_compiler = __('Download Tailwind Standalone CLI or Node.js (npm) packages first.', 'compilekit');
				}
				
				$this->add_admin_notice_from_result( array(
					'success' => false,
					/* translators: %s: required dependency message (e.g. "Download Tailwind Standalone CLI first.") */
					'message' => sprintf( __( 'Compiler not detected. %s', 'compilekit' ), $required_compiler ),
				), 'warning' );
			}
		}
		
	}
	
	
	/**
	 * Loads and displays the settings page for the plugin in the WordPress admin dashboard.
	 *
	 * @return void
	 */
	public function compilekit_settings_page() : void {
		require_once COMPILEKIT_PATH . 'admin/views/settings-page.php';
	}
	
	
	/**
	 * Scans the theme's assets directory for CSS files and returns an array of paths.
	 *
	 * This method looks for all the .css files in the current theme.
	 * It generates a list of paths relative to the WordPress root directory and assigns
	 * human-readable labels for use in the UI.
	 *
	 * @return array
	 */
	public static function scan_theme_css_files() : array {
		$theme_dir = wp_normalize_path( trailingslashit( get_stylesheet_directory() ) );
		
		$fs = CompileKit_Helpers::fs();
		if ( ! $fs || ! $fs->is_dir( $theme_dir ) ) {
			return array();
		}
		
		$files           = array();
		$theme_style_css = wp_normalize_path( $theme_dir . 'style.css' );
		
		$iterator = new RecursiveIteratorIterator(
			new RecursiveDirectoryIterator( $theme_dir, FilesystemIterator::SKIP_DOTS )
		);
		
		foreach ( $iterator as $file ) {
			if ( ! $file->isFile() ) {
				continue;
			}
			
			if ( strtolower( $file->getExtension() ) !== 'css' ) {
				continue;
			}
			
			$full_path = wp_normalize_path( $file->getPathname() );
			
			// Exclude main theme style.css (contains theme header)
			if ( $full_path === $theme_style_css ) {
				continue;
			}
			
			// ABSPATH-relative value (this is what you should save in your option)
			if ( strpos( $full_path, $theme_dir ) !== 0 ) {
				continue;
			}
			
			// Theme-relative path, good for saving in options:
			$rel = ltrim( substr( $full_path, strlen( $theme_dir ) ), '/' ); // assets/styles/src/input.css
			
			$files[ $rel ] = $rel;
		}
		
		asort( $files, SORT_NATURAL | SORT_FLAG_CASE );
		
		return $files;
	}
	
	
	/**
	 * Adds a custom node to the WordPress admin bar to display CompileKit status.
	 *
	 * @param WP_Admin_Bar $wp_admin_bar Instance of the WordPress admin bar.
	 */
	public function compilekit_admin_bar( \WP_Admin_Bar $wp_admin_bar ) : void {
		if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) || is_admin() ) {
			return;
		}
		
		if ( (int) get_option( 'compilekit_run_on_refresh', 0 ) !== 1 ) {
			return;
		}
		
		$user_id = get_current_user_id();
		if ( $user_id <= 0 ) {
			return;
		}
		
		$notice_key = 'compilekit_compilation_status_' . $user_id;
		$status     = (string) get_transient( $notice_key );
		
		// Determine Admin Bar Title based on the status
		if ( $status === 'success' ) {
			$title_text = __( 'CSS Successfully Compiled ✅', 'compilekit' );
		} elseif ( $status === 'error' ) {
			$title_text = __( 'CSS Compile Error ❌', 'compilekit' );
		} elseif ( $status === 'unknown' ) {
			$title_text = __( 'CSS Compile Status Unknown ⚠️', 'compilekit' );
		} else {
			$title_text = __( 'Auto-Compilation Mode', 'compilekit' );
		}
		
		$icon_url = COMPILEKIT_URL . 'assets/img/icon.png';
		$title    = sprintf(
			'<img src="%s" style="display:inline; height:20px; width:auto; margin-right:2px; vertical-align:middle;" alt="" /> %s',
			esc_url( $icon_url ),
			esc_html( $title_text )
		);
		
		$wp_admin_bar->add_node( array(
			'id'    => 'compilekit-status',
			'title' => $title,
			'href'  => esc_url( admin_url( 'admin.php?page=compilekit' ) ),
			'meta'  => array(
				'class' => 'compilekit-status',
			),
		) );
		
		// Clear status so it doesn't persist on next normal refresh
		if ( $status !== '' ) {
			delete_transient( $notice_key );
		}
	}

}

new CompileKit_Admin();