<?php
/**
 * session_room.php — Jitsi Meet video room (teacher + student)
 *
 * Security:
 *  - Auth required (any logged-in role).
 *  - Session must be live (status = 'live'); others get a redirect with message.
 *  - Jitsi room name is from DB (never from $_GET) — no injection possible.
 *  - Jitsi External API pre-fills displayName from session username.
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

if (!is_logged_in()) {
    flash('warning', 'Please log in to join a session.');
    redirect('login.php');
}

$user = current_user();
$id = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    flash('danger', 'Invalid session.');
    redirect(($_SESSION['role'] === 'teacher' ? 'live_sessions.php' : 'join_session.php'));
}

// Fetch session — only allow entry if status=live
$stmt = db()->prepare(
    'SELECT ls.id, ls.title, ls.subject, ls.room_name, ls.status, u.username AS teacher_name
     FROM live_sessions ls
     JOIN users u ON ls.teacher_id = u.id
     WHERE ls.id = ? LIMIT 1'
);
$stmt->bind_param('i', $id);
$stmt->execute();
$session = $stmt->get_result()->fetch_assoc();

if (!$session) {
    flash('danger', 'Session not found.');
    redirect($_SESSION['role'] === 'teacher' ? 'live_sessions.php' : 'join_session.php');
}

if ($session['status'] !== 'live') {
    $msg = $session['status'] === 'scheduled'
        ? 'This session hasn\'t started yet. Wait for the teacher to go live.'
        : 'This session has ended.';
    flash('info', $msg);
    redirect($_SESSION['role'] === 'teacher' ? 'live_sessions.php' : 'join_session.php');
}

$room_name = $session['room_name'];
$display_name = $user['username'];
$back_url = $_SESSION['role'] === 'teacher' ? 'live_sessions.php' : 'join_session.php';

// Output the room page — full-screen Jitsi embed
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        <?= e($session['title']) ?> — TeachTech Live
    </title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Inter', sans-serif;
            background: #0d0d14;
            color: #fff;
            height: 100dvh;
            display: flex;
            flex-direction: column;
        }

        .room-bar {
            display: flex;
            align-items: center;
            gap: 1rem;
            padding: .75rem 1.25rem;
            background: rgba(255, 255, 255, .05);
            border-bottom: 1px solid rgba(255, 255, 255, .08);
            flex-shrink: 0;
        }

        .room-bar .logo {
            font-weight: 700;
            font-size: 1.1rem;
            color: #fff;
            text-decoration: none;
        }

        .room-bar .room-title {
            flex: 1;
            font-size: .95rem;
            font-weight: 600;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .room-bar .room-sub {
            font-size: .8rem;
            color: rgba(255, 255, 255, .5);
        }

        .live-pill {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            background: #e53e3e20;
            border: 1px solid #e53e3e60;
            color: #fc8181;
            padding: .25rem .75rem;
            border-radius: 999px;
            font-size: .78rem;
            font-weight: 700;
        }

        .live-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #e53e3e;
            animation: pulse 1.2s infinite;
            display: inline-block;
        }

        @keyframes pulse {

            0%,
            100% {
                opacity: 1;
                transform: scale(1);
            }

            50% {
                opacity: .4;
                transform: scale(1.5);
            }
        }

        .leave-btn {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            background: #e53e3e;
            color: #fff;
            border: none;
            cursor: pointer;
            padding: .5rem 1rem;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
            text-decoration: none;
            white-space: nowrap;
            transition: background .15s;
        }

        .leave-btn:hover {
            background: #c53030;
        }

        #jitsi-container {
            flex: 1;
            width: 100%;
        }

        /* Loading state while Jitsi bootstraps */
        .jitsi-loading {
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 1rem;
            height: 100%;
            color: rgba(255, 255, 255, .6);
            font-size: .95rem;
        }

        .spinner {
            width: 40px;
            height: 40px;
            border: 3px solid rgba(255, 255, 255, .2);
            border-top-color: #6c63ff;
            border-radius: 50%;
            animation: spin .8s linear infinite;
        }

        @keyframes spin {
            to {
                transform: rotate(360deg);
            }
        }
    </style>
</head>

<body>
    <!-- Top bar -->
    <div class="room-bar">
        <a class="logo" href="<?= $back_url ?>">
            <i class="fa-solid fa-graduation-cap"></i> TeachTech
        </a>
        <div style="flex:1; min-width:0;">
            <div class="room-title">
                <?= e($session['title']) ?>
            </div>
            <?php if ($session['subject']): ?>
                <div class="room-sub">
                    <?= e($session['subject']) ?> · by
                    <?= e($session['teacher_name']) ?>
                </div>
            <?php endif; ?>
        </div>
        <span class="live-pill"><span class="live-dot"></span> LIVE</span>
        <a href="<?= $back_url ?>" class="leave-btn">
            <i class="fa-solid fa-right-from-bracket"></i> Leave
        </a>
    </div>

    <!-- Jitsi container -->
    <div id="jitsi-container">
        <div class="jitsi-loading" id="loading-indicator">
            <div class="spinner"></div>
            <span>Connecting to session…</span>
        </div>
    </div>

    <!-- Jitsi External API -->
    <script src="https://meet.jit.si/external_api.js"></script>
    <script>
        const domain = 'meet.jit.si';
        const options = {
            roomName: <?= json_encode($room_name) ?>,
            width: '100%',
            height: '100%',
            parentNode: document.getElementById('jitsi-container'),
            configOverwrite: {
                startWithAudioMuted: true,
                disableDeepLinking: true,
                prejoinPageEnabled: false,
            },
            interfaceConfigOverwrite: {
                TOOLBAR_BUTTONS: [
                    'microphone', 'camera', 'closedcaptions', 'desktop',
                    'fullscreen', 'hangup', 'chat', 'recording',
                    'raisehand', 'videoquality', 'tileview'
                ],
                SHOW_JITSI_WATERMARK: false,
                DEFAULT_BACKGROUND: '#0d0d14',
            },
            userInfo: {
                displayName: <?= json_encode($display_name) ?>
            },
        };

        const api = new JitsiMeetExternalAPI(domain, options);

        api.addEventListener('videoConferenceJoined', () => {
            document.getElementById('loading-indicator').style.display = 'none';
        });

        // Redirect to dashboard when user hangs up
        api.addEventListener('readyToClose', () => {
            window.location.href = <?= json_encode($back_url) ?>;
        });
    </script>
</body>

</html>