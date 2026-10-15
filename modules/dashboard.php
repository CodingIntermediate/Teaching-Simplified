<?php
require_once '../config.php';
Auth::requireLogin();

$user = Auth::getCurrentUser();
$db = db();

// Redirect based on role
if ($user['role'] == 'admin') {
    header('Location: admin/dashboard.php');
    exit();
} elseif ($user['role'] == 'teacher') {
    header('Location: teacher/dashboard.php');
    exit();
} else {
    header('Location: student/dashboard.php');
    exit();
}
?>