=== CompileKit - Tailwind CSS Compiler ===
Contributors: mstrdh
Tags: theme, tailwind, css, cli, compiler
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 7.4
Stable tag: 3.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Compile Tailwind CSS with a server-side compiler. Provides an admin UI, auto-compilation mode, and environment-aware output.

== Description ==
*	Compiles Tailwind CSS v4 directly on the server.
*	Choose a compiler: Standalone CLI binary or Node.js (npm) packages.
*	Run compilation manually from the Admin UI or enable Auto-Compilation Mode for front-end refresh builds.
*	Configure input and output CSS paths inside the active theme.
*	Environment-aware output: Local/Staging is unminified (optional source maps), Live is minified.


== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Go to "CompileKit" in the admin menu.
4. Install the Standalone CLI binary or Tailwind npm packages, set paths, and run the compiler.


== External services ==
This plugin can connect to GitHub to download the Tailwind Standalone CLI binary when requested in the admin UI.
An HTTPS request to GitHub is made only when an admin clicks “Download Tailwind Standalone CLI” or “Reinstall Tailwind Standalone CLI”. The request uses the user-agent “WordPress/compilekit”. Learn more about [privacy policy](https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement) and [terms of service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service).


== Changelog ==

= 3.0.0 =
* New admin UI (redesigned settings and workflow)
* Added compiler selection: Auto, Standalone CLI, or Node.js (npm)
* Environment-aware builds [Local/Staging is unminified, optional source maps || Live is minified]
* Improved diagnostics and admin notices for compiler failures
* Improved compiler workflow and overall stability
* Refactored architecture for reliability and maintainability

= 2.2.0 =
* Minor updates

= 2.1.9 =
* Added an option to change worker threads count

= 2.1.8 =
* General Improvements

= 2.1.7 =
* Added "Update Available" notice on checker screen
* Enhanced error display for failed compilations.

= 2.1.6 =
* Added multiple fallback options to detect installed Tailwind Compiler version

= 2.1.5 =
* Added @tailwindcss/typography and removed tailwind-clamp from the list of npm modules

= 2.1.4 =
* General code updates and improvements.

= 2.1.3 =
* General code updates and improvements.
* Added fallback method to compile CSS via npx if binary fails.

= 2.1.2 =
* General code updates.
* Updated LICENSE.
* Tailwind CLI binary is now deleted during plugin uninstall.

= 2.1.1 =
* New updates to compile with WordPress requirements.

= 2.1.0 =
* Added Updates page.
* Added an option to check the current and latest CLI version.
* Added an option to reinstall the latest CLI version.

= 2.0.0 =
* Initial public release.