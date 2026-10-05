<?php
/**
 * RMS -- Global Configuration
 * Edit credentials below to match your XAMPP / server environment.
 */

// ---- Database credentials (XAMPP default shown) ----
define('DB_HOST', 'localhost');
define('DB_NAME', 'bca_rms');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// ---- Base URL (adjust if the project is placed elsewhere) ----
if (!defined('BASE_URL')) {
    define('BASE_URL', '/result_mgmt_system');
}

// ---- Application ----
define('APP_NAME', 'BCA Result Management System');
// Demo credentials used by the one-click login on the demo sign-in screen.
define('DEMO_USERNAME', 'admin');
define('DEMO_PASSWORD', 'admin123');
define('INSTALL_FILE_EXISTS', file_exists(__DIR__ . '/../setup/install.lock'));
define('UPLOAD_DIR', __DIR__ . '/../uploads/students');

// ---- Session ----
if (!defined('SESSION_NAME')) {
    define('SESSION_NAME', 'rms_session');
}

// ---- Error display (turn OFF in production) ----
define('DEV_MODE', true);
if (DEV_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', '1');
} else {
    error_reporting(0);
    ini_set('display_errors', '0');
}

// ---- Default grading model (pass percent configurable later in Settings) ----

// ---- Nepal timezone: everything user-facing is Bikram Sambat (BS).
define('APP_TIMEZONE', 'Asia/Kathmandu');
date_default_timezone_set(APP_TIMEZONE);