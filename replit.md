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
- **Google Calendar Integration:** Live calendar events from Google Calendar API displayed on News & Events page via `[triuu_upcoming_events]` shortcode. Fetches next 7 days of events, excludes Sunday services, groups by day of week.
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
    - **AI-related plugins (added externally):**
        - `elementor-scope-fixer.php` (28K, v1.9.0) - **DO NOT MODIFY**
        - `ai-patch-runner.php` (57K, v3.0.0 "AI Website Styler") - User-friendly AI styling assistant
        - `openai-wp-service.php` (3.6K, v1.0.0) - Centralized OpenAI API service layer
        - `openai-content-editor.php` (5.4K) - Admin helper for AI-assisted content editing
- **Google Fonts:** Self-hosted versions of Barlow, Manrope, Roboto, Roboto Slab, Roboto Condensed, and Poppins.

## AI Website Styler Usage

The **AI Website Styler** MU plugin (`ai-patch-runner.php` v3.0.0) provides an intuitive, user-friendly interface for making styling changes to the WordPress site using AI. It's fully integrated with the OpenAI WP Service layer.

### Access
Navigate to **WordPress Admin → Tools → AI Website Styler**

### Features (4 Tabs)
1. **Quick Tasks** - Template-based common styling operations with simple forms:
   - Change colors (background, header, footer, buttons, links, headings)
   - Change fonts and typography (body text, headings, navigation)
   - Adjust spacing (sections, paragraphs, header/footer padding)

2. **Page Styler** - Target specific pages or posts for styling changes:
   - Select any page/post from a dropdown
   - Describe styling changes in plain English
   - Elementor-aware with context for proper CSS targeting

3. **Custom Request** - Free-form natural language interface:
   - Describe any styling change in plain English
   - AI interprets and generates appropriate CSS
   - Works with all pages and site-wide elements

4. **History** - Snapshot management and rollback:
   - View all past changes with user-friendly names
   - One-click restore to any previous state
   - Creates new snapshot when restoring (so restores can be undone)

### How It Works
The plugin uses a two-step workflow:
1. **Preview**: Describe your changes → AI generates CSS → Shows diff preview
2. **Apply**: Review preview → Click "Apply Changes" → Changes written to theme CSS

All styling changes are appended to `wp-content/themes/triuu/style.css` to ensure they persist and properly override Elementor's auto-generated styles. This prevents changes from being lost when Elementor regenerates its CSS files.

### Example Usage
1. Go to AI Website Styler → Custom Request tab
2. Type: "Make the Services page background light gray"
3. Click "Preview Changes" to see what CSS will be added
4. Click "Apply Changes" to implement the styling

### Security & Constraints
- All changes create automatic snapshots for easy rollback
- Only modifies `wp-content` directory (themes, never core files)
- Explicitly avoids Elementor's auto-generated CSS files
- Requires OpenAI API key (configured via `OPENAI_API_KEY` environment variable)
- Uses transient storage to ensure preview/apply consistency

### Recent Changes (Oct 30, 2025)
- **Google Calendar Integration COMPLETED**: Added `[triuu_upcoming_events]` shortcode to TRIUU Sermons Manager plugin to display live calendar events from Google Calendar API
- **News & Events Page Reorganized**: Moved "Late Breaking · The Week Ahead" section immediately after Sunday Service section for better visibility
- **Secure API Credentials**: Google Calendar API credentials stored as environment variables (GOOGLE_CALENDAR_API_KEY, GOOGLE_CALENDAR_ID)
- **Full Event Details Display**: Events now show complete information with full dates (e.g., "Friday, October 31, 2025"), times, locations, and descriptions in chronological order
- **Enhanced UX for Events**:
  - Event titles increased to 1.1rem font size for better readability
  - All event locations converted to clickable Google Maps links that open in new windows for easy directions
  - All URLs in event descriptions automatically converted to clickable links with target="_blank"
  - Smart HTML handling: Preserves both plain text and HTML-formatted descriptions from Google Calendar
- **Event Filtering**: 
  - Sunday services automatically excluded from the week ahead display
  - Past events filtered out - only events starting today or later are displayed
  - Shows next 7 days of upcoming events from current date

### Previous Changes (Oct 20, 2025)
- **v3.0.0 Complete Redesign COMPLETED**: Rewritten from developer-centric "AI Patch Runner" to user-friendly "AI Website Styler" with 4-tab interface
- **Fixed Preview/Apply Bug**: Now uses transient storage to ensure previewed changes exactly match applied changes (no duplicate AI calls)
- **Fixed Elementor CSS Issue**: AI now forbidden from modifying wp-content/uploads/elementor/css/ files, always appends CSS to child theme style.css
- **Added Helper Functions**: WordPress page/post selector, Elementor detection, theme file detection, and AI prompt builder
- **Modern UI/UX**: Color-coded notices, dynamic form fields with JavaScript, responsive design, and collapsible diffs
- **Preserved Core Functions**: All snapshot management, path security, file operations, and rollback features remain intact