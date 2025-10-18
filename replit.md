# Triuu WordPress Theme Project

## Overview
This is a WordPress theme based on Hello Elementor, customized for the Triuu site. The theme was originally built for Local (Flywheel) development environment.

## Project Structure
- `wordpress/` - WordPress core installation
- `wordpress/wp-content/themes/triuu/` - **Active Triuu theme** (copied from original import)
- `wordpress/wp-content/themes/hello-elementor/` - Parent Hello Elementor theme
- `Triuu/` - Original theme files from GitHub import (archived, not actively used)
- `wp-content/` - Original wp-content from GitHub import (archived, not actively used)
- `assets/` - Original assets from GitHub import (archived, not actively used)
- `router.php` - PHP server routing script for WordPress

**Important:** To edit the active theme, modify files in `wordpress/wp-content/themes/triuu/`, NOT the root-level directories.

## Current State
- **Fully configured and running** on Replit with production database imported
- WordPress 6.8.3 with SQLite database integration
- Theme: Hello Elementor (parent theme for Triuu customization)
- Site: Tri-County Unitarian Universalists (TRI-UU)
- Database table prefix: `RYX_` (from kenpond.com production data)
- PHP 8.2.23
- **Production data imported** from MySQL database dump

## Recent Changes
- 2025-10-18: Initial import to Replit environment
- 2025-10-18: Configured complete WordPress environment with SQLite database
- 2025-10-18: Set up PHP development server on port 5000
- 2025-10-18: Configured deployment settings for production
- 2025-10-18: **Imported production MySQL database** (converted to SQLite format)
  - Converted MySQL dump (localhost_1760781795824.sql) to SQLite
  - Database contains multiple sites (RYX_, jiA_, ysB_, JGK_ prefixes)
  - Active site: RYX_ prefix (kenpond.com / Triuu site)
  - Updated wp-config.php table prefix to RYX_
  - Updated site URLs from kenpond.com to Replit domain

## Architecture
**WordPress Setup:**
- WordPress core installed in `wordpress/` directory
- SQLite database integration via `wp-sqlite-db` (stored in `wordpress/wp-content/database/`)
- PHP 8.2 built-in development server
- Custom router script (`router.php`) for proper URL handling
- Dynamic URL configuration for Replit proxy environment

**Themes:**
- Triuu theme: `wordpress/wp-content/themes/triuu/` (main theme)
- Hello Elementor: `wordpress/wp-content/themes/hello-elementor/` (parent theme)

**Key Configuration:**
- Database: SQLite (file-based) at `wordpress/wp-content/database/wordpress.db`
  - Converted from MySQL production database
  - Contains multiple WordPress installations (4 sites total)
  - Active site uses `RYX_` table prefix
- Table Prefix: `RYX_` (configured in wp-config.php)
- Server: PHP built-in server binding to `0.0.0.0:5000`
- URLs: Dynamic configuration via `$_SERVER['HTTP_HOST']` for Replit proxy support
- File system: Direct method for file operations
- Debug mode: Enabled with logging to `wordpress/wp-content/debug.log`

## Setup Notes
**Current Status:**
- Site is fully configured with production database imported
- WordPress admin login available at `/wp-admin/`
- Use existing WordPress credentials from kenpond.com production site
- No installation wizard needed - site is ready to use

**Database Information:**
- The imported database contains 4 WordPress sites:
  1. **RYX_** - kenpond.com (Triuu/TRI-UU site) - **ACTIVE**
  2. jiA_ - dataforyourbeta.com
  3. ysB_ - temporary staging site
  4. JGK_ - temporary staging site

**Theme Features:**
- Custom fonts (Barlow, Manrope, Roboto Condensed)
- Elementor page builder integration
- Self-hosted font files
- Customizer and settings modules
- Admin home modules with conversion banner
- Dynamic header/footer support

## Development
- Workflow: "WordPress Server" runs PHP dev server
- Logs: Available at `wordpress/wp-content/debug.log`
- Database: File-based SQLite, automatically created on first run

## Deployment
- Type: VM (always-on for WordPress state management)
- Command: `php -S 0.0.0.0:5000 -t wordpress router.php`
- Port: 5000

**Note:** Deployment configuration is stored in Replit's internal metadata (not tracked in git due to `.gitignore`). The deployment settings are configured through Replit's deployment tools and use the same command as the development workflow.
