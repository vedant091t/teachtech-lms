<?php
/**
 * login.php — OTP-based login
 * Flow: Enter email → validate → enforce cooldown → generate OTP → hash → email → verify_otp.php
 *
 * Security model:
 *  - OTP is stored as bcrypt hash (password_hash) — plaintext never persisted.
 *  - 60-second resend cooldown prevents OTP spam.
 *  - Brute-force lockout (5 wrong attempts → 15-min lockout) enforced in verify_otp.php.
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/mailer.php';
require_once __DIR__ . '/core/layout.php';

// Already logged in → send to their dashboard
if (is_logged_in()) {
    redirect($_SESSION['role'] === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php');
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $email = trim(filter_input(INPUT_POST, 'email', FILTER_SANITIZE_EMAIL));

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } else {
        $stmt = db()->prepare(
            'SELECT id, username, role, is_verified, otp_last_sent_at, otp_locked_until
             FROM users WHERE email = ? LIMIT 1'
        );
        $stmt->bind_param('s', $email);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();

        if (!$user) {
            $error = 'No account found with that email. Please register first.';
        } elseif (!$user['is_verified']) {
            $error = 'Your email is not verified yet. Check your inbox for a previous OTP.';
        } elseif ($user['otp_locked_until'] && strtotime($user['otp_locked_until']) > time()) {
            // Account is locked — show time remaining
            $remaining = ceil((strtotime($user['otp_locked_until']) - time()) / 60);
            $error = "Too many incorrect attempts. Please try again in {$remaining} minute(s).";
        } else {
            // Enforce 60-second resend cooldown
            $cooldown = 60;
            if ($user['otp_last_sent_at'] && (time() - strtotime($user['otp_last_sent_at'])) < $cooldown) {
                $wait = $cooldown - (time() - strtotime($user['otp_last_sent_at']));
                $error = "Please wait {$wait} second(s) before requesting a new OTP.";
            } else {
                // Generate OTP and store only its bcrypt hash
                $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
                $otp_hash = password_hash($otp, PASSWORD_BCRYPT);

                $upd = db()->prepare(
                    'UPDATE users
                     SET otp_hash = ?, otp = NULL, otp_expires_at = DATE_ADD(NOW(), INTERVAL 10 MINUTE),
                         otp_attempts = 0, otp_locked_until = NULL, otp_last_sent_at = NOW()
                     WHERE id = ?'
                );
                $upd->bind_param('si', $otp_hash, $user['id']);
                $upd->execute();

                // Store minimal state for OTP page
                $_SESSION['otp_email'] = $email;
                $_SESSION['otp_action'] = 'login';

                $sent = send_otp($email, $otp, 'login');
                if (!$sent && $_ENV['APP_ENV'] === 'development') {
                    // Dev fallback: show OTP in session — never in production
                    $_SESSION['dev_otp'] = $otp;
                }

                flash('info', 'OTP sent! Check your inbox (and spam folder).');
                redirect('verify_otp.php');
            }
        }
    }
}

render_header('Sign In');
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="asset/logo.png" alt="TeachTech">
            <span>TeachTech</span>
        </div>
        <h1 class="auth-title">Welcome back</h1>
        <p class="auth-subtitle">Enter your registered email to receive a sign-in OTP</p>

        <?php render_flash(); ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><i class="fa-solid fa-circle-xmark"></i><span><?= e($error) ?></span></div>
        <?php endif; ?>

        <form method="POST" data-loading>
            <?= csrf_field() ?>
            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-envelope"></i>
                    <input class="form-control" type="email" name="email" id="email" placeholder="you@example.com"
                        required autofocus value="<?= e($_POST['email'] ?? '') ?>">
                </div>
            </div>
            <button class="btn btn-primary btn-full btn-lg" type="submit" data-loading-text="Sending OTP…">
                <i class="fa-solid fa-paper-plane"></i> Send OTP
            </button>
        </form>

        <p class="auth-footer">
            Don't have an account? <a href="register.php">Register here</a>
        </p>
    </div>
</div>
<?php render_footer(); ?>