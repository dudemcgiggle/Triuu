#!/usr/bin/env python3
"""
OpenAI API Key Testing Script
Tests if the provided API key is valid by making a simple API call.
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


def test_openai_api_key(api_key):
    """
    Test if an OpenAI API key is valid.

    Args:
        api_key: The OpenAI API key to test

    Returns:
        dict: Result containing success status and details
    """
    print(f"Testing OpenAI API key: {api_key[:8]}...{api_key[-4:]}")
    print("-" * 60)

    url = "https://api.openai.com/v1/chat/completions"
    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }

    # Simple test payload
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

        print(f"Response Status Code: {response.status_code}")

        if response.status_code == 200:
            data = response.json()
            content = data.get('choices', [{}])[0].get('message', {}).get('content', '')
            model = data.get('model', 'unknown')
            usage = data.get('usage', {})

            print("\n✓ SUCCESS: API key is valid and working!")
            print(f"\nModel: {model}")
            print(f"Response: {content}")
            print(f"\nToken Usage:")
            print(f"  - Prompt tokens: {usage.get('prompt_tokens', 'N/A')}")
            print(f"  - Completion tokens: {usage.get('completion_tokens', 'N/A')}")
            print(f"  - Total tokens: {usage.get('total_tokens', 'N/A')}")

            return {
                "success": True,
                "status_code": response.status_code,
                "model": model,
                "response": content,
                "usage": usage
            }
        else:
            error_data = response.json() if response.text else {}
            error_message = error_data.get('error', {}).get('message', response.text)

            print(f"\n✗ FAILED: API returned error status {response.status_code}")
            print(f"Error: {error_message}")

            return {
                "success": False,
                "status_code": response.status_code,
                "error": error_message
            }

    except requests.exceptions.Timeout:
        print("\n✗ FAILED: Request timed out")
        return {"success": False, "error": "Request timeout"}

    except requests.exceptions.RequestException as e:
        print(f"\n✗ FAILED: Network error - {str(e)}")
        return {"success": False, "error": str(e)}

    except Exception as e:
        print(f"\n✗ FAILED: Unexpected error - {str(e)}")
        return {"success": False, "error": str(e)}


if __name__ == "__main__":
    # Check if API key is provided as command line argument
    if len(sys.argv) > 1:
        api_key = sys.argv[1]
    else:
        # Check environment variable
        api_key = os.getenv('OPENAI_API_KEY')
        if not api_key:
            print("Error: No API key provided!")
            print("Usage: python test_openai_api_key.py <api_key>")
            print("   or: export OPENAI_API_KEY=<api_key> && python test_openai_api_key.py")
            sys.exit(1)

    result = test_openai_api_key(api_key)

    # Exit with appropriate code
    sys.exit(0 if result.get("success") else 1)
