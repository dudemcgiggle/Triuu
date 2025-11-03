# WordPress SQLite Database Fix - November 2025

## 🔍 Problem Summary

The WordPress site was completely broken due to a corrupted `wp-content/db.php` file. Instead of containing the proper SQLite database driver code, it had HTML error page content, preventing WordPress from connecting to the SQLite database.

## 🎯 Root Cause

The `db.php` drop-in file is critical for WordPress—it's loaded instead of the standard MySQL driver. When this file was corrupted with HTML error content, WordPress tried to execute HTML as PHP code, causing fatal errors and preventing any database connection.

**Key Issue**: Even though the SQLite database file itself was perfectly fine (22MB with all production data - 591 posts, 11 pages, 5 sermons), WordPress couldn't access it due to the corrupted driver.

## ✅ Solution Implemented

### 1. **Removed Corrupted db.php**
Deleted the broken HTML-filled version of `wp-content/db.php`

### 2. **Installed SQLite Database Integration Plugin**
- **Plugin**: SQLite Database Integration v2.1.15
- **Source**: WordPress.org official plugin repository
- **Location**: `wp-content/plugins/sqlite-database-integration/`

### 3. **Created Proper db.php Drop-in**
Created a new `wp-content/db.php` with correct file paths:
- Fixed path to `constants.php`
- Fixed paths to all SQLite class files:
  - `class-wp-sqlite-db.php`
  - `class-wp-sqlite-lexer.php`
  - `class-wp-sqlite-token.php`
  - `class-wp-sqlite-translator.php`
  - `class-wp-sqlite-query-rewriter.php`
  - `db.php` (SQLite driver core)
- All paths now correctly point to: `wp-content/plugins/sqlite-database-integration/wp-includes/sqlite/`

### 4. **Added Required Constant to wp-config.php**
Added the following line to `wp-config.php`:
```php
define('DB_ENGINE', 'sqlite');
```
The SQLite plugin requires this constant to be set.

### 5. **Fixed Startup Script**
Removed unnecessary PostgreSQL references from `start-wordpress.sh` since the site uses SQLite.

## 📁 Current Configuration

### Database
- **Engine**: SQLite
- **Database File**: `wp-content/database/wordpress.db` (22MB)
- **Table Prefix**: `RYX_`

### Content Statistics
- **Posts**: 591
- **Pages**: 11
- **Sermons**: 5

### WordPress Version
- **WordPress**: 6.8.3
- **PHP**: 8.2.23 (Development Server)

## 🚀 How to Start WordPress

```bash
cd ~/workspace
php -S 0.0.0.0:5000 -t wordpress router.php
```

Then access the site at: **http://localhost:5000**

## 🔒 Protected Files (.gitignore)

The following are excluded from version control for security and size:
- `wordpress/wp-content/database/` - Database files (*.db)
- `wordpress/wp-content/db.php` - SQLite drop-in (managed by plugin)
- `wordpress/wp-content/plugins/sqlite-database-integration/` - Plugin files
- `wordpress/wp-config.php` - Configuration with credentials
- `temp-db-info.txt` - SQL dump backups

## 📝 Important Notes

1. **Database Driver**: The `wp-content/db.php` file is automatically managed by the SQLite Database Integration plugin. Do not edit it manually.

2. **Database Location**: The SQLite database file must be at `wp-content/database/wordpress.db` for the plugin to find it.

3. **DB_ENGINE Constant**: This must remain set to `'sqlite'` in `wp-config.php` for the plugin to activate.

4. **Backups**: The original database backup is available as `wordpress.db.backup` (22MB) and SQL dump as `temp-db-info.txt` (22MB).

## 🛠️ Troubleshooting

### If WordPress shows database connection errors:

1. **Verify db.php exists and is valid**:
   ```bash
   ls -lh wordpress/wp-content/db.php
   head -5 wordpress/wp-content/db.php
   ```
   Should show PHP code, not HTML.

2. **Verify database file exists**:
   ```bash
   ls -lh wordpress/wp-content/database/wordpress.db
   ```
   Should show ~22MB file.

3. **Check DB_ENGINE constant**:
   ```bash
   grep "DB_ENGINE" wordpress/wp-config.php
   ```
   Should show: `define('DB_ENGINE', 'sqlite');`

4. **Verify plugin is installed**:
   ```bash
   ls -la wordpress/wp-content/plugins/sqlite-database-integration/
   ```

### If site is slow or unresponsive:

Check for stuck processes:
```bash
ps aux | grep php
pkill -f "php.*5000"
```

## 📚 Reference Links

- [SQLite Database Integration Plugin](https://wordpress.org/plugins/sqlite-database-integration/)
- [WordPress Database Drop-ins](https://developer.wordpress.org/advanced-administration/wordpress/wp-config/#database-drop-in)

## ✅ Verification Checklist

After setup, verify:
- [ ] WordPress homepage loads
- [ ] Posts are accessible
- [ ] Pages load correctly
- [ ] Admin panel works
- [ ] Images display properly
- [ ] No PHP errors in server output

---

**Last Updated**: November 3, 2025
**Status**: ✅ Fully Operational
**Database**: SQLite (22MB, all production data intact)
