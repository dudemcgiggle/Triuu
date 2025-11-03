#!/usr/bin/env python3
"""
Detailed OpenAI API Key Testing Script
Provides detailed diagnostics for API key validation.
"""

import os
import json
import sys

try:
    import requests
except ImportError:
    print("Installing requests library...")
    os.system("pip install requests")
    import requests


def test_openai_api_key_detailed(api_key):
    """
    Test OpenAI API key with detailed error reporting.
    """
    print(f"Testing OpenAI API key: {api_key[:8]}...{api_key[-4:]}")
    print(f"Key length: {len(api_key)} characters")
    print("-" * 60)

    url = "https://api.openai.com/v1/chat/completions"
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }

    payload = {
        "model": "gpt-4o-mini",
        "messages": [
            {"role": "user", "content": "Say 'API key test successful' if you can read this."}
        ],
        "max_tokens": 20,
        "temperature": 0.1
    }

    try:
        print("Making API request to OpenAI...")
        response = requests.post(url, headers=headers, json=payload, timeout=30)

        print(f"Status Code: {response.status_code}")
        print(f"Response Headers: {dict(response.headers)}")
        print(f"\nRaw Response Body:")
        print(response.text)
        print("-" * 60)

        if response.status_code == 200:
            data = response.json()
            content = data.get('choices', [{}])[0].get('message', {}).get('content', '')
            print("\n✓ SUCCESS: API key is valid and working!")
            print(f"Response: {content}")
            return {"success": True, "response": content}

        else:
            try:
                error_data = response.json()
                print("\n✗ FAILED: API Error")
                print(json.dumps(error_data, indent=2))
            except:
                print("\n✗ FAILED: Could not parse error response")
                print(f"Raw response: {response.text}")

            # Common error interpretations
            if response.status_code == 401:
                print("\nInterpretation: Invalid API key (Unauthorized)")
            elif response.status_code == 403:
                print("\nInterpretation: Access forbidden - API key may be invalid, expired, or from a different service")
            elif response.status_code == 429:
                print("\nInterpretation: Rate limit exceeded or quota reached")
            elif response.status_code == 500:
                print("\nInterpretation: OpenAI server error")

            return {"success": False, "status": response.status_code}

    except Exception as e:
        print(f"\n✗ FAILED: {type(e).__name__}: {str(e)}")
        import traceback
        traceback.print_exc()
        return {"success": False, "error": str(e)}


if __name__ == "__main__":
    if len(sys.argv) > 1:
        api_key = sys.argv[1]
    else:
        api_key = os.getenv('OPENAI_API_KEY')
        if not api_key:
            print("Error: No API key provided!")
            sys.exit(1)

    # Also test basic API key format
    print("\nAPI Key Format Check:")
    if api_key.startswith('sk-'):
        print("✓ Key starts with 'sk-' (standard OpenAI format)")
    else:
        print("⚠ Key does NOT start with 'sk-' (unusual for OpenAI keys)")

    if len(api_key) < 20:
        print("⚠ Key seems too short")
    elif len(api_key) > 200:
        print("⚠ Key seems too long")
    else:
        print(f"✓ Key length ({len(api_key)}) is reasonable")

    print("\n" + "=" * 60)
    result = test_openai_api_key_detailed(api_key)
    sys.exit(0 if result.get("success") else 1)
