#!/usr/bin/env python3
"""
Universal API Key Identification and Testing Script
Identifies the service and tests the API key accordingly.
"""

import os
import json
import sys
import re

try:
    import requests
except ImportError:
    print("Installing requests library...")
    os.system("pip install requests")
    import requests


def identify_api_key_service(api_key):
    """
    Attempt to identify which service an API key belongs to based on format.
    """
    identifications = []

    # OpenAI
    if api_key.startswith('sk-'):
        identifications.append({
            "service": "OpenAI",
            "confidence": "high",
            "reason": "Starts with 'sk-' prefix"
        })

    # Anthropic Claude
    if api_key.startswith('sk-ant-'):
        identifications.append({
            "service": "Anthropic Claude",
            "confidence": "high",
            "reason": "Starts with 'sk-ant-' prefix"
        })

    # Google AI/Gemini (typically 39 chars)
    if len(api_key) == 39 and api_key.isalnum():
        identifications.append({
            "service": "Google AI / Gemini",
            "confidence": "medium",
            "reason": "39 alphanumeric characters"
        })

    # Generic 32-char keys (could be many services)
    if len(api_key) == 32 and api_key.isalnum():
        identifications.append({
            "service": "Unknown Service (32-char alphanumeric)",
            "confidence": "low",
            "reason": "32 character alphanumeric format - could be custom API, weather API, news API, etc."
        })

    # Hugging Face
    if api_key.startswith('hf_'):
        identifications.append({
            "service": "Hugging Face",
            "confidence": "high",
            "reason": "Starts with 'hf_' prefix"
        })

    # If no specific identification
    if not identifications:
        identifications.append({
            "service": "Unknown",
            "confidence": "unknown",
            "reason": f"Format: {len(api_key)} chars, pattern not recognized"
        })

    return identifications


def test_generic_endpoints(api_key):
    """
    Test common API patterns with the provided key.
    """
    print("\n" + "=" * 60)
    print("Testing Generic API Endpoints")
    print("=" * 60)

    test_results = []

    # Test 1: OpenAI (even though format doesn't match)
    print("\n1. Testing OpenAI endpoint...")
    try:
        response = requests.post(
            "https://api.openai.com/v1/chat/completions",
            headers={
                "Authorization": f"Bearer {api_key}",
                "Content-Type": "application/json"
            },
            json={
                "model": "gpt-4o-mini",
                "messages": [{"role": "user", "content": "test"}],
                "max_tokens": 10
            },
            timeout=10
        )
        result = f"Status: {response.status_code}"
        if response.status_code == 200:
            result += " ✓ SUCCESS"
        test_results.append(("OpenAI", result))
        print(f"   {result}")
    except Exception as e:
        test_results.append(("OpenAI", f"Error: {str(e)}"))
        print(f"   Error: {str(e)}")

    # Test 2: Check if it's a placeholder/example key
    print("\n2. Checking for common test patterns...")
    if api_key in ['test', 'demo', 'example', 'YOUR_API_KEY', 'xxxxxxxxxxxx']:
        print("   ⚠ This appears to be a placeholder/example key")
        test_results.append(("Pattern Check", "Placeholder detected"))
    else:
        print("   ✓ Does not match common placeholder patterns")
        test_results.append(("Pattern Check", "Appears to be real key"))

    return test_results


def main(api_key):
    """
    Main function to identify and test the API key.
    """
    print("=" * 60)
    print("API Key Analysis and Testing Tool")
    print("=" * 60)

    # Display masked key
    if len(api_key) > 12:
        masked = api_key[:4] + "..." + api_key[-4:]
    else:
        masked = api_key[:2] + "..." + api_key[-2:]

    print(f"\nAPI Key: {masked}")
    print(f"Length: {len(api_key)} characters")
    print(f"Type: {'Alphanumeric' if api_key.isalnum() else 'Contains special chars'}")

    # Identify service
    print("\n" + "=" * 60)
    print("Service Identification")
    print("=" * 60)

    identifications = identify_api_key_service(api_key)

    for i, ident in enumerate(identifications, 1):
        print(f"\n{i}. {ident['service']}")
        print(f"   Confidence: {ident['confidence']}")
        print(f"   Reason: {ident['reason']}")

    # Run tests
    test_results = test_generic_endpoints(api_key)

    # Summary
    print("\n" + "=" * 60)
    print("Summary")
    print("=" * 60)
    print(f"\nThe API key format suggests: {identifications[0]['service']}")

    if api_key.startswith('sk-'):
        print("\nRecommendation: This looks like an OpenAI key.")
        print("Test it at: https://platform.openai.com/api-keys")
    elif len(api_key) == 32 and api_key.isalnum():
        print("\nRecommendation: This is a 32-character alphanumeric key.")
        print("Common services with this format:")
        print("  - OpenWeatherMap")
        print("  - NewsAPI")
        print("  - Custom APIs")
        print("  - Various third-party services")
        print("\nPlease check which service this key is intended for.")
    else:
        print("\nRecommendation: Unable to identify service from key format.")
        print("Please verify which API service this key is for.")

    return identifications


if __name__ == "__main__":
    if len(sys.argv) > 1:
        api_key = sys.argv[1]
    else:
        api_key = os.getenv('API_KEY') or os.getenv('OPENAI_API_KEY')
        if not api_key:
            print("Usage: python identify_and_test_api_key.py <api_key>")
            sys.exit(1)

    main(api_key)
