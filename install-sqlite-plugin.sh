#!/bin/bash
# SQLite Plugin Installation Script for User Environment
# Run this from your /home/runner/workspace directory

echo "🔧 Installing SQLite Database Plugin for WordPress"
echo "=================================================="
echo ""

# Check if we're in the right location
if [ ! -d "wordpress/wp-content" ]; then
    echo "❌ ERROR: wordpress/wp-content directory not found!"
    echo "Please run this script from /home/runner/workspace"
    exit 1
fi

echo "📍 Detected WordPress installation at: $(pwd)/wordpress"
echo ""

# Method 1: Try to download from the running web server
echo "📥 Method 1: Downloading from local web server..."
if curl -f -o wordpress/wp-content/db.php http://localhost:5000/download-sqlite-db.php 2>/dev/null; then
    echo "✅ Successfully downloaded SQLite plugin!"
    ls -lh wordpress/wp-content/db.php
    echo ""
    echo "🎉 Installation complete!"
    exit 0
fi

echo "⚠️  Web server download failed, trying Method 2..."
echo ""

# Method 2: Try to download from GitHub
echo "📥 Method 2: Downloading from GitHub..."
if curl -L -f -o wordpress/wp-content/db.php "https://raw.githubusercontent.com/aaemnnosttv/wp-sqlite-db/1.3.1/src/db.php" 2>/dev/null; then
    echo "✅ Successfully downloaded from GitHub!"
    ls -lh wordpress/wp-content/db.php
    echo ""
    echo "🎉 Installation complete!"
    exit 0
fi

echo "❌ Both download methods failed."
echo ""
echo "📋 Manual Installation Required:"
echo "1. The SQLite plugin file is too large to transfer automatically"
echo "2. You can manually download it from:"
echo "   https://github.com/aaemnnosttv/wp-sqlite-db"
echo "3. Or try running this command:"
echo "   wget -O wordpress/wp-content/db.php https://raw.githubusercontent.com/aaemnnosttv/wp-sqlite-db/1.3.1/src/db.php"
echo ""
