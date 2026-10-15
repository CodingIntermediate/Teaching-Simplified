<?php
// Redirect to shared profile page
require_once '../../config.php';
Auth::requireRole('student');
header('Location: ' . SITE_URL . 'modules/auth/profile.php');
exit();
?>