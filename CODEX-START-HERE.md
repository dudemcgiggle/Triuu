# 🚨 CODEX: START HERE - WordPress Site Navigation Guide

## ⚠️ CRITICAL: You Are Likely in the Wrong Location

If you're seeing `/workspace/Triuu` or anything inside a Git clone, **STOP**.

The **ACTUAL RUNNING WordPress site** is NOT in a Git repository folder.

---

## ✅ Navigate to the Correct Location

Run these commands **EXACTLY**:

```bash
cd /home/runner/workspace
ls -la
```

### What You Should See:
```
replit.md
wordpress/
TRI-UU-COMPLETE-TECHNICAL-SPECIFICATION.md
TRIUU-SERMONS-SYSTEM-SUMMARY.md
SERVICES-PAGE-REDESIGN-GUIDE.md
attached_assets/
```

### ❌ If You DON'T See These Files:
You are in the wrong environment. This means you're in a container or workspace that doesn't have access to the live Replit WordPress installation.

**You need to access the actual Replit workspace at:**
```
/home/runner/workspace/
```

---

## 📍 Absolute File Paths - Copy These Exactly

### Documentation Files (Read These First):
```bash
/home/runner/workspace/replit.md
/home/runner/workspace/TRI-UU-COMPLETE-TECHNICAL-SPECIFICATION.md
/home/runner/workspace/TRIUU-SERMONS-SYSTEM-SUMMARY.md
/home/runner/workspace/SERVICES-PAGE-REDESIGN-GUIDE.md
```

### WordPress Installation:
```bash
/home/runner/workspace/wordpress/
```

### Custom Plugin (The One You're Looking For):
```bash
/home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php
```

### Child Theme CSS:
```bash
/home/runner/workspace/wordpress/wp-content/themes/triuu/style.css
```

### Child Theme Functions:
```bash
/home/runner/workspace/wordpress/wp-content/themes/triuu/functions.php
```

### SQLite Database:
```bash
/home/runner/workspace/wordpress/wp-content/database/wordpress.db
```

---

## 🔍 Verification Commands

Run these to confirm you're in the right place:

```bash
# Should return: /home/runner/workspace
pwd

# Should show: replit.md, wordpress/, and other project files
ls -la /home/runner/workspace/

# Should show the custom plugin file
ls -la /home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/

# Should show line 534 (featured_sermon_shortcode function)
grep -n "featured_sermon_shortcode" /home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php
```

---

## 📖 Reading Order - Documentation

Read these files **IN THIS ORDER**:

### 1️⃣ PRIMARY: Start Here
```bash
cat /home/runner/workspace/replit.md
```
**This is your master reference guide.** It contains:
- Complete file system structure
- Exact line numbers for all three shortcodes
- Container architecture requirements
- CSS rules and critical files list
- Environment variables
- Design system specifications

### 2️⃣ Technical Specifications
```bash
cat /home/runner/workspace/TRI-UU-COMPLETE-TECHNICAL-SPECIFICATION.md
```

### 3️⃣ Sermons System Details
```bash
cat /home/runner/workspace/TRIUU-SERMONS-SYSTEM-SUMMARY.md
```

### 4️⃣ Design Reference
```bash
cat /home/runner/workspace/SERVICES-PAGE-REDESIGN-GUIDE.md
```

---

## 🎯 Quick Reference: The Three Shortcodes

**File:** `/home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php`

| Shortcode | Function Name | Line Number |
|-----------|---------------|-------------|
| `[triuu_featured_sermon]` | `featured_sermon_shortcode` | Line 534 |
| `[triuu_upcoming_events]` | `upcoming_events_shortcode` | Line 657 |
| `[triuu_book_club]` | `book_club_shortcode` | Line 941 |

### View a Shortcode Function:
```bash
# Example: View the featured sermon shortcode (lines 534-650)
sed -n '534,650p' /home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php
```

---

## 🚫 What NOT to Do

### ❌ DO NOT Look For:
- `AGENTS.md` (doesn't exist)
- WordPress files in `/workspace/Triuu/` (wrong location)
- `replit.md` in Git repositories (not there)

### ❌ DO NOT Modify:
- `/home/runner/workspace/wordpress/wp-config.php`
- `/home/runner/workspace/wordpress/wp-content/mu-plugins/elementor-scope-fixer.php`
- `/home/runner/workspace/wordpress/wp-content/uploads/elementor/css/*` (auto-generated)
- `/home/runner/workspace/wordpress/wp-content/database/wordpress.db` (directly)

### ✅ SAFE to Modify:
- `/home/runner/workspace/wordpress/wp-content/themes/triuu/style.css` (append CSS here)
- `/home/runner/workspace/wordpress/wp-content/themes/triuu/functions.php`
- `/home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php`

---

## 🔐 Environment Variables (Secrets)

These are **already configured** in Replit. You don't need the actual values.

Access them in PHP code like this:
```php
$api_key = getenv('GOOGLE_CALENDAR_API_KEY');
$calendar_id = getenv('GOOGLE_CALENDAR_ID');
$openai_key = getenv('OPENAI_API_KEY');
```

**Available Secrets:**
- `GOOGLE_CALENDAR_API_KEY`
- `GOOGLE_CALENDAR_ID`
- `OPENAI_API_KEY`

---

## 🎨 Required Container Structure

**CRITICAL:** All three shortcodes MUST maintain this wrapper:

```html
<div class="triuu-county-widget">
  <div class="page-wrapper">
    <!-- Shortcode content -->
  </div>
</div>
```

Breaking this structure will cause layout issues across the site.

---

## ✅ Success Checklist

Before you start working, verify:

- [ ] You're in `/home/runner/workspace/` (run `pwd`)
- [ ] You can see `replit.md` (run `ls -la`)
- [ ] You can find the plugin at `/home/runner/workspace/wordpress/wp-content/plugins/triuu-sermons-manager/triuu-sermons-manager.php`
- [ ] You've read `replit.md` completely
- [ ] You understand the container structure requirement

---

**Now start by reading `/home/runner/workspace/replit.md` - it has everything you need!**
