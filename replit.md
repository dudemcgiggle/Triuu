# Triuu WordPress Theme Project

## Overview
This project is a customized WordPress theme for the Tri-County Unitarian Universalists (TRI-UU) website, built as a child theme of Hello Elementor. It is fully configured and running on Replit, with production data imported and optimized for performance. The main purpose is to provide a streamlined, secure, and easily maintainable WordPress environment for the TRI-UU site, enabling dynamic content display and AI-powered styling.

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
- **Responsive Design:** Mobile-specific CSS adjustments ensure proper display and eliminate layout gaps.
- **Elementor Integration:** Extensive use of Elementor for page building, with custom CSS and assets imported.
- **Font Management:** Self-hosted Google Fonts are used to ensure consistent loading and prevent CORS issues.
- **Design System:** Brand colors (`#614E6B`, `#A5849F`, `#4A566D`), Barlow typography, and a 1200px max-width, centered layout.

**Technical Implementations:**
- **WordPress Core:** Installed in the `wordpress/` directory.
- **Database:** SQLite integration (`wp-sqlite-db`), with the database file at `wordpress/wp-content/database/wordpress.db`.
- **Server:** PHP 8.2.23 built-in development server, binding to `0.0.0.0:5000`.
- **Routing:** A custom `router.php` script handles proper URL routing.
- **Dynamic URLs:** Configured to support Replit's proxy environment.
- **Child Theme Architecture:** Customizations are within the `triuu` child theme.
- **Custom Plugin:** `TRIUU Sermons Manager` for dynamic sermon management, including a custom post type, admin interface, and shortcodes.
- **Widget Structure:** Standardized Elementor HTML Widget structure with a single house wrapper (`.tri-county-widget`) and page-scope wrapper (e.g., `.triuu-our-organization`). CSS selectors are strictly tied to the page-scope wrapper.
- **Modal UX Enhancer:** Inline, widget-scoped JavaScript for modal functionality.
- **Optimization:** Significant cleanup of plugins, pages, and database tables for performance.

**Feature Specifications:**
- **Sermons Manager:** Custom WordPress plugin to manage and display upcoming sermons dynamically via `[triuu_featured_sermon]` shortcode.
- **Google Calendar Integration:** Live calendar events from Google Calendar API displayed via `[triuu_upcoming_events]` shortcode, showing next 7 days of events, excluding Sunday services.
- **Book Club Shortcode:** `[triuu_book_club]` shortcode displays meeting information with an optional PDF download button.
- **Standardized Shortcode Wrapper:** All custom shortcodes (`[triuu_featured_sermon]`, `[triuu_upcoming_events]`, `[triuu_book_club]`) must be wrapped in `<div class="triuu-county-widget"><div class="page-wrapper">...</div></div>` for consistent 1200px centered layout and styling.
- **Smart Location Links:** Event locations automatically link to Google Maps or Zoom based on content.
- **AI Website Styler:** A must-use plugin (`ai-patch-runner.php`) providing an AI-powered interface for styling changes, accessible via WordPress Admin → Tools → AI Website Styler. It allows quick tasks, page-specific styling, and custom requests, with snapshot management and rollback capabilities. All changes are appended to `wp-content/themes/triuu/style.css`.

**System Design Choices:**
- **File-based Database:** SQLite was chosen for simplicity and to avoid external database dependencies.
- **Production Data Import:** All production data has been imported and optimized.
- **Deployment Strategy:** Utilizes a Reserved VM deployment on Replit.
- **Security:** Nonces, sanitization, and capability checks are implemented in custom plugin development.

## External Dependencies
- **Elementor (Page Builder):** For front-end page design.
- **Elementor Pro:** Enhances Elementor functionalities.
- **WP SQLite DB:** Plugin enabling SQLite database usage for WordPress.
- **Must-Use Plugins:**
    - `elementor-scope-fixer.php` (DO NOT MODIFY)
    - `ai-patch-runner.php` (AI Website Styler)
    - `openai-wp-service.php` (Centralized OpenAI API service layer)
    - `openai-content-editor.php` (Admin helper for AI-assisted content editing)
- **Google Fonts:** Self-hosted versions of Barlow, Manrope, Roboto, Roboto Slab, Roboto Condensed, and Poppins.
- **Google Calendar API:** Used for fetching event data.
- **OpenAI API:** Integrated for AI-powered styling and content assistance.