# CompileKit – Tailwind CSS compiler for WordPress

Compile Tailwind CSS with a server-side compiler. Provides an admin UI, auto-compilation mode, and environment-aware output.

## Features

- Compiles Tailwind CSS v4 directly on the server.
- Choose a compiler: Standalone CLI binary or Node.js (npm) packages.
- Compile manually from the admin UI or enable Auto-Compilation Mode to rebuild on each front-end page refresh.
- Configure input/output CSS paths inside the active theme.
- Environment-aware output: Local/Staging builds are unminified (optionally with source maps), Live builds are minified for performance.

## Notes

- **Auto-Compilation Mode** is intended for development. **Disable** it when finished editing!