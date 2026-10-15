<?php
require_once '../../config.php';
Auth::logout();
header('Location: ' . SITE_URL . 'modules/auth/login.php');
exit();
?>