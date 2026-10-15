<?php
require_once 'config.php';

$db = db();

// Method 1: Update existing admin
$db->query("UPDATE users SET password = ? WHERE username = 'admin'", [password_hash('admin123', PASSWORD_DEFAULT)]);

// Method 2: If admin doesn't exist, create it
$admin = $db->selectOne("SELECT id FROM users WHERE username = 'admin'");
if (!$admin) {
    $db->insert('users', [
        'username' => 'admin',
        'email' => 'admin@university.com',
        'password' => password_hash('admin123', PASSWORD_DEFAULT),
        'first_name' => 'System',
        'last_name' => 'Administrator',
        'role' => 'admin',
        'is_active' => 1
    ]);
}

echo "✅ Login fixed!<br>";
echo "Username: admin<br>";
echo "Password: admin123<br>";
echo "<a href='modules/auth/login.php'>Click here to login</a>";
?>