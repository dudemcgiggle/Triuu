# TRI-UU WordPress API Integration

## Quick Start

### API Key
```
DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n
```

### Base URL
```
https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1
```

### Current Status
🔴 **Access Blocked** - API is live but external access is blocked by proxy

## Files in This Repository

### API Client & Testing Tools

1. **[triuu_api_client.py](./triuu_api_client.py)** ⭐ **RECOMMENDED**
   - Full-featured Python API client
   - Object-oriented interface
   - All endpoints implemented
   - Error handling
   - Usage examples included

   ```bash
   python3 triuu_api_client.py
   ```

2. **[test_triuu_wordpress_api.py](./test_triuu_wordpress_api.py)**
   - Comprehensive test suite
   - Tests all API endpoints
   - Reports pass/fail status

   ```bash
   python3 test_triuu_wordpress_api.py
   ```

### Documentation

3. **[TRIUU_WORDPRESS_API_GUIDE.md](./TRIUU_WORDPRESS_API_GUIDE.md)** 📚 **MAIN DOCS**
   - Complete API documentation
   - All endpoints with examples
   - Security features
   - Usage guide

4. **[API_ACCESS_STATUS.md](./API_ACCESS_STATUS.md)** ⚠️ **CURRENT ISSUE**
   - Detailed status of access issue
   - Troubleshooting steps
   - Possible solutions
   - Testing procedures

5. **[API_KEY_TESTING_README.md](./API_KEY_TESTING_README.md)**
   - Original API key testing tools
   - OpenAI API testing (not applicable here)
   - Updated with clarification

### WordPress Plugin

6. **[wordpress/wp-content/mu-plugins/triuu-claude-api.php](./wordpress/wp-content/mu-plugins/triuu-claude-api.php)**
   - Local copy of WordPress plugin
   - **Note:** Actual live plugin is at `/wordpress/wp-content/plugins/triuu-claude-api/`

### Legacy Testing Tools

7. **test_openai_api_key.py** - OpenAI testing (not applicable)
8. **test_openai_api_key_detailed.py** - OpenAI testing (not applicable)
9. **identify_and_test_api_key.py** - Generic API key identifier

## What This API Does

The TRI-UU WordPress API provides programmatic access to:

### File Operations
- **Read** files from wp-content
- **Write/Update** existing files
- **Create** new files
- **Delete** files (with automatic backup)
- **List** directory contents
- **Move/Rename** files
- **Copy** files

### Content Management
- **Get** all posts and pages
- **Create** new posts
- **Update** existing posts
- Access media library
- Upload media files

### Theme Management
- **Read** child theme CSS
- **Append** CSS to child theme
- Modify theme files

### Plugin Management
- **Read** sermons plugin code
- **Update** sermons plugin
- Modify plugin files

### System Information
- Get WordPress version
- Get PHP version
- Get site configuration
- List installed plugins and themes

## Using the API Client

### Basic Usage

```python
from triuu_api_client import TriuuAPIClient

# Initialize client
client = TriuuAPIClient(
    base_url="https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1",
    api_key="DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n",
    verbose=True  # Enable detailed logging
)

# Get site info
info = client.site_info()
print(info['site_name'])

# Read a file
file_data = client.read_file('/wp-content/themes/triuu/style.css')
print(file_data['content'])

# List directory
files = client.list_files('/wp-content/themes/triuu')
for item in files['items']:
    print(f"{item['name']} - {item['type']}")

# Write a file
result = client.write_file(
    '/wp-content/themes/triuu/custom.css',
    '/* Custom CSS */'
)
print(result['message'])
```

### Advanced Usage

```python
# Get all posts
posts = client.get_posts()
for post in posts:
    print(f"{post['title']} - {post['status']}")

# Create a new post
new_post = client.create_post(
    title="My New Post",
    content="<p>Post content here</p>",
    status="draft"
)

# Upload media
result = client.upload_media(
    '/path/to/image.jpg',
    title='My Image'
)

# Append CSS to theme
client.append_theme_css('''
.custom-class {
    color: red;
}
''')
```

## Available Endpoints

### Authentication: None Required
- `GET /health` - Health check

### Authentication: API Key Required (X-Claude-API-Key header)

**Site Info:**
- `GET /site-info`

**File Operations:**
- `GET /files/read?path=<path>`
- `POST /files/write` (body: `{path, content}`)
- `POST /files/create` (body: `{path, content}`)
- `DELETE /files/delete?path=<path>`
- `GET /files/list?path=<path>`
- `POST /files/move` (body: `{from, to}`)
- `POST /files/copy` (body: `{from, to}`)

**Content:**
- `GET /content/posts`
- `GET /content/pages`
- `POST /content/posts` (body: `{title, content, ...}`)
- `PUT /content/posts/{id}` (body: `{title, content, ...}`)

**Media:**
- `GET /media`
- `POST /media/upload` (multipart form)

**Theme:**
- `GET /theme/css`
- `POST /theme/css` (body: `{css}`)

**Plugins:**
- `GET /plugins/sermons`
- `PUT /plugins/sermons` (body: `{code}`)

**System:**
- `POST /system/execute` (body: `{command}`) ⚠️ **USE WITH CAUTION**

## Current Access Issue

### Problem
All requests return `403 Access denied` from an Envoy proxy before reaching WordPress.

### Evidence
```bash
$ curl "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/health"
Access denied
```

### What's Working
✅ WordPress site is running
✅ API plugin is installed and active
✅ API key is configured
✅ Endpoints are registered

### What's Not Working
❌ External access is blocked by proxy/WAF

### Solutions
See **[API_ACCESS_STATUS.md](./API_ACCESS_STATUS.md)** for:
- Detailed analysis
- Possible solutions
- Replit configuration steps
- Alternative access methods

## Testing Once Access is Restored

### Quick Test
```bash
# Should return: {"status":"healthy",...}
curl "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/health"
```

### Full Test Suite
```bash
python3 test_triuu_wordpress_api.py
```

Expected output:
```
TRI-UU WORDPRESS API TEST SUITE
✓ PASS - Health Check
✓ PASS - Site Info
✓ PASS - List Files
✓ PASS - List Plugins
✓ PASS - List Themes

Results: 5/5 tests passed
🎉 All tests passed! API is working correctly.
```

## Safe File Operations

### Safe to Modify
✅ `/wp-content/themes/triuu/style.css` - Child theme CSS
✅ `/wp-content/themes/triuu/functions.php` - Child theme functions
✅ `/wp-content/plugins/triuu-sermons-manager/` - Custom plugin files

### DO NOT MODIFY
❌ `/wp-config.php` - WordPress configuration
❌ `/wp-content/database/` - SQLite database
❌ `/wp-content/mu-plugins/elementor-scope-fixer.php` - Critical MU plugin
❌ `/wp-content/uploads/elementor/css/` - Auto-generated Elementor CSS

## Security Features

1. **API Key Authentication** - All sensitive endpoints require valid API key
2. **Path Restrictions** - File operations restricted to wp-content directory
3. **Automatic Backups** - Files are backed up before deletion
4. **Path Sanitization** - Directory traversal attempts are blocked
5. **Error Messages** - Detailed but safe error reporting

## Environment Variables

Optional environment variables for the API client:

```bash
export TRIUU_API_URL="https://your-site.com/wp-json/triuu-claude/v1"
export TRIUU_API_KEY="your-api-key-here"
```

Then run:
```bash
python3 triuu_api_client.py
```

## Example Workflows

### Update Theme CSS
```python
client = TriuuAPIClient(base_url, api_key)

# Read current CSS
css_data = client.read_file('/wp-content/themes/triuu/style.css')
current_css = css_data['content']

# Append new CSS
new_css = current_css + "\n\n/* New styles */\n.my-class { color: blue; }\n"

# Write back
client.write_file('/wp-content/themes/triuu/style.css', new_css)
```

### Create a New Post
```python
client = TriuuAPIClient(base_url, api_key)

post = client.create_post(
    title="Weekly Sermon: Faith and Hope",
    content="<p>This week's message focuses on...</p>",
    status="publish",
    categories=[1, 5]  # Category IDs
)

print(f"Created post ID: {post['id']}")
```

### Backup and Modify Plugin
```python
client = TriuuAPIClient(base_url, api_key)

# Read current plugin code
code = client.get_sermons_plugin()

# Create backup
client.create_file(
    '/wp-content/plugins/triuu-sermons-manager/backup.php.bak',
    code
)

# Make modifications
modified_code = code.replace('old_function', 'new_function')

# Update plugin
client.update_sermons_plugin(modified_code)
```

## Support & Troubleshooting

### Common Issues

**Issue:** `403 Access denied`
**Solution:** See [API_ACCESS_STATUS.md](./API_ACCESS_STATUS.md)

**Issue:** `401 Missing API key`
**Solution:** Add `-H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n"`

**Issue:** `404 File not found`
**Solution:** Verify file path is correct and starts with `/wp-content/`

**Issue:** `400 Invalid path`
**Solution:** Ensure path is within wp-content directory

### Getting Help

1. Check the documentation: [TRIUU_WORDPRESS_API_GUIDE.md](./TRIUU_WORDPRESS_API_GUIDE.md)
2. Review access status: [API_ACCESS_STATUS.md](./API_ACCESS_STATUS.md)
3. Run the test suite: `python3 test_triuu_wordpress_api.py`
4. Enable verbose mode in API client for detailed logging

## Version History

- **v1.0** - Initial API implementation
  - Basic file operations
  - Content management
  - Theme and plugin operations
  - API key authentication

- **v1.1** - Enhanced features (current)
  - Improved error handling
  - Automatic backups
  - Comprehensive documentation
  - Python client library
  - Test suite

---

**Last Updated:** 2025-11-03
**Status:** 🔴 API Ready, Access Blocked
**Next Step:** Configure proxy/WAF access
