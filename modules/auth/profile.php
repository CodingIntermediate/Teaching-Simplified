<?php
require_once '../../config.php';
Auth::requireLogin();

$db = db();
$user = Auth::getCurrentUser();

// Helper function to safely escape values
function safeHtml($value) {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $first_name = trim($_POST['first_name'] ?? '');
    $last_name = trim($_POST['last_name'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    
    // Validate
    $errors = [];
    if (empty($first_name)) $errors[] = 'First name is required';
    if (empty($last_name)) $errors[] = 'Last name is required';
    if (empty($email)) $errors[] = 'Email is required';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Invalid email format';
    
    if (!empty($errors)) {
        setFlash('error', implode('<br>', $errors));
        header('Location: profile.php');
        exit();
    }
    
    $update_data = [
        'first_name' => $first_name,
        'last_name' => $last_name,
        'phone' => $phone,
        'email' => $email
    ];
    
    // Handle profile picture upload
    if (isset($_FILES['profile_pic']) && $_FILES['profile_pic']['error'] == 0) {
        $upload = uploadFile($_FILES['profile_pic'], UPLOAD_DIR . 'profiles/', ['jpg', 'jpeg', 'png']);
        if ($upload['success']) {
            // Delete old profile pic if exists
            if (!empty($user['profile_pic']) && file_exists(UPLOAD_DIR . 'profiles/' . $user['profile_pic'])) {
                unlink(UPLOAD_DIR . 'profiles/' . $user['profile_pic']);
            }
            $update_data['profile_pic'] = $upload['filename'];
        } else {
            setFlash('error', $upload['message']);
            header('Location: profile.php');
            exit();
        }
    }
    
    // Handle password change
    if (!empty($_POST['new_password'])) {
        if (empty($_POST['current_password'])) {
            setFlash('error', 'Please enter your current password');
            header('Location: profile.php');
            exit();
        }
        
        if (password_verify($_POST['current_password'], $user['password'])) {
            if (strlen($_POST['new_password']) < 6) {
                setFlash('error', 'New password must be at least 6 characters');
                header('Location: profile.php');
                exit();
            }
            if ($_POST['new_password'] !== $_POST['confirm_password']) {
                setFlash('error', 'Passwords do not match');
                header('Location: profile.php');
                exit();
            }
            $update_data['password'] = password_hash($_POST['new_password'], PASSWORD_DEFAULT);
            setFlash('success', 'Profile and password updated successfully!');
        } else {
            setFlash('error', 'Current password is incorrect');
            header('Location: profile.php');
            exit();
        }
    }
    
    $db->update('users', $update_data, 'id = ?', [$user['id']]);
    setFlash('success', 'Profile updated successfully!');
    header('Location: profile.php');
    exit();
}

// Refresh user data after update
$user = Auth::getCurrentUser();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .profile-card {
            border: none;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.08);
            transition: transform 0.3s;
        }
        .profile-card:hover {
            transform: translateY(-5px);
        }
        .profile-avatar {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            object-fit: cover;
            border: 4px solid #fff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .profile-avatar-placeholder {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto;
            border: 4px solid #fff;
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .role-badge {
            padding: 6px 20px;
            border-radius: 50px;
            font-weight: 500;
        }
        .role-badge.admin { background: #dc3545; color: white; }
        .role-badge.teacher { background: #28a745; color: white; }
        .role-badge.student { background: #007bff; color: white; }
        .stat-box {
            background: #f8f9fa;
            padding: 10px 15px;
            border-radius: 10px;
            margin: 5px 0;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?>
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="fas fa-user"></i> <?php echo safeHtml($_SESSION['full_name'] ?? ''); ?>
                </span>
                <a class="nav-link" href="<?php echo SITE_URL; ?>modules/dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Dashboard
                </a>
                <a class="nav-link" href="change_password.php">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a class="nav-link" href="../auth/logout.php">
                    <i class="fas fa-sign-out-alt"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <div class="row">
            <!-- Profile Card -->
            <div class="col-md-4">
                <div class="card profile-card">
                    <div class="card-body text-center">
                        <?php if (!empty($user['profile_pic'])): ?>
                            <img src="<?php echo SITE_URL . 'assets/uploads/profiles/' . safeHtml($user['profile_pic']); ?>" 
                                 class="profile-avatar mb-3" alt="Profile Picture">
                        <?php else: ?>
                            <div class="profile-avatar-placeholder mb-3">
                                <i class="fas fa-user fa-4x text-white"></i>
                            </div>
                        <?php endif; ?>
                        
                        <h5 class="mt-2"><?php echo safeHtml($user['first_name'] . ' ' . $user['last_name']); ?></h5>
                        <p class="text-muted">@<?php echo safeHtml($user['username']); ?></p>
                        
                        <span class="role-badge <?php echo safeHtml($user['role']); ?>">
                            <?php echo ucfirst(safeHtml($user['role'])); ?>
                        </span>
                        
                        <hr>
                        
                        <div class="text-start">
                            <div class="stat-box">
                                <i class="fas fa-envelope text-primary"></i>
                                <strong>Email:</strong> <?php echo safeHtml($user['email']); ?>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-phone text-success"></i>
                                <strong>Phone:</strong> <?php echo !empty($user['phone']) ? safeHtml($user['phone']) : '<span class="text-muted">Not provided</span>'; ?>
                            </div>
                            <div class="stat-box">
                                <i class="fas fa-calendar text-info"></i>
                                <strong>Joined:</strong> <?php echo date('F d, Y', strtotime($user['created_at'] ?? 'now')); ?>
                            </div>
                            <?php if (!empty($user['last_login'])): ?>
                            <div class="stat-box">
                                <i class="fas fa-clock text-warning"></i>
                                <strong>Last Login:</strong> <?php echo date('F d, Y h:i A', strtotime($user['last_login'])); ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Edit Profile Form -->
            <div class="col-md-8">
                <div class="card profile-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-edit"></i> Edit Profile</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="first_name" class="form-label fw-bold">First Name *</label>
                                    <input type="text" class="form-control" id="first_name" name="first_name" 
                                           value="<?php echo safeHtml($user['first_name']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="last_name" class="form-label fw-bold">Last Name *</label>
                                    <input type="text" class="form-control" id="last_name" name="last_name" 
                                           value="<?php echo safeHtml($user['last_name']); ?>" required>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="email" class="form-label fw-bold">Email *</label>
                                <input type="email" class="form-control" id="email" name="email" 
                                       value="<?php echo safeHtml($user['email']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="phone" class="form-label fw-bold">Phone</label>
                                <input type="text" class="form-control" id="phone" name="phone" 
                                       value="<?php echo safeHtml($user['phone']); ?>" placeholder="Enter your phone number">
                            </div>
                            
                            <div class="mb-3">
                                <label for="profile_pic" class="form-label fw-bold">Profile Picture</label>
                                <input type="file" class="form-control" id="profile_pic" name="profile_pic" accept="image/*">
                                <small class="text-muted">Upload a new profile picture (JPG, PNG - Max 2MB)</small>
                            </div>
                            
                            <hr>
                            
                            <h6><i class="fas fa-key text-warning"></i> Change Password</h6>
                            <p class="text-muted small">Leave blank to keep current password</p>
                            
                            <div class="mb-3">
                                <label for="current_password" class="form-label">Current Password</label>
                                <input type="password" class="form-control" id="current_password" name="current_password" 
                                       placeholder="Enter your current password">
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="new_password" class="form-label">New Password</label>
                                    <input type="password" class="form-control" id="new_password" name="new_password" 
                                           placeholder="Enter new password (min 6 chars)">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                                           placeholder="Re-enter new password">
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2">
                                <button type="submit" class="btn btn-primary btn-lg">
                                    <i class="fas fa-save"></i> Update Profile
                                </button>
                                <a href="../dashboard.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Back to Dashboard
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Confirm before leaving page with unsaved changes
        let formChanged = false;
        document.querySelector('form').addEventListener('change', function() {
            formChanged = true;
        });
        
        window.addEventListener('beforeunload', function(e) {
            if (formChanged) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
            }
        });
        
        document.querySelector('form').addEventListener('submit', function() {
            formChanged = false;
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>