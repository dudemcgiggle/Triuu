<?php
/**
 * Download SQLite db.php plugin
 * Stop the server, run this with: php get-db-plugin.php
 */

$source = __DIR__ . '/wp-content/db.php';
$dest = __DIR__ . '/wp-content/db.php.new';

if (file_exists($source) && filesize($source) > 100000) {
    echo "✅ Plugin file already exists and looks correct!\n";
    echo "File: $source\n";
    echo "Size: " . filesize($source) . " bytes\n";
    exit(0);
}

// Base64 encoded gzipped plugin data
$data = <<<'BASE64'
H4sICMDAB2kAA2RiLnBocC5zcWxpdGUuYmFja3VwAOw9/XPbNrI/X/4KVKNWUqsPO2muqWP7zrGV
2HP+qi03yfRlOJQISUwoUiFBy75r/ve3uwD4TYmy3Fw772kyLUksFvuFxQJYwLv/mE/nT3rff/+E
fc8unXBiu+zcnPEd9vaSXf9yagvOjl5h4REPRr49F7bn7ugSyxTm0AzgwbdvuQ//8+Yd2+2yJn61
mOdqyBNX8IlvYm02vGf/8j7aM5MNvGBq35tB2MIWDkIx9fwd1r81XXZmChF4bvyd3Vyd7LCpEPNg
p9czTT5zXS8QXXGbID0FM7HFNBx2R94sBhe3vcW8E3x2gKiONcSqv3I/IKa2u8+62/jlin8ObZ8H
7PL4coc97/4dPuL3wdQO2Nh2OJuFgWBDzuaOOQI+oWXAOvKASVf0rGEXhNrFGm8937oETAFzPNMK
BASE64;

// This is a minimal version - you'll need to download the full plugin from GitHub
echo "❌ This script needs the full base64 data embedded.\n";
echo "\n";
echo "Please try this instead:\n";
echo "1. Stop this server (Ctrl+C)\n";
echo "2. Run: cd ~/workspace\n";
echo "3. Check if you have the database file: ls -lh wordpress/wp-content/database/wordpress.db\n";
echo "4. If yes, I'll provide another solution to get the plugin installed.\n";
exit(1);
