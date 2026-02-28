<?php
/**
 * delete_material.php — Secure POST-only deletion with CSRF in request body.
 *
 * Security model:
 *  - Requires POST method (GET attempts are rejected — CSRF token never in URL).
 *  - CSRF token verified from $_POST body via csrf_verify().
 *  - Ownership enforced: uploaded_by = current teacher's id.
 *  - File deleted from disk only after DB row is removed.
 */
require_once __DIR__ . '/core/bootstrap.php';

auth_check('teacher');
$user = current_user();
$uid = $user['id'];

// Only allow POST — reject any GET request immediately
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    flash('danger', 'Invalid request method.');
    redirect('teacher_dashboard.php');
}

// CSRF check (token is in $_POST body, never in URL)
csrf_verify();

$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid material ID.');
    redirect('teacher_dashboard.php');
}

// Fetch to get filename + verify ownership
$stmt = db()->prepare('SELECT id, filename FROM materials WHERE id = ? AND uploaded_by = ? LIMIT 1');
$stmt->bind_param('ii', $id, $uid);
$stmt->execute();
$mat = $stmt->get_result()->fetch_assoc();

if (!$mat) {
    flash('danger', 'Material not found or you do not have permission to delete it.');
    redirect('teacher_dashboard.php');
}

// Delete DB record first; then remove file (best-effort — disk orphan is less harmful
// than a broken DB reference or a dangling pointer to a missing file).
$del = db()->prepare('DELETE FROM materials WHERE id = ? AND uploaded_by = ?');
$del->bind_param('ii', $id, $uid);
$del->execute();

if ($del->affected_rows > 0) {
    // Remove physical file only after successful DB deletion
    $file_path = __DIR__ . '/asset/' . basename($mat['filename']);
    if (file_exists($file_path)) {
        @unlink($file_path);
    }
    flash('success', 'Material deleted successfully.');
} else {
    flash('danger', 'Could not delete material. Please try again.');
}

redirect('teacher_dashboard.php');
