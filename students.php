<?php
/**
 * students.php — Teacher-only: view all students and their download activity
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('teacher');
$user = current_user();
$uid = $user['id'];

// ── Summary stats ─────────────────────────────────────────────────────────────
$stats = db()->query(
    "SELECT
        COUNT(*)                                              AS total_students,
        SUM((SELECT COUNT(*) FROM materials WHERE uploaded_by = $uid)) AS my_materials
     FROM users WHERE role = 'student' AND is_verified = 1"
)->fetch_assoc();

// Total downloads across all teacher's materials
$dl_row = db()->prepare(
    'SELECT COALESCE(SUM(download_count), 0) AS total_downloads
     FROM materials WHERE uploaded_by = ?'
);
$dl_row->bind_param('i', $uid);
$dl_row->execute();
$total_downloads = $dl_row->get_result()->fetch_assoc()['total_downloads'];

// ── Student list with their registration date ─────────────────────────────────
$search = trim($_GET['q'] ?? '');

$where = "WHERE u.role = 'student' AND u.is_verified = 1";
$params = [];
$types = '';

if ($search !== '') {
    $where .= ' AND (u.username LIKE ? OR u.email LIKE ?)';
    $params[] = "%{$search}%";
    $params[] = "%{$search}%";
    $types .= 'ss';
}

// We track downloads per material (not per student+material), so we show
// total downloads on teacher's materials — stored in materials.download_count.
// For "who downloaded what", that requires a download_log table.
// For now we show student info + when they joined.
$stmt = db()->prepare(
    "SELECT u.id, u.username, u.email, u.phone, u.created_at,
            COUNT(dl.id) AS downloads_from_me
     FROM users u
     LEFT JOIN download_log dl ON dl.student_id = u.id
         AND dl.material_id IN (SELECT id FROM materials WHERE uploaded_by = ?)
     $where
     GROUP BY u.id, u.username, u.email, u.phone, u.created_at
     ORDER BY downloads_from_me DESC, u.created_at DESC"
);

if ($params) {
    // We need to bind teacher id + any search params
    $stmt->bind_param('i' . $types, $uid, ...$params);
} else {
    $stmt->bind_param('i', $uid);
}
$stmt->execute();
$students = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);


render_header('Students', 'teacher');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container">

            <?php render_flash(); ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fa-solid fa-users"></i></div>
                    <div>
                        <div class="stat-val">
                            <?= count($students) ?>
                        </div>
                        <div class="stat-lbl">Total Students</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fa-solid fa-download"></i></div>
                    <div>
                        <div class="stat-val">
                            <?= (int) $total_downloads ?>
                        </div>
                        <div class="stat-lbl">Downloads on My Materials</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon orange"><i class="fa-solid fa-book-open"></i></div>
                    <div>
                        <?php
                        $my_mats = db()->prepare('SELECT COUNT(*) FROM materials WHERE uploaded_by = ?');
                        $my_mats->bind_param('i', $uid);
                        $my_mats->execute();
                        [$mat_count] = $my_mats->get_result()->fetch_row();
                        ?>
                        <div class="stat-val">
                            <?= (int) $mat_count ?>
                        </div>
                        <div class="stat-lbl">My Materials</div>
                    </div>
                </div>
            </div>

            <!-- Header + search -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">Student Directory</h1>
                    <p class="page-subtitle">
                        <?= count($students) ?> verified student
                        <?= count($students) !== 1 ? 's' : '' ?> registered
                    </p>
                </div>
            </div>

            <!-- Search bar -->
            <form method="GET" style="margin-bottom:1.5rem;">
                <div class="filter-bar">
                    <div class="form-group" style="flex:1;">
                        <div class="input-icon-wrap">
                            <i class="fa-solid fa-magnifying-glass"></i>
                            <input class="form-control" type="search" name="q" placeholder="Search by name or email…"
                                value="<?= e($search) ?>">
                        </div>
                    </div>
                    <button class="btn btn-primary" type="submit">
                        <i class="fa-solid fa-filter"></i> Search
                    </button>
                    <?php if ($search): ?>
                        <a href="students.php" class="btn btn-ghost">
                            <i class="fa-solid fa-xmark"></i> Clear
                        </a>
                    <?php endif; ?>
                </div>
            </form>

            <!-- Students table -->
            <?php if (empty($students)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-user-slash"></i>
                    <h3>No students found</h3>
                    <p>
                        <?= $search ? 'Try different search terms.' : 'No verified students have registered yet.' ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body" style="padding:0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>#</th>
                                    <th>Student Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th><i class="fa-solid fa-download" title="Downloads of my materials"></i> Downloads</th>
                                    <th>Joined</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($students as $i => $s): ?>
                                    <tr>
                                        <td class="text-muted text-sm">
                                            <?= $i + 1 ?>
                                        </td>
                                        <td>
                                            <div style="display:flex;align-items:center;gap:.75rem;">
                                                <div class="stat-icon purple"
                                                    style="width:36px;height:36px;border-radius:50%;font-size:.9rem;flex-shrink:0;">
                                                    <span style="font-weight:700;color:#fff;">
                                                        <?= strtoupper(mb_substr($s['username'], 0, 1)) ?>
                                                    </span>
                                                </div>
                                                <span class="font-semibold">
                                                    <?= e($s['username']) ?>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="text-sm text-muted">
                                            <?= e($s['email']) ?>
                                        </td>
                                        <td class="text-sm text-muted">
                                            <?= e($s['phone'] ?? '—') ?>
                                        </td>
                                        <td>
                                            <?php $dl = (int)$s['downloads_from_me']; ?>
                                            <span style="font-weight:<?= $dl > 0 ? '700' : '400' ?>;color:<?= $dl > 0 ? 'var(--primary)' : 'var(--text-muted)' ?>;display:inline-flex;align-items:center;gap:.3rem;">
                                                <i class="fa-solid fa-download" style="font-size:.75rem;"></i>
                                                <?= $dl ?>
                                            </span>
                                        </td>
                                        <td class="text-sm text-muted"><?= time_ago($s['created_at']) ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>


            <?php endif; ?>

        </div>
    </main>
    <?php render_footer(); ?>
</div>