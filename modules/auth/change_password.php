<?php
require_once '../../config.php';
Auth::requireLogin();

$db = db();
$user = Auth::getCurrentUser();
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validate
    if (empty($current_password)) {
        $error = 'Please enter your current password';
    } elseif (empty($new_password)) {
        $error = 'Please enter a new password';
    } elseif (strlen($new_password) < 6) {
        $error = 'New password must be at least 6 characters';
    } elseif ($new_password !== $confirm_password) {
        $error = 'Passwords do not match';
    } else {
        // Verify current password
        if (password_verify($current_password, $user['password'])) {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $db->update('users', ['password' => $hashed_password], 'id = ?', [$user['id']]);
            
            $success = 'Password changed successfully!';
            
            // Log the activity (optional)
            // logActivity($user['id'], 'password_changed', 'User changed their password');
            
            // You can also add a flash message
            setFlash('success', 'Password changed successfully!');
        } else {
            $error = 'Current password is incorrect';
        }
    }
}

// Get user role for display
$role_display = ucfirst($user['role']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Password - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .password-card {
            max-width: 500px;
            margin: 60px auto;
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .password-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 15px 15px 0 0;
            padding: 20px;
        }
        .password-card .card-body {
            padding: 30px;
        }
        .password-strength {
            height: 5px;
            margin-top: 5px;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .password-strength.weak { background: #dc3545; width: 25%; }
        .password-strength.medium { background: #ffc107; width: 50%; }
        .password-strength.strong { background: #28a745; width: 75%; }
        .password-strength.very-strong { background: #28a745; width: 100%; }
        .show-password {
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 50%;
            transform: translateY(-50%);
            color: #6c757d;
        }
        .show-password:hover {
            color: #007bff;
        }
        .password-field {
            position: relative;
        }
        .password-field input {
            padding-right: 40px;
        }
    </style>
</head>
<body>
    <!-- Navigation based on role -->
    <nav class="navbar navbar-expand-lg navbar-dark <?php 
        echo $user['role'] == 'admin' ? 'bg-danger' : ($user['role'] == 'teacher' ? 'bg-success' : 'bg-primary'); 
    ?>">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap"></i> Change Password
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="fas fa-user"></i> <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>
                    <span class="badge bg-light text-dark ms-1"><?php echo $role_display; ?></span>
                </span>
                <a class="nav-link" href="<?php echo SITE_URL; ?>modules/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="<?php echo SITE_URL; ?>modules/auth/profile.php">
                    <i class="fas fa-user-edit"></i> Profile
                </a>
                <a class="nav-link" href="<?php echo SITE_URL; ?>modules/auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container">
        <div class="row">
            <div class="col-md-12">
                <div class="card password-card">
                    <div class="card-header text-center">
                        <i class="fas fa-key fa-2x mb-2"></i>
                        <h4 class="mb-0">Change Password</h4>
                        <p class="mb-0 small opacity-75">Update your account password</p>
                    </div>
                    <div class="card-body">
                        <?php if ($success): ?>
                            <div class="alert alert-success alert-dismissible fade show" role="alert">
                                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($error): ?>
                            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                                <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                            </div>
                        <?php endif; ?>
                        
                        <form method="POST" id="passwordForm">
                            <!-- Current Password -->
                            <div class="mb-3">
                                <label for="current_password" class="form-label fw-bold">
                                    <i class="fas fa-lock"></i> Current Password
                                </label>
                                <div class="password-field">
                                    <input type="password" class="form-control" 
                                           id="current_password" name="current_password" 
                                           placeholder="Enter your current password" required>
                                    <span class="show-password" onclick="togglePassword('current_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                            </div>
                            
                            <!-- New Password -->
                            <div class="mb-3">
                                <label for="new_password" class="form-label fw-bold">
                                    <i class="fas fa-key"></i> New Password
                                </label>
                                <div class="password-field">
                                    <input type="password" class="form-control" 
                                           id="new_password" name="new_password" 
                                           placeholder="Enter new password (min 6 characters)" 
                                           required minlength="6"
                                           onkeyup="checkPasswordStrength(this.value)">
                                    <span class="show-password" onclick="togglePassword('new_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div id="passwordStrength" class="password-strength"></div>
                                <div id="passwordStrengthText" class="small text-muted mt-1"></div>
                            </div>
                            
                            <!-- Confirm New Password -->
                            <div class="mb-3">
                                <label for="confirm_password" class="form-label fw-bold">
                                    <i class="fas fa-check-circle"></i> Confirm New Password
                                </label>
                                <div class="password-field">
                                    <input type="password" class="form-control" 
                                           id="confirm_password" name="confirm_password" 
                                           placeholder="Re-enter new password" required>
                                    <span class="show-password" onclick="togglePassword('confirm_password')">
                                        <i class="fas fa-eye"></i>
                                    </span>
                                </div>
                                <div id="passwordMatch" class="small mt-1"></div>
                            </div>
                            
                            <!-- Password Requirements -->
                            <div class="alert alert-info small">
                                <i class="fas fa-info-circle"></i> Password must be at least 6 characters.
                                <br><i class="fas fa-shield-alt"></i> For better security, use a mix of:
                                <span class="badge bg-secondary">Uppercase</span>
                                <span class="badge bg-secondary">Lowercase</span>
                                <span class="badge bg-secondary">Numbers</span>
                                <span class="badge bg-secondary">Special characters</span>
                            </div>
                            
                            <!-- Security Notice -->
                            <div class="alert alert-warning small">
                                <i class="fas fa-exclamation-triangle"></i> 
                                <strong>Security Tip:</strong> Never share your password with anyone. 
                                The system will never ask for your password via email or phone.
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg" id="submitBtn">
                                    <i class="fas fa-save"></i> Change Password
                                </button>
                                <a href="<?php echo SITE_URL; ?>modules/dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                            
                            <!-- Last password change info -->
                            <?php if (!empty($user['updated_at'])): ?>
                            <div class="text-center mt-3 small text-muted">
                                <i class="fas fa-clock"></i> Last password change: 
                                <?php echo date('F d, Y h:i A', strtotime($user['updated_at'])); ?>
                            </div>
                            <?php endif; ?>
                        </form>
                    </div>
                </div>
                
                <!-- Security Tips -->
                <div class="row mt-3">
                    <div class="col-md-6 mx-auto">
                        <div class="card">
                            <div class="card-body text-center">
                                <h6><i class="fas fa-shield-alt text-success"></i> Password Security Tips</h6>
                                <div class="row text-start small">
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success"></i> Use 8+ characters</li>
                                            <li><i class="fas fa-check text-success"></i> Use mixed case</li>
                                            <li><i class="fas fa-check text-success"></i> Include numbers</li>
                                        </ul>
                                    </div>
                                    <div class="col-md-6">
                                        <ul class="list-unstyled">
                                            <li><i class="fas fa-check text-success"></i> Include special chars</li>
                                            <li><i class="fas fa-check text-success"></i> Don't reuse passwords</li>
                                            <li><i class="fas fa-check text-success"></i> Change regularly</li>
                                        </ul>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle password visibility
        function togglePassword(fieldId) {
            const field = document.getElementById(fieldId);
            const icon = field.nextElementSibling.querySelector('i');
            
            if (field.type === 'password') {
                field.type = 'text';
                icon.className = 'fas fa-eye-slash';
            } else {
                field.type = 'password';
                icon.className = 'fas fa-eye';
            }
        }
        
        // Check password strength
        function checkPasswordStrength(password) {
            const strengthBar = document.getElementById('passwordStrength');
            const strengthText = document.getElementById('passwordStrengthText');
            
            let strength = 0;
            let level = '';
            let color = '';
            
            if (password.length >= 6) strength++;
            if (password.length >= 10) strength++;
            if (/[a-z]/.test(password) && /[A-Z]/.test(password)) strength++;
            if (/\d/.test(password)) strength++;
            if (/[^a-zA-Z0-9]/.test(password)) strength++;
            
            if (strength <= 1) {
                level = 'Weak';
                color = '#dc3545';
                strengthBar.className = 'password-strength weak';
            } else if (strength <= 2) {
                level = 'Medium';
                color = '#ffc107';
                strengthBar.className = 'password-strength medium';
            } else if (strength <= 3) {
                level = 'Strong';
                color = '#28a745';
                strengthBar.className = 'password-strength strong';
            } else {
                level = 'Very Strong';
                color = '#28a745';
                strengthBar.className = 'password-strength very-strong';
            }
            
            strengthText.innerHTML = `<span style="color: ${color}"><strong>${level}</strong> password</span>`;
            
            if (password.length === 0) {
                strengthBar.className = 'password-strength';
                strengthText.innerHTML = '';
            }
        }
        
        // Check password match
        document.getElementById('confirm_password').addEventListener('keyup', function() {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = this.value;
            const matchDiv = document.getElementById('passwordMatch');
            
            if (confirmPass.length > 0) {
                if (newPass === confirmPass) {
                    matchDiv.innerHTML = '<span style="color: #28a745;"><i class="fas fa-check-circle"></i> Passwords match</span>';
                } else {
                    matchDiv.innerHTML = '<span style="color: #dc3545;"><i class="fas fa-times-circle"></i> Passwords do not match</span>';
                }
            } else {
                matchDiv.innerHTML = '';
            }
        });
        
        // Prevent form submission if passwords don't match
        document.getElementById('passwordForm').addEventListener('submit', function(e) {
            const newPass = document.getElementById('new_password').value;
            const confirmPass = document.getElementById('confirm_password').value;
            
            if (newPass !== confirmPass) {
                e.preventDefault();
                alert('Passwords do not match!');
                return false;
            }
            
            const submitBtn = document.getElementById('submitBtn');
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Changing Password...';
        });
        
        // Auto-close alerts after 5 seconds
        setTimeout(function() {
            document.querySelectorAll('.alert').forEach(function(alert) {
                const bsAlert = new bootstrap.Alert(alert);
                bsAlert.close();
            });
        }, 5000);
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>