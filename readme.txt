=== CompileKit for Tailwind CSS ===
Contributors: mstrdh
Tags: theme, tailwind, css, cli
Requires at least: 6.0
Tested up to: 6.8
Requires PHP: 8.0
Stable tag: 2.1.6
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Integrates Tailwind CSS Standalone CLI with WordPress for streamlined builds and asset compilation.

== Description ==
- Integrates Tailwind CSS Standalone CLI directly with WordPress.
- Option to compile on every page reload or manually.
- Configure input/output paths and CLI flags.
- Fully local. No Node.js or npm required.


== Installation ==
1. Upload the plugin folder to `/wp-content/plugins/`.
2. Activate the plugin through the Plugins menu.
3. Go to "CompileKit" in the admin menu.
4. Download the CLI binary, set paths, and run compiler.

== External services ==
This plugin connects to GitHub API to check for Tailwind CSS CLI updates. It's needed to get the latest available version or download CLI if missing.
HTTP request to GitHub's public API with user-agent "WordPress/compilekit" happens only when user clicks "Check for Updates", "Download Tailwind CLI" or "Reinstall Tailwind CLI" buttons in Admin panel.
Learn more about [privacy policy](https://docs.github.com/en/site-policy/privacy-policies/github-privacy-statement) and [terms of service](https://docs.github.com/en/site-policy/github-terms/github-terms-of-service).

== Changelog ==

= 2.1.6 =
* Release Date: 17 October 2025
* Added multiple fallback options to detect installed Tailwind Compiler version

= 2.1.5 =
* Release Date: 4 September 2025
* Added @tailwindcss/typography and removed tailwind-clamp from the list of npm modules

= 2.1.4 =
* Release Date: 29 August 2025
* General code updates and improvements.

= 2.1.3 =
* Release Date: 28 August 2025
* General code updates and improvements.
* Added fallback method to compile CSS via npx if binary fails.

= 2.1.2 =
* Release Date: 23 August 2025
* General code updates.
* Updated LICENSE.
* Tailwind CLI binary is now deleted during plugin uninstall.

= 2.1.1 =
* Release Date: 15 August 2025
* New updates to compile with WordPress requirements.

= 2.1.0 =
* Release Date: 4 August 2025
* Added Updates page.
* Added an option to check the current and latest CLI version.
* Added an option to reinstall the latest CLI version.

= 2.0.0 =
* Release Date: 3 August 2025
* Initial public release.