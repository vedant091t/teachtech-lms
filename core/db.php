<?php
/**
 * Database — singleton connection.
 * Call db() anywhere after bootstrap to get the mysqli instance.
 * NOTE: Do NOT put side-effect code (like $conn = db()) at file scope —
 *       this file is required by bootstrap AFTER $_ENV is populated.
 */
defined('TT_APP') or die('Direct access not allowed.');

function db(): mysqli
{
    static $conn = null;

    if ($conn instanceof mysqli) {
        return $conn;
    }

    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? 'root';
    $pass = $_ENV['DB_PASS'] ?? '';
    $name = $_ENV['DB_NAME'] ?? 'academy';

    mysqli_report(MYSQLI_REPORT_OFF); // Handle errors manually below
    $conn = new mysqli($host, $user, $pass, $name);

    if ($conn->connect_error) {
        $err = $conn->connect_error;
        error_log('[TeachTech DB] ' . $err);

        if (($_ENV['APP_ENV'] ?? 'development') === 'production') {
            http_response_code(503);
            die('Service temporarily unavailable.');
        }

        // Dev: show helpful message
        http_response_code(500);
        die('
            <div style="font-family:monospace;padding:2rem;background:#fee2e2;border:2px solid #ef4444;border-radius:8px;max-width:600px;margin:2rem auto;">
              <h2 style="color:#b91c1c;margin:0 0 1rem">Database Connection Failed</h2>
              <p><strong>Error:</strong> ' . htmlspecialchars($err) . '</p>
              <p><strong>Fix:</strong> Open <strong>XAMPP Control Panel</strong> and click <strong>Start</strong> next to MySQL.</p>
              <p>Then refresh this page.</p>
            </div>
        ');
    }

    $conn->set_charset('utf8mb4');
    return $conn;
}

// Expose $conn as global for any legacy code that uses it directly
$conn = null; // lazy — only set when first needed
