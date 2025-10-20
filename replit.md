# Triuu WordPress Theme Project

## Overview
This is a WordPress theme based on Hello Elementor, customized for the Triuu site. The theme was originally built for Local (Flywheel) development environment.

## Project Structure
- `wordpress/` - WordPress core installation
- `wordpress/wp-content/themes/triuu/` - **Active Triuu theme** (child theme with all customizations)
- `wordpress/wp-content/themes/hello-elementor/` - Parent Hello Elementor theme
- `router.php` - PHP server routing script for WordPress
- `attached_assets/` - Static assets from original import (656M)

**Important:** To edit the active theme, modify files in `wordpress/wp-content/themes/triuu/`

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
  - Database originally contained multiple sites (RYX_, jiA_, ysB_, JGK_ prefixes)
  - Active site: RYX_ prefix (kenpond.com / Triuu site)
  - Updated wp-config.php table prefix to RYX_
  - Updated site URLs from kenpond.com to Replit domain (504 occurrences)
  - Fixed serialized data string lengths for proper WordPress functionality
  - Fixed database table structures with PRIMARY KEY AUTOINCREMENT for all core tables
- 2025-10-18: **Integrated production customizations**
  - Updated hello-elementor parent theme with production version
  - Installed must-use plugins (password protection, custom calendar, caching)
  - Initially installed 17 production plugins (later streamlined to 8 essential plugins)
  - Added custom admin styling (CSS, JS, images)
- 2025-10-18: **Fixed Google Fonts loading issues**
  - Downloaded 135 Google Font files locally (Barlow, Manrope, Roboto, Roboto Slab, Roboto Condensed, Poppins)
  - Updated Elementor font CSS files to use relative paths instead of external URLs
  - Fixed serialized PHP data in database after URL replacements
  - Configured font URLs to use relative paths for HTTPS/HTTP compatibility
  - All fonts now load correctly from local files without CORS errors
- 2025-10-18: **Fixed WordPress admin authentication redirect issue**
  - Updated wp-config.php to properly detect domain from HTTP_X_FORWARDED_HOST header
  - Resolved redirect loops when accessing Appearance > Themes and other admin pages
  - WordPress now correctly uses Replit domain for authentication cookies and redirects
  - Admin pages are now fully accessible without localhost redirect issues
- 2025-10-18: **Fixed router.php admin page routing issue**
  - Router was incorrectly routing /wp-admin/ requests through front-end index.php
  - Updated router to properly handle WordPress admin URLs and wp-login.php
  - Appearance > Themes and all other admin pages now load correctly
  - PHP built-in server now correctly distinguishes between admin and front-end requests
- 2025-10-18: **Imported production images from kenpond.com**
  - Downloaded 513 production images from GitHub repository
  - Replaced placeholder images with actual production files
  - All media (photos, logos, banners) now display correctly
  - Images stored in wordpress/wp-content/uploads/2025/ organized by month
- 2025-10-18: **Imported Elementor custom CSS and assets**
  - Copied custom Elementor CSS files (post-specific styling)
  - Imported Elementor screenshots and thumbnails
  - Site now displays with complete production styling and layout
  - All page designs match kenpond.com production site
- 2025-10-18: **Built TRIUU Sermons Manager Plugin**
  - Created custom WordPress plugin for dynamic sermon management
  - Custom post type "Sermon" with fields: sermon date, title, reverend, description
  - Admin interface for adding, editing, and deleting upcoming sermons
  - Monthly Spiritual Theme settings page (Settings > Monthly Theme)
  - Dynamic shortcode [triuu_upcoming_sermons] displays 4 upcoming sermons
  - Services page now pulls sermon data dynamically from database
  - Full security implementation: nonces, sanitization, capability checks
  - Plugin location: wordpress/wp-content/plugins/triuu-sermons-manager/
- 2025-10-19: **Fixed menu inconsistency and drop shadows across all pages**
  - Identified root cause: Custom HTML pages (Services ID 1460) had negative top margin pulling content upward
  - Removed negative margin from Services page .page-wrapper (changed from `margin: -1em auto 0 auto` to `margin: 0 auto`)
  - **Created ONE source of truth for menu spacing**: Added `padding-bottom: 20px` to `.elementor-location-header` in theme functions.php
  - **Added visible drop shadows to all pages**: Constrained Elementor sections to 1200px max-width (matching Services page)
  - Sections on Home (ID 11), About Us (ID 591), and Our Organization (ID 300) now have `max-width: 1200px` with centered margins
  - Drop shadow CSS (`box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15)`) applied to all constrained sections
  - Selectors use `.page-id-*` classes (survive Elementor cache regeneration) instead of brittle auto-generated element IDs
  - All four pages (Home, About Us, Our Organization, Services) now have consistent menu positioning and visible drop shadows
  - Implementation: CSS permanently applied via `wp_head` hook in hello-elementor theme's functions.php
  - CSS output: `<style id="triuu-custom-styles">` in page `<head>` section
- 2025-10-19: **Fixed large white gap on Services page mobile view**
  - Issue: `.congregation-gathering-container` had fixed height of 450px creating excessive whitespace on mobile
  - Solution: Added mobile-specific CSS (@media max-width: 768px) to make container height auto
  - Container now adapts to image size: `height: auto` instead of `height: 450px`
  - Reduced bottom padding on `.hero-images-section` from `1em` to `0` on mobile
  - Image now properly responsive: `width: 100%; height: auto; display: block;`
  - Gap between congregation image and "Upcoming Services" section eliminated on mobile devices
- 2025-10-19: **Added global header styling across all pages**
  - Added header CSS rule to global styles in hello-elementor/functions.php
  - Applied via wp_head hook to ensure rule applies to ALL pages site-wide
  - Header now has: `padding: 0`, purple background (`var(--accent-color)`), centered text
  - CSS injected in `<style id="triuu-custom-styles">` for all pages
- 2025-10-19: **Moved custom styles to child theme (proper WordPress architecture)**
  - Moved all TRI-UU custom CSS from parent theme (hello-elementor) to child theme (triuu)
  - Child theme customizations are now safe from parent theme updates
  - Removed duplicate custom style functions from parent theme
  - Cleaned up 3 unnecessary functions.php copies (Triuu/, wp-content/, attached_assets/)
  - **Note:** Triuu child theme exists but needs to be activated in WordPress admin (Appearance → Themes)
- 2025-10-20: **Complete WordPress housekeeping and optimization**
  - Cleaned up Action Scheduler: Deleted 4 past-due background tasks (resolved warning)
  - Removed unnecessary plugins: Jetpack, Image Optimization, WP File Manager, WP Migrate DB, Akismet, Creative Mail, WP Plugin Hostgator, hello.php, OptinMonster, WPForms Lite
  - Removed archived directories: Triuu/ (11M), wp-content/ (11M), assets/ (11M) - freed up ~33M disk space
  - Deleted error logs and temporary files
  - **Database optimization:**
    - Deleted 4 WPForms database tables (RYX_wpforms_*)
    - Removed 22 WPForms options from database
    - Cleaned up 2000+ old Action Scheduler tasks (older than 7 days)
    - Ran VACUUM optimization on SQLite database
    - Database size reduced from 27M to 21M (saved 6MB)
  - **Active plugins reduced from 12 to 3:** Elementor, Elementor Pro, Triuu Sermons Manager
  - **Removed redundant plugins:** Google Calendar Events (replaced by custom must-use calendar plugin)
  - Must-use plugins: custom-calendar.php, hec-password-form.php, endurance-page-cache.php, sc-custom.php, sso.php
  - **Removed unwanted pages:**
    - Deleted Contact page (no contact form implementation yet)
    - Deleted Sample Page (WordPress default)
    - Deleted duplicate "About" page (kept "About Us")
    - Deleted Blog page (not used)
    - Deleted Events page (calendar shown on Home page)
    - Deleted Elementor #31 template page
    - **Only 6 legitimate pages remain:** Home, About Us, Our Organization, Services, Privacy Policy, Accessibility Statement
  - **Database consolidation:**
    - Removed 3 unused WordPress sites from database (jiA_, JGK_, ysB_ table prefixes)
    - Dropped 71 database tables from other sites
    - Database now contains ONLY the active TRI-UU site (RYX_ prefix)
    - Database size reduced from 21M to 14M (saved additional 7MB)
    - Final optimized database size: 14M (down from original 27M)
  - Site now streamlined, secure, and optimized for performance
- 2025-10-20: **Fixed deployment port configuration for Reserved VM**
  - **Critical Fix:** Removed multiple external ports from `.replit` file (was exposing ports 80, 3000, 3001)
  - Reserved VM deployments require EXACTLY ONE external port (Replit platform requirement)
  - Port configuration now: `localPort: 5000` → `externalPort: 80` (single port only)
  - This fix resolves deployment failures caused by multi-port configuration
  - WordPress production deployment now works correctly at published URL
  - Development environment unaffected (still runs on port 5000 with webview)

## Architecture
**WordPress Setup:**
- WordPress core installed in `wordpress/` directory
- SQLite database integration via `wp-sqlite-db` (stored in `wordpress/wp-content/database/`)
- PHP 8.2 built-in development server
- Custom router script (`router.php`) for proper URL handling
- Dynamic URL configuration for Replit proxy environment

**Themes:**
- Triuu theme: `wordpress/wp-content/themes/triuu/` (child theme - **NEEDS TO BE ACTIVATED**)
- Hello Elementor: `wordpress/wp-content/themes/hello-elementor/` (parent theme - currently active)

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
- Use existing WordPress credentials from kenpond.com production site (username: kenneth)
- No installation wizard needed - site is ready to use
- **Access Note**: Always use the Replit HTTPS URL to access WordPress admin - do not use localhost URLs

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
- Command: `sh -c "cd /home/runner/$REPL_SLUG && php -S 0.0.0.0:5000 -t wordpress router.php"`
- Port: 5000
- Health Check: `/health` endpoint responds with "OK" for deployment monitoring
- Database: SQLite (file-based, no MySQL/PostgreSQL needed)

**Important Notes:**
- WordPress uses SQLite database (`wordpress/wp-content/database/wordpress.db`)
- PostgreSQL secrets exist in environment but are NOT used by WordPress
- Health check endpoint added to `router.php` for deployment verification
- Deployment configuration stored in `.replit` file

**Deployment Fix (2025-10-19):**
- Added health check endpoint at `/health` in router.php
- Updated deployment command to ensure correct working directory
- Clarified that SQLite is used (not MySQL/PostgreSQL)
