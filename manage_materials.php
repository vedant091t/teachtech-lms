<?php
/**
 * manage_materials.php — Teacher's full material list with delete
 * Redirects to teacher_dashboard.php which now serves this purpose.
 * Kept for URL compatibility.
 */
require_once __DIR__ . '/core/bootstrap.php';
redirect('teacher_dashboard.php');
