<?php
/**
 * join_session.php — Student view: browse and join live study sessions
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('student');

// Fetch all non-ended sessions, teachers' names, sorted live-first then scheduled
$stmt = db()->prepare(
    'SELECT ls.id, ls.title, ls.subject, ls.status, ls.scheduled_at, ls.started_at, u.username AS teacher_name
     FROM live_sessions ls
     JOIN users u ON ls.teacher_id = u.id
     WHERE ls.status != "ended"
     ORDER BY ls.status = "live" DESC, ls.scheduled_at ASC, ls.created_at DESC'
);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Count live right now for the stat card
$live_count = count(array_filter($sessions, fn($s) => $s['status'] === 'live'));

render_header('Live Sessions', 'student');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container">

            <?php render_flash(); ?>

            <div class="page-header">
                <div>
                    <h1 class="page-title">
                        <i class="fa-solid fa-video" style="color:var(--primary)"></i> Live Sessions
                    </h1>
                    <p class="page-subtitle">
                        <?= $live_count
                            ? '<span style="color:var(--success);font-weight:600;">' . $live_count . ' session' . ($live_count > 1 ? 's' : '') . ' live right now</span> — join below!'
                            : 'No sessions are live right now. Check back soon.' ?>
                    </p>
                </div>
            </div>

            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-satellite-dish"></i>
                    <h3>No sessions scheduled</h3>
                    <p>Your teachers haven't created any sessions yet. Check back later!</p>
                </div>
            <?php else: ?>
                <div class="sessions-grid">
                    <?php foreach ($sessions as $s):
                        $is_live = $s['status'] === 'live';
                        ?>
                        <div class="session-card <?= $is_live ? 'session-live' : '' ?>">

                            <div class="session-card-head">
                                <div style="flex:1;min-width:0;">
                                    <div class="session-title truncate">
                                        <?= e($s['title']) ?>
                                    </div>
                                    <div class="session-subject">
                                        <?= $s['subject'] ? e($s['subject']) . ' · ' : '' ?>
                                        <i class="fa-solid fa-chalkboard-user"></i>
                                        <?= e($s['teacher_name']) ?>
                                    </div>
                                </div>
                                <?php if ($is_live): ?>
                                    <span class="live-badge"><span class="live-dot"></span> LIVE</span>
                                <?php else: ?>
                                    <span class="badge badge-scheduled">Scheduled</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($s['scheduled_at']): ?>
                                <div class="session-meta">
                                    <i class="fa-regular fa-calendar-clock"></i>
                                    <?= date('d M Y, g:i A', strtotime($s['scheduled_at'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($is_live && $s['started_at']): ?>
                                <div class="session-meta" style="color:var(--success);">
                                    <i class="fa-solid fa-circle-dot"></i>
                                    Started
                                    <?= time_ago($s['started_at']) ?>
                                </div>
                            <?php endif; ?>

                            <div class="session-actions">
                                <?php if ($is_live): ?>
                                    <a href="session_room.php?id=<?= $s['id'] ?>" class="btn btn-success btn-sm" target="_blank">
                                        <i class="fa-solid fa-right-to-bracket"></i> Join Now
                                    </a>
                                <?php else: ?>
                                    <button class="btn btn-ghost btn-sm" disabled title="Waiting for teacher to go live">
                                        <i class="fa-solid fa-hourglass-half"></i> Not live yet
                                    </button>
                                <?php endif; ?>
                            </div>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    </main>
    <?php render_footer(); ?>
</div>