# API Key Testing Tools

This directory contains tools for testing and validating API keys for various services.

## Overview

Three Python scripts are provided for comprehensive API key testing:

1. **test_openai_api_key.py** - Standard OpenAI API key testing
2. **test_openai_api_key_detailed.py** - Detailed diagnostic testing with full error reporting
3. **identify_and_test_api_key.py** - Universal API key identification and testing

## Scripts

### 1. test_openai_api_key.py

Basic OpenAI API key validation script.

**Usage:**
```bash
python3 test_openai_api_key.py <api_key>
# or
export OPENAI_API_KEY=<api_key>
python3 test_openai_api_key.py
```

**Features:**
- Quick validation of OpenAI API keys
- Shows model, response, and token usage
- Returns appropriate exit codes (0=success, 1=failure)

### 2. test_openai_api_key_detailed.py

Detailed diagnostic script with comprehensive error reporting.

**Usage:**
```bash
python3 test_openai_api_key_detailed.py <api_key>
```

**Features:**
- Format validation (checks for 'sk-' prefix)
- Full HTTP response headers and body
- Detailed error interpretation
- Status code explanations

### 3. identify_and_test_api_key.py

Universal API key identifier and tester.

**Usage:**
```bash
python3 identify_and_test_api_key.py <api_key>
```

**Features:**
- Identifies API service from key format
- Tests against multiple common API endpoints
- Provides recommendations based on key format
- Detects placeholder/example keys

## API Key Format Guide

### OpenAI
- **Format:** `sk-...` (starts with 'sk-')
- **Length:** Variable (typically 48-51 characters)
- **Example:** `sk-proj-abcd1234...`

### Anthropic Claude
- **Format:** `sk-ant-...` (starts with 'sk-ant-')
- **Length:** Variable
- **Example:** `sk-ant-api03-...`

### Google AI / Gemini
- **Format:** 39 alphanumeric characters
- **Example:** `AIzaSyAbCdEfGhIjKlMnOpQrStUvWxYz1234567`

### Generic 32-Character Keys
- **Format:** 32 alphanumeric characters
- **Common services:**
  - OpenWeatherMap
  - NewsAPI
  - Custom APIs
  - Various third-party services

## Important Update: Key Identity Clarified

### ⚠️ This is NOT for OpenAI or Third-Party Services

**The key `DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n` is a custom WordPress REST API authentication key.**

**Correct Use:**
- ✅ TRI-UU WordPress REST API authentication
- ✅ File operations on the WordPress site
- ✅ Managing WordPress themes and plugins

**Incorrect Use:**
- ❌ OpenAI API calls
- ❌ Third-party services (OpenWeatherMap, NewsAPI, etc.)

### Correct Testing

For testing this WordPress API key, please use:
- **Tool:** `test_triuu_wordpress_api.py`
- **Documentation:** `TRIUU_WORDPRESS_API_GUIDE.md`
- **Plugin:** `wordpress/wp-content/mu-plugins/triuu-claude-api.php`

### Legacy Test Results (OpenAI Test - Not Applicable)

The original tests in this document tested the key against OpenAI's API, which was incorrect:

**Analysis:**
- **Length:** 32 characters
- **Type:** Alphanumeric
- **Format:** Does NOT match OpenAI format (missing 'sk-' prefix)
- **Service:** Custom WordPress REST API (not OpenAI)
- **OpenAI Test:** Failed (403 Forbidden - Expected, as this is not an OpenAI key)

**Conclusion:**
This is a custom WordPress authentication key for the TRI-UU site's Claude Code API plugin. The OpenAI testing tools in this directory are not applicable to this key.

## Requirements

- Python 3.6+
- requests library (automatically installed if missing)

## Security Notes

- **Never commit real API keys to version control**
- Use environment variables for production keys
- Add `.env` files to `.gitignore`
- Rotate keys immediately if exposed
- Use separate keys for development/testing/production

## Integration with WordPress

The OpenAI WordPress plugin is located at:
```
/wordpress/wp-content/mu-plugins/openai-wp-service.php
```

It expects the API key to be set via:
1. Environment variable: `OPENAI_API_KEY`
2. PHP constant: `OPENAI_API_KEY` (in wp-config.php)

**Example wp-config.php integration:**
```php
define('OPENAI_API_KEY', 'sk-your-key-here');
```

## Troubleshooting

### 403 Forbidden Error
- Key is invalid or expired
- Key is for a different service
- Key doesn't have required permissions
- Network/firewall blocking request

### 401 Unauthorized Error
- Invalid API key format
- Key has been revoked
- Key not activated

### 429 Rate Limit Error
- Too many requests
- Quota exceeded
- Need to upgrade plan or wait

## Next Steps

1. Identify the correct service for the provided API key
2. Test with the appropriate endpoint
3. Update WordPress configuration if using OpenAI
4. Document the service and usage in project files
