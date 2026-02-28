<?php
/**
 * add_material.php — Upload new study material
 */
require_once __DIR__ . '/core/bootstrap.php';
require_once __DIR__ . '/core/layout.php';

auth_check('teacher');
$user = current_user();

$errors = [];
$old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $old = [
        'title' => trim($_POST['title'] ?? ''),
        'description' => trim($_POST['description'] ?? ''),
        'subject' => trim($_POST['subject'] ?? ''),
    ];

    // Validate text fields
    if (strlen($old['title']) < 3 || strlen($old['title']) > 200) {
        $errors['title'] = 'Title must be 3–200 characters.';
    }
    if (empty($_FILES['file']['name'])) {
        $errors['file'] = 'Please select a file to upload.';
    }

    if (empty($errors)) {
        $upload = handle_upload($_FILES['file'], __DIR__ . '/asset');

        if (!$upload['ok']) {
            $errors['file'] = $upload['error'];
        } else {
            $uid = $user['id'];
            $stmt = db()->prepare(
                'INSERT INTO materials (title, description, subject, filename, file_size, uploaded_by)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $stmt->bind_param(
                'ssssii',
                $old['title'],
                $old['description'],
                $old['subject'],
                $upload['filename'],
                $upload['size'],
                $uid
            );

            if ($stmt->execute()) {
                flash('success', '"' . $old['title'] . '" uploaded successfully!');
                redirect('manage_materials.php');
            } else {
                // DB failed — clean up the uploaded file to prevent orphan on disk
                $orphan = __DIR__ . '/asset/' . $upload['filename'];
                if (file_exists($orphan)) {
                    @unlink($orphan);
                }
                $errors['_global'] = 'Database error. Please try again.';
            }
        }
    }
}

render_header('Upload Material', 'teacher');
?>
<div class="app-layout">
    <main class="page-main">
        <div class="page-container" style="max-width:700px;">

            <?php render_flash(); ?>

            <div class="breadcrumb">
                <a href="teacher_dashboard.php">Dashboard</a>
                <span class="breadcrumb-sep">›</span>
                <span>Upload Material</span>
            </div>

            <?php if (!empty($errors['_global'])): ?>
                <div class="alert alert-danger"><i
                        class="fa-solid fa-circle-xmark"></i><span><?= e($errors['_global']) ?></span></div>
            <?php endif; ?>

            <div class="card">
                <div class="card-header">
                    <h2 class="card-title"><i class="fa-solid fa-cloud-arrow-up"></i> New Study Material</h2>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" data-loading>
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label class="form-label" for="title">Title <span
                                    style="color:var(--danger)">*</span></label>
                            <input class="form-control <?= isset($errors['title']) ? 'is-invalid' : '' ?>" type="text"
                                name="title" id="title" placeholder="e.g. Object-Oriented Programming — Unit 3" required
                                maxlength="200" value="<?= e($old['title'] ?? '') ?>">
                            <?php if (isset($errors['title'])): ?>
                                <span class="form-error"><?= e($errors['title']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="subject">Subject / Topic</label>
                            <input class="form-control" type="text" name="subject" id="subject"
                                placeholder="e.g. Data Structures, Java, Networks…" maxlength="100"
                                value="<?= e($old['subject'] ?? '') ?>">
                            <span class="form-hint">Optional — helps students filter by topic</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label" for="description">Description</label>
                            <textarea class="form-control" name="description" id="description"
                                placeholder="Brief note about what this material covers…"
                                maxlength="1000"><?= e($old['description'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group">
                            <label class="form-label">File <span style="color:var(--danger)">*</span></label>
                            <div class="file-drop <?= isset($errors['file']) ? 'is-invalid' : '' ?>">
                                <input type="file" name="file"
                                    accept=".pdf,.doc,.docx,.ppt,.pptx,.jpg,.jpeg,.png,.mp4,.webm,.avi,.mov" required>
                                <i class="fa-solid fa-cloud-arrow-up"></i>
                                <p class="file-drop-label">Click or drag file here</p>
                                <p class="text-xs text-muted" style="margin-top:.5rem;">PDF, DOC, DOCX, PPT, PPTX, JPG,
                                    PNG, MP4, WEBM, AVI, MOV — up to 10 MB</p>
                            </div>
                            <?php if (isset($errors['file'])): ?>
                                <span class="form-error"><?= e($errors['file']) ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="flex gap-3" style="justify-content:flex-end;">
                            <a href="manage_materials.php" class="btn btn-ghost">Cancel</a>
                            <button type="submit" class="btn btn-primary" data-loading-text="Uploading…">
                                <i class="fa-solid fa-upload"></i> Upload Material
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div>
    </main>
    <?php render_footer(); ?>
</div>