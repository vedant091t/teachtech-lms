<?php
/**
 * Layout: Header partial
 * Usage: include partial_header('Page Title', $scripts=[]);
 * $nav: 'none' | 'student' | 'teacher'
 */
defined('TT_APP') or die();

function render_header(string $title, string $nav = 'none'): void
{
    $base = app_url('public');   // Reads APP_BASE_URL from .env; empty = served at web-root
    $user = current_user();
    ?>
    <!doctype html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="theme-color" content="#6C63FF">
        <title>
            <?= e($title) ?> — TeachTech
        </title>
        <meta name="description" content="TeachTech Learning Management System">

        <!-- Preconnect -->
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

        <!-- Google Fonts: Inter -->
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

        <!-- Font Awesome 6 -->
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

        <!-- Design System -->
        <link rel="stylesheet" href="<?= $base ?>/css/app.css">
    </head>

    <body>
        <?php if ($nav !== 'none'): ?>
            <nav class="navbar">
                <div class="page-container">
                    <a href="<?= $nav === 'teacher' ? 'teacher_dashboard.php' : 'student_dashboard.php' ?>" class="nav-brand">
                        <img src="asset/logo.png" alt="TeachTech logo">
                        <span class="nav-brand-name">TeachTech</span>
                    </a>

                    <div class="nav-menu">
                        <?php if ($nav === 'teacher'): ?>
                            <a href="teacher_dashboard.php" class="nav-link"><i
                                    class="fa-solid fa-gauge-high"></i><span>Dashboard</span></a>
                            <a href="add_material.php" class="nav-link"><i class="fa-solid fa-plus"></i><span>Upload</span></a>
                            <a href="manage_materials.php" class="nav-link"><i class="fa-solid fa-folder-open"></i><span>My
                                    Materials</span></a>
                            <a href="students.php" class="nav-link"><i class="fa-solid fa-users"></i><span>Students</span></a>
                            <a href="live_sessions.php" class="nav-link"><i class="fa-solid fa-video"></i><span>Live
                                    Sessions</span></a>
                        <?php else: ?>
                            <a href="student_dashboard.php" class="nav-link"><i
                                    class="fa-solid fa-graduation-cap"></i><span>Browse</span></a>
                            <a href="join_session.php" class="nav-link"><i class="fa-solid fa-video"></i><span>Live
                                    Sessions</span></a>
                        <?php endif; ?>

                        <div class="nav-divider"></div>
                        <span class="nav-user"><i class="fa-solid fa-user-circle"></i><span>
                                <?= e($user['username']) ?>
                            </span></span>
                        <a href="logout.php" class="btn btn-ghost btn-sm" title="Logout"><i
                                class="fa-solid fa-right-from-bracket"></i></a>
                    </div>
                </div>
            </nav>
        <?php endif; ?>
        <?php
}

function render_footer(): void
{
    $year = date('Y');
    ?>
        <footer
            style="text-align:center;padding:2rem;color:var(--text-muted);font-size:.8rem;border-top:1px solid var(--border);margin-top:auto;">
            &copy;
            <?= $year ?> TeachTech &mdash; Built with care for learners.
        </footer>
        <div id="toast-container" class="toast-container"></div>
        <script src="<?= app_url('public/js/app.js') ?>"></script>
    </body>

    </html>
    <?php
}
