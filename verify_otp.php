<?php
/**
 * verify_otp.php — OTP verification for both login and registration
 *
 * Security model:
 *  - Verifies OTP with password_verify() against the stored bcrypt hash (otp_hash).
 *  - Tracks wrong attempts in otp_attempts; locks account for 15 min after 5 failures.
 *  - OTP hash, attempts, and expiry are cleared on success.
 *  - On success → establishes full session → redirects to role-based dashboard.
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

if (is_logged_in()) {
    redirect($_SESSION['role'] === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php');
}

// Must have started the OTP flow
if (empty($_SESSION['otp_email'])) {
    flash('danger', 'Session expired. Please start again.');
    redirect('login.php');
}

$email = $_SESSION['otp_email'];
$action = $_SESSION['otp_action'] ?? 'login';
$error = '';

// Dev hint (only in development mode)
$dev_otp = $_SESSION['dev_otp'] ?? null;

// ── OTP lockout check (shown before POST so user sees it on page load too) ────
$lock_stmt = db()->prepare(
    'SELECT otp_locked_until FROM users WHERE email = ? LIMIT 1'
);
$lock_stmt->bind_param('s', $email);
$lock_stmt->execute();
$lock_row = $lock_stmt->get_result()->fetch_assoc();

$is_locked = $lock_row && $lock_row['otp_locked_until']
    && strtotime($lock_row['otp_locked_until']) > time();
if ($is_locked) {
    $remaining = ceil((strtotime($lock_row['otp_locked_until']) - time()) / 60);
    $error = "Too many incorrect attempts. Verification locked for {$remaining} more minute(s).";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$is_locked) {
    csrf_verify();

    $input = trim(implode('', [
        $_POST['d1'] ?? '',
        $_POST['d2'] ?? '',
        $_POST['d3'] ?? '',
        $_POST['d4'] ?? '',
        $_POST['d5'] ?? '',
        $_POST['d6'] ?? '',
    ]));

    // Also accept from hidden field (paste scenario)
    if (empty($input) || !preg_match('/^\d{6}$/', $input)) {
        $input = trim($_POST['otp-hidden'] ?? '');
    }

    if (!preg_match('/^\d{6}$/', $input)) {
        $error = 'Please enter the full 6-digit OTP.';
    } else {
        // Fetch user with all OTP fields
        $stmt = db()->prepare(
            'SELECT id, username, role, otp_hash, otp_expires_at, otp_attempts,
                    otp_locked_until, is_verified
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            flash('danger', 'Account not found.');
            redirect('login.php');
        }

        // Re-check lockout from fresh DB data
        if ($user['otp_locked_until'] && strtotime($user['otp_locked_until']) > time()) {
            $remaining = ceil((strtotime($user['otp_locked_until']) - time()) / 60);
            $error = "Too many incorrect attempts. Verification locked for {$remaining} more minute(s).";
        } elseif ($user['otp_expires_at'] && strtotime($user['otp_expires_at']) < time()) {
            $error = 'OTP has expired. Please go back and request a new one.';
        } elseif (empty($user['otp_hash']) || !password_verify($input, $user['otp_hash'])) {
            // Wrong OTP — increment attempt counter; lock if threshold reached
            $new_attempts = ((int) $user['otp_attempts']) + 1;
            $max_attempts = 5;
            $lock_for_min = 15;

            if ($new_attempts >= $max_attempts) {
                // Lock the account
                $lock_until = date('Y-m-d H:i:s', time() + $lock_for_min * 60);
                $upd = db()->prepare(
                    'UPDATE users SET otp_attempts = ?, otp_locked_until = ? WHERE id = ?'
                );
                $upd->bind_param('isi', $new_attempts, $lock_until, $user['id']);
                $upd->execute();
                $error = "Too many incorrect attempts. Verification locked for {$lock_for_min} minutes.";
            } else {
                $left = $max_attempts - $new_attempts;
                $upd = db()->prepare('UPDATE users SET otp_attempts = ? WHERE id = ?');
                $upd->bind_param('ii', $new_attempts, $user['id']);
                $upd->execute();
                $error = "Incorrect OTP. {$left} attempt(s) remaining.";
            }
        } else {
            // ── SUCCESS: mark verified, clear all OTP state ────────────────────
            $upd = db()->prepare(
                'UPDATE users
                 SET is_verified     = 1,
                     otp             = NULL,
                     otp_hash        = NULL,
                     otp_expires_at  = NULL,
                     otp_attempts    = 0,
                     otp_locked_until = NULL,
                     last_login      = NOW()
                 WHERE id = ?'
            );
            $upd->bind_param('i', $user['id']);
            $upd->execute();

            // Establish full session
            session_regenerate_id(true);
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            unset($_SESSION['otp_email'], $_SESSION['otp_action'], $_SESSION['dev_otp']);

            flash('success', 'Welcome back, ' . $user['username'] . '!');
            redirect($user['role'] === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php');
        }
    }
}

render_header('Verify OTP');
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="asset/logo.png" alt="TeachTech">
            <span>TeachTech</span>
        </div>
        <h1 class="auth-title">Check your email</h1>
        <p class="auth-subtitle">
            We sent a 6-digit code to <strong><?= e($email) ?></strong>
        </p>

        <?php if ($error): ?>
                <div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <?php if ($dev_otp && $_ENV['APP_ENV'] === 'development'): ?>
                <div class="alert alert-warning">
                    <i class="fa-solid fa-bug"></i>
                    <span><strong>Dev mode:</strong> OTP is <strong><?= e($dev_otp) ?></strong></span>
                </div>
        <?php endif; ?>

        <?php if (!$is_locked): ?>
            <form method="POST" id="otp-form" data-loading>
                <?= csrf_field() ?>

                <div class="otp-input-group">
                    <?php for ($i = 1; $i <= 6; $i++): ?>
                            <input class="otp-digit form-control" type="text" name="d<?= $i ?>" maxlength="1" inputmode="numeric"
                                pattern="\d" autocomplete="<?= $i === 1 ? 'one-time-code' : 'off' ?>" aria-label="Digit <?= $i ?>">
                    <?php endfor; ?>
                </div>
                <input type="hidden" name="otp-hidden" id="otp-hidden">

                <!-- Countdown -->
                <p class="text-center text-sm text-muted" style="margin-bottom:1rem;">
                    Code expires in <strong id="otp-countdown" data-seconds="600">10:00</strong>
                </p>

                <button class="btn btn-primary btn-full btn-lg" type="submit" data-loading-text="Verifying…">
                    <i class="fa-solid fa-shield-halved"></i> Verify OTP
                </button>
            </form>
        <?php endif; ?>

        <p class="auth-footer">
            Wrong email? <a href="<?= $action === 'register' ? 'register.php' : 'login.php' ?>">Go back</a>
        </p>
    </div>
</div>
<?php render_footer(); ?>