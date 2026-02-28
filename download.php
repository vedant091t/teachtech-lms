<?php
/**
 * download.php — Serves files securely and tracks downloads.
 * Files are resolved only by DB id — no filesystem path from URL ever.
 */
require_once __DIR__ . '/core/bootstrap.php';

auth_check(); // Any logged-in user (student or teacher) may download

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    http_response_code(400);
    die('Bad request.');
}

$stmt = db()->prepare('SELECT id, filename FROM materials WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();

if (!$mat) {
    http_response_code(404);
    die('Material not found.');
}

// Resolve real path — never user-supplied
$file_path = realpath(__DIR__ . '/asset/' . basename($mat['filename']));
$asset_dir = realpath(__DIR__ . '/asset');

// Extra path-traversal guard: resolved path must be inside /asset/
if (!$file_path || strpos($file_path, $asset_dir) !== 0 || !is_file($file_path)) {
    http_response_code(404);
    die('File not found on server.');
}

// Track download — increment aggregate count AND log who downloaded
$upd = db()->prepare('UPDATE materials SET download_count = download_count + 1 WHERE id = ?');
$upd->bind_param('i', $id);
$upd->execute();

// Log the individual download (only if user is a student)
$uid = $_SESSION['user_id'] ?? null;
if ($uid) {
    $log = db()->prepare('INSERT INTO download_log (student_id, material_id) VALUES (?, ?)');
    $log->bind_param('ii', $uid, $id);
    $log->execute();
}


// Stream
$mime = mime_content_type($file_path) ?: 'application/octet-stream';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($mat['filename']) . '"');
header('Content-Length: ' . filesize($file_path));
header('Cache-Control: no-cache, no-store, must-revalidate');
header('X-Content-Type-Options: nosniff');

readfile($file_path);
exit;
