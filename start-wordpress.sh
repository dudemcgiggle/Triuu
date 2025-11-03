#!/bin/bash
# WordPress with PostgreSQL startup script

echo "🚀 Starting WordPress with PostgreSQL..."

# Start PostgreSQL
echo "📊 Starting PostgreSQL..."
su - claude -c "/usr/lib/postgresql/16/bin/pg_ctl -D /home/user/Triuu/pg_data -l /home/user/Triuu/pg_data/logfile start" 2>&1

# Wait for PostgreSQL to be ready
echo "⏳ Waiting for PostgreSQL to be ready..."
for i in {1..30}; do
    if pg_isready -h localhost > /dev/null 2>&1; then
        echo "✅ PostgreSQL is ready!"
        break
    fi
    if [ $i -eq 30 ]; then
        echo "❌ PostgreSQL failed to start within 30 seconds"
        exit 1
    fi
    sleep 1
done

# Start PHP server
echo "🌐 Starting WordPress PHP server on port 5000..."
cd /home/user/Triuu
php -S 0.0.0.0:5000 -t wordpress router.php
