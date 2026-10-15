<?php
class Auth {
    
    public static function register($username, $email, $password, $first_name, $last_name, $role = 'student') {
        $db = db();
        
        // Check if user exists
        $existing = $db->selectOne("SELECT id FROM users WHERE username = ? OR email = ?", [$username, $email]);
        if ($existing) {
            return ['success' => false, 'message' => 'Username or email already exists'];
        }
        
        // Validate password strength
        if (strlen($password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        // Hash password
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        // Insert user
        $user_id = $db->insert('users', [
            'username' => $username,
            'email' => $email,
            'password' => $hashed_password,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'role' => $role
        ]);
        
        if ($user_id) {
            return ['success' => true, 'message' => 'Registration successful', 'user_id' => $user_id];
        }
        
        return ['success' => false, 'message' => 'Registration failed'];
    }
    
    public static function login($username, $password) {
        $db = db();
        
        $user = $db->selectOne("SELECT * FROM users WHERE username = ? OR email = ?", [$username, $username]);
        
        if ($user && password_verify($password, $user['password'])) {
            if ($user['is_active']) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['full_name'] = $user['first_name'] . ' ' . $user['last_name'];
                $_SESSION['email'] = $user['email'];
                
                // Update last login
                $db->update('users', ['last_login' => date('Y-m-d H:i:s')], 'id = ?', [$user['id']]);
                
                return ['success' => true, 'message' => 'Login successful', 'user' => $user];
            } else {
                return ['success' => false, 'message' => 'Account is deactivated. Please contact administrator.'];
            }
        }
        
        return ['success' => false, 'message' => 'Invalid username or password'];
    }
    
    public static function logout() {
        $_SESSION = array();
        
        if (ini_get("session.use_cookies")) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }
        
        session_destroy();
        return true;
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }
        
        $db = db();
        return $db->selectOne("SELECT * FROM users WHERE id = ?", [$_SESSION['user_id']]);
    }
    
    public static function hasRole($role) {
        return self::isLoggedIn() && $_SESSION['role'] == $role;
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ' . SITE_URL . 'modules/auth/login.php');
            exit();
        }
    }
    
    public static function requireRole($role) {
        self::requireLogin();
        
        if ($_SESSION['role'] != $role) {
            header('Location: ' . SITE_URL . 'modules/dashboard.php');
            exit();
        }
    }
    
    public static function isAdmin() {
        return self::hasRole('admin');
    }
    
    public static function isTeacher() {
        return self::hasRole('teacher');
    }
    
    public static function isStudent() {
        return self::hasRole('student');
    }
    
    public static function changePassword($user_id, $old_password, $new_password) {
        $db = db();
        $user = $db->selectOne("SELECT password FROM users WHERE id = ?", [$user_id]);
        
        if (!password_verify($old_password, $user['password'])) {
            return ['success' => false, 'message' => 'Current password is incorrect'];
        }
        
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'New password must be at least 6 characters'];
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $db->update('users', ['password' => $hashed_password], 'id = ?', [$user_id]);
        
        return ['success' => true, 'message' => 'Password changed successfully'];
    }
    
    public static function updateProfile($user_id, $data) {
        $db = db();
        $db->update('users', $data, 'id = ?', [$user_id]);
        return ['success' => true, 'message' => 'Profile updated successfully'];
    }
    
    public static function generatePasswordResetToken($email) {
        $db = db();
        $user = $db->selectOne("SELECT id FROM users WHERE email = ?", [$email]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Email not found'];
        }
        
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        $db->update('users', [
            'reset_token' => $token,
            'reset_expires' => $expires
        ], 'id = ?', [$user['id']]);
        
        return ['success' => true, 'token' => $token];
    }
    
    public static function resetPassword($token, $new_password) {
        $db = db();
        $user = $db->selectOne("SELECT id FROM users WHERE reset_token = ? AND reset_expires > NOW()", [$token]);
        
        if (!$user) {
            return ['success' => false, 'message' => 'Invalid or expired reset token'];
        }
        
        if (strlen($new_password) < 6) {
            return ['success' => false, 'message' => 'Password must be at least 6 characters'];
        }
        
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $db->update('users', [
            'password' => $hashed_password,
            'reset_token' => null,
            'reset_expires' => null
        ], 'id = ?', [$user['id']]);
        
        return ['success' => true, 'message' => 'Password reset successfully'];
    }
}
?>