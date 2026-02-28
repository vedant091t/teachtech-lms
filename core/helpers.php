<?php
/**
 * Application Helpers
 * Pure functions only. No side effects. No globals.
 */
defined('TT_APP') or die('Direct access not allowed.');

// ── Auth ────────────────────────────────────────────────────────────────────

function auth_check(string $role = null): void {
    if (empty($_SESSION['user_id'])) {
        flash('warning', 'Please log in to continue.');
        redirect('login.php');   // relative — works at root or subdirectory
    }
    if ($role && $_SESSION['role'] !== $role) {
        http_response_code(403);
        flash('danger', 'Access denied.');
        redirect('login.php');
    }
}

function is_logged_in(): bool {
    return !empty($_SESSION['user_id']);
}

function current_user(): array {
    return [
        'id'       => $_SESSION['user_id']   ?? null,
        'username' => $_SESSION['username']  ?? '',
        'role'     => $_SESSION['role']      ?? '',
    ];
}

// ── URL / Path helpers ────────────────────────────────────────────────────────

/**
 * Returns an application URL optionally appended with $path.
 * Reads APP_BASE_URL from .env (default empty string = served at web-root).
 * Example: APP_BASE_URL=/teachtech  →  app_url('public/css/app.css')
 *          returns '/teachtech/public/css/app.css'
 */
function app_url(string $path = ''): string {
    $base = rtrim($_ENV['APP_BASE_URL'] ?? '', '/');
    if ($path === '') return $base ?: '/';
    return $base . '/' . ltrim($path, '/');
}

// ── CSRF ────────────────────────────────────────────────────────────────────

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="' . e($_ENV['CSRF_TOKEN_NAME']) . '" value="' . csrf_token() . '">';
}

function csrf_verify(): void {
    $name  = $_ENV['CSRF_TOKEN_NAME'];
    $token = $_POST[$name] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!hash_equals($_SESSION['csrf'] ?? '', $token)) {
        http_response_code(419);
        die('CSRF token mismatch.');
    }
}

// ── Flash Messages ───────────────────────────────────────────────────────────

function flash(string $type, string $msg): void {
    $_SESSION['_flash'] = ['type' => $type, 'msg' => $msg];
}

function get_flash(): ?array {
    if (!isset($_SESSION['_flash'])) return null;
    $f = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    return $f;
}

function render_flash(): void {
    $f = get_flash();
    if (!$f) return;
    $icons = [
        'success' => 'fa-circle-check',
        'danger'  => 'fa-circle-xmark',
        'warning' => 'fa-triangle-exclamation',
        'info'    => 'fa-circle-info',
    ];
    $icon = $icons[$f['type']] ?? 'fa-circle-info';
    echo '<div class="alert alert-' . e($f['type']) . '" role="alert">'
       . '<i class="fa-solid ' . $icon . '"></i>'
       . '<span>' . e($f['msg']) . '</span>'
       . '</div>';
}

// ── HTTP ─────────────────────────────────────────────────────────────────────

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

// ── Output ───────────────────────────────────────────────────────────────────

/** HTML-safe output shorthand */
function e(mixed $val): string {
    return htmlspecialchars((string) $val, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** JSON response with correct headers */
function json_response(mixed $data, int $code = 200): never {
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── File Upload ──────────────────────────────────────────────────────────────

/**
 * MIME type allowlist — maps allowed extensions to their accepted MIME types.
 * finfo_file() is used to detect the actual MIME from file bytes, not the name.
 */
const UPLOAD_MIME_MAP = [
    'pdf'  => ['application/pdf'],
    'doc'  => ['application/msword'],
    'docx' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'],
    'ppt'  => ['application/vnd.ms-powerpoint'],
    'pptx' => ['application/vnd.openxmlformats-officedocument.presentationml.presentation'],
    'jpg'  => ['image/jpeg'],
    'jpeg' => ['image/jpeg'],
    'png'  => ['image/png'],
    'gif'  => ['image/gif'],
    'mp4'  => ['video/mp4'],
    'webm' => ['video/webm'],
    'avi'  => ['video/x-msvideo', 'video/avi'],
    'mov'  => ['video/quicktime'],
];

/**
 * Validate and move an uploaded file.
 *
 * Security checks (in order):
 *  1. PHP upload error code must be UPLOAD_ERR_OK.
 *  2. File size must not exceed UPLOAD_MAX_SIZE env var.
 *  3. Extension must be in the allowed list (from ALLOWED_FILE_TYPES env var).
 *  4. Double-extension attack blocked (e.g. "shell.php.pdf" rejected).
 *  5. Actual MIME type from file bytes (finfo_file) must match the extension.
 *
 * Returns ['ok' => true, 'filename' => '...', 'ext' => '...', 'size' => int]
 *      or ['ok' => false, 'error' => '...']
 */
function handle_upload(array $file, string $dest_dir): array {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => 'Upload error code: ' . $file['error']];
    }

    // 1. Size check
    $max = (int)($_ENV['UPLOAD_MAX_SIZE'] ?? 10485760);
    if ($file['size'] > $max) {
        return ['ok' => false, 'error' => 'File exceeds the ' . format_bytes($max) . ' size limit.'];
    }

    // 2. Extension allowlist
    $allowed_exts = array_map('trim', explode(',', $_ENV['ALLOWED_FILE_TYPES'] ?? 'pdf,doc,docx,ppt,pptx'));
    $original_name = $file['name'];
    $ext = strtolower(pathinfo($original_name, PATHINFO_EXTENSION));

    if (!in_array($ext, $allowed_exts, true)) {
        return ['ok' => false, 'error' => 'File type ".' . $ext . '" is not allowed.'];
    }

    // 3. Double-extension check — block "shell.php.pdf", "evil.exe.docx", etc.
    $basename_no_ext = pathinfo($original_name, PATHINFO_FILENAME);
    if (str_contains($basename_no_ext, '.')) {
        return ['ok' => false, 'error' => 'File name contains multiple extensions, which is not allowed.'];
    }

    // 4. MIME validation using file bytes (finfo), not the browser-supplied type
    if (function_exists('finfo_open')) {
        $finfo     = finfo_open(FILEINFO_MIME_TYPE);
        $real_mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        $allowed_mimes = UPLOAD_MIME_MAP[$ext] ?? [];
        if (!empty($allowed_mimes) && !in_array($real_mime, $allowed_mimes, true)) {
            return [
                'ok'    => false,
                'error' => 'File content does not match its extension (detected: ' . $real_mime . ').',
            ];
        }
    }

    // 5. Generate a safe unique filename — no user-supplied name parts in final path
    $filename = time() . '_' . bin2hex(random_bytes(6)) . '.' . $ext;

    if (!is_dir($dest_dir)) {
        mkdir($dest_dir, 0755, true);
    }

    if (!move_uploaded_file($file['tmp_name'], $dest_dir . DIRECTORY_SEPARATOR . $filename)) {
        return ['ok' => false, 'error' => 'Failed to save the file. Check directory permissions.'];
    }

    return ['ok' => true, 'filename' => $filename, 'ext' => $ext, 'size' => $file['size']];
}

function format_bytes(int $bytes, int $prec = 1): string {
    foreach (['B','KB','MB','GB'] as $unit) {
        if ($bytes < 1024) return round($bytes, $prec) . ' ' . $unit;
        $bytes /= 1024;
    }
    return round($bytes, $prec) . ' TB';
}

// ── Misc ─────────────────────────────────────────────────────────────────────

function file_icon_class(string $filename): string {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    if ($ext === 'pdf')                             return 'pdf';
    if (in_array($ext, ['doc','docx']))             return 'doc';
    if (in_array($ext, ['ppt','pptx']))             return 'ppt';
    if (in_array($ext, ['jpg','jpeg','png','gif'])) return 'img';
    if (in_array($ext, ['mp4','avi','mov','webm'])) return 'vid';
    return 'file';
}

function file_fa_icon(string $icon_class): string {
    return match($icon_class) {
        'pdf'   => 'fa-file-pdf',
        'doc'   => 'fa-file-word',
        'ppt'   => 'fa-file-powerpoint',
        'img'   => 'fa-file-image',
        'vid'   => 'fa-file-video',
        default => 'fa-file',
    };
}

function paginate(int $total, int $per_page, int $current_page): array {
    $total_pages = max(1, (int)ceil($total / $per_page));
    $current     = max(1, min($current_page, $total_pages));
    $offset      = ($current - 1) * $per_page;
    return [
        'total'       => $total,
        'per_page'    => $per_page,
        'current'     => $current,
        'total_pages' => $total_pages,
        'offset'      => $offset,
    ];
}

function time_ago(string $datetime): string {
    $diff = time() - strtotime($datetime);
    if ($diff < 60)     return 'just now';
    if ($diff < 3600)   return (int)($diff/60) . 'm ago';
    if ($diff < 86400)  return (int)($diff/3600) . 'h ago';
    if ($diff < 604800) return (int)($diff/86400) . 'd ago';
    return date('d M Y', strtotime($datetime));
}
