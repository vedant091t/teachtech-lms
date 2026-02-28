<?php
/**
 * register.php — New user registration
 * Flow: Fill form → validate → insert → generate OTP → email → verify_otp.php
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/mailer.php';
require_once __DIR__ . '/core/layout.php';

if (is_logged_in()) {
    redirect($_SESSION['role'] === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php');
}

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old = [
        'username' => trim($_POST['username'] ?? ''),
        'email' => trim($_POST['email'] ?? ''),
        'phone' => trim($_POST['phone'] ?? ''),
        'role' => $_POST['role'] ?? 'student',
    ];

    // ── Validation ──────────────────────────────────────────────────────
    if (strlen($old['username']) < 2 || strlen($old['username']) > 80) {
        $errors['username'] = 'Username must be 2–80 characters.';
    }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Enter a valid email address.';
    }
    if (!preg_match('/^\+?[0-9\s\-]{8,15}$/', $old['phone'])) {
        $errors['phone'] = 'Enter a valid phone number (8–15 digits).';
    }
    if (!in_array($old['role'], ['student', 'teacher'], true)) {
        $errors['role'] = 'Invalid role selected.';
    }

    if (empty($errors)) {
        // Duplicate check
        $chk = db()->prepare('SELECT id FROM users WHERE email = ? OR phone = ? LIMIT 1');
        $chk->bind_param('ss', $old['email'], $old['phone']);
        $chk->execute();

        if ($chk->get_result()->num_rows > 0) {
            $errors['email'] = 'An account with this email or phone already exists.';
        } else {
            // Generate OTP — store only bcrypt hash, never plaintext
            $otp = str_pad(random_int(0, 999999), 6, '0', STR_PAD_LEFT);
            $otp_hash = password_hash($otp, PASSWORD_BCRYPT);

            $ins = db()->prepare(
                'INSERT INTO users
                    (username, email, phone, role, otp, otp_hash, otp_expires_at,
                     otp_attempts, otp_last_sent_at, is_verified)
                 VALUES (?, ?, ?, ?, NULL, ?, DATE_ADD(NOW(), INTERVAL 10 MINUTE), 0, NOW(), 0)'
            );
            $ins->bind_param(
                'sssss',
                $old['username'],
                $old['email'],
                $old['phone'],
                $old['role'],
                $otp_hash
            );

            if ($ins->execute()) {
                $_SESSION['otp_email'] = $old['email'];
                $_SESSION['otp_action'] = 'register';

                $sent = send_otp($old['email'], $otp, 'registration');
                if (!$sent && $_ENV['APP_ENV'] === 'development') {
                    $_SESSION['dev_otp'] = $otp;
                }

                flash('success', 'Account created! Check your email for the OTP to verify your account.');
                redirect('verify_otp.php');
            } else {
                $errors['_global'] = 'Registration failed. Please try again.';
            }
        }
    }
}

render_header('Create Account');
?>
<div class="auth-page">
    <div class="auth-card">
        <div class="auth-logo">
            <img src="asset/logo.png" alt="TeachTech">
            <span>TeachTech</span>
        </div>
        <h1 class="auth-title">Create account</h1>
        <p class="auth-subtitle">Join TeachTech as a teacher or student</p>

        <?php render_flash(); ?>
        <?php if (!empty($errors['_global'])): ?>
            <div class="alert alert-danger"><i
                    class="fa-solid fa-circle-xmark"></i><span><?= e($errors['_global']) ?></span></div>
        <?php endif; ?>

        <form method="POST" data-loading>
            <?= csrf_field() ?>

            <!-- Role selector -->
            <div class="role-tabs">
                <div class="role-tab <?= ($old['role'] ?? 'student') === 'student' ? 'active' : '' ?>"
                    data-role="student">
                    <i class="fa-solid fa-graduation-cap"></i> Student
                </div>
                <div class="role-tab <?= ($old['role'] ?? '') === 'teacher' ? 'active' : '' ?>" data-role="teacher">
                    <i class="fa-solid fa-chalkboard-teacher"></i> Teacher
                </div>
            </div>
            <input type="hidden" name="role" id="role-input" value="<?= e($old['role'] ?? 'student') ?>">

            <div class="form-group">
                <label class="form-label" for="username">Full name</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-user"></i>
                    <input class="form-control <?= isset($errors['username']) ? 'is-invalid' : '' ?>" type="text"
                        name="username" id="username" placeholder="Your full name" required
                        value="<?= e($old['username'] ?? '') ?>">
                </div>
                <?php if (isset($errors['username'])): ?>
                    <span class="form-error"><?= e($errors['username']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="email">Email address</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-envelope"></i>
                    <input class="form-control <?= isset($errors['email']) ? 'is-invalid' : '' ?>" type="email"
                        name="email" id="email" placeholder="you@example.com" required
                        value="<?= e($old['email'] ?? '') ?>">
                </div>
                <?php if (isset($errors['email'])): ?>
                    <span class="form-error"><?= e($errors['email']) ?></span>
                <?php endif; ?>
            </div>

            <div class="form-group">
                <label class="form-label" for="phone">Phone number</label>
                <div class="input-icon-wrap">
                    <i class="fa-solid fa-phone"></i>
                    <input class="form-control <?= isset($errors['phone']) ? 'is-invalid' : '' ?>" type="tel"
                        name="phone" id="phone" placeholder="+91 98765 43210" required
                        value="<?= e($old['phone'] ?? '') ?>">
                </div>
                <?php if (isset($errors['phone'])): ?>
                    <span class="form-error"><?= e($errors['phone']) ?></span>
                <?php endif; ?>
            </div>

            <button class="btn btn-primary btn-full btn-lg" type="submit" data-loading-text="Creating account…">
                <i class="fa-solid fa-user-plus"></i> Create Account
            </button>
        </form>

        <p class="auth-footer">
            Already have an account? <a href="login.php">Sign in</a>
        </p>
    </div>
</div>
<?php render_footer(); ?>