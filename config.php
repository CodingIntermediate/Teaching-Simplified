<?php
// ============================================
// FIRST: Include session configuration
// ============================================
require_once __DIR__ . '/session_config.php';

// ============================================
// THEN: Database configuration
// ============================================
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'university_platform');

// Site configuration
define('SITE_URL', 'http://localhost/university_platform/');
define('SITE_NAME', 'Capsule Learning Platform');

// Upload directories
define('UPLOAD_DIR', __DIR__ . '/assets/uploads/');
define('SYLLABUS_DIR', UPLOAD_DIR . 'syllabi/');
define('RESOURCE_DIR', UPLOAD_DIR . 'resources/');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/db.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/nlp.php';
?>