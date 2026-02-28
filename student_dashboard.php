<?php
/**
 * student_dashboard.php — Browse and download study materials
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('student');

$user = current_user();

// ── Filters ──────────────────────────────────────────────────────────────────
$search = trim($_GET['q'] ?? '');
$teacher_id = (int) ($_GET['teacher'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 12;

// ── Stats ────────────────────────────────────────────────────────────────────
$stats_row = db()->query(
    'SELECT
       (SELECT COUNT(*) FROM materials) AS total_materials,
       (SELECT COUNT(DISTINCT uploaded_by) FROM materials) AS active_teachers,
       (SELECT COUNT(*) FROM materials WHERE DATE(upload_date) = CURDATE()) AS today_uploads'
)->fetch_assoc();

// ── Teachers list for filter ──────────────────────────────────────────────────
$teachers = db()->query(
    'SELECT DISTINCT u.id, u.username FROM materials m
     JOIN users u ON m.uploaded_by = u.id
     ORDER BY u.username'
)->fetch_all(MYSQLI_ASSOC);

// ── Count query ──────────────────────────────────────────────────────────────
$where = [];
$params = [];
$types = '';

if ($search !== '') {
    $where[] = '(m.title LIKE ? OR m.description LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'ss';
}
if ($teacher_id > 0) {
    $where[] = 'm.uploaded_by = ?';
    $params[] = $teacher_id;
    $types .= 'i';
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// Total count for pagination
$count_stmt = db()->prepare("SELECT COUNT(*) FROM materials m {$where_sql}");
if ($params)
    $count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
[$total] = $count_stmt->get_result()->fetch_row();

$pg = paginate($total, $per_page, $page);
$offset = $pg['offset'];

// ── Materials ─────────────────────────────────────────────────────────────────
$data_stmt = db()->prepare(
    "SELECT m.id, m.title, m.description, m.filename, m.upload_date,
            u.username AS teacher_name, m.download_count
     FROM materials m
     JOIN users u ON m.uploaded_by = u.id
     {$where_sql}
     ORDER BY m.upload_date DESC
     LIMIT ? OFFSET ?"
);
$all_params = array_merge($params, [$per_page, $offset]);
$all_types = $types . 'ii';
$data_stmt->bind_param($all_types, ...$all_params);
$data_stmt->execute();
$materials = $data_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

render_header('Browse Materials', 'student');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container">

            <?php render_flash(); ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fa-solid fa-book-open"></i></div>
                    <div>
                        <div class="stat-val"><?= (int) $stats_row['total_materials'] ?></div>
                        <div class="stat-lbl">Total Materials</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fa-solid fa-chalkboard-teacher"></i></div>
                    <div>
                        <div class="stat-val"><?= (int) $stats_row['active_teachers'] ?></div>
                        <div class="stat-lbl">Active Teachers</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fa-solid fa-fire"></i></div>
                    <div>
                        <div class="stat-val"><?= (int) $stats_row['today_uploads'] ?></div>
                        <div class="stat-lbl">Added Today</div>
                    </div>
                </div>
            </div>

            <!-- Filter Bar -->
            <form method="GET">
                <div class="filter-bar">
                    <div class="form-group">
                        <label class="form-label">Search</label>
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="form-control" type="search" name="q" placeholder="Title or description…"
                                value="<?= e($search) ?>">
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Teacher</label>
                        <select class="form-control" name="teacher" data-auto-submit>
                            <option value="0">All teachers</option>
                            <?php foreach ($teachers as $t): ?>
                                <option value="<?= $t['id'] ?>" <?= $teacher_id === (int) $t['id'] ? 'selected' : '' ?>>
                                    <?= e($t['username']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-filter"></i> Filter
                    </button>
                    <?php if ($search || $teacher_id): ?>
                        <a href="student_dashboard.php" class="btn btn-ghost">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Page header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Study Materials</h1>
                    <p class="page-subtitle">
                        <?= $pg['total'] ?> material<?= $pg['total'] !== 1 ? 's' : '' ?> found
                        <?= $search ? ' for "' . e($search) . '"' : '' ?>
                    </p>
                </div>
            </div>

            <!-- Grid -->
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-folder-open"></i>
                    <h3>No materials found</h3>
                    <p><?= $search ? 'Try different keywords or clear the filters.' : 'No materials have been uploaded yet.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="materials-grid">
                    <?php foreach ($materials as $mat):
                        $icon_class = file_icon_class($mat['filename']);
                        $fa_icon = file_fa_icon($icon_class);
                        ?>
                        <div class="material-card card-appear">
                            <div class="material-card-head">
                                <div class="file-icon-badge <?= $icon_class ?>">
                                    <i class="fa-solid <?= $fa_icon ?>"></i>
                                </div>
                                <div style="flex:1;min-width:0;">
                                    <div class="material-title truncate"><?= e($mat['title']) ?></div>
                                </div>
                            </div>
                            <?php if ($mat['description']): ?>
                                <p class="material-desc"><?= e($mat['description']) ?></p>
                            <?php endif; ?>

                            <?php if ($icon_class === 'vid'): ?>
                                <!-- Inline video preview — download.php enforces auth -->
                                <div class="video-preview">
                                    <video controls preload="metadata"
                                        style="width:100%;border-radius:var(--radius-sm);background:#000;max-height:180px;">
                                        <source src="download.php?id=<?= $mat['id'] ?>&preview=1">
                                        Your browser does not support HTML5 video.
                                    </video>
                                </div>
                            <?php endif; ?>

                            <div class="material-meta">
                                <span><i class="fa-solid fa-user-tie"></i> <?= e($mat['teacher_name']) ?></span>
                                <span><i class="fa-regular fa-clock"></i> <?= time_ago($mat['upload_date']) ?></span>
                                <?php if ($mat['download_count']): ?>
                                    <span><i class="fa-solid fa-download"></i> <?= (int) $mat['download_count'] ?></span>
                                <?php endif; ?>
                            </div>
                            <a href="download.php?id=<?= $mat['id'] ?>" class="btn btn-primary btn-full">
                                <i class="fa-solid <?= $icon_class === 'vid' ? 'fa-circle-play' : 'fa-download' ?>"></i>
                                <?= $icon_class === 'vid' ? 'Watch / Download' : 'Download' ?>
                            </a>
                        </div>
                    <?php endforeach; ?>
                </div>

                <!-- Pagination -->
                <?php if ($pg['total_pages'] > 1): ?>
                    <nav class="pagination" aria-label="Pagination">
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg['current'] - 1])) ?>"
                            class="page-btn <?= $pg['current'] <= 1 ? 'btn:disabled' : '' ?>" <?= $pg['current'] <= 1 ? 'aria-disabled="true"' : '' ?>>
                            <i class="fa-solid fa-chevron-left"></i>
                        </a>
                        <?php for ($p = 1; $p <= $pg['total_pages']; $p++): ?>
                            <a href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"
                                class="page-btn <?= $p === $pg['current'] ? 'active' : '' ?>">
                                <?= $p ?>
                            </a>
                        <?php endfor; ?>
                        <a href="?<?= http_build_query(array_merge($_GET, ['page' => $pg['current'] + 1])) ?>"
                            class="page-btn <?= $pg['current'] >= $pg['total_pages'] ? 'btn:disabled' : '' ?>">
                            <i class="fa-solid fa-chevron-right"></i>
                        </a>
                    </nav>
                <?php endif; ?>
            <?php endif; ?>

        </div>
    </main>
    <?php render_footer(); ?>
</div>