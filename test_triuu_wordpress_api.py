#!/usr/bin/env python3
"""
TRI-UU WordPress API Testing Tool
Tests the custom WordPress REST API for Claude Code integration.
"""

import sys
import json
try:
    import requests
except ImportError:
    print("Installing requests library...")
    import os
    os.system("pip install requests")
    import requests


class TriuuWordPressAPITester:
    """Test client for TRI-UU WordPress REST API"""

    def __init__(self, base_url, api_key):
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.headers = {
            'X-Claude-API-Key': api_key,
            'Content-Type': 'application/json'
        }

    def _make_request(self, method, endpoint, **kwargs):
        """Make an HTTP request to the API"""
        url = f"{self.base_url}{endpoint}"
        print(f"\n{'='*60}")
        print(f"{method} {endpoint}")
        print(f"{'='*60}")

        try:
            if method == 'GET':
                response = requests.get(url, headers=self.headers, **kwargs)
            elif method == 'POST':
                response = requests.post(url, headers=self.headers, **kwargs)
            else:
                raise ValueError(f"Unsupported method: {method}")

            print(f"Status Code: {response.status_code}")

            try:
                data = response.json()
                print(f"Response:\n{json.dumps(data, indent=2)}")
                return response.status_code, data
            except:
                print(f"Response (text): {response.text}")
                return response.status_code, response.text

        except requests.exceptions.RequestException as e:
            print(f"Error: {str(e)}")
            return None, str(e)

    def test_health(self):
        """Test health endpoint (no auth)"""
        print("\n" + "="*60)
        print("TEST 1: Health Check (no authentication)")
        print("="*60)
        # Try without API key
        url = f"{self.base_url}/health"
        try:
            response = requests.get(url, timeout=10)
            print(f"Status Code: {response.status_code}")
            print(f"Response: {response.text}")
            if response.status_code == 200:
                print("✓ Health endpoint is accessible")
                return True
        except Exception as e:
            print(f"✗ Health endpoint failed: {str(e)}")
        return False

    def test_site_info(self):
        """Test site-info endpoint (requires auth)"""
        print("\n" + "="*60)
        print("TEST 2: Site Info (with authentication)")
        print("="*60)
        status, data = self._make_request('GET', '/site-info')
        return status == 200

    def test_list_files(self, path='/themes'):
        """Test files/list endpoint"""
        print("\n" + "="*60)
        print(f"TEST 3: List Files (path={path})")
        print("="*60)
        status, data = self._make_request('GET', f'/files/list?path={path}')
        return status == 200

    def test_plugins_list(self):
        """Test plugins/list endpoint"""
        print("\n" + "="*60)
        print("TEST 4: List Plugins")
        print("="*60)
        status, data = self._make_request('GET', '/plugins/list')
        return status == 200

    def test_themes_list(self):
        """Test themes/list endpoint"""
        print("\n" + "="*60)
        print("TEST 5: List Themes")
        print("="*60)
        status, data = self._make_request('GET', '/themes/list')
        return status == 200

    def run_all_tests(self):
        """Run all API tests"""
        print("\n" + "█"*60)
        print("TRI-UU WORDPRESS API TEST SUITE")
        print("█"*60)
        print(f"Base URL: {self.base_url}")
        print(f"API Key: {self.api_key[:8]}...{self.api_key[-4:]}")

        results = []
        results.append(('Health Check', self.test_health()))
        results.append(('Site Info', self.test_site_info()))
        results.append(('List Files', self.test_list_files()))
        results.append(('List Plugins', self.test_plugins_list()))
        results.append(('List Themes', self.test_themes_list()))

        # Summary
        print("\n" + "█"*60)
        print("TEST SUMMARY")
        print("█"*60)

        passed = sum(1 for _, result in results if result)
        total = len(results)

        for test_name, result in results:
            status = "✓ PASS" if result else "✗ FAIL"
            print(f"{status} - {test_name}")

        print(f"\nResults: {passed}/{total} tests passed")

        if passed == total:
            print("\n🎉 All tests passed! API is working correctly.")
            return 0
        else:
            print(f"\n⚠ {total - passed} test(s) failed. Check the output above for details.")
            return 1


def main():
    """Main entry point"""
    # Default configuration
    BASE_URL = "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1"
    API_KEY = "DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n"

    # Allow override from command line
    if len(sys.argv) > 1:
        API_KEY = sys.argv[1]
    if len(sys.argv) > 2:
        BASE_URL = sys.argv[2]

    tester = TriuuWordPressAPITester(BASE_URL, API_KEY)
    exit_code = tester.run_all_tests()
    sys.exit(exit_code)


if __name__ == "__main__":
    main()
