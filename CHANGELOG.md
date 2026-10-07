# Changelog

All notable changes to plrsys are documented here.

## [v0.1.0-alpha.3] - 2026-10-07

- Redesigned the login and register pages with a shared form layout, a new pixel-style box, and a refreshed password requirements panel.
- Unified button styling across the home and about pages with shared `btn primary` and `btn secondary` classes.
- Improved the footer and mobile bottom navigation spacing, plus the layout of the changelog and main content area.
- Self-hosted the Press Start 2P and VT323 fonts instead of loading them from Google Fonts.
- Added standalone Dart Sass install and compile scripts (`scripts/install-sass.sh`, `scripts/compile-scss.sh`) with a pinned, checksum-verified download.
- Moved the hand-edited stylesheet changes back into the SCSS sources and recompiled the CSS.
- Documented the stylesheet build workflow in CONTRIBUTING and added a notice not to edit the compiled CSS.
- Added curl to the development container image.
- Thanks to @Celestial04 for the interface and styling contributions.

## [v0.1.0-alpha.2] - 2026-10-05

- Added a routed API front controller with health, login, token refresh, token management, two-factor verification, ping, echo, and API error endpoints.
- Added OpenAPI documentation for the API.
- Added English and French localization support across the application, including authentication, dashboard, settings, management, system, legal, and public pages.
- Added translation catalog validation with `scripts/check-lang.php`.
- Updated TOTP QR generation to use GD-independent SVG output.
- Expanded the database schema for API tokens and additional account and fronting functionality.
- Improved release tooling, version handling, deployment configuration, and development environment setup.

## [v0.1.0-alpha.1] - 2026-10-02

- Renamed the project from Innerspace to plrsys and reorganized the application, deployment, and script files.
- Added dashboard session history with session durations and average session time.
- Added fronting session management with member selection and real-time session duration.
- Added account deletion with verification.
- Added an XML sitemap for SEO.
- Added version retrieval from the VERSION file and displayed the version in the footer and changelog.
- Added an interactive release manager for versioning, changelog management, tagging, deployment, and release checks.
- Added the initial database schema for active visitors, friends, fronting sessions, and user management.
- Added secure Nginx deployment configuration and a manual signup script for testing.
- Improved the README and added contributing and security documentation.
- Added a development container with Docker Compose database access, Nginx configuration, and SSH agent support.
