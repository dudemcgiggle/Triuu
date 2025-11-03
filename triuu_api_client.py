#!/usr/bin/env python3
"""
TRI-UU WordPress API Client
Complete Python client for interacting with the TRI-UU WordPress REST API
"""

import sys
import json
import os
from typing import Optional, Dict, Any, List

try:
    import requests
except ImportError:
    print("Installing requests library...")
    os.system("pip install requests")
    import requests


class TriuuAPIClient:
    """Client for TRI-UU WordPress REST API"""

    def __init__(self, base_url: str, api_key: str, verbose: bool = False):
        """
        Initialize the API client

        Args:
            base_url: Base URL for the API (e.g., https://site.com/wp-json/triuu-claude/v1)
            api_key: API key for authentication
            verbose: Print detailed request/response info
        """
        self.base_url = base_url.rstrip('/')
        self.api_key = api_key
        self.verbose = verbose
        self.headers = {
            'X-Claude-API-Key': api_key,
            'Content-Type': 'application/json'
        }

    def _log(self, message: str):
        """Log message if verbose mode is enabled"""
        if self.verbose:
            print(f"[DEBUG] {message}")

    def _request(self, method: str, endpoint: str, **kwargs) -> Dict[Any, Any]:
        """
        Make an HTTP request to the API

        Args:
            method: HTTP method (GET, POST, PUT, DELETE)
            endpoint: API endpoint (e.g., /health, /site-info)
            **kwargs: Additional arguments to pass to requests

        Returns:
            Response data as dictionary
        """
        url = f"{self.base_url}{endpoint}"
        self._log(f"{method} {url}")

        # Add headers
        headers = kwargs.pop('headers', {})
        headers.update(self.headers)

        try:
            response = requests.request(
                method=method,
                url=url,
                headers=headers,
                timeout=30,
                **kwargs
            )

            self._log(f"Status: {response.status_code}")

            # Try to parse JSON response
            try:
                data = response.json()
                if self.verbose:
                    print(json.dumps(data, indent=2))

                # Check for errors
                if response.status_code >= 400:
                    error_msg = data.get('message', 'Unknown error')
                    raise APIError(error_msg, response.status_code, data)

                return data
            except json.JSONDecodeError:
                # Not JSON, return text
                text = response.text
                if response.status_code >= 400:
                    raise APIError(text, response.status_code, {})
                return {'text': text}

        except requests.exceptions.RequestException as e:
            raise APIError(f"Request failed: {str(e)}", 0, {})

    # Health & Info Endpoints

    def health(self) -> Dict[str, Any]:
        """Check API health status"""
        return self._request('GET', '/health')

    def site_info(self) -> Dict[str, Any]:
        """Get WordPress site information"""
        return self._request('GET', '/site-info')

    # File Operations

    def read_file(self, path: str) -> Dict[str, Any]:
        """
        Read file contents

        Args:
            path: File path relative to WordPress root (e.g., /wp-content/themes/triuu/style.css)
        """
        return self._request('GET', f'/files/read?path={path}')

    def write_file(self, path: str, content: str) -> Dict[str, Any]:
        """
        Write/update existing file

        Args:
            path: File path relative to WordPress root
            content: File content to write
        """
        return self._request('POST', '/files/write', json={
            'path': path,
            'content': content
        })

    def create_file(self, path: str, content: str) -> Dict[str, Any]:
        """
        Create new file

        Args:
            path: File path relative to WordPress root
            content: File content
        """
        return self._request('POST', '/files/create', json={
            'path': path,
            'content': content
        })

    def delete_file(self, path: str) -> Dict[str, Any]:
        """
        Delete file (creates backup first)

        Args:
            path: File path relative to WordPress root
        """
        return self._request('DELETE', f'/files/delete?path={path}')

    def list_files(self, path: str = '/wp-content') -> Dict[str, Any]:
        """
        List directory contents

        Args:
            path: Directory path relative to WordPress root
        """
        return self._request('GET', f'/files/list?path={path}')

    def move_file(self, from_path: str, to_path: str) -> Dict[str, Any]:
        """
        Move/rename file

        Args:
            from_path: Current file path
            to_path: New file path
        """
        return self._request('POST', '/files/move', json={
            'from': from_path,
            'to': to_path
        })

    def copy_file(self, from_path: str, to_path: str) -> Dict[str, Any]:
        """
        Copy file

        Args:
            from_path: Source file path
            to_path: Destination file path
        """
        return self._request('POST', '/files/copy', json={
            'from': from_path,
            'to': to_path
        })

    # Content Operations

    def get_posts(self) -> List[Dict[str, Any]]:
        """Get all posts"""
        result = self._request('GET', '/content/posts')
        return result.get('posts', [])

    def get_pages(self) -> List[Dict[str, Any]]:
        """Get all pages"""
        result = self._request('GET', '/content/pages')
        return result.get('pages', [])

    def create_post(self, title: str, content: str, **kwargs) -> Dict[str, Any]:
        """
        Create new post

        Args:
            title: Post title
            content: Post content
            **kwargs: Additional post data (status, author, etc.)
        """
        data = {'title': title, 'content': content}
        data.update(kwargs)
        return self._request('POST', '/content/posts', json=data)

    def update_post(self, post_id: int, **kwargs) -> Dict[str, Any]:
        """
        Update existing post

        Args:
            post_id: Post ID
            **kwargs: Post data to update (title, content, status, etc.)
        """
        return self._request('PUT', f'/content/posts/{post_id}', json=kwargs)

    # Media Operations

    def get_media(self) -> List[Dict[str, Any]]:
        """Get media library items"""
        result = self._request('GET', '/media')
        return result.get('media', [])

    def upload_media(self, file_path: str, title: Optional[str] = None) -> Dict[str, Any]:
        """
        Upload media file

        Args:
            file_path: Local file path to upload
            title: Optional media title
        """
        with open(file_path, 'rb') as f:
            files = {'file': f}
            data = {}
            if title:
                data['title'] = title

            # Don't use default headers for file upload
            headers = {'X-Claude-API-Key': self.api_key}

            return self._request('POST', '/media/upload',
                               headers=headers, files=files, data=data)

    # Theme Operations

    def get_theme_css(self) -> str:
        """Get child theme CSS"""
        result = self._request('GET', '/theme/css')
        return result.get('css', '')

    def append_theme_css(self, css: str) -> Dict[str, Any]:
        """
        Append CSS to child theme

        Args:
            css: CSS code to append
        """
        return self._request('POST', '/theme/css', json={'css': css})

    # Plugin Operations

    def get_sermons_plugin(self) -> str:
        """Get sermons plugin code"""
        result = self._request('GET', '/plugins/sermons')
        return result.get('code', '')

    def update_sermons_plugin(self, code: str) -> Dict[str, Any]:
        """
        Update sermons plugin code

        Args:
            code: PHP code for the plugin
        """
        return self._request('PUT', '/plugins/sermons', json={'code': code})

    # System Operations

    def execute_command(self, command: str) -> Dict[str, Any]:
        """
        Execute shell command (USE WITH CAUTION)

        Args:
            command: Shell command to execute
        """
        return self._request('POST', '/system/execute', json={'command': command})


class APIError(Exception):
    """API error exception"""

    def __init__(self, message: str, status_code: int, data: Dict[Any, Any]):
        self.message = message
        self.status_code = status_code
        self.data = data
        super().__init__(self.message)


def main():
    """Example usage"""
    # Default configuration
    BASE_URL = "https://d7f73166-7024-44ae-8aa6-ac6790250f9e-00-9rqlqn9o3dh0.janeway.replit.dev/wp-json/triuu-claude/v1"
    API_KEY = "DnUALkn1l8tiiwpRQfWVam7UmVHzWZ7n"

    # Allow override from environment
    base_url = os.getenv('TRIUU_API_URL', BASE_URL)
    api_key = os.getenv('TRIUU_API_KEY', API_KEY)

    # Create client
    client = TriuuAPIClient(base_url, api_key, verbose=True)

    print("="*60)
    print("TRI-UU WordPress API Client")
    print("="*60)

    try:
        # Test health
        print("\n1. Testing health endpoint...")
        health = client.health()
        print(f"✓ API is healthy: {health.get('status')}")

        # Test site info
        print("\n2. Getting site info...")
        info = client.site_info()
        print(f"✓ Site: {info.get('site_name')}")
        print(f"  WordPress: {info.get('wordpress_version')}")
        print(f"  PHP: {info.get('php_version')}")

        # List theme directory
        print("\n3. Listing theme directory...")
        files = client.list_files('/wp-content/themes/triuu')
        print(f"✓ Found {len(files.get('items', []))} items")

        # Read theme CSS
        print("\n4. Reading theme CSS...")
        css_file = client.read_file('/wp-content/themes/triuu/style.css')
        print(f"✓ Read {len(css_file.get('content', ''))} bytes")

        print("\n" + "="*60)
        print("All tests passed! ✓")
        print("="*60)

    except APIError as e:
        print(f"\n✗ API Error: {e.message}")
        print(f"  Status: {e.status_code}")
        if e.data:
            print(f"  Data: {json.dumps(e.data, indent=2)}")
        sys.exit(1)
    except Exception as e:
        print(f"\n✗ Unexpected error: {str(e)}")
        sys.exit(1)


if __name__ == "__main__":
    main()
