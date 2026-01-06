<?php
/**
 * Setup Script
 * Run this once to initialize the system
 */

require_once 'config/database.php';

echo "Teacher Attendance System - Setup\n";
echo "=================================\n\n";

// Check database connection
try {
    $db = getDBConnection();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    die("✗ Database connection failed: " . $e->getMessage() . "\n");
}

// Check if tables exist
$tables = ['admins', 'teachers', 'webauthn_credentials', 'lesson_timetables', 'public_holidays', 'attendance_logs', 'audit_logs'];
$missingTables = [];

foreach ($tables as $table) {
    try {
        $stmt = $db->query("SELECT 1 FROM `$table` LIMIT 1");
        echo "✓ Table '$table' exists\n";
    } catch (PDOException $e) {
        $missingTables[] = $table;
        echo "✗ Table '$table' does not exist\n";
    }
}

if (!empty($missingTables)) {
    echo "\n⚠ Warning: Some tables are missing. Please import database/schema.sql\n";
} else {
    echo "\n✓ All required tables exist\n";
}

// Check admin user
$stmt = $db->query("SELECT COUNT(*) as count FROM admins");
$adminCount = $stmt->fetch()['count'];

if ($adminCount > 0) {
    echo "✓ Admin user exists\n";
} else {
    echo "⚠ No admin user found. Creating default admin...\n";
    $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
    $stmt = $db->prepare("INSERT INTO admins (username, password_hash, email) VALUES (?, ?, ?)");
    $stmt->execute(['admin', $passwordHash, 'admin@example.com']);
    echo "✓ Default admin created (username: admin, password: admin123)\n";
}

// Check PHP version
$phpVersion = phpversion();
echo "\nPHP Version: $phpVersion\n";

if (version_compare($phpVersion, '7.4.0', '<')) {
    echo "⚠ Warning: PHP 7.4 or higher is recommended\n";
} else {
    echo "✓ PHP version is compatible\n";
}

// Check required PHP extensions
$requiredExtensions = ['pdo', 'pdo_mysql', 'json', 'mbstring'];
$missingExtensions = [];

foreach ($requiredExtensions as $ext) {
    if (extension_loaded($ext)) {
        echo "✓ Extension '$ext' is loaded\n";
    } else {
        $missingExtensions[] = $ext;
        echo "✗ Extension '$ext' is not loaded\n";
    }
}

if (!empty($missingExtensions)) {
    echo "\n⚠ Warning: Some required extensions are missing. Please install them.\n";
}

// Check timezone
$timezone = date_default_timezone_get();
echo "\nTimezone: $timezone\n";

if ($timezone !== 'Africa/Kampala' && $timezone !== 'Africa/Nairobi') {
    echo "⚠ Warning: Timezone should be set to Africa/Kampala or Africa/Nairobi\n";
} else {
    echo "✓ Timezone is correctly set\n";
}

// Check HTTPS
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || 
           (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

if ($isHttps) {
    echo "✓ HTTPS is enabled\n";
} else {
    echo "⚠ Warning: HTTPS is not enabled. WebAuthn requires HTTPS in production.\n";
}

// Check write permissions
$writableDirs = ['.', 'config'];
$unwritableDirs = [];

foreach ($writableDirs as $dir) {
    if (is_writable($dir)) {
        echo "✓ Directory '$dir' is writable\n";
    } else {
        $unwritableDirs[] = $dir;
        echo "⚠ Warning: Directory '$dir' is not writable\n";
    }
}

echo "\n=================================\n";
echo "Setup check complete!\n\n";

if (empty($missingTables) && empty($missingExtensions) && empty($unwritableDirs)) {
    echo "✓ System is ready to use!\n";
    echo "\nNext steps:\n";
    echo "1. Access the system: " . (isset($_SERVER['HTTP_HOST']) ? 'http://' . $_SERVER['HTTP_HOST'] . dirname($_SERVER['SCRIPT_NAME']) : 'http://localhost/igaratrack2.0') . "\n";
    echo "2. Login as admin (username: admin, password: admin123)\n";
    echo "3. Change the default admin password\n";
    echo "4. Add teachers and register their biometrics\n";
    echo "5. Set up lesson timetables\n";
} else {
    echo "⚠ Please fix the issues above before using the system.\n";
}

