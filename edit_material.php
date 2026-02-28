<?php
/**
 * edit_material.php — Update title, description, subject, optionally swap file
 *
 * File lifecycle (safe order):
 *  1. Handle upload (move new file to /asset/).
 *  2. Attempt DB UPDATE.
 *  3a. If DB succeeded → delete old file (best-effort).
 *  3b. If DB failed   → delete the newly uploaded file (cleanup orphan), keep old file.
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('teacher');
$user = current_user();
$uid = $user['id'];

$id = (int) ($_GET['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid material ID.');
    redirect('manage_materials.php');
}

// Fetch — ensure ownership
$fetch = db()->prepare('SELECT * FROM materials WHERE id = ? AND uploaded_by = ? LIMIT 1');
$fetch->bind_param('ii', $id, $uid);
$fetch->execute();
$material = $fetch->get_result()->fetch_assoc();

if (!$material) {
    flash('danger', 'Material not found or access denied.');
    redirect('manage_materials.php');
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $subject = trim($_POST['subject'] ?? '');

    if (strlen($title) < 3 || strlen($title) > 200) {
        $errors['title'] = 'Title must be 3–200 characters.';
    }

    // Track whether we uploaded a new file during this request
    $new_filename = null;
    $new_size = null;

    if (!empty($_FILES['file']['name'])) {
        $upload = handle_upload($_FILES['file'], __DIR__ . '/asset');
        if (!$upload['ok']) {
            $errors['file'] = $upload['error'];
        } else {
            $new_filename = $upload['filename'];
            $new_size = $upload['size'];
        }
    }

    if (empty($errors)) {
        // Decide what goes into the DB
        $db_filename = $new_filename ?? $material['filename'];
        $db_size = $new_size ?? $material['file_size'];
        $old_path = __DIR__ . '/asset/' . basename($material['filename']);

        // Single, correctly-typed UPDATE:
        //   title(s), description(s), subject(s), filename(s), file_size(i), id(i), uploaded_by(i)
        $upd = db()->prepare(
            'UPDATE materials
             SET title = ?, description = ?, subject = ?, filename = ?, file_size = ?
             WHERE id = ? AND uploaded_by = ?'
        );
        $upd->bind_param('ssssiii', $title, $description, $subject, $db_filename, $db_size, $id, $uid);

        if ($upd->execute() && $upd->affected_rows >= 0) {
            // DB committed — now safe to remove old file (if we replaced it)
            if ($new_filename && file_exists($old_path)) {
                @unlink($old_path);
            }
            flash('success', 'Material updated successfully!');
            redirect('manage_materials.php');
        } else {
            // DB failed — rollback: remove the newly uploaded file to prevent orphan
            if ($new_filename) {
                $new_path = __DIR__ . '/asset/' . $new_filename;
                if (file_exists($new_path)) {
                    @unlink($new_path);
                }
            }
            $errors['_global'] = 'Update failed. Please try again.';
        }
    } elseif ($new_filename) {
        // Validation failed but a file was uploaded — clean it up
        $cleanup_path = __DIR__ . '/asset/' . $new_filename;
        if (file_exists($cleanup_path)) {
            @unlink($cleanup_path);
        }
    }

    // Repopulate form on error
    $material['title'] = $title;
    $material['description'] = $description;
    $material['subject'] = $subject;
}

render_header('Edit Material', 'teacher');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container" style="max-width:700px;">

            <div class="breadcrumb">
                <a href="teacher_dashboard.php">Dashboard</a>
                <span class="breadcrumb-sep">›</span>
                <a href="manage_materials.php">My Materials</a>
                <span class="breadcrumb-sep">›</span>
                <span>Edit</span>
            </div>

            <?php if (!empty($errors['_global'])): ?>
                <div class="alert alert-danger"><i
                        class="fa-solid fa-circle-xmark"></i><span><?= e($errors['_global']) ?></span></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fa-solid fa-pencil"></i> Edit Material</h2>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" data-loading>
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label class="form-label" for="title">Title *</label>
                            <input class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" type="text"
                                name="title" id="title" required maxlength="200" value="<?= e($material['title']) ?>">
                            <?php if (isset($errors['title'])): ?>
                                <span class="form-error"><?= e($errors['title']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="subject">Subject / Topic</label>
                            <input class="form-control" type="text" name="subject" id="subject" maxlength="100"
                                value="<?= e($material['subject'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" name="description" id="description"
                                maxlength="1000"><?= e($material['description']) ?></textarea>
                        </div>

                        <!-- Current file -->
                        <div class="form-group">
                            <label class="form-label">Current file</label>
                            <div class="flex items-center gap-3"
                                style="padding:.75rem;background:var(--gray-50);border-radius:var(--radius-sm);border:1px solid var(--border);">
                                <?php $ic = file_icon_class($material['filename']); ?>
                                <span class="file-icon-badge <?= $ic ?>" style="width:32px;height:32px;font-size:1rem;">
                                    <i class="fa-solid <?= file_fa_icon($ic) ?>"></i>
                                </span>
                                <span class="text-sm font-semibold"><?= e($material['filename']) ?></span>
                            </div>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Replace file <span class="text-muted">(optional)</span></label>
                            <div class="file-drop">
                                <input type="file" name="file"
                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.mp4,.webm,.avi,.mov">
                                <i class="fa-solid fa-arrow-up-from-bracket"></i>
                                <p class="file-drop-label">Click or drag a new file to replace</p>
                            </div>
                            <?php if (isset($errors['file'])): ?>
                                <span class="form-error"><?= e($errors['file']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex gap-3" style="justify-content:flex-end;">
                            <a href="manage_materials.php" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary" data-loading-text="Saving…">
                                <i class="fa-solid fa-floppy-disk"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
    <?php render_footer(); ?>
</div>