# TRI-UU WordPress Claude Code API Guide

## Overview

This guide documents the custom WordPress REST API plugin created for Claude Code to interact with the TRI-UU WordPress site.

## API Key

```
DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n
```

**Important:** This is a custom WordPress authentication key, NOT an OpenAI or third-party service key.

## Base URL

```
https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1
```

## Plugin Location

The WordPress plugin is located at:
```
/wordpress/wp-content/mu-plugins/triuu-claude-api.php
```

This is a **Must-Use (MU) Plugin** which means it loads automatically when WordPress starts, without needing to be activated through the WordPress admin.

## Authentication

All authenticated endpoints require the API key to be passed in the HTTP header:

```bash
X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n
```

## Available Endpoints

### 1. Health Check
**Endpoint:** `GET /health`
**Authentication:** Not required
**Description:** Check if the API is running

**Example:**
```bash
curl "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/health"
```

**Response:**
```json
{
  "status": "ok",
  "message": "TRI-UU Claude API is running",
  "timestamp": "2025-11-03 18:45:00",
  "wordpress_version": "6.x"
}
```

### 2. Site Information
**Endpoint:** `GET /site-info`
**Authentication:** Required
**Description:** Get WordPress site information

**Example:**
```bash
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/site-info"
```

**Response:**
```json
{
  "site_name": "TRI-UU",
  "site_url": "https://...",
  "home_url": "https://...",
  "wordpress_version": "6.x",
  "php_version": "8.x",
  "theme": "Triuu Theme",
  "theme_version": "1.0",
  "content_dir": "/path/to/wp-content",
  "plugin_dir": "/path/to/plugins",
  "theme_dir": "/path/to/theme"
}
```

### 3. List Files
**Endpoint:** `GET /files/list`
**Authentication:** Required
**Parameters:**
- `path` (optional): Directory path relative to wp-content (default: `/`)

**Description:** List files and directories within wp-content

**Example:**
```bash
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/files/list?path=/themes/triuu"
```

**Response:**
```json
{
  "path": "/themes/triuu",
  "full_path": "/absolute/path/to/wp-content/themes/triuu",
  "items": [
    {
      "name": "style.css",
      "path": "/themes/triuu/style.css",
      "type": "file",
      "size": 12345,
      "modified": 1730000000
    },
    {
      "name": "functions.php",
      "path": "/themes/triuu/functions.php",
      "type": "file",
      "size": 54321,
      "modified": 1730000000
    }
  ],
  "count": 2
}
```

### 4. Read File
**Endpoint:** `GET /files/read`
**Authentication:** Required
**Parameters:**
- `path` (required): File path relative to wp-content

**Description:** Read the contents of a file

**Example:**
```bash
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/files/read?path=/themes/triuu/style.css"
```

**Response:**
```json
{
  "path": "/themes/triuu/style.css",
  "full_path": "/absolute/path/to/wp-content/themes/triuu/style.css",
  "content": "/* Theme CSS content here */",
  "size": 12345,
  "modified": 1730000000,
  "encoding": "UTF-8"
}
```

### 5. Write File
**Endpoint:** `POST /files/write`
**Authentication:** Required
**Parameters:**
- `path` (required): File path relative to wp-content
- `content` (required): File content to write

**Description:** Write content to a file

**Example:**
```bash
curl -X POST \
  -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  -H "Content-Type: application/json" \
  -d '{
    "path": "/themes/triuu/custom.css",
    "content": "/* Custom styles */"
  }' \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/files/write"
```

**Response:**
```json
{
  "success": true,
  "path": "/themes/triuu/custom.css",
  "full_path": "/absolute/path/to/wp-content/themes/triuu/custom.css",
  "bytes_written": 23,
  "message": "File written successfully"
}
```

### 6. List Plugins
**Endpoint:** `GET /plugins/list`
**Authentication:** Required
**Description:** List all WordPress plugins

**Example:**
```bash
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/plugins/list"
```

**Response:**
```json
{
  "plugins": [
    {
      "path": "elementor/elementor.php",
      "name": "Elementor",
      "version": "3.x",
      "author": "Elementor Team",
      "description": "Page builder plugin",
      "active": true
    }
  ],
  "count": 10,
  "active_count": 5
}
```

### 7. List Themes
**Endpoint:** `GET /themes/list`
**Authentication:** Required
**Description:** List all WordPress themes

**Example:**
```bash
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/themes/list"
```

**Response:**
```json
{
  "themes": [
    {
      "slug": "triuu",
      "name": "Triuu Theme",
      "version": "1.0",
      "author": "TRI-UU Development",
      "description": "Custom theme for TRI-UU",
      "template": "triuu",
      "stylesheet": "triuu",
      "active": true
    }
  ],
  "count": 3,
  "current_theme": "Triuu Theme"
}
```

## Error Responses

### Missing API Key (401)
```json
{
  "code": "missing_api_key",
  "message": "API key is required. Please provide X-Claude-API-Key header.",
  "data": {
    "status": 401
  }
}
```

### Invalid API Key (403)
```json
{
  "code": "invalid_api_key",
  "message": "Invalid API key provided.",
  "data": {
    "status": 403
  }
}
```

### Invalid Path (400)
```json
{
  "code": "invalid_path",
  "message": "Path must be within wp-content directory",
  "data": {
    "status": 400
  }
}
```

### File/Directory Not Found (404)
```json
{
  "code": "file_not_found",
  "message": "File not found: /path/to/file",
  "data": {
    "status": 404
  }
}
```

## Security Features

### Path Security
- All file operations are restricted to the `wp-content` directory
- Directory traversal attempts (`../`) are automatically blocked
- Paths are sanitized to prevent unauthorized access

### API Key Authentication
- API key is stored in the plugin file (should be moved to wp-config.php for production)
- All endpoints (except health check) require valid authentication
- Failed authentication returns appropriate error messages

### Recommended Production Security Improvements

1. **Move API key to wp-config.php:**
   ```php
   define('TRIUU_CLAUDE_API_KEY', 'your-key-here');
   ```

2. **Add IP whitelisting:**
   ```php
   $allowed_ips = ['1.2.3.4', '5.6.7.8'];
   if (!in_array($_SERVER['REMOTE_ADDR'], $allowed_ips)) {
       wp_die('Access denied');
   }
   ```

3. **Add rate limiting:**
   - Implement request rate limiting per IP
   - Use WordPress transients to track requests

4. **Enable HTTPS only:**
   - Ensure all requests use HTTPS
   - Add HSTS headers

## Testing Tool

A Python testing tool is provided: `test_triuu_wordpress_api.py`

**Usage:**
```bash
python3 test_triuu_wordpress_api.py [api_key] [base_url]
```

**Default values:**
- API Key: `DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n`
- Base URL: `https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1`

**Example:**
```bash
# Test with defaults
python3 test_triuu_wordpress_api.py

# Test with custom API key
python3 test_triuu_wordpress_api.py "your-api-key-here"

# Test with custom URL
python3 test_triuu_wordpress_api.py "your-api-key" "https://your-site.com/wp-json/triuu-claude/v1"
```

## Current Status

**⚠️ Important Note:** As of the latest test, all endpoints are returning **403 Access Denied**, including:
- The main WordPress site
- The WordPress REST API root endpoint
- All custom API endpoints

This suggests:
1. The WordPress site may not be running on the Replit URL
2. There may be site-level authentication required
3. The URL might be expired or incorrect
4. The site is behind a WAF or security layer blocking requests

**Next Steps:**
1. Verify the WordPress site is accessible at the provided URL
2. Check if there's site-level authentication required
3. Confirm the Replit instance is running
4. Deploy the plugin file to the live WordPress instance (if different from local)

## Plugin Deployment

The plugin file is located at:
```
/home/user/Triuu/wordpress/wp-content/mu-plugins/triuu-claude-api.php
```

**For Must-Use (MU) Plugins:**
1. Place the file in `/wp-content/mu-plugins/`
2. WordPress automatically loads it (no activation needed)
3. Verify in WordPress admin: Plugins → Must-Use

**For Regular Plugins:**
1. Create a subdirectory: `/wp-content/plugins/triuu-claude-api/`
2. Move the file there
3. Activate through WordPress admin

## Use Cases

### Reading Theme Files
```bash
# List theme directory
curl -H "X-Claude-API-Key: ..." \
  ".../files/list?path=/themes/triuu"

# Read style.css
curl -H "X-Claude-API-Key: ..." \
  ".../files/read?path=/themes/triuu/style.css"
```

### Modifying Plugin Files
```bash
# Read plugin file
curl -H "X-Claude-API-Key: ..." \
  ".../files/read?path=/plugins/triuu-sermons-manager/triuu-sermons-manager.php"

# Write modified content
curl -X POST -H "X-Claude-API-Key: ..." \
  -d '{"path":"/plugins/triuu-sermons-manager/triuu-sermons-manager.php","content":"<?php ..."}' \
  ".../files/write"
```

### Managing WordPress Content
```bash
# Get site information
curl -H "X-Claude-API-Key: ..." ".../site-info"

# List all plugins
curl -H "X-Claude-API-Key: ..." ".../plugins/list"

# List all themes
curl -H "X-Claude-API-Key: ..." ".../themes/list"
```

## Support

For issues or questions about the TRI-UU WordPress API:
1. Check the plugin file for inline documentation
2. Review error messages returned by the API
3. Verify API key and permissions
4. Check WordPress error logs

## Version History

- **v1.0.0** - Initial release
  - Health check endpoint
  - Site information endpoint
  - File operations (list, read, write)
  - Plugin and theme listing
  - API key authentication
  - Path security features
