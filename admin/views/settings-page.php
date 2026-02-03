<?php
if ( !defined( 'ABSPATH' ) ) {
	exit;
}

$compilekit_css_files            = CompileKit_Admin::scan_theme_css_files();
$compilekit_current_input        = get_option( 'compilekit_input_css', '' );
$compilekit_current_output       = get_option( 'compilekit_output_css', '' );
$compilekit_compiler_mode        = get_option( 'compilekit_compiler_mode', 'auto' );
$compilekit_run_on_refresh       = get_option( 'compilekit_run_on_refresh', 0 );
$compilekit_worker_threads       = get_option( 'compilekit_worker_threads', COMPILEKIT_WORKER_THREADS_DEFAULT );
$compilekit_current_environment  = CompileKit_Environment::get_environment();
$compilekit_node_modules_exists  = CompileKit_Environment::node_modules_exists();
$compilekit_node_modules_version = CompileKit_Environment::get_node_modules_version();
$compilekit_cli_exists           = CompileKit_Environment::standalone_executable_cli_exists();
$compilekit_cli_version          = CompileKit_Environment::get_standalone_executable_cli_version();

settings_errors();
?>
<div id="compilekit-admin-settings" class="p-6 pb-12 space-y-6" data-theme="emerald">
	
	<!-- HEADER -->
	<header id="header">
		<div class="flex gap-x-2 items-start mb-3">
			<h1 class="m-0! text-3xl! font-medium! lg:text-5xl!"><?php esc_html_e( 'CompileKit', 'compilekit' ); ?></h1>
			<span class="badge badge-primary">
				<?php echo esc_html( sprintf(
					/* translators: %s: plugin version */
						__( 'v%s', 'compilekit' ),
						COMPILEKIT_VERSION
					) );
				?>
			</span>
		</div>
		<p class="m-0! text-lg! lg:text-xl!"><?php esc_html_e( 'Tailwind CSS v4 server-side compiler for WordPress', 'compilekit' ); ?></p>
	</header>
	<!-- /HEADER -->
	
	<!-- LAYOUT -->
	<div class="grid gap-6 xl:grid-cols-[1fr_1fr_minmax(430px,_1fr)]">
		
		<main id="main" class="overflow-hidden shadow-md border rounded-box bg-white xl:col-span-2 xl:row-start-1">
			<header class="pt-4.5 pb-4 px-6 border-b bg-primary flex items-center gap-1.5">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-7 stroke-white">
					<path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.281c.063.374.313.686.645.87.074.04.147.083.22.127.325.196.72.257 1.075.124l1.217-.456a1.125 1.125 0 0 1 1.37.49l1.296 2.247a1.125 1.125 0 0 1-.26 1.431l-1.003.827c-.293.241-.438.613-.43.992a7.723 7.723 0 0 1 0 .255c-.008.378.137.75.43.991l1.004.827c.424.35.534.955.26 1.43l-1.298 2.247a1.125 1.125 0 0 1-1.369.491l-1.217-.456c-.355-.133-.75-.072-1.076.124a6.47 6.47 0 0 1-.22.128c-.331.183-.581.495-.644.869l-.213 1.281c-.09.543-.56.94-1.11.94h-2.594c-.55 0-1.019-.398-1.11-.94l-.213-1.281c-.062-.374-.312-.686-.644-.87a6.52 6.52 0 0 1-.22-.127c-.325-.196-.72-.257-1.076-.124l-1.217.456a1.125 1.125 0 0 1-1.369-.49l-1.297-2.247a1.125 1.125 0 0 1 .26-1.431l1.004-.827c.292-.24.437-.613.43-.991a6.932 6.932 0 0 1 0-.255c.007-.38-.138-.751-.43-.992l-1.004-.827a1.125 1.125 0 0 1-.26-1.43l1.297-2.247a1.125 1.125 0 0 1 1.37-.491l1.216.456c.356.133.751.072 1.076-.124.072-.044.146-.086.22-.128.332-.183.582-.495.644-.869l.214-1.28Z" />
					<path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
				</svg>
				<h2 class="m-0! text-2xl! text-white! font-medium!"><?php esc_html_e( 'Plugin Configuration', 'compilekit' ); ?></h2>
			</header>
			<div class="body p-6 relative">
				
				<form method="post" action="options.php">
					<?php settings_fields( 'compilekit_settings' ); ?>
					
					<div class="group grid md:gap-x-8 md:grid-cols-2">
						<fieldset class="fieldset">
							
							<label for="compilekit_input_css" class="fieldset-label">
								<?php esc_html_e( 'Input CSS File', 'compilekit' ); ?>
							</label>
							
							<?php if ( !empty( $compilekit_css_files ) ): ?>
								<select name="compilekit_input_css" id="compilekit_input_css" class="select w-full!">
									<option value=""><?php esc_html_e( '-- Select Input CSS --', 'compilekit' ); ?></option>
									<?php foreach ( $compilekit_css_files as $compilekit_path => $compilekit_display ) : ?>
										<option value="<?php echo esc_attr( $compilekit_path ); ?>" <?php selected( $compilekit_current_input, $compilekit_path ); ?>>
											<?php echo esc_html( $compilekit_display ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else: ?>
								<input type="text" name="compilekit_input_css" id="compilekit_input_css"
								       value="<?php echo esc_attr( $compilekit_current_input ); ?>" class="input w-full"
								       placeholder="<?php echo esc_attr__( 'wp-content/themes/your-theme/assets/css/input.css', 'compilekit' ); ?>">
							<?php endif; ?>
							
							<p class="label block!"><?php echo wp_kses_post(
								sprintf(
								// translators: %s: Tailwind CSS import directive.
									__( 'Input CSS file must include &nbsp;%s', 'compilekit' ),
									'<code>' . esc_html( '@import "tailwindcss";' ) . '</code>'
								) ); ?>
							</p>
						</fieldset>
						
						<fieldset class="fieldset">
							<label for="compilekit_output_css" class="fieldset-label">
								<?php esc_html_e( 'Output CSS File', 'compilekit' ); ?>
							</label>
							
							<?php if ( !empty( $compilekit_css_files ) ): ?>
								<select name="compilekit_output_css" id="compilekit_output_css" class="select w-full!">
									<option value=""><?php esc_html_e( '-- Select Output CSS --', 'compilekit' ); ?></option>
									<?php foreach ( $compilekit_css_files as $compilekit_path => $compilekit_display ): ?>
										<option value="<?php echo esc_attr( $compilekit_path ); ?>" <?php selected( $compilekit_current_output, $compilekit_path ); ?>>
											<?php echo esc_html( $compilekit_display ); ?>
										</option>
									<?php endforeach; ?>
								</select>
							<?php else: ?>
								<input type="text" name="compilekit_output_css" id="compilekit_output_css"
								       value="<?php echo esc_attr( $compilekit_current_output ); ?>" class="input w-full"
								       placeholder="<?php echo esc_attr__( 'wp-content/themes/your-theme/assets/css/output.css', 'compilekit' ); ?>">
							<?php endif; ?>
								<p class="label"><?php esc_html_e( 'The compiled CSS will be saved to this file.', 'compilekit' ); ?></p>
						</fieldset>
					</div>
					
					<div class="md:h-7"></div>
					
					<div class="group grid md:gap-x-8 md:grid-cols-2">
						<fieldset class="fieldset">
							<label for="compilekit_environment" class="fieldset-label">
								<?php esc_html_e( 'Environment', 'compilekit' ); ?>
							</label>
							
							<select name="compilekit_environment" id="compilekit_environment" class="select w-full!">
								<option value="local" <?php selected( $compilekit_current_environment, 'local' ); ?>>
									<?php esc_html_e( 'Local Development', 'compilekit' ); ?>
								</option>
								<option value="staging" <?php selected( $compilekit_current_environment, 'staging' ); ?>>
									<?php esc_html_e( 'Staging Server', 'compilekit' ); ?>
								</option>
								<option value="live" <?php selected( $compilekit_current_environment, 'live' ); ?>>
									<?php esc_html_e( 'Live Server', 'compilekit' ); ?>
								</option>
							</select>
							
							<p class="grid! gap-y-0.5 label">
								<span class="block"><strong><?php esc_html_e( 'Local, Staging:', 'compilekit' ); ?></strong>
								<?php esc_html_e( 'Unminified CSS', 'compilekit' ); ?></span>
								
								<span class="block"><strong><?php esc_html_e( 'Live:', 'compilekit' ); ?></strong>
								<?php esc_html_e( 'Minified CSS', 'compilekit' ); ?></span>
							</p>
						</fieldset>
						
						<?php if ( !empty( $compilekit_current_input ) && !empty( $compilekit_current_output ) ) : ?>
							<fieldset class="fieldset">
								<label for="compilekit_environment" class="fieldset-label mb-2">
									<?php esc_html_e( 'Auto-Compilation Mode', 'compilekit' ); ?>
								</label>
								
								<div class="form-toggle mb-1">
									<label class="label gap-x-2 leading-snug! text-sm text-base-content font-normal" for="compilekit_run_on_refresh">
										<input type="hidden" name="compilekit_run_on_refresh" value="0">
										<input type="checkbox" name="compilekit_run_on_refresh"
										       class="toggle toggle-primary" id="compilekit_run_on_refresh" value="1" <?php checked( $compilekit_run_on_refresh, 1 ); ?>>
										<?php esc_html_e( 'Compile CSS on every page load', 'compilekit' ); ?>
									</label>
								</div>
								
								<p class="label flex-wrap gap-y-0.5">
									<?php echo wp_kses_post(
										sprintf(
											/* translators: %s: Underlined reminder text. */
											esc_html__( 'This will re-compile CSS on each page reload. %s', 'compilekit' ),
											'<u>' . esc_html__( 'Disable when finished with edits.', 'compilekit' ) . '</u>'
										) );
									?>
								</p>
							</fieldset>
						<?php endif; ?>
					</div>
					
					<div class="md:h-7"></div>
					
					<div class="group grid md:gap-x-8 md:grid-cols-2">
						<fieldset class="fieldset">
							<label for="compilekit_worker_threads" class="fieldset-label">
								<?php esc_html_e( 'Build Threads', 'compilekit' ); ?>
							</label>
							
							<div class="w-full mt-0.5">
								<input name="compilekit_worker_threads" value="<?php echo esc_attr( $compilekit_worker_threads ); ?>"
								       type="range" min="1" max="65" step="16"
								       id="compilekit_worker_threads" class="range range-primary range-xs w-full m-0!"  />
								<div class="flex justify-between px-1 mt-0.5 text-xs text-center">
									<span class="inline-block min-w-4">&bull;</span>
									<span class="inline-block min-w-4">&bull;</span>
									<span class="inline-block min-w-4">&bull;</span>
									<span class="inline-block min-w-4">&bull;</span>
									<span class="inline-block min-w-4">&bull;</span>
								</div>
								<div class="flex justify-between px-1 mt-0.5 text-xs text-center font-medium">
									<span class="inline-block min-w-4">1</span>
									<span class="inline-block min-w-4">17</span>
									<span class="inline-block min-w-4">33</span>
									<span class="inline-block min-w-4">49</span>
									<span class="inline-block min-w-4">65</span>
								</div>
							</div>
							
							<p class="label">
								<?php esc_html_e( 'Reduce if compilation fails or your system becomes slow.', 'compilekit' ); ?>
							</p>
						</fieldset>
						
					</div>
					
					<div class="divider"></div>
					
					<div class="flex gap-4 items-center justify-between flex-wrap">
						<?php submit_button( esc_html__( 'Save Settings', 'compilekit' ), 'btn', 'submit', false ); ?>
					</div>
				</form>
				
				<?php if ( !empty( $compilekit_current_input ) && !empty( $compilekit_current_output ) ) : ?>
					<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=compilekit' ) ); ?>"
					      class="absolute z-1 right-6 bottom-6">
						<?php wp_nonce_field( 'compilekit_run_manually' ); ?>
						<button type="submit" name="compilekit_run_manually" class="btn btn-primary font-medium! text-[15px]!">
							<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" class="size-4.5">
								<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
							</svg>
							<?php esc_html_e('Compile CSS Manually', 'compilekit'); ?>
						</button>
					</form>
				<?php endif; ?>
			</div>
		</main>
		
		<footer id="environment" class="overflow-hidden shadow-md rounded-box bg-white xl:col-span-2 xl:row-start-2">
			<header class="pt-4.5 pb-4 px-6 border-b bg-secondary flex items-center gap-2">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.6" stroke="currentColor" class="size-7 stroke-white">
					<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
				</svg>
				<h2 class="m-0! text-2xl! font-medium! text-white!">
					<?php echo wp_kses_post(
						sprintf(
							// translators: %s: The server path to the node_modules directory.
							__( '%s Environment Actions:', 'compilekit' ),
							'<span class="capitalize">' . $compilekit_current_environment . '</span>'
						) );
					?>
				</h2>
			</header>
			<div class="body text-sm p-6">
				<?php if (
					( ( $compilekit_compiler_mode === 'auto' || $compilekit_compiler_mode === 'cli' ) && !$compilekit_cli_exists )
					|| ( $compilekit_compiler_mode === 'node' && !$compilekit_node_modules_exists )
				) : ?>
					<div class="alert alert-soft alert-error flex gap-x-2 mb-5" role="alert">
						<svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6 shrink-0 stroke-current" fill="none" viewBox="0 0 24 24"
						     aria-label="<?php echo esc_attr__( 'Warning:', 'compilekit' ); ?>">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
						</svg>
						<div>
							<strong><?php esc_html_e( 'Note:', 'compilekit' ); ?></strong>
							<?php if ( ( $compilekit_compiler_mode === 'auto' || $compilekit_compiler_mode === 'cli' ) && !$compilekit_cli_exists ) : ?>
								<span><?php esc_html_e( 'Tailwind Standalone Executable binary is not downloaded. Click the button below to download it.', 'compilekit' ); ?></span>
							<?php endif; ?>
							<?php if ( $compilekit_compiler_mode === 'node' && !$compilekit_node_modules_exists ) : ?>
								<span><?php esc_html_e( 'Tailwind Node.js packages are not installed. Install Node.js and npm first.', 'compilekit' ); ?></span>
							<?php endif; ?>
						</div>
					</div>
				<?php endif; ?>
				
				<form method="post" action="#environment"
				      class="flex flex-wrap items-center justify-between gap-5 border bg-violet-50 rounded-box px-5 py-4">
					<?php wp_nonce_field( 'compilekit_compiler_mode' ); ?>
					<fieldset class="text-base flex flex-wrap gap-x-6 gap-y-4 items-center xl:gap-x-8">
						<label class="label">
							<input type="radio" name="compilekit_compiler_mode" class="radio radio-secondary" value="auto" <?php checked( $compilekit_compiler_mode, 'auto' ); ?>/>
							Auto (recommended)
						</label>
						
						<label class="label">
							<input type="radio" name="compilekit_compiler_mode" class="radio radio-secondary" value="node" <?php checked( $compilekit_compiler_mode, 'node' ); ?>/>
							Node.js (npm)
						</label>
						
						<label class="label">
							<input type="radio" name="compilekit_compiler_mode" class="radio radio-secondary" value="cli" <?php checked( $compilekit_compiler_mode, 'cli' ); ?>/>
							CLI (standalone executable)
						</label>
					</fieldset>
					
					<button type="submit" name="compilekit_save_compiler_mode" class="btn btn-secondary font-medium! text-[15px]!">
						<?php esc_html_e( 'Set Active Compiler', 'compilekit' ); ?>
					</button>
				</form>
				
				<ul role="list" class="text-[0.9375rem] mt-5! list-disc! ml-9 marker:text-secondary">
					<?php
					if ( $compilekit_compiler_mode === 'auto' ) {
						echo '<li>' . esc_html__( 'Prefer Tailwind CLI as a Standalone Executable binary without installing Node.js.', 'compilekit' ) . '</li>';
					}
					if ( $compilekit_current_environment === 'live' ) {
						echo '<li>' . sprintf(
									/* translators: %s: the word "enabled" in bold */
									wp_kses_post( __( 'Stylesheet minification is %s.', 'compilekit' ) ),
									'<strong><u>' . esc_html__( 'enabled', 'compilekit' ) . '</u></strong>'
						) . '</li>';
					} else {
						echo '<li>' . sprintf(
							/* translators: 1: the word "disabled" in bold, 2: reason text */
								wp_kses_post( __( 'Stylesheet minification is %1$s %2$s.', 'compilekit' ) ),
								'<strong><u>' . esc_html__( 'disabled', 'compilekit' ) . '</u></strong>',
								esc_html__( 'for testing and debugging', 'compilekit' )
							) . '</li>';
					}
					echo '<li>' . wp_kses_post( sprintf(
						/* translators: %s: installation directory path */
							__( 'Tailwind Binary and Node.js packages will be downloaded to %s.', 'compilekit' ),
							'<code>wp-content/uploads/compilekit/</code>'
						) ) . '</li>';
					?>
				</ul>
				
				<div class="divider "></div>
				
				<?php if ( $compilekit_compiler_mode === 'auto' || $compilekit_compiler_mode === 'cli' ) : ?>
				
					<div class="flex flex-wrap gap-x-5 gap-y-4">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=compilekit' ) ); ?>">
							<?php
							if ( $compilekit_cli_exists ) {
								wp_nonce_field( 'compilekit_download_cli_force' );
							} else {
								wp_nonce_field( 'compilekit_download_cli' );
							}
							?>
							<button type="submit" name="<?php echo $compilekit_cli_exists ? 'compilekit_download_cli_force' : 'compilekit_download_cli' ?>"
							        class="btn <?php echo $compilekit_cli_exists ? '' : 'btn-soft btn-success' ?> font-medium! text-[15px]!">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-4.5">
									<?php if ( $compilekit_cli_exists ) : ?>
										<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
									<?php else : ?>
										<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
									<?php endif; ?>
								</svg>
								<?php echo esc_html( $compilekit_cli_exists
									? __( 'Reinstall Tailwind Standalone CLI', 'compilekit' )
									: __( 'Download Tailwind Standalone CLI', 'compilekit' )
								);
								?>
							</button>
						</form>
						
						<?php if ( $compilekit_cli_exists ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=compilekit' ) ); ?>"
							      onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure? This will delete Tailwind Standalone CLI.', 'compilekit' ) ); ?>');">
								<?php wp_nonce_field( 'compilekit_remove_cli' ); ?>
								<button type="submit" name="compilekit_remove_cli" class="btn btn-soft btn-error font-medium! text-[15px]!">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4.5">
										<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
									</svg>
									<?php esc_html_e( 'Delete Tailwind Standalone CLI', 'compilekit' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
				
				<?php elseif ( $compilekit_compiler_mode === 'node' ) : ?>
				
					<div class="flex flex-wrap gap-x-5 gap-y-4">
						<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=compilekit' ) ); ?>">
							<?php wp_nonce_field( 'compilekit_download_modules' ); ?>
							<button type="submit" name="compilekit_download_modules"
							        class="btn <?php echo $compilekit_node_modules_exists ? '' : 'btn-soft btn-success' ?> font-medium! text-[15px]!">
								<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.7" stroke="currentColor" class="size-4.5">
									<?php if ( $compilekit_node_modules_exists ) : ?>
										<path stroke-linecap="round" stroke-linejoin="round" d="M19.5 12c0-1.232-.046-2.453-.138-3.662a4.006 4.006 0 0 0-3.7-3.7 48.678 48.678 0 0 0-7.324 0 4.006 4.006 0 0 0-3.7 3.7c-.017.22-.032.441-.046.662M19.5 12l3-3m-3 3-3-3m-12 3c0 1.232.046 2.453.138 3.662a4.006 4.006 0 0 0 3.7 3.7 48.656 48.656 0 0 0 7.324 0 4.006 4.006 0 0 0 3.7-3.7c.017-.22.032-.441.046-.662M4.5 12l3 3m-3-3-3 3" />
									<?php else : ?>
										<path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" />
									<?php endif; ?>
								</svg>
								<?php echo esc_html( $compilekit_node_modules_exists
									? __( 'Reinstall Tailwind Node.js packages', 'compilekit' )
									: __( 'Install Tailwind Node.js packages', 'compilekit' )
								);
								?>
							</button>
						</form>
						
						<?php if ( $compilekit_node_modules_exists ) : ?>
							<form method="post" action="<?php echo esc_url( admin_url( 'admin.php?page=compilekit' ) ); ?>"
							      onsubmit="return confirm('<?php echo esc_js( __( 'Are you sure? This will delete Tailwind Node.js packages (node_modules and related files).', 'compilekit' ) ); ?>');">
								<?php wp_nonce_field( 'compilekit_remove_modules' ); ?>
								<button type="submit" name="compilekit_remove_modules" class="btn btn-soft btn-error font-medium! text-[15px]!">
									<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-4.5">
										<path stroke-linecap="round" stroke-linejoin="round" d="m14.74 9-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 0 1-2.244 2.077H8.084a2.25 2.25 0 0 1-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 0 0-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 0 1 3.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 0 0-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 0 0-7.5 0" />
									</svg>
									<?php esc_html_e( 'Delete Tailwind Node.js packages', 'compilekit' ); ?>
								</button>
							</form>
						<?php endif; ?>
					</div>
				
				<?php endif; ?>
				
			</div>
		</footer>
		
		<aside id="plugin-info" class="overflow-hidden shadow-md rounded-box bg-white xl:col-start-3 xl:row-start-1">
			<header class="pt-4.5 pb-4 px-6 border-b bg-slate-200 flex items-center gap-1.5">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-7">
					<path stroke-linecap="round" stroke-linejoin="round" d="M9.879 7.519c1.171-1.025 3.071-1.025 4.242 0 1.172 1.025 1.172 2.687 0 3.712-.203.179-.43.326-.67.442-.745.361-1.45.999-1.45 1.827v.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 5.25h.008v.008H12v-.008Z" />
				</svg>
				<h2 class="m-0! text-2xl! font-medium!"><?php esc_html_e( 'How It Works', 'compilekit' ); ?></h2>
			</header>
			<div class="body text-sm p-6">
				<p><?php esc_html_e( 'CompileKit compiles Tailwind CSS v4 on the server using one of two available compilers: Standalone Executable or Node.js (npm).', 'compilekit' ); ?></p>
				<p><?php esc_html_e( 'You can trigger compilation manually from the Admin UI or by enabling Auto-Compilation Mode to compile styles on each front-end page refresh.', 'compilekit' ); ?></p>
				<div class="divider"></div>
				<h3 class="mt-0! mb-1.5!"><?php esc_html_e( 'Local Development', 'compilekit' ); ?></h3>
				<p class="mb-0!"><?php esc_html_e( 'Use the Node.js compiler and install Tailwind npm packages for an advanced IDE assistance.', 'compilekit' ); ?></p>
				<p><?php esc_html_e( 'Outputs an unminified stylesheet with a source map.', 'compilekit' ); ?></p>
				
				<h3 class="mt-6! mb-1.5!"><?php esc_html_e( 'Staging Environment', 'compilekit' ); ?></h3>
				<p class="mb-0!"><?php esc_html_e( 'Use the most reliable compiler available on the server.', 'compilekit' ); ?></p>
				<p><?php esc_html_e( 'Outputs an unminified stylesheet without a source map.', 'compilekit' ); ?></p>
				
				
				<h3 class="mt-6! mb-1.5!"><?php esc_html_e( 'Live Environment', 'compilekit' ); ?></h3>
				<p class="mb-0!"><?php esc_html_e( 'Use the most reliable compiler available on the server.', 'compilekit' ); ?></p>
				<p><?php esc_html_e( 'Outputs a minified stylesheet without a source map.', 'compilekit' ); ?></p>
				
			</div>
		</aside>
		
		<aside id="system-info" class="overflow-hidden shadow-md rounded-box bg-white xl:col-start-3 xl:row-start-2">
			<header class="pt-4.5 pb-4 px-6 border-b bg-slate-200 flex items-center gap-1.5">
				<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" class="size-7">
					<path stroke-linecap="round" stroke-linejoin="round" d="m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z" />
				</svg>
				<h2 class="m-0! text-2xl! font-medium!"><?php esc_html_e( 'System Info', 'compilekit' ); ?></h2>
			</header>
			<div class="body p-2">
				
				<ul class="text-base list m-0">
					
					<li class="list-row items-center mb-0!">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12a7.5 7.5 0 0 0 15 0m-15 0a7.5 7.5 0 1 1 15 0m-15 0H3m16.5 0H21m-1.5 0H12m-8.457 3.077 1.41-.513m14.095-5.13 1.41-.513M5.106 17.785l1.15-.964m11.49-9.642 1.149-.964M7.501 19.795l.75-1.3m7.5-12.99.75-1.3m-6.063 16.658.26-1.477m2.605-14.772.26-1.477m0 17.726-.26-1.477M10.698 4.614l-.26-1.477M16.5 19.794l-.75-1.299M7.5 4.205 12 12m6.894 5.785-1.149-.964M6.256 7.178l-1.15-.964m15.352 8.864-1.41-.513M4.954 9.435l-1.41-.514M12.002 12l-3.75 6.495" />
						</svg>
						
						<div><h3 class="font-normal! text-base! m-0! inline-block!"><?php esc_html_e( 'Plugin Version', 'compilekit' ); ?></h3></div>
						<div><span class="badge badge-soft badge-outline badge-primary"><?php echo esc_html( COMPILEKIT_VERSION ); ?></span></div>
					</li>
					
					<li class="list-row items-center mb-0!">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M5.25 14.25h13.5m-13.5 0a3 3 0 0 1-3-3m3 3a3 3 0 1 0 0 6h13.5a3 3 0 1 0 0-6m-16.5-3a3 3 0 0 1 3-3h13.5a3 3 0 0 1 3 3m-19.5 0a4.5 4.5 0 0 1 .9-2.7L5.737 5.1a3.375 3.375 0 0 1 2.7-1.35h7.126c1.062 0 2.062.5 2.7 1.35l2.587 3.45a4.5 4.5 0 0 1 .9 2.7m0 0a3 3 0 0 1-3 3m0 3h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Zm-3 6h.008v.008h-.008v-.008Zm0-6h.008v.008h-.008v-.008Z" />
						</svg>
						
						<div><h3 class="font-normal! text-base! m-0! inline-block!"><?php esc_html_e( 'Environment', 'compilekit' ); ?></h3></div>
						<div>
							<?php
							$compilekit_env_labels = array(
								'local'   => '<span class="badge badge-warning">' . esc_html__( 'Local', 'compilekit' ) . '</span>',
								'staging' => '<span class="badge badge-info">' . esc_html__( 'Staging', 'compilekit' ) . '</span>',
								'live'    => '<span class="badge badge-success">' . esc_html__( 'Live', 'compilekit' ) . '</span>',
							);
							
							echo wp_kses_post( $compilekit_env_labels[ $compilekit_current_environment ] ?? '' );
							?>
						</div>
					</li>
					
					<li class="list-row items-center mb-0!">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
						</svg>
						
						<div><h3 class="font-normal! text-base! m-0! inline-block!"><?php esc_html_e( 'Auto-Compilation', 'compilekit' ); ?></h3></div>
						<div>
							<?php echo wp_kses_post( $compilekit_run_on_refresh
								? '<span class="badge badge-outline badge-soft badge-error">' . esc_html__( 'Enabled', 'compilekit' ) . '</span>'
								: '<span class="badge ">' . esc_html__( 'Disabled', 'compilekit' ) . '</span>'
							);
							?>
						</div>
					</li>
					
					<li class="list-row items-center mb-0!">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="m6.75 7.5 3 2.25-3 2.25m4.5 0h3m-9 8.25h13.5A2.25 2.25 0 0 0 21 18V6a2.25 2.25 0 0 0-2.25-2.25H5.25A2.25 2.25 0 0 0 3 6v12a2.25 2.25 0 0 0 2.25 2.25Z" />
						</svg>
						
						<div class="flex gap-x-1.5">
							<h3 class="font-normal! text-base! m-0! inline-block!"><?php esc_html_e( 'Standalone CLI Binary', 'compilekit' ); ?></h3>
							<?php if ( $compilekit_cli_exists && $compilekit_cli_version !== '' ) : ?>
								<span class="badge badge-secondary badge-xs shrink-0"><?php echo esc_html( $compilekit_cli_version ); ?></span>
							<?php endif; ?>
						</div>
						<div>
							<?php echo wp_kses_post( $compilekit_cli_exists
								? '<span class="badge badge-outline badge-soft badge-secondary">' . esc_html__( 'Installed', 'compilekit' ) . '</span>'
								: '<span class="badge">' . esc_html__( 'Not Installed', 'compilekit' ) . '</span>'
							);
							?>
						</div>
					</li>
					
					<li class="list-row items-center mb-0!">
						<svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" class="size-6">
							<path stroke-linecap="round" stroke-linejoin="round" d="M20.25 6.375c0 2.278-3.694 4.125-8.25 4.125S3.75 8.653 3.75 6.375m16.5 0c0-2.278-3.694-4.125-8.25-4.125S3.75 4.097 3.75 6.375m16.5 0v11.25c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125V6.375m16.5 0v3.75m-16.5-3.75v3.75m16.5 0v3.75C20.25 16.153 16.556 18 12 18s-8.25-1.847-8.25-4.125v-3.75m16.5 0c0 2.278-3.694 4.125-8.25 4.125s-8.25-1.847-8.25-4.125" />
						</svg>
						<div class="flex gap-x-1.5">
							<h3 class="font-normal! text-base! m-0! inline-block!"><?php esc_html_e( 'Node.js Packages', 'compilekit' ); ?></h3>
							<?php if ( $compilekit_node_modules_exists && $compilekit_node_modules_version !== '' ) : ?>
								<span class="badge badge-secondary badge-xs shrink-0"><?php echo esc_html( $compilekit_node_modules_version ); ?></span>
							<?php endif; ?>
						</div>
						<div>
							<?php echo wp_kses_post( $compilekit_node_modules_exists
								? '<span class="badge badge-outline badge-soft badge-secondary">' . esc_html__( 'Installed', 'compilekit' ) . '</span>'
								: '<span class="badge">' . esc_html__( 'Not Installed', 'compilekit' ) . '</span>'
							);
							?>
						</div>
					</li>
					
				
				</ul>
				
			</div>
		</aside>
		
	</div>
	<!-- /LAYOUT -->
	
</div>