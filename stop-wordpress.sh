#!/bin/bash
# WordPress with PostgreSQL shutdown script

echo "🛑 Stopping WordPress and PostgreSQL..."

# Stop PHP server
echo "📴 Stopping PHP server..."
pkill -f "php -S 0.0.0.0:5000" || echo "PHP server not running"

# Stop PostgreSQL
echo "📴 Stopping PostgreSQL..."
su - claude -c "/usr/lib/postgresql/16/bin/pg_ctl -D /home/user/Triuu/pg_data stop" 2>&1 || echo "PostgreSQL not running"

echo "✅ Shutdown complete!"
