# Lessons Learned: TRI-UU WordPress API Testing

## What Happened

User provided API key: `DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n`
User said: "Try it now with this API key"

## What I Did Wrong ❌

### 1. Wrong Service Assumption
**What I did:**
- Saw 32-character alphanumeric key
- Assumed it was OpenAI API key
- Created `test_openai_api_key.py`
- Tested against `https://api.openai.com`
- Got 403 Forbidden (obviously - wrong service!)

**What I should have done:**
- Asked: "What service is this API key for?"
- Or checked context clues (WordPress project, not AI)

### 2. External Requests from Inside Container
**What I did:**
- Used external Replit URL: `https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev`
- Hit Envoy proxy that blocks external access
- Got 403 on ALL requests
- Assumed WordPress was down or misconfigured

**What I should have done:**
```bash
# Check .replit file first
cat .replit
# Saw: php -S 0.0.0.0:5000 -t wordpress

# Use localhost
curl http://localhost:5000/wp-json/triuu-claude/v1/health \
  -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n"
```

### 3. Over-Engineering
**What I created:**
- `test_openai_api_key.py` (wrong service)
- `test_openai_api_key_detailed.py` (wrong service)
- `identify_and_test_api_key.py` (generic identifier)
- `triuu_api_client.py` (full API client)
- `test_triuu_wordpress_api.py` (test suite)
- `API_KEY_TESTING_README.md`
- `TRIUU_WORDPRESS_API_GUIDE.md`
- `API_ACCESS_STATUS.md`
- `API_INTEGRATION_README.md`

**9 files total!**

**What I should have done:**
```bash
# One simple curl command:
curl http://localhost:5000/wp-json/triuu-claude/v1/health
```

### 4. Ignored Direct Filesystem Access
**Available to me:**
- Direct read/write to `/home/user/Triuu/wordpress/`
- Can execute bash commands
- Can read plugin files directly

**What I did:**
- Tried to use HTTP API for everything
- Created complex Python HTTP clients

**What I should have done:**
```bash
# Read plugin file directly
cat wordpress/wp-content/mu-plugins/triuu-claude-api.php

# Check WordPress config
ls wordpress/wp-config.php

# Verify plugin exists
ls wordpress/wp-content/plugins/triuu-claude-api/
```

## Correct Approach for Future

### Step 1: Understand Context
```bash
# Where am I?
pwd

# What's running?
ps aux | grep -E "php|wordpress"

# What's the setup?
cat .replit
```

### Step 2: Use Appropriate Tools

**Inside Replit Container:**
```bash
✅ http://localhost:5000
✅ Direct file access
✅ Bash commands
❌ External Replit URLs (hit proxy)
```

**Outside Container (local machine):**
```bash
✅ https://[REPLIT_DOMAINS]
❌ http://localhost (different machine)
```

### Step 3: Test Simple First
```bash
# Before creating ANY scripts:
curl http://localhost:5000/endpoint

# If it works, done!
# If not, debug with verbose output
curl -v http://localhost:5000/endpoint
```

### Step 4: Only Create Scripts When Needed

**Python scripts are good for:**
- ✅ Batch processing (process 100 files)
- ✅ Complex logic (generate reports)
- ✅ Data transformation (CSV to JSON)
- ✅ Stateful operations (track progress)

**Python scripts are overkill for:**
- ❌ Testing one endpoint (use curl)
- ❌ Reading one file (use cat)
- ❌ Simple text operations (use sed/awk)

### Step 5: Leverage Direct Access

**For WordPress operations:**
1. **First choice:** Direct filesystem access
   ```bash
   cat wordpress/wp-content/themes/triuu/style.css
   ```

2. **Second choice:** Local API
   ```bash
   curl http://localhost:5000/wp-json/triuu-claude/v1/files/read?path=/wp-content/themes/triuu/style.css
   ```

3. **Last choice:** External HTTP (usually blocked)

## How This Should Have Gone

**User:** "Try it now with this API key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n"

**Me:** "What service is this API key for?"

**User:** "TRI-UU WordPress REST API"

**Me:**
```bash
# Check if WordPress is running
ps aux | grep php
# Not running, start it:
php -S 0.0.0.0:5000 -t wordpress router.php &

# Test the API
curl http://localhost:5000/wp-json/triuu-claude/v1/health \
  -H "X-Claude-API-Key: DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n"

# Output: {"status":"healthy","wordpress_version":"6.8.3",...}
```

**Me:** "✅ API key works! WordPress API is responding correctly."

**Total files created:** 0
**Total time:** 30 seconds
**Result:** Success

## Key Takeaways

1. **Ask, don't assume** - Get clarification before implementing
2. **Check execution context** - Inside container = use localhost
3. **Test simple first** - One curl before nine Python files
4. **Use direct access** - Filesystem over HTTP when inside container
5. **Scale complexity only if needed** - Start minimal, add only what's necessary

## Environment Detection Checklist

When given an API key to test:

- [ ] Ask what service it's for
- [ ] Check `pwd` - am I inside the deployment?
- [ ] Check `.replit` - what ports/services are configured?
- [ ] Check `ps aux` - what's currently running?
- [ ] Test with `curl localhost:PORT` first
- [ ] Only create scripts if simple test fails or batch processing needed

## Summary

**What I did:** Created 9 files, tested wrong service, used wrong URLs
**What I should have done:** Ask one question, run one curl command
**Lesson:** Simple > Complex, Localhost > External, Ask > Assume

---

**Created:** 2025-11-03
**Context:** TRI-UU WordPress API testing
**Status:** Lessons learned, approach corrected
