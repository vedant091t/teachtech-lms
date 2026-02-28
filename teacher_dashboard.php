<?php
/**
 * teacher_dashboard.php — Teacher's overview of their uploaded materials
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('teacher');
$user = current_user();
$uid = $user['id'];

// Stats
$stats = db()->prepare(
    'SELECT COUNT(*) AS total_materials,
            COALESCE(SUM(download_count), 0) AS total_downloads,
            COUNT(CASE WHEN DATE(upload_date) = CURDATE() THEN 1 END) AS today_uploads
     FROM materials WHERE uploaded_by = ?'
);
$stats->bind_param('i', $uid);
$stats->execute();
$s = $stats->get_result()->fetch_assoc();

// Materials
$mats_stmt = db()->prepare(
    'SELECT id, title, description, filename, upload_date, download_count
     FROM materials WHERE uploaded_by = ? ORDER BY upload_date DESC LIMIT 50'
);
$mats_stmt->bind_param('i', $uid);
$mats_stmt->execute();
$materials = $mats_stmt->get_result()->fetch_all(MYSQLI_ASSOC);

render_header('Teacher Dashboard', 'teacher');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container">

            <?php render_flash(); ?>

            <!-- Stats -->
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-icon purple"><i class="fa-solid fa-folder"></i></div>
                    <div>
                        <div class="stat-val"><?= (int) $s['total_materials'] ?></div>
                        <div class="stat-lbl">My Materials</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon green"><i class="fa-solid fa-download"></i></div>
                    <div>
                        <div class="stat-val"><?= (int) $s['total_downloads'] ?></div>
                        <div class="stat-lbl">Total Downloads</div>
                    </div>
                </div>
                <div class="stat-card">
                    <div class="stat-icon blue"><i class="fa-solid fa-calendar-day"></i></div>
                    <div>
                        <div class="stat-val"><?= (int) $s['today_uploads'] ?></div>
                        <div class="stat-lbl">Uploaded Today</div>
                    </div>
                </div>
            </div>

            <!-- Header -->
            <div class="page-header">
                <div>
                    <h1 class="page-title">My Materials</h1>
                    <p class="page-subtitle">Manage everything you've shared with students</p>
                </div>
                <a href="add_material.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus"></i> Upload Material
                </a>
            </div>

            <!-- Table -->
            <?php if (empty($materials)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-cloud-arrow-up"></i>
                    <h3>Nothing uploaded yet</h3>
                    <p>Start by uploading your first study material.</p>
                    <a href="add_material.php" class="btn btn-primary mt-4">
                        <i class="fa-solid fa-plus"></i> Upload Now
                    </a>
                </div>
            <?php else: ?>
                <div class="card">
                    <div class="card-body" style="padding:0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Title</th>
                                    <th>File</th>
                                    <th>Uploaded</th>
                                    <th><i class="fa-solid fa-download" title="Downloads"></i></th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($materials as $m):
                                    $icon_class = file_icon_class($m['filename']);
                                    $fa_icon = file_fa_icon($icon_class);
                                    ?>
                                    <tr>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="file-icon-badge <?= $icon_class ?>"
                                                    style="width:32px;height:32px;font-size:1rem;">
                                                    <i class="fa-solid <?= $fa_icon ?>"></i>
                                                </span>
                                                <div>
                                                    <div class="font-semibold" style="max-width:200px;" class="truncate">
                                                        <?= e($m['title']) ?>
                                                    </div>
                                                    <?php if ($m['description']): ?>
                                                        <div class="text-xs text-muted">
                                                            <?= e(mb_strimwidth($m['description'], 0, 60, '…')) ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="text-sm text-muted">
                                            <?= e(strtoupper(pathinfo($m['filename'], PATHINFO_EXTENSION))) ?>
                                        </td>
                                        <td class="text-sm text-muted"><?= time_ago($m['upload_date']) ?></td>
                                        <td class="text-sm"><?= (int) $m['download_count'] ?></td>
                                        <td>
                                            <div class="flex gap-2">
                                                <a href="edit_material.php?id=<?= $m['id'] ?>" class="btn btn-outline btn-sm">
                                                    <i class="fa-solid fa-pencil"></i>
                                                </a>
                                                <!-- Delete: POST form so CSRF token never appears in URL/logs/referrer -->
                                                <form method="POST" action="delete_material.php" style="display:inline;"
                                                    data-confirm="Delete '<?= e(addslashes($m['title'])) ?>'? This cannot be undone.">
                                                    <?= csrf_field() ?>
                                                    <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                                                    <button type="submit" class="btn btn-danger btn-sm"
                                                        aria-label="Delete material">
                                                        <i class="fa-solid fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
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