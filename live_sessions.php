<?php
/**
 * live_sessions.php — Teacher: create, manage, and go live with study sessions
 *
 * Actions (POST):
 *  - create  : create a new session (generates unique Jitsi room name)
 *  - go_live : set status=live, started_at=NOW()
 *  - end     : set status=ended, ended_at=NOW()
 *  - delete  : hard delete a session (only if ended or scheduled)
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('teacher');
$user = current_user();
$uid = $user['id'];

// ── POST actions ─────────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $title = trim($_POST['title'] ?? '');
        $subject = trim($_POST['subject'] ?? '');
        $sched = trim($_POST['scheduled_at'] ?? '') ?: null;

        if (strlen($title) < 2 || strlen($title) > 200) {
            flash('danger', 'Session title must be 2–200 characters.');
        } else {
            // Generate a unique room name: teachtech-{teacher_id}-{random_hex}
            $room = 'teachtech-' . $uid . '-' . bin2hex(random_bytes(5));

            $ins = db()->prepare(
                'INSERT INTO live_sessions (teacher_id, title, subject, room_name, scheduled_at)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $ins->bind_param('issss', $uid, $title, $subject, $room, $sched);
            $ins->execute()
                ? flash('success', 'Session "' . $title . '" created! Click Go Live when ready.')
                : flash('danger', 'Could not create session. Please try again.');
        }

    } elseif ($action === 'go_live') {
        $id = (int) ($_POST['session_id'] ?? 0);
        $upd = db()->prepare(
            'UPDATE live_sessions SET status = "live", started_at = NOW()
             WHERE id = ? AND teacher_id = ? AND status = "scheduled"'
        );
        $upd->bind_param('ii', $id, $uid);
        $upd->execute();
        $upd->affected_rows > 0
            ? flash('success', 'You are now LIVE! Students can join.')
            : flash('warning', 'Session is already live or not found.');

    } elseif ($action === 'end') {
        $id = (int) ($_POST['session_id'] ?? 0);
        $upd = db()->prepare(
            'UPDATE live_sessions SET status = "ended", ended_at = NOW()
             WHERE id = ? AND teacher_id = ? AND status = "live"'
        );
        $upd->bind_param('ii', $id, $uid);
        $upd->execute();
        flash(
            $upd->affected_rows > 0 ? 'success' : 'warning',
            $upd->affected_rows > 0 ? 'Session ended.' : 'Session was not live.'
        );

    } elseif ($action === 'delete') {
        $id = (int) ($_POST['session_id'] ?? 0);
        $del = db()->prepare(
            'DELETE FROM live_sessions WHERE id = ? AND teacher_id = ? AND status != "live"'
        );
        $del->bind_param('ii', $id, $uid);
        $del->execute();
        flash(
            $del->affected_rows > 0 ? 'success' : 'danger',
            $del->affected_rows > 0 ? 'Session deleted.' : 'Cannot delete a live session. End it first.'
        );
    }

    redirect('live_sessions.php');
}

// ── Fetch sessions ────────────────────────────────────────────────────────────
$stmt = db()->prepare('SELECT * FROM live_sessions WHERE teacher_id = ? ORDER BY created_at DESC');
$stmt->bind_param('i', $uid);
$stmt->execute();
$sessions = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

render_header('Live Sessions', 'teacher');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container">
            <?php render_flash(); ?>

            <div class="page-header">
                <div>
                    <h1 class="page-title"><i class="fa-solid fa-video" style="color:var(--primary)"></i> Live Sessions
                    </h1>
                    <p class="page-subtitle">Create a session, go live, and students can join instantly via video call.
                    </p>
                </div>
            </div>

            <!-- Create Session Form -->
            <div class="card" style="margin-bottom:2rem;">
                <div class="card-header">
                    <h2 class="card-title"><i class="fa-solid fa-plus-circle"></i> Create New Session</h2>
                </div>
                <div class="card-body">
                    <form method="POST" class="session-create-form" data-loading>
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create">

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:1rem;">
                            <div class="form-group">
                                <label class="form-label" for="title">Session Title *</label>
                                <input class="form-control" type="text" name="title" id="title"
                                    placeholder="e.g. Physics — Wave Motion" required maxlength="200">
                            </div>
                            <div class="form-group">
                                <label class="form-label" for="subject">Subject</label>
                                <input class="form-control" type="text" name="subject" id="subject"
                                    placeholder="e.g. Physics" maxlength="100">
                            </div>
                        </div>
                        <div class="form-group">
                            <label class="form-label" for="scheduled_at">
                                Schedule for <span class="text-muted">(optional — leave blank to start anytime)</span>
                            </label>
                            <input class="form-control" type="datetime-local" name="scheduled_at" id="scheduled_at">
                        </div>
                        <div style="text-align:right;">
                            <button class="btn btn-primary" type="submit" data-loading-text="Creating…">
                                <i class="fa-solid fa-plus"></i> Create Session
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Sessions List -->
            <?php if (empty($sessions)): ?>
                <div class="empty-state">
                    <i class="fa-solid fa-video-slash"></i>
                    <h3>No sessions yet</h3>
                    <p>Create your first live session above.</p>
                </div>
            <?php else: ?>
                <h2 class="section-title" style="margin-bottom:1rem;">My Sessions</h2>
                <div class="sessions-grid">
                    <?php foreach ($sessions as $s):
                        $is_live = $s['status'] === 'live';
                        $is_scheduled = $s['status'] === 'scheduled';
                        $is_ended = $s['status'] === 'ended';
                        ?>
                        <div class="session-card <?= $is_live ? 'session-live' : '' ?>">

                            <div class="session-card-head">
                                <div style="flex:1;min-width:0;">
                                    <div class="session-title truncate">
                                        <?= e($s['title']) ?>
                                    </div>
                                    <?php if ($s['subject']): ?>
                                        <div class="session-subject">
                                            <?= e($s['subject']) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <!-- Status badge -->
                                <?php if ($is_live): ?>
                                    <span class="live-badge"><span class="live-dot"></span> LIVE</span>
                                <?php elseif ($is_scheduled): ?>
                                    <span class="badge badge-scheduled">Scheduled</span>
                                <?php else: ?>
                                    <span class="badge badge-ended">Ended</span>
                                <?php endif; ?>
                            </div>

                            <?php if ($s['scheduled_at']): ?>
                                <div class="session-meta">
                                    <i class="fa-regular fa-calendar-clock"></i>
                                    <?= date('d M Y, g:i A', strtotime($s['scheduled_at'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($is_live && $s['started_at']): ?>
                                <div class="session-meta">
                                    <i class="fa-solid fa-circle-dot" style="color:var(--success);"></i>
                                    Live since
                                    <?= date('g:i A', strtotime($s['started_at'])) ?>
                                </div>
                            <?php endif; ?>
                            <?php if ($is_ended && $s['ended_at']): ?>
                                <div class="session-meta text-muted">
                                    <i class="fa-solid fa-check"></i>
                                    Ended at
                                    <?= date('d M, g:i A', strtotime($s['ended_at'])) ?>
                                </div>
                            <?php endif; ?>

                            <div class="session-actions">
                                <?php if ($is_live): ?>
                                    <a href="session_room.php?id=<?= $s['id'] ?>" class="btn btn-success btn-sm" target="_blank">
                                        <i class="fa-solid fa-right-to-bracket"></i> Enter Room
                                    </a>
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="end">
                                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                        <button class="btn btn-danger btn-sm" type="submit" data-confirm="End this live session?">
                                            <i class="fa-solid fa-stop-circle"></i> End Session
                                        </button>
                                    </form>

                                <?php elseif ($is_scheduled): ?>
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="go_live">
                                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                        <button class="btn btn-primary btn-sm" type="submit">
                                            <i class="fa-solid fa-circle-dot"></i> Go Live
                                        </button>
                                    </form>
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                        <button class="btn btn-outline btn-sm" type="submit" data-confirm="Delete this session?">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>

                                <?php else: /* ended */ ?>
                                    <span class="text-muted text-sm">Session ended</span>
                                    <form method="POST" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="session_id" value="<?= $s['id'] ?>">
                                        <button class="btn btn-ghost btn-sm" type="submit"
                                            data-confirm="Delete this session record?">
                                            <i class="fa-solid fa-trash"></i>
                                        </button>
                                    </form>
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