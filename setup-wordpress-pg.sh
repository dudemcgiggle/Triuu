#!/bin/bash
# WordPress PostgreSQL Configuration Script
# This script configures WordPress to use PostgreSQL with PG4WP

echo "🔧 WordPress PostgreSQL Setup"
echo "============================="
echo ""

# Restore PG4WP db.php
echo "📦 Installing PG4WP database drop-in..."
if [ -f "wordpress/wp-content/pg4wp/db.php" ]; then
    cp wordpress/wp-content/pg4wp/db.php wordpress/wp-content/db.php
    echo "✅ PG4WP db.php installed"
else
    echo "❌ ERROR: PG4WP plugin not found!"
    exit 1
fi

# Create logs directory
mkdir -p wordpress/wp-content/pg4wp/logs
chmod 777 wordpress/wp-content/pg4wp/logs
echo "✅ PG4WP logs directory created"

echo ""
echo "✅ WordPress is now configured for PostgreSQL!"
echo ""
echo "🚀 To start WordPress, run:"
echo "   bash start-wordpress.sh"
echo ""
echo "📍 Your WordPress will be accessible at:"
echo "   http://localhost:5000"
echo ""
echo "🎉 All your content (pages, sermons, posts) should be restored!"
