# TRi-UU WordPress Repository Overview

**Generated:** 2025-11-02
**Purpose:** Comprehensive technical documentation for sharing with other Claude instances

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Directory Structure](#directory-structure)
3. [Configuration Files](#configuration-files)
4. [WordPress Theme](#wordpress-theme)
5. [Custom Plugins](#custom-plugins)
6. [Shortcodes Reference](#shortcodes-reference)
7. [Architecture & Technical Details](#architecture--technical-details)

---

## Project Overview

**Repository Name:** TRi-UU
**Type:** WordPress Website
**Primary Framework:** WordPress with Elementor Page Builder
**Purpose:** Church/Community website for Trinity UU with sermon management, event calendars, and custom content delivery

### Key Features
- Custom sermon management system with monthly themes
- Google Calendar integration for events
- Book club management
- Custom responsive calendar widget
- Elementor-based page building
- PHP built-in server routing support

---

## Directory Structure

```
Triuu/
├── router.php                          # PHP built-in server router
├── wordpress/                          # WordPress installation root
│   ├── wp-content/
│   │   ├── themes/
│   │   │   └── triuu/                  # Custom child theme
│   │   │       ├── style.css           # Theme styles and customizations
│   │   │       └── functions.php       # Theme functions (minimal bootstrap)
│   │   ├── plugins/
│   │   │   ├── elementor/              # Elementor page builder
│   │   │   ├── elementor-pro/          # Elementor Pro features
│   │   │   ├── triuu-sermons-manager/  # Custom sermon management plugin
│   │   │   ├── triuu-sandbox/          # Development/testing plugin
│   │   │   └── wp-file-manager/        # File management plugin
│   │   └── mu-plugins/                 # Must-use plugins (auto-loaded)
│   │       ├── ai-patch-runner.php     # AI-based patch runner
│   │       ├── custom-calendar.php     # Google Calendar integration
│   │       ├── elementor-scope-fixer.php
│   │       ├── endurance-page-cache.php
│   │       ├── hec-password-form.php
│   │       ├── openai-content-editor.php
│   │       ├── openai-wp-service.php
│   │       ├── sc-custom.php
│   │       └── sso.php
│   └── [standard WordPress files]
├── attached_assets/                     # Legacy/backup assets
└── [documentation files]
```

### Key Directories Explained

- **`router.php`** - Custom router for PHP's built-in web server, handles admin routes and static assets
- **`wordpress/wp-content/themes/triuu/`** - Custom child theme (minimal, extends Hello Elementor)
- **`wordpress/wp-content/plugins/`** - Standard WordPress plugins directory
- **`wordpress/wp-content/mu-plugins/`** - Must-use plugins that load automatically
- **`attached_assets/`** - Legacy files and backups (not actively used)

---

## Configuration Files

### 1. router.php

**Location:** `/router.php`
**Purpose:** Custom router for PHP built-in server to handle WordPress routing

```php
<?php
/**
 * Router script for PHP built-in server running WordPress
 * This handles routing for WordPress when using PHP's built-in web server
 */

// Simple health check endpoint for deployment
if ($_SERVER['REQUEST_URI'] === '/health' || $_SERVER['REQUEST_URI'] === '/health.php') {
    http_response_code(200);
    header('Content-Type: text/plain');
    echo 'OK';
    exit;
}

// Get the requested URI
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

// Handle admin requests - let WordPress process them
if (strpos($uri, '/wp-admin/') === 0 || $uri === '/wp-admin') {
    // Set working directory
    chdir(__DIR__ . '/wordpress');
    // Don't modify SCRIPT variables for admin pages - let them be handled naturally
    return false;
}

// If it's a wp-login.php request, let it through
if (strpos($uri, '/wp-login.php') !== false) {
    chdir(__DIR__ . '/wordpress');
    return false;
}

// If the file exists and is not a PHP file in wp-content or wp-includes, serve it directly
if ($uri !== '/' && file_exists(__DIR__ . '/wordpress' . $uri)) {
    // Serve static assets directly (CSS, JS, images, fonts, etc.)
    if (!preg_match('/\.php$/', $uri)) {
        return false;
    }
}

// For everything else, route through WordPress front-end
$_SERVER['SCRIPT_FILENAME'] = __DIR__ . '/wordpress/index.php';
$_SERVER['SCRIPT_NAME'] = '/index.php';
$_SERVER['PHP_SELF'] = '/index.php';

chdir(__DIR__ . '/wordpress');
require __DIR__ . '/wordpress/index.php';
```

**Key Features:**
- Health check endpoint at `/health`
- Proper routing for wp-admin and wp-login
- Static asset serving
- WordPress front-end routing

---

## WordPress Theme

### Theme: triuu (Child Theme)

**Parent Theme:** Hello Elementor
**Location:** `wordpress/wp-content/themes/triuu/`

### style.css (First 100 lines)

```css
.triuu-county-widget .triuu_services {
        --accent-color: #614E6B; /* Accent color for elements */
        --hover-color: #A5849F; /* Color on hover */
        --body-bg: #fff; /* Background color for body */
        --container-bg: #ffffff; /* Background color for containers */
        --placeholder-bg: #dddddd; /* Background color for placeholders */
        --card-border: #dddddd; /* Border color for cards */
        font-family: 'Barlow', sans-serif; /* Font family for text */
        color: #000000; /* Text color */
        line-height: 1.5; /* Line height for text */
        font-weight: 200; /* Font weight */
}

/* Change background color for Services page */
.page-id-XX {
    background-color: #ffffff;
}

/* Change background color of Services page to white */
.page-id-1460 {
    background-color: #ffffff !important;
}

.page-id-1460 .elementor-widget-container {
    padding-bottom: 20px; /* Adjust the value as needed */
}

.page-id-591 .elementor-container {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.4) !important;
}

.page-id-300 { background-color: #ffffff !important; }

/* Centered 1200px container wrapper for shortcodes */
.triuu-county-widget .page-wrapper {
    max-width: 1200px;
    margin: 0 auto;
    padding: 2em 1em;
    background: #ffffff;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15);
}
```

**Color Scheme:**
- **Primary Accent:** `#614E6B` (Deep purple)
- **Hover Color:** `#A5849F` (Light purple)
- **Text Color:** `#000000` (Black)
- **Background:** `#ffffff` (White)
- **Font Family:** Barlow (sans-serif)

### functions.php

**Location:** `wordpress/wp-content/themes/triuu/functions.php`

```php
<?php
/**
 * Triuu Child Theme — functions.php
 * Purpose: minimal, safe bootstrap with one enqueue block (cache-busted) and a no-op token for tests.
 */

defined('ABSPATH') || exit;

/* AI:start:noop */
/**
 * No-op test function (inert). Safe to keep or remove.
 */
if (!function_exists('tri_ai_noop_test')) {
    function tri_ai_noop_test() { return 'ok'; }
}
/* AI:end:noop */

/* AI:start:enqueue-child-style */
/**
 * Enqueue the child stylesheet with filemtime-based cache-busting.
 * Loads after the parent Hello Elementor styles.
 */
add_action('wp_enqueue_scripts', function () {
    // Avoid double-enqueue if another tool already added it.
    if (wp_style_is('triuu-child', 'enqueued')) {
        return;
    }

    $uri  = get_stylesheet_uri(); // points to child style.css
    $path = get_stylesheet_directory() . '/style.css';
    $ver  = file_exists($path) ? filemtime($path) : wp_get_theme()->get('Version');

    // Allow opt-out via filter: add_filter('triuu_enqueue_child_style', '__return_false');
    $should_enqueue = apply_filters('triuu_enqueue_child_style', true);
    if ($should_enqueue) {
        wp_enqueue_style('triuu-child', $uri, array(), $ver);
    }
}, 20);
/* AI:end:enqueue-child-style */

/* AI:start:sandbox-php */
/* (reserved for tokenized PHP edits) */
/* AI:end:sandbox-php */
```

**Features:**
- Minimal, safe bootstrap approach
- File modification time-based cache busting
- Filter hook for conditional style loading
- Reserved sections for AI-assisted editing

---

## Custom Plugins

### 1. TRIUU Sermons Manager

**Plugin Name:** TRIUU Sermons Manager
**Version:** 2.0.0
**Location:** `wordpress/wp-content/plugins/triuu-sermons-manager/`
**Main File:** `triuu-sermons-manager.php`

#### Features
- Sermon CRUD operations (Create, Read, Update, Delete)
- Monthly spiritual theme management
- Frontend sermon display with dynamic shortcodes
- Google Calendar integration for events
- Book club functionality
- Admin interface for managing sermons

#### Database Storage
- Uses WordPress options table
- Option name: `triuu_sermons_data` (array of sermon objects)
- Option name: `triuu_monthly_theme` (current theme string)

#### Sermon Data Structure
```php
array(
    'id' => 'sermon_xxxxx',
    'title' => 'Sermon Title',
    'date' => 'YYYY-MM-DD',
    'reverend' => 'Rev. Name',
    'description' => 'Sermon description text'
)
```

#### Registered Shortcodes
See [Shortcodes Reference](#shortcodes-reference) section below.

---

### 2. Custom Calendar (Must-Use Plugin)

**Plugin Name:** Public Calendar Shortcode (API-Key Only)
**Version:** 3.2.7
**Location:** `wordpress/wp-content/mu-plugins/custom-calendar.php`

#### Features
- Google Calendar API integration
- Responsive design (7-column grid on ≥801px, rolling 7-day list on ≤800px)
- Prev/Next navigation
- "Today" highlighting
- Timezone-aware (site timezone / America/New_York fallback)
- Modal popup for event details (desktop)
- Clickable Zoom links and location mapping

#### Requirements
- Google Calendar API Key
- Google Calendar ID

---

### 3. TriUU Sandbox

**Plugin Name:** TriUU Sandbox
**Version:** 0.1.0
**Location:** `wordpress/wp-content/plugins/triuu-sandbox/`

#### Purpose
Safe minimal development/testing plugin for validation and experimentation.

#### Features
- Simple admin page for validation
- Test shortcode: `[triuu_hello]`
- REST API ping endpoint: `/wp-json/triuu/v1/ping`
- Frontend asset loading (guarded)

---

### 4. Other Must-Use Plugins

- **ai-patch-runner.php** - AI-based patch runner with snapshot recovery
- **openai-content-editor.php** - OpenAI content editing integration
- **openai-wp-service.php** - OpenAI service wrapper
- **elementor-scope-fixer.php** - Fixes Elementor scoping issues
- **endurance-page-cache.php** - Page caching functionality
- **hec-password-form.php** - Custom password protection
- **sc-custom.php** - Custom shortcode implementations
- **sso.php** - Single sign-on functionality

---

## Shortcodes Reference

### 1. `[triuu_upcoming_sermons]`

**Plugin:** TRIUU Sermons Manager
**File:** `triuu-sermons-manager.php:427`

**Purpose:** Display list of all upcoming sermons with monthly theme

**Output:**
- Monthly spiritual theme (if set)
- Service cards for each upcoming sermon showing:
  - Date (abbreviated format: "Oct 15")
  - Title
  - Speaker/Reverend name
  - Description

**Styling:** Uses `.service-cards` and `.service-card` classes

---

### 2. `[triuu_next_sermon]`

**Plugin:** TRIUU Sermons Manager
**File:** `triuu-sermons-manager.php:480`

**Purpose:** Display information about the next upcoming sermon

**Attributes:**
- `format` - Output format (default: 'full')
  - `'full'` - Title and speaker
  - `'title'` - Title only
  - `'date'` - Date only
  - `'speaker'` - Speaker only

**Example Usage:**
```
[triuu_next_sermon format="full"]
[triuu_next_sermon format="title"]
```

**Fallback:** Returns "Sunday Service (In person & Zoom)" if no upcoming sermons

---

### 3. `[triuu_featured_sermon]`

**Plugin:** TRIUU Sermons Manager
**File:** `triuu-sermons-manager.php:534`

**Purpose:** Display featured sermon card with full styling and Zoom link

**Output:**
- Date badge
- "Sunday Service" kicker
- Sermon title (large, styled)
- Speaker name with "Live in person and on Zoom" text
- Description
- Zoom launch button (hardcoded link)

**Zoom Link:** `https://zoom.us/j/95277568906?pwd=PJeDQqyY1WMwoJRrkI9Xn4sQG36P2f.1`

**Styling:** Uses `.triuu-county-widget`, `.page-wrapper`, `.feature` classes

---

### 4. `[triuu_upcoming_events]`

**Plugin:** TRIUU Sermons Manager
**File:** `triuu-sermons-manager.php:665`

**Purpose:** Display upcoming events from Google Calendar (next 7 days)

**Requirements:**
- Environment variable: `GOOGLE_CALENDAR_API_KEY`
- Environment variable: `GOOGLE_CALENDAR_ID`

**Features:**
- Filters out "Sunday Service" events
- Shows only upcoming events (future from current time)
- Displays date, time, title, location, description
- Auto-detects and formats Zoom links
- Google Maps integration for physical locations
- Clickable links in descriptions

**Output Format:**
- Grid layout (responsive, 2 columns on desktop)
- Event cards with:
  - Date and time
  - Title
  - Location (with map/Zoom links)
  - Description

---

### 5. `[triuu_book_club]`

**Plugin:** TRIUU Sermons Manager
**File:** `triuu-sermons-manager.php:953`

**Purpose:** Display book club information with next meeting details

**Attributes:**
- `pdf_url` - URL to downloadable reading list PDF

**Features:**
- Shows meeting schedule (4th Monday, 1:00 PM)
- Contact information (Nancy Garrison)
- Auto-fetches next meeting from Google Calendar
- Displays Zoom link if available
- Optional PDF download button

**Example Usage:**
```
[triuu_book_club pdf_url="https://example.com/reading-list.pdf"]
```

---

### 6. `[custom_calendar]`

**Plugin:** Custom Calendar (mu-plugin)
**File:** `mu-plugins/custom-calendar.php:11`

**Purpose:** Display Google Calendar in responsive month/week view

**Attributes:**
- `api_key` - Google Calendar API key (required)
- `calendar_id` - Google Calendar ID (required)

**Features:**
- Auto-switches between month grid (desktop) and week list (mobile)
- Prev/Next navigation
- Today highlighting
- Clickable events with modal popup (desktop)
- Timezone-aware display
- Zoom link detection and formatting
- Email and URL auto-linking

**Example Usage:**
```
[custom_calendar api_key="YOUR_API_KEY" calendar_id="YOUR_CALENDAR_ID"]
```

---

### 7. `[triuu_hello]`

**Plugin:** TriUU Sandbox
**File:** `triuu-sandbox/triuu-sandbox.php:53`

**Purpose:** Simple test/validation shortcode

**Attributes:**
- `name` - Name to greet (default: 'friend')

**Example Usage:**
```
[triuu_hello name="Ken"]
```

**Output:** `Hello, Ken 👋`

---

## Architecture & Technical Details

### Technology Stack

**Platform:** WordPress 6.x
**PHP Version:** 7.4+ (designed for modern PHP)
**Web Server:** PHP Built-in Server (development) / Apache/Nginx (production)
**Database:** MySQL/MariaDB
**Page Builder:** Elementor Pro
**Parent Theme:** Hello Elementor

### External Integrations

1. **Google Calendar API v3**
   - Used for event management
   - Requires API key and calendar ID
   - Timezone-aware event fetching
   - RFC3339 formatted datetime handling

2. **Zoom**
   - Hardcoded meeting link in featured sermon shortcode
   - Auto-detected in calendar events
   - Link format: `https://zoom.us/j/[meeting-id]?pwd=[password]`

### Custom Development Patterns

#### 1. AI-Assisted Editing Markers
Files use special comment markers for AI-assisted editing:
```php
/* AI:start:section-name */
// Code block
/* AI:end:section-name */
```

This allows for safe, tokenized edits by AI tools.

#### 2. Singleton Pattern
The TRIUU Sermons Manager uses singleton pattern:
```php
class TRIUU_Sermons_Manager {
    private static $instance = null;

    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }
        return self::$instance;
    }
}
```

#### 3. WordPress Hooks & Filters
Extensive use of WordPress action/filter hooks:
- `add_action('admin_menu', ...)`
- `add_action('admin_init', ...)`
- `add_action('wp_enqueue_scripts', ...)`
- `add_shortcode(...)`
- `apply_filters('triuu_enqueue_child_style', true)`

#### 4. Security Practices
- Nonce verification for form submissions
- Capability checks (`current_user_can('manage_options')`)
- Data sanitization (`sanitize_text_field`, `sanitize_textarea_field`)
- Output escaping (`esc_html`, `esc_attr`, `esc_url`)
- ABSPATH checks to prevent direct file access

### Data Flow

#### Sermon Management Flow
```
Admin Interface → Form Submission → Nonce Verification →
Sanitization → WordPress Options API → Frontend Shortcode →
Render with Escaping
```

#### Calendar Integration Flow
```
Shortcode Call → API Key/Calendar ID Check → Google Calendar API Request →
JSON Response → Timezone Conversion → Event Filtering →
HTML Rendering with Modal Popup
```

### Responsive Design Strategy

**Breakpoint:** 800px / 801px

- **Mobile (≤800px):** Rolling 7-day list view for calendar
- **Desktop (≥801px):** Full month grid view with modal popups
- Auto-switches on window resize
- Uses CSS media queries and JavaScript matchMedia API

### Cache Strategy

- **Theme styles:** File modification time-based versioning
  ```php
  $ver = file_exists($path) ? filemtime($path) : wp_get_theme()->get('Version');
  ```
- **API responses:** No explicit caching (relies on browser/WordPress transients if needed)

### Environment Variables

Required environment variables (typically set in hosting or `.env` file):
- `GOOGLE_CALENDAR_API_KEY` - Google Calendar API access
- `GOOGLE_CALENDAR_ID` - Target calendar identifier

### Timezone Handling

Default timezone: **America/New_York**

Uses WordPress timezone functions with fallback:
```php
$tz = function_exists('wp_timezone')
    ? wp_timezone()
    : new DateTimeZone('America/New_York');
```

### URL Structure

- **Health Check:** `/health` or `/health.php`
- **Admin:** `/wp-admin/`
- **Login:** `/wp-login.php`
- **REST API:** `/wp-json/triuu/v1/ping`
- **Calendar View:** `?view=week&wk=0` or `?cal_year=2025&cal_month=11`

### Plugin Dependencies

**Required:**
- Elementor (free)
- Elementor Pro (premium)

**Optional:**
- WP File Manager (utility)

### Must-Use Plugins Behavior

Plugins in `mu-plugins/` directory:
- Load automatically before regular plugins
- Cannot be deactivated from admin
- Load in alphabetical order
- No activation/deactivation hooks available

---

## Development Notes

### Router Configuration
The `router.php` file is specifically designed for local development using PHP's built-in server:
```bash
php -S localhost:8000 router.php
```

For production, this file is typically not used (Apache/Nginx handle routing).

### Testing Endpoints

1. **Health Check:** `http://localhost:8000/health`
2. **REST Ping:** `http://localhost:8000/wp-json/triuu/v1/ping`
3. **Admin:** `http://localhost:8000/wp-admin/`

### Common Page IDs

- Page ID 1460: Services page (white background)
- Page ID 591: Page with shadow on containers
- Page ID 300: Generic white background page

These are hardcoded in theme CSS for specific styling.

### Color Palette

| Color | Hex Code | Usage |
|-------|----------|-------|
| Deep Purple | `#614E6B` | Primary accent, buttons |
| Light Purple | `#A5849F` | Hover states |
| Lighter Purple | `#C8A8CF` | Headings |
| Dark Gray | `#4A566D` | Secondary text |
| Medium Gray | `#666666` | Body text |
| Light Gray | `#999999` | Meta text |
| Border Gray | `#dddddd` | Borders, dividers |
| White | `#ffffff` | Backgrounds |

### Typography

**Primary Font:** Barlow (sans-serif)
**Heading Font:** Barlow Condensed (sans-serif)

Font weights used:
- 200: Light
- 300: Regular body text
- 400: Medium/normal
- 600: Semi-bold
- 700: Bold headings

---

## File Locations Quick Reference

| Component | Path |
|-----------|------|
| Router | `/router.php` |
| Theme CSS | `/wordpress/wp-content/themes/triuu/style.css` |
| Theme Functions | `/wordpress/wp-content/themes/triuu/functions.php` |
| Sermons Plugin | `/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php` |
| Calendar Plugin | `/wordpress/wp-content/mu-plugins/custom-calendar.php` |
| Sandbox Plugin | `/wordpress/wp-content/plugins/triuu-sandbox/triuu-sandbox.php` |
| Elementor | `/wordpress/wp-content/plugins/elementor/` |
| Elementor Pro | `/wordpress/wp-content/plugins/elementor-pro/` |

---

## Support & Maintenance

### Key Contact Information

**Book Club Contact:**
Nancy Garrison - garrisonnancy@yahoo.com

**Meeting Schedule:**
Book Club: 1:00 PM, Fourth Monday of each month

### Hardcoded URLs to Update

When migrating or updating:

1. **Zoom Meeting Link** (in `triuu_featured_sermon` shortcode):
   ```
   https://zoom.us/j/95277568906?pwd=PJeDQqyY1WMwoJRrkI9Xn4sQG36P2f.1
   ```

2. **Environment Variables** (set in hosting environment):
   - `GOOGLE_CALENDAR_API_KEY`
   - `GOOGLE_CALENDAR_ID`

3. **Email Addresses:**
   - garrisonnancy@yahoo.com (Book Club contact)

---

## Conclusion

This repository implements a modern WordPress-based church website with custom sermon management, calendar integration, and event handling. The architecture emphasizes:

- **Modularity:** Separate plugins for distinct features
- **Security:** Proper sanitization, escaping, and capability checks
- **Flexibility:** Extensive use of shortcodes and filters
- **Responsiveness:** Mobile-first design with adaptive layouts
- **Maintainability:** AI-assisted editing markers and clean code structure

The codebase is well-documented and follows WordPress coding standards with modern PHP practices.

---

**End of Repository Overview**
*Generated for TRi-UU WordPress Repository*
*Last Updated: 2025-11-02*
