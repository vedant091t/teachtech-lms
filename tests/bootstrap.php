<?php
/**
 * Test bootstrap — sets up the minimal environment PHPUnit needs to
 * load helpers.php without a real DB connection or HTTP session.
 *
 * Functions that touch $_SESSION or db() are avoided or mocked in tests.
 */
// Sentinel — guards core/helpers.php and core/db.php access-control checks.
// When run via --prepend (prepend.php), TT_APP is already defined; the guard prevents
// a "Constant already defined" warning.
if (!defined('TT_APP')) {
    define('TT_APP', true);
}

// Minimal $_ENV so helpers.php constants are available
$_ENV['APP_ENV'] = 'testing';
$_ENV['APP_BASE_URL'] = '';
$_ENV['CSRF_TOKEN_NAME'] = 'csrf_token';
$_ENV['UPLOAD_MAX_SIZE'] = '10485760';
$_ENV['ALLOWED_FILE_TYPES'] = 'pdf,doc,docx,ppt,pptx,jpg,jpeg,png';

// Start a session so CSRF helpers work
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../core/helpers.php';
