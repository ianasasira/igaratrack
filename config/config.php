<?php
/**
 * Application Configuration
 */

// Timezone
date_default_timezone_set('Africa/Kampala');

// Session configuration
ini_set('session.cookie_httponly', 1);
ini_set('session.use_only_cookies', 1);
ini_set('session.cookie_secure', 0); // Set to 1 in production with HTTPS

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Application paths
define('BASE_PATH', dirname(__DIR__));
define('BASE_URL', 'http://localhost/igaratrack2.0');

// WebAuthn configuration
define('RP_ID', 'localhost'); // Change to your domain in production
define('RP_NAME', 'Teacher Attendance System');
define('RP_ORIGIN', BASE_URL);

// Security
define('REQUIRE_HTTPS', false); // Set to true in production

// Include database config
require_once BASE_PATH . '/config/database.php';

// Helper functions
require_once BASE_PATH . '/includes/functions.php';

