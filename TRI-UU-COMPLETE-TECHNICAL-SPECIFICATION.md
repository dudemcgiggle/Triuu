# TRI-UU WordPress Project — Complete Technical Specification

**Date Generated:** October 20, 2025  
**Project Status:** ✅ Production-Ready (Port Configuration Fixed)  
**Environment:** Replit Cloud Development + Reserved VM Deployment  
**Authority:** This document is the authoritative source of truth for all AI assistants working on this project.

---

## Table of Contents

1. [Project Overview](#1-project-overview)
2. [Technical Architecture](#2-technical-architecture)
3. [Architectural Constraints (CRITICAL)](#3-architectural-constraints-critical)
4. [Elementor Widget Structure Rules (LOCKED)](#4-elementor-widget-structure-rules-locked)
5. [Widget-Local Modal Enhancer](#5-widget-local-modal-enhancer)
6. [Must-Use Plugins Architecture](#6-must-use-plugins-architecture)
7. [AI Tooling Layer](#7-ai-tooling-layer)
8. [Development Environment & Deployment](#8-development-environment--deployment)
9. [Operational Guardrails](#9-operational-guardrails)
10. [Known Pitfalls & Forbidden Practices](#10-known-pitfalls--forbidden-practices)
11. [Current Status & Next Actions](#11-current-status--next-actions)

---

## 1. Project Overview

### Organization
- **Site Name:** Tri-County Unitarian Universalists (TRI-UU)
- **Original Domain:** kenpond.com
- **Current Dev URL:** Dynamic (Replit proxy, e.g., janeway.replit.dev)
- **Production URL:** triuu-kwp.replit.app

### WordPress Setup
- **Version:** WordPress 6.8.3
- **PHP:** 8.2.23
- **Database:** SQLite (file-based, 14MB optimized from 27MB)
- **Page Builder:** Elementor 3.32.4 + Elementor Pro 3.32.2
- **Parent Theme:** Hello Elementor 3.4.4
- **Child Theme:** Triuu (custom, contains all site-specific code)

### Site Structure
- **Active Pages:** 6 (Home, About Us, Our Organization, Services, Privacy Policy, Accessibility Statement)
- **Active Core Plugins:** 3 (Elementor, Elementor Pro, TRIUU Sermons Manager)
- **Must-Use Plugins:** 10 (see Section 6)
- **Production Images:** 513 (from kenpond.com GitHub repository)
- **Self-Hosted Fonts:** 135 files (Barlow, Manrope, Roboto, Roboto Slab, Roboto Condensed)

---

## 2. Technical Architecture

### Directory Structure
```
workspace/
├── .replit                          # Replit config (CRITICAL: single port only)
├── router.php                       # Custom PHP router for WordPress
├── replit.md                        # Project documentation
├── wordpress/                       # WordPress core
│   ├── wp-content/
│   │   ├── themes/
│   │   │   ├── triuu/              # ⭐ CHILD THEME (needs activation)
│   │   │   │   ├── functions.php   # Custom theme functions
│   │   │   │   ├── style.css       # Custom styles (menu, shadows)
│   │   │   │   └── ...
│   │   │   └── hello-elementor/    # Parent theme
│   │   ├── plugins/
│   │   │   ├── elementor/
│   │   │   ├── elementor-pro/
│   │   │   └── triuu-sermons-manager/
│   │   ├── mu-plugins/             # ⭐ MUST-USE PLUGINS (10 files)
│   │   │   ├── elementor-scope-fixer.php      # v1.9.0 (DO NOT MODIFY)
│   │   │   ├── ai-patch-runner.php            # v1.2.0
│   │   │   ├── openai-wp-service.php          # v1.0.0
│   │   │   ├── openai-content-editor.php
│   │   │   ├── custom-calendar.php
│   │   │   ├── hec-password-form.php
│   │   │   ├── endurance-page-cache.php
│   │   │   ├── sc-custom.php
│   │   │   └── sso.php
│   │   ├── uploads/
│   │   │   ├── 2025/02/, 2025/09/  # Production images
│   │   │   └── elementor/google-fonts/  # 135 self-hosted font files
│   │   └── database/
│   │       └── wordpress.db        # SQLite (14MB)
│   └── wp-config.php               # Dynamic domain detection
└── attached_assets/                # Static assets archive (656MB)
```

### Database Configuration
- **Type:** SQLite (file-based, no PostgreSQL/MySQL)
- **Location:** `wordpress/wp-content/database/wordpress.db`
- **Size:** 14MB (optimized from 27MB — 48% reduction)
- **Table Prefix:** `RYX_` (production site only)
- **History:** Converted from MySQL production database, consolidated from 4 sites to 1

### Design System
- **Typography:** Barlow (primary), Manrope, Roboto Condensed
- **Colors:** 
  - Accent: `#614E6B` (purple)
  - Hover: `#A5849F` (lighter purple)
- **Layout:** 1200px max container width
- **Shadows:** `box-shadow: 0 4px 20px rgba(0, 0, 0, 0.15)` on content sections
- **Menu Spacing:** Single source of truth via `.elementor-location-header { padding-bottom: 20px }`

---

## 3. Architectural Constraints (CRITICAL)

### ⚠️ RULE SET FOR ALL AI ASSISTANTS

These constraints are **LOCKED** and **NON-NEGOTIABLE**. Any AI assistant (ChatGPT, Claude, Replit Agent, etc.) working on this project **MUST** follow these rules:

#### A. Elementor Widget CSS Scoping (NEVER VIOLATE)
1. ✅ All widget CSS selectors **MUST START WITH** `.triuu-our-organization` only
2. ❌ **NEVER** include `.tri-county-widget` in widget CSS selectors (upstream scoping adds it automatically)
3. ❌ **NO** `@import` statements inside widget `<style>` blocks
4. ❌ **NO** XML declarations (`<?xml ...?>`) inside widget HTML
5. ✅ Use local header wrapper `.local-page-header` (do not style bare `header`)

#### B. Must-Use Plugin Stability
1. ❌ **DO NOT MODIFY** `elementor-scope-fixer.php` — treat as a black box
   - **Context:** A previous update to this MU plugin broke CSS on three pages
   - **Decision:** MU plugin modifications are frozen; use widget-side rules instead
2. ✅ Route all OpenAI calls through `openai-wp-service.php` (never raw HTTP clients)
3. ✅ Use AI Patch Runner for orchestrated multi-file changes (dry-run → apply)

#### C. Content Safety
1. ✅ All AI content edits must be **preview-before-apply** (never auto-modify live content)
2. ✅ No PII or secrets in code (use env vars or WP constants)
3. ✅ All transformations must be **idempotent** and **auditable**

---

## 4. Elementor Widget Structure Rules (LOCKED)

### Canonical Widget Template

**GhostWriter / ChatGPT / Any AI:** This is the **exact structure** you must follow when creating or editing Elementor HTML widgets:

```html
<div class="tri-county-widget" data-ai-scoped="1">
  <div class="triuu-our-organization">
    <style>
      /* ✅ CORRECT: All selectors start with .triuu-our-organization */
      .triuu-our-organization {
        --accent-color: #614E6B;
        --hover-color: #A5849F;
        --body-bg: #f5f5f5;
        --container-bg: #ffffff;
        --placeholder-bg: #dddddd;
        --card-border: #dddddd;
        font-family: 'Barlow', sans-serif;
        line-height: 1.5;
        color: #333333;
        font-weight: 300;
        background: var(--body-bg);
      }
      
      /* ✅ Reset scoped to widget */
      .triuu-our-organization * { 
        margin: 0; 
        padding: 0; 
        box-sizing: border-box; 
      }
      
      /* ✅ Page wrapper */
      .triuu-our-organization .page-wrapper { 
        max-width: 1200px; 
        margin: 1em auto; 
        padding: 1em; 
        background: var(--container-bg); 
      }
      
      /* ✅ Local header (not bare header) */
      .triuu-our-organization .local-page-header { 
        background: var(--accent-color); 
        color: #fff; 
        padding: 1em; 
        text-align: center; 
        margin-bottom: 1em; 
      }
      
      /* ✅ Other widget-specific styles */
      .triuu-our-organization .card-grid { /* ... */ }
      .triuu-our-organization .read-more-btn { /* ... */ }
    </style>

    <div class="page-wrapper">
      <header class="local-page-header">
        <h1>Tri-County UU — Our Organization</h1>
      </header>

      <!-- Content sections here -->
      
    </div>

    <!-- Optional: modal enhancer (see Section 5) -->
  </div>
</div>
```

### ✅ Good Selector Examples
```css
.triuu-our-organization .card-grid { /* CORRECT */ }
.triuu-our-organization .read-more-btn { /* CORRECT */ }
.triuu-our-organization header.local-page-header { /* CORRECT */ }
```

### ❌ Forbidden Selector Patterns
```css
/* WRONG: Over-scoped (double-scoping) */
.triuu-our-organization .tri-county-widget .card-grid { }
.tri-county-widget .triuu-our-organization .card-grid { }

/* WRONG: Bare header (must use .local-page-header) */
.triuu-our-organization header { }

/* WRONG: @import in widget */
@import url('https://fonts.googleapis.com/css2?family=Barlow');

/* WRONG: XML declaration in HTML */
<?xml version="1.0" encoding="UTF-8"?>
```

---

## 5. Widget-Local Modal Enhancer

### Purpose
Add ESC close, focus trap, focus restore, backdrop click-to-close, and lazy images — **without touching global CSS/JS or MU plugins**.

### Implementation
Paste this script **at the bottom of any widget that uses modals** (inside the `.triuu-our-organization` div):

```html
<script>
/*
  Widget-Local Modal Enhancer (scoped to nearest .tri-county-widget)
  Adds: ESC close, focus trap, restore focus, backdrop-click close, lazy images
*/
(function () {
  function root(el){while(el&&el.parentElement&&!el.classList.contains('tri-county-widget'))el=el.parentElement;return(el&&el.classList&&el.classList.contains('tri-county-widget'))?el:document.querySelector('.tri-county-widget')}
  var ROOT=root(document.currentScript||document.body); if(!ROOT) return;

  function focusables(c){return Array.prototype.slice.call(c.querySelectorAll(['a[href]','area[href]','button:not([disabled])','input:not([disabled]):not([type="hidden"])','select:not([disabled])','textarea:not([disabled])','iframe','[tabindex]:not([tabindex="-1"])','[contenteditable="true"]'].join(','))).filter(function(el){return el.offsetParent!==null||el===document.activeElement})}

  var lastTriggerById=Object.create(null), trap=Object.create(null);

  function on(modal){
    if(trap[modal.id]) return;
    function kd(e){
      if(e.key==='Escape'){e.preventDefault(); close(modal.id); return;}
      if(e.key==='Tab'){var L=focusables(modal); if(!L.length) return; var f=L[0], l=L[L.length-1];
        if(e.shiftKey&&document.activeElement===f){e.preventDefault(); l.focus();}
        else if(!e.shiftKey&&document.activeElement===l){e.preventDefault(); f.focus();}
      }
    }
    function clickBg(e){ if(e.target===modal){ close(modal.id); } }
    modal.addEventListener('keydown', kd);
    modal.addEventListener('click', clickBg);
    trap[modal.id]={kd:kd, clickBg:clickBg};
  }

  function off(modal){
    var r=trap[modal.id]; if(!r) return;
    modal.removeEventListener('keydown', r.kd);
    modal.removeEventListener('click', r.clickBg);
    delete trap[modal.id];
  }

  function open(id, trigger){
    var m=document.getElementById(id); if(!m) return;
    m.setAttribute('aria-modal','true'); m.setAttribute('role', m.getAttribute('role')||'dialog');
    m.removeAttribute('aria-hidden'); m.style.display='flex';
    if(trigger) lastTriggerById[id]=trigger;
    var L=focusables(m); if(L.length){ L[0].focus(); } else { var x=m.querySelector('.close-btn')||m; x.setAttribute('tabindex','-1'); x.focus(); }
    on(m);
  }

  function close(id){
    var m=document.getElementById(id); if(!m) return;
    m.style.display='none'; m.setAttribute('aria-hidden','true');
    off(m);
    var t=lastTriggerById[id]; if(t&&document.body.contains(t)){try{t.focus();}catch(e){}}
  }

  window.openModal=function(id){ open(id); };
  window.closeModal=function(id){ close(id); };

  function isOpenCall(el){ var oc=(el.getAttribute('onclick')||'').replace(/\s+/g,''); return /^openModal\(['"]/.test(oc); }

  ROOT.addEventListener('click', function(e){
    var t=e.target, opener=t.closest('[data-modal-open]');
    if(opener){ e.preventDefault(); open(opener.getAttribute('data-modal-open'), opener); return; }
    var c=t.closest('[onclick]'); if(c&&isOpenCall(c)){ var m=c.getAttribute('onclick').match(/openModal\(['"]([^'"]+)['"]\)/); if(m&&m[1]){ lastTriggerById[m[1]]=c; } }
  });

  ROOT.querySelectorAll('[data-modal-open], a[onclick], button[onclick]').forEach(function(el){
    el.addEventListener('keydown', function(e){
      if(e.key==='Enter'||e.key===' '){
        var id=el.getAttribute('data-modal-open'); if(!id){ var m=(el.getAttribute('onclick')||'').match(/openModal\(['"]([^'"]+)['"]\)/); id=m&&m[1]; }
        if(id){ e.preventDefault(); open(id, el); }
      }
    });
  });

  ROOT.querySelectorAll('img:not([loading])').forEach(function(img){ img.setAttribute('loading','lazy'); });
})();
</script>
```

### A11y Requirements
- `role="dialog"` and `aria-modal="true"` while open
- `aria-hidden="true"` when closed
- ESC key closes modal
- Focus trapped inside modal (Tab/Shift+Tab cycles within)
- Focus returns to trigger button when closed
- Backdrop click closes modal

---

## 6. Must-Use Plugins Architecture

### Active MU Plugins (10 total)

| Plugin | Version | Purpose | Modify? |
|--------|---------|---------|---------|
| **elementor-scope-fixer.php** | 1.9.0 | Scopes Elementor HTML widget CSS to `.tri-county-widget`; strips `@import`, fixes invalid headings | ❌ **NO** (frozen) |
| **ai-patch-runner.php** | 1.2.0 | Generate & apply guarded file edits to child theme using OpenAI | ✅ Yes (with caution) |
| **openai-wp-service.php** | 1.0.0 | Thin service layer for OpenAI API calls (no Composer) | ✅ Yes (with caution) |
| **openai-content-editor.php** | — | Admin helper for AI-assisted content shaping | ✅ Yes (with caution) |
| **custom-calendar.php** | — | Google Calendar integration | ✅ Yes |
| **hec-password-form.php** | — | Password protection | ✅ Yes |
| **endurance-page-cache.php** | — | Page caching | ✅ Yes |
| **sc-custom.php** | — | Custom functionality | ✅ Yes |
| **sso.php** | — | Single sign-on | ✅ Yes |
| **assets/** | — | CSS/JS for password form, etc. | ✅ Yes |

### ⚠️ Elementor Scope Fixer — DO NOT MODIFY

**Context:** This plugin was updated to v1.9.0, which then broke CSS on three pages during deployment testing.

**Decision:** The MU plugin is now **frozen** and treated as a **black box**. All scoping work must be done at the **widget level** using the canonical structure in Section 4.

**What it does:**
- Wraps Elementor HTML widgets in `.tri-county-widget`
- Strips XML declarations (`<?xml ...?>`)
- Removes `@import` statements (handles Google Fonts' semicolons)
- Fixes invalid headings (h7-h9 → `<h4 class="h8">`)
- Scopes CSS to avoid double-prefixing and header bleed

**Configuration:**
- **House Scope:** `.tri-county-widget` (default, configurable via Settings)
- **Allowlist Prefixes:** `.elementor-`, `.elementor`, `.e-con-`, `.global-`, `:root` (configurable)

**Access:** WordPress Admin → Tools → Elementor Scope Fixer

---

## 7. AI Tooling Layer

### A. OpenAI WP Service (Service Layer)

**File:** `wordpress/wp-content/mu-plugins/openai-wp-service.php`  
**Purpose:** Centralized, WordPress-native service for OpenAI API calls

**Key Functions:**
```php
// Get API key from environment
openai_wp_get_key(): string

// Call OpenAI Chat Completions
openai_wp_chat(
  array $messages,  // [['role'=>'system'|'user', 'content'=>'...']]
  array $args       // model, max_tokens, temperature, timeout, cache_ttl
): array|WP_Error   // ['content', 'model', 'usage', 'raw']
```

**Configuration:**
- API Key: `OPENAI_API_KEY` environment variable (or `wp-config.php` constant)
- Default Model: `gpt-4o-mini`
- Default Tokens: 300
- Default Timeout: 30s
- Optional response caching via transients

**Rules for AI Assistants:**
1. ✅ **ALWAYS** use `openai_wp_chat()` for AI calls (never raw `wp_remote_post()` to OpenAI)
2. ✅ Sanitize inputs and outputs
3. ✅ Never log API keys
4. ✅ Keep responses auditable (log job ID, duration, truncated payloads)

---

### B. AI Patch Runner (Change Orchestration)

**File:** `wordpress/wp-content/mu-plugins/ai-patch-runner.php`  
**Purpose:** Generate and apply controlled, scripted transformations to the child theme

**Workflow:**
1. **Generate:** Describe the change → AI generates file blocks
2. **Preview:** Review the proposed changes (dry-run)
3. **Apply:** Commit changes with automatic backups

**Supported Operations:**
- `=== FILE: path ===` ... `=== END FILE ===` (replace entire file)
- `=== APPEND: path ===` ... `=== END APPEND ===` (append to file)

**Safety Features:**
- Admin-only access
- Preview before write
- Automatic backups of original files
- Path validation (must be inside child theme)
- File type validation (`.css`, `.php`, `.js` only)

**Access:** WordPress Admin → Tools → AI Patch Runner

**Rules for AI Assistants:**
1. ✅ Use PatchRunner for **multi-file refactors** or **regex transformations**
2. ✅ **Dry-run first**, review, then apply
3. ✅ Emit changes as PatchRunner tasks (not ad-hoc search/replace)
4. ✅ Preserve **idempotence** and **ASCII-only** output
5. ✅ Log each transformation with diff summary

---

### C. OpenAI Content Editor (Admin Helper)

**File:** `wordpress/wp-content/mu-plugins/openai-content-editor.php`  
**Purpose:** Admin-side helper for AI-assisted content shaping (summaries, rewrites, tone alignment)

**Constraints:**
1. ✅ Treat as a **tooling surface**, not a theme/plugin coupling point
2. ✅ No PII or secrets in code (use env vars / WP constants)
3. ✅ All AI calls must be **opt-in** and **reviewed**
4. ❌ **NEVER** auto-modify live content without preview

**Rules for AI Assistants:**
1. ❌ **DO NOT** re-architect or "integrate deeper" with theme rendering
2. ✅ Keep utilities **admin-only**, **non-destructive**
3. ✅ Use **preview-before-apply** workflows

---

## 8. Development Environment & Deployment

### Replit Configuration

**File:** `.replit`

```toml
modules = ["nodejs-20", "php-8.2", "postgresql-16", "python-3.11", "web"]

[nix]
channel = "stable-25_05"
packages = ["unzip", "sqlite"]

[workflows]
runButton = "Project"

[[workflows.workflow]]
name = "WordPress Server"
task = "shell.exec"
args = "php -S 0.0.0.0:5000 -t wordpress router.php"
waitForPort = 5000
outputType = "webview"

[[ports]]
localPort = 5000
externalPort = 80
# ⚠️ CRITICAL: Must have EXACTLY ONE external port for Reserved VM deployment

[deployment]
deploymentTarget = "vm"  # Reserved VM (not Autoscale)
run = ["sh", "-c", "cd /home/runner/$REPL_SLUG && php -S 0.0.0.0:5000 -t wordpress router.php"]
```

### ⚠️ CRITICAL PORT CONFIGURATION

**Rule:** Reserved VM deployments require **EXACTLY ONE external port**. Multiple ports cause deployment failure.

**Correct:**
```toml
[[ports]]
localPort = 5000
externalPort = 80
```

**Incorrect (WILL FAIL):**
```toml
[[ports]]
localPort = 5000
externalPort = 80

[[ports]]
localPort = 3000
externalPort = 3000  # ❌ Extra port breaks deployment
```

**Recent Fix (2025-10-20):** Removed extra ports (3000, 3001) that were causing deployment failures.

---

### Development vs. Production

| Environment | URL | Port | Database | Access |
|-------------|-----|------|----------|--------|
| **Development** | Dynamic (janeway.replit.dev) | 5000 (internal) | Same SQLite file | Webview preview |
| **Production** | triuu-kwp.replit.app | 80 (external) | Same SQLite file | Public URL |

**Important:** Changes in development require republishing to appear on production URL.

---

### Deployment Type: Reserved VM

**Why Reserved VM (not Autoscale)?**
- WordPress requires **persistent storage** (SQLite database file)
- Background processes (cron jobs, scheduled tasks)
- Always-on server (not request-based)
- State management in server memory

**Deployment Command:**
```bash
php -S 0.0.0.0:5000 -t wordpress router.php
```

**Health Check:** `/health` endpoint in `router.php` responds with "OK"

---

## 9. Operational Guardrails

### A. Development Tools

**Local (by Flywheel):**
- Windows development uses **Open Site Shell → PowerShell**
- All automation scripts must be PowerShell-compatible

**WP-CLI Pattern (Dry-Run → Apply):**
```bash
# Step 1: Dry-run (read-only)
wp eval '/* read-only pass, echo findings */'

# Step 2: Apply (only after successful dry-run)
wp eval '/* apply change transactionally, report counts */'
```

---

### B. Code Quality Standards

**For All Code (Widget HTML, PHP, JS, CSS):**
1. ✅ **ASCII-only** (no smart quotes, no NBSP, no em-dashes)
2. ✅ **Idempotent scripts** (safe to run multiple times)
3. ✅ **Explicit progress messages** (clear success/failure logging)
4. ✅ **No user-specific absolute paths** (use `$PSScriptRoot`, relative paths)
5. ✅ **Structured source code** (not littered in root directory)

---

### C. Security Best Practices

**Implemented:**
- ✅ Child theme architecture (safe from parent theme updates)
- ✅ Must-use plugins for critical functionality
- ✅ Nonces, sanitization, capability checks in custom plugins
- ✅ Dynamic URL detection (no hardcoded domains)
- ✅ Self-hosted fonts (no external dependencies)
- ✅ Minimal plugin footprint (3 active plugins + 10 MU plugins)

**Access:**
- WordPress Admin: `/wp-admin/`
- Username: `kenneth` (production credentials)
- Always use HTTPS Replit URL (not localhost)

---

## 10. Known Pitfalls & Forbidden Practices

### ❌ NEVER Do These Things

1. **CSS Over-Scoping**
   ```css
   /* WRONG: Double-scoping */
   .triuu-our-organization .tri-county-widget .card-grid { }
   .tri-county-widget .triuu-our-organization .card-grid { }
   ```

2. **Bare Header Styling**
   ```css
   /* WRONG: Must use .local-page-header */
   .triuu-our-organization header { }
   ```

3. **@import in Widget Styles**
   ```html
   <style>
   @import url('https://fonts.googleapis.com/...'); /* ❌ FORBIDDEN */
   </style>
   ```

4. **XML Declarations in Widget HTML**
   ```html
   <?xml version="1.0" encoding="UTF-8"?> <!-- ❌ FORBIDDEN -->
   ```

5. **Modifying Elementor Scope Fixer MU Plugin**
   - ❌ Treat `elementor-scope-fixer.php` as a black box (frozen)

6. **Direct OpenAI API Calls**
   ```php
   // WRONG: Raw HTTP client
   wp_remote_post('https://api.openai.com/v1/chat/completions', ...);
   
   // CORRECT: Use service
   openai_wp_chat($messages, $args);
   ```

7. **Auto-Modifying Live Content**
   - ❌ Always use **preview-before-apply** for AI content edits

8. **Ad-Hoc Multi-File Refactors**
   - ❌ Use PatchRunner for orchestrated changes (not search/replace)

---

### ✅ Acceptance Checklist (For PRs / Code Reviews)

Before committing any code change, verify:

- [ ] Widget CSS selectors all begin with `.triuu-our-organization`
- [ ] `.tri-county-widget` appears **only** as outer wrapper in markup (not in CSS)
- [ ] No `@import` in widget `<style>`
- [ ] No XML declarations in HTML
- [ ] If modals: inline modal enhancer included, a11y works (ESC, trap, restore)
- [ ] AI code calls `openai_wp_chat()`, not raw HTTP clients
- [ ] Multi-file changes expressed as PatchRunner tasks (dry-run first)
- [ ] Scripts/code blocks are ASCII-only and idempotent
- [ ] No modifications to `elementor-scope-fixer.php`

---

## 11. Current Status & Next Actions

### ✅ Working (Production-Ready)

- WordPress core functioning perfectly
- PHP server running (all [200] responses in logs)
- Database optimized (14MB SQLite, RYX_ prefix only)
- All fonts loading from local files (135 font files)
- All production images displaying (513 images)
- Custom sermon management system operational
- **Deployment configuration corrected** (single port 5000→80)
- Port configuration fix documented in `replit.md`

---

### ⚠️ Requires User Action

1. **Activate Triuu Child Theme**
   - Go to WordPress Admin → **Appearance → Themes**
   - Find **"Triuu"** theme
   - Click **"Activate"**
   - (Currently Hello Elementor is active, but all custom CSS is in Triuu child theme)

2. **Redeploy to Production**
   - Click **"Publish"** button in Replit
   - With corrected port configuration, deployment should succeed
   - Published site will be live at `triuu-kwp.replit.app`

3. **Verify Published Site**
   - Test front-end pages load correctly
   - Test WordPress admin login (`/wp-admin/`)
   - Verify custom styles appear (drop shadows, menu spacing)
   - Test mobile responsiveness

---

### 📊 Optimization History

| Date | Action | Result |
|------|--------|--------|
| 2025-10-18 | Imported production MySQL database | Converted to SQLite (27MB) |
| 2025-10-20 | Database consolidation | Removed 3 sites, dropped 71 tables |
| 2025-10-20 | Plugin cleanup | 12 plugins → 3 core + 10 MU |
| 2025-10-20 | Page cleanup | 15+ pages → 6 legitimate pages |
| 2025-10-20 | Database optimization | VACUUM: 27MB → 14MB (48% reduction) |
| 2025-10-20 | **Port configuration fix** | **Single port (5000→80) for Reserved VM** |

---

### 📝 Future Enhancements (Optional)

1. Implement contact form (Contact page was deleted)
2. Google Calendar API integration testing
3. Performance optimization (caching configuration)
4. SEO metadata review
5. Mobile testing across devices
6. Backup strategy for SQLite database
7. Staging environment setup

---

## 12. One-Liner Summary (for AI Memory)

**Widgets:** Page-scoped CSS (`.triuu-our-organization`), no house-scope in CSS, local header wrapper, no `@import` or XML.  
**UX:** Inline, widget-local modal enhancer (ESC, trap, restore, backdrop close, lazy images).  
**Infra:** Leave MU plugin as-is; use PatchRunner for orchestrated changes; route AI through OpenAI WP Service; treat Content Editor as admin tool with preview-before-apply.  
**Deployment:** Reserved VM (single port 5000→80), SQLite database, Replit environment.

---

## 13. Support & Troubleshooting

### Key Files for Debugging

| File | Purpose |
|------|---------|
| `wordpress/wp-content/debug.log` | WordPress error log |
| `.replit` | Replit configuration (ports, deployment) |
| `wordpress/wp-config.php` | WordPress configuration |
| `wordpress/wp-content/themes/triuu/functions.php` | Custom theme functions |
| `router.php` | WordPress URL routing |

### Common Issues & Solutions

| Issue | Solution |
|-------|----------|
| Port conflicts | Ensure only port 5000→80 in `.replit` |
| Theme not showing custom styles | Activate Triuu child theme in WordPress admin |
| Images not loading | Check `wp-content/uploads/` directory |
| Fonts not loading | Verify self-hosted fonts in `elementor/google-fonts/` |
| Changes not visible in production | Hard refresh (Ctrl+Shift+R) or republish |
| Deployment fails | Verify single external port in `.replit` |
| CSS scoping issues | Check widget CSS starts with `.triuu-our-organization` |

---

**END OF SPECIFICATION**

**Version:** 1.0  
**Generated:** October 20, 2025  
**Authority:** This document supersedes all previous documentation and is the authoritative source for all AI assistants working on TRI-UU WordPress project.
