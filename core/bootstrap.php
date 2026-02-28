<?php
/**
 * Application Bootstrap
 * Every page starts here: require_once __DIR__ . '/core/bootstrap.php';
 * Load order: autoload → dotenv → helpers → db → session
 */
define('TT_APP', true);

// ── 1. Composer autoloader (classes only, no side-effects) ───────────────────
$autoload = __DIR__ . '/../vendor/autoload.php';
if (file_exists($autoload)) {
    require_once $autoload;
}

// ── 2. Load .env BEFORE anything touches $_ENV ───────────────────────────────
if (class_exists('Dotenv\Dotenv')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->safeLoad();
}

// ── 3. Hard defaults (used when .env key is absent) ─────────────────────────
$_ENV['APP_ENV'] = $_ENV['APP_ENV'] ?? 'development';
$_ENV['APP_DEBUG'] = $_ENV['APP_DEBUG'] ?? 'true';
$_ENV['APP_BASE_URL'] = $_ENV['APP_BASE_URL'] ?? '';          // '' = served at root, '/teachtech' for subdirectory
$_ENV['DB_HOST'] = $_ENV['DB_HOST'] ?? 'localhost';
$_ENV['DB_USER'] = $_ENV['DB_USER'] ?? 'root';
$_ENV['DB_PASS'] = $_ENV['DB_PASS'] ?? '';
$_ENV['DB_NAME'] = $_ENV['DB_NAME'] ?? 'academy';
$_ENV['SESSION_NAME'] = $_ENV['SESSION_NAME'] ?? 'teachtech_sid';
$_ENV['SESSION_LIFETIME'] = $_ENV['SESSION_LIFETIME'] ?? '3600';
$_ENV['CSRF_TOKEN_NAME'] = $_ENV['CSRF_TOKEN_NAME'] ?? 'csrf_token';
$_ENV['UPLOAD_MAX_SIZE'] = $_ENV['UPLOAD_MAX_SIZE'] ?? '10485760';
$_ENV['ALLOWED_FILE_TYPES'] = $_ENV['ALLOWED_FILE_TYPES'] ?? 'pdf,doc,docx,ppt,pptx,jpg,jpeg,png,gif,mp4,webm,avi,mov';

// ── 4. Helpers (pure functions, no DB calls) ─────────────────────────────────
require_once __DIR__ . '/helpers.php';

// ── 5. DB (now safe: $_ENV is populated) ────────────────────────────────────
require_once __DIR__ . '/db.php';

// ── 6. Secure session ────────────────────────────────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    session_name($_ENV['SESSION_NAME']);
    session_set_cookie_params([
        'lifetime' => (int) $_ENV['SESSION_LIFETIME'],
        'path' => '/',
        'secure' => $_ENV['APP_ENV'] === 'production',
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
    session_start();

    if (empty($_SESSION['_init'])) {
        session_regenerate_id(true);
        $_SESSION['_init'] = true;
        $_SESSION['_created'] = time();
    }

    $lifetime = (int) $_ENV['SESSION_LIFETIME'];
    if (isset($_SESSION['_last']) && (time() - $_SESSION['_last']) > $lifetime) {
        session_unset();
        session_destroy();
        session_start();
        $_SESSION['_flash'] = ['type' => 'warning', 'msg' => 'Session expired. Please log in again.'];
    }
    $_SESSION['_last'] = time();
}
