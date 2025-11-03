#!/bin/bash
# WordPress Database Import Script
# This script imports your WordPress backup into PostgreSQL

echo "🚀 WordPress Database Import Script"
echo "===================================="
echo ""

# Check if backup files exist
if [ ! -f "backups/wordpress-backup.sql" ]; then
    echo "❌ ERROR: backups/wordpress-backup.sql not found!"
    echo "Please make sure the file is in /home/user/Triuu/backups/"
    exit 1
fi

echo "✅ Found backup file: backups/wordpress-backup.sql"
echo ""

# Check PostgreSQL status
echo "🔍 Checking PostgreSQL status..."
if ! pg_isready -h localhost > /dev/null 2>&1; then
    echo "⚠️  PostgreSQL is not running. Starting it now..."
    su - claude -c "/usr/lib/postgresql/16/bin/pg_ctl -D /home/user/Triuu/pg_data -l /home/user/Triuu/pg_data/logfile start"
    sleep 3
fi

if pg_isready -h localhost > /dev/null 2>&1; then
    echo "✅ PostgreSQL is running"
else
    echo "❌ ERROR: Could not start PostgreSQL"
    exit 1
fi

echo ""
echo "📊 Importing WordPress data into PostgreSQL..."
echo "This may take a minute with 22MB of data..."
echo ""

# Import the SQL dump
su - claude -c "psql -U wpuser -d wordpress -h localhost" < backups/wordpress-backup.sql

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ SUCCESS! Database imported successfully!"
    echo ""
    echo "📊 Database Statistics:"
    su - claude -c "psql -U wpuser -d wordpress -h localhost -c \"SELECT COUNT(*) as table_count FROM information_schema.tables WHERE table_schema = 'public';\""
    echo ""
    echo "🎉 Next steps:"
    echo "1. Restore PG4WP plugin (run: bash setup-wordpress-pg.sh)"
    echo "2. Start WordPress server"
    echo "3. Access your site with all content!"
else
    echo ""
    echo "❌ ERROR: Import failed!"
    echo "Check the error messages above for details."
    exit 1
fi
