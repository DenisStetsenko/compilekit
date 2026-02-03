"use strict";

const gulp 				 = require('gulp');
const browserSync  = require('browser-sync').create();
const domain			 = 'dev-tailwind-wp-theme.local';
const { spawn } 	 = require('child_process');
const { exec } 		 = require('child_process');
const handleErrors = err => console.error(err);

/**
 * Global Paths
 */
const basePaths = {
	php: [ './**/*.php','./*.php' ],
	css: {
		input: './assets/css/input.css',
		output: './assets/css/output.css'
	}
};


/**
 * BrowserSync
 */
const browserSyncOptions = {
	logPrefix: domain.toUpperCase(),
	proxy: "https://" + domain,
	host: domain,
	open: false,
	notify: false,
	ghost: false,
	ui: false,
	files: [
		'./**/*.php',
		'!./assets/css/output.css'
	],
	injectChanges: true,
	snippetOptions: {
		whitelist: [ "/wp-admin/admin-ajax.php" ],
	},
	https: {
		key: "/Applications/MAMP/Library/OpenSSL/certs/" + domain + ".key",
		cert: "/Applications/MAMP/Library/OpenSSL/certs/" + domain + ".crt"
	}
};


/**
 * Watch Task
 */
gulp.task('watch', function () {
	// Init BrowserSync
	browserSync.init(browserSyncOptions, (err, bs) => {
		if ( err ) {
			console.error('BrowserSync error:', err.message);
		}
	});
	
	// Run Tailwind Watch (Only Once)
	spawn('npx', ['@tailwindcss/cli', '-i', basePaths.css.input, '-o', basePaths.css.output, '--watch'], {
		stdio: 'inherit',
		shell: true
	}).on('error', (err) => {
		console.error('⚠️ Tailwind Error:', err.message);
	});
	
	// Reload on PHP changes
	gulp.watch(basePaths.php).on('change', browserSync.reload);
	
	// Watch for Tailwind CSS changes & reload after recompilation
	gulp.watch(basePaths.css.output).on('change', browserSync.reload);
});


/**
 * Build Task
 */
gulp.task('build', function (done) {
	
	spawn('npx', ['@tailwindcss/cli', '-i', basePaths.css.input, '-o', basePaths.css.output], {
		stdio: 'inherit',
		shell: true
	}).on('error', (err) => {
		console.error('⚠️ Tailwind Error:', err.message);
	}).on('close', done);
	
});

/**
 * Default Task
 */
gulp.task('default', gulp.series('build'));