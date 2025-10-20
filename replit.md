# Triuu WordPress Theme Project

## Overview
This project is a customized WordPress theme for the Tri-County Unitarian Universalists (TRI-UU) website, built as a child theme of Hello Elementor. It is fully configured and running on Replit, with production data imported and optimized for performance. The main purpose is to provide a streamlined, secure, and easily maintainable WordPress environment for the TRI-UU site.

## User Preferences
- I prefer clear and concise explanations.
- I want an iterative development approach.
- Ask for confirmation before making any significant architectural changes.
- Do not make changes to the `attached_assets/` folder.
- Do not modify the Elementor ScopeFixer MU plugin; it should be treated as a black box. All scoping work must be done at the widget level.

## System Architecture
The project is built on WordPress 6.8.3, utilizing a custom child theme (`triuu`) based on Hello Elementor.

**UI/UX Decisions:**
- **Consistent Styling:** Global header styling, consistent menu positioning, and visible drop shadows are applied across all pages.
- **Responsive Design:** Mobile-specific CSS adjustments ensure proper display and eliminate layout gaps on smaller screens.
- **Elementor Integration:** Extensive use of Elementor for page building, with custom CSS and assets imported.
- **Font Management:** Self-hosted Google Fonts (Barlow, Manrope, Roboto, etc.) are used, with local files to ensure consistent loading and prevent CORS issues.

**Technical Implementations:**
- **WordPress Core:** Installed in the `wordpress/` directory.
- **Database:** SQLite integration (`wp-sqlite-db`), with the database file located at `wordpress/wp-content/database/wordpress.db`. The production MySQL database was converted to SQLite.
- **Server:** PHP 8.2.23 built-in development server, binding to `0.0.0.0:5000`.
- **Routing:** A custom `router.php` script handles proper URL routing, including distinctions between admin and front-end requests.
- **Dynamic URLs:** Configured to support Replit's proxy environment using `$_SERVER['HTTP_HOST']`.
- **Child Theme Architecture:** Customizations are contained within the `triuu` child theme, ensuring parent theme updates do not overwrite changes.
- **Custom Plugin:** `TRIUU Sermons Manager` plugin for dynamic sermon management, including a custom post type, admin interface, and shortcode.
- **Widget Structure:** Standardized Elementor HTML Widget structure with a single house wrapper (`.tri-county-widget`) and page-scope wrapper (e.g., `.triuu-our-organization`). CSS selectors are strictly tied to the page-scope wrapper.
- **Modal UX Enhancer:** Inline, widget-scoped JavaScript for modal functionality, including accessibility features (ESC close, focus trap, focus restore, `aria-modal`).
- **Optimization:** Significant cleanup of plugins, pages, and database tables to streamline the site and improve performance.

**Feature Specifications:**
- **Sermons Manager:** Custom WordPress plugin to manage and display upcoming sermons dynamically.
- **Global Styling:** Custom CSS applied via `wp_head` hook for consistent header and section styling.
- **Responsive Images:** Images are configured to be responsive with `width: 100%; height: auto; display: block;`.

**System Design Choices:**
- **File-based Database:** SQLite was chosen for simplicity and to avoid external database dependencies in the Replit environment.
- **Production Data Import:** All production data, including content, images, and Elementor configurations, has been imported and optimized.
- **Deployment Strategy:** Utilizes a Reserved VM deployment on Replit, requiring a single external port configuration.
- **Security:** Nonces, sanitization, and capability checks are implemented in custom plugin development.

## External Dependencies
- **Elementor (Page Builder):** Used for front-end page design and content creation.
- **Elementor Pro:** Enhances Elementor functionalities.
- **WP SQLite DB:** Plugin enabling SQLite database usage for WordPress.
- **Must-Use Plugins:**
    - `custom-calendar.php`
    - `hec-password-form.php` (Password protection)
    - `endurance-page-cache.php` (Caching)
    - `sc-custom.php`
    - `sso.php`
- **Google Fonts:** Self-hosted versions of Barlow, Manrope, Roboto, Roboto Slab, Roboto Condensed, and Poppins.