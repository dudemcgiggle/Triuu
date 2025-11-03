<?php
/**
 * Convert SQL dump to SQLite database
 * Run: php create-sqlite-db.php
 */

echo "🔧 Creating SQLite Database from SQL Dump\n";
echo "==========================================\n\n";

$sql_file = 'temp-db-info.txt';
$db_file = 'wordpress/wp-content/database/wordpress.db';

if (!file_exists($sql_file)) {
    die("❌ ERROR: $sql_file not found\n");
}

// Create directory if needed
@mkdir('wordpress/wp-content/database', 0755, true);

// Remove existing database
if (file_exists($db_file)) {
    echo "🗑️  Removing existing database...\n";
    unlink($db_file);
}

echo "📦 Opening SQLite database...\n";
try {
    $pdo = new PDO("sqlite:$db_file");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    echo "📥 Reading SQL dump (22MB)...\n";
    $sql = file_get_contents($sql_file);

    echo "⚙️  Executing SQL statements...\n";
    $pdo->exec($sql);

    $size = filesize($db_file);
    echo "\n✅ SUCCESS!\n";
    echo "📍 Database created: $db_file\n";
    echo "📦 Size: " . number_format($size) . " bytes\n\n";
    echo "🎉 Your WordPress database is ready!\n";

} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
