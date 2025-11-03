# TRI-UU WordPress API Access Status

## Current Situation

### ✅ What's Working
- WordPress site is live and running
- API plugin is installed and active in WordPress admin
- API key is configured: `DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n`
- All endpoints are registered in WordPress

### ❌ Current Issue: Proxy/WAF Blocking External Access

**Problem:** All requests to the WordPress site return `403 Access denied` before reaching WordPress.

**Evidence:**
```bash
$ curl "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/"
Access denied

$ curl "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/"
Access denied

$ curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/health"
Access denied
```

**HTTP Response Analysis:**
```
HTTP/1.1 200 OK          # Initial connection to envoy proxy succeeds
date: Mon, 03 Nov 2025 18:49:24 GMT
server: envoy            # Envoy proxy in front of WordPress

HTTP/2 403               # Actual response is 403 Forbidden
content-length: 13
content-type: text/plain
```

## Root Cause

There is an **Envoy proxy** or WAF (Web Application Firewall) in front of the WordPress site that is blocking external requests.

This is likely:
1. **Replit's security layer** - Replit deployments can have access restrictions
2. **IP whitelist** - Only certain IPs are allowed to access the site
3. **Authentication requirement** - Additional authentication needed at proxy level
4. **CORS/Origin restrictions** - Requests from certain origins are blocked

## Possible Solutions

### Option 1: Replit Access Configuration
If this is a Replit deployment, check:

1. **Secrets/Environment Variables:**
   - Check if there's a `REPLIT_AUTH_TOKEN` or similar
   - Look for firewall/access control environment variables

2. **Replit Deployment Settings:**
   - Open the Replit project
   - Check "Deploy" settings
   - Look for "Access Control" or "Security" settings
   - Ensure the deployment is set to "Public" if external access is needed

3. **Replit Firewall Rules:**
   - Check if IP whitelisting is enabled
   - Add the IP addresses that need access

### Option 2: Add Proxy Bypass Header
Some proxies allow bypass with a specific header:

```bash
curl -H "X-Replit-User-Id: <user-id>" \
     -H "X-Replit-User-Name: <username>" \
     -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
     "https://.../"
```

### Option 3: Use Replit Auth
If using Replit's authentication:

```bash
curl -H "Authorization: Bearer <replit-token>" \
     -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
     "https://.../"
```

### Option 4: Alternative Access Method
If external access is permanently restricted:

1. **Local development:** Test API on local WordPress installation
2. **Replit Shell:** Run commands directly in Replit's shell environment
3. **WordPress Plugin:** Use WordPress admin interface to test
4. **Alternative URL:** Check if there's a different public URL

## Testing Once Access is Restored

### Quick Test Commands

```bash
# 1. Health check (no auth required)
curl "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/health"

# Expected response:
# {"status":"healthy","wordpress_version":"6.8.3","php_version":"8.2.23",...}

# 2. Site info (requires auth)
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/site-info"

# Expected response:
# {"site_name":"TRI-UU","wordpress_version":"6.8.3",...}

# 3. List files
curl -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n" \
  "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1/files/list?path=/wp-content/themes/triuu"

# Expected response:
# {"path":"/wp-content/themes/triuu","items":[...]}
```

### Python Test Suite

```bash
# Run comprehensive test suite
python3 test_triuu_wordpress_api.py

# Or use the API client
python3 triuu_api_client.py
```

### Expected Test Results
When access is working, you should see:
```
TRI-UU WORDPRESS API TEST SUITE
Base URL: https://...
API Key: DnUA...WZ7n

✓ PASS - Health Check
✓ PASS - Site Info
✓ PASS - List Files
✓ PASS - List Plugins
✓ PASS - List Themes

Results: 5/5 tests passed

🎉 All tests passed! API is working correctly.
```

## Files Ready for Use

Once access is configured, these files are ready:

1. **triuu_api_client.py** - Full-featured Python API client
2. **test_triuu_wordpress_api.py** - Test suite for all endpoints
3. **TRIUU_WORDPRESS_API_GUIDE.md** - Complete API documentation

## Debugging Access Issues

### Check from Replit Shell
If you have access to the Replit shell:

```bash
# Test from inside Replit
curl localhost:80/wp-json/triuu-claude/v1/health

# Or
curl 127.0.0.1/wp-json/triuu-claude/v1/health
```

### Check WordPress Logs
Look for error logs in:
- `/wp-content/debug.log`
- Replit console output
- Apache/Nginx error logs

### Verify Plugin is Active
In WordPress admin:
1. Go to Plugins → Installed Plugins
2. Check that "TRI-UU Claude Code API" is active
3. Go to Settings → Claude Code API
4. Verify API key matches: `DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n`

## Next Steps

1. **Check Replit deployment settings** for access control
2. **Verify if site is truly public** or requires authentication
3. **Get any required authentication tokens** for proxy access
4. **Test from Replit shell** if external access is restricted
5. **Once access is confirmed,** run test suite to verify all endpoints

## Support Information

**API Base URL:**
```
https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1
```

**API Key:**
```
DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n
```

**Server:** Envoy proxy in front of WordPress

**Issue:** 403 Forbidden from proxy, before reaching WordPress

**Status:** WordPress API is ready, but proxy/WAF is blocking external access

---

**Last Updated:** 2025-11-03
**Status:** 🔴 Access Blocked (Proxy/WAF Issue)
