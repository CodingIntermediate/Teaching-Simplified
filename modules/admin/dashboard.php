<?php
//session_start();
require_once '../../config.php';

// Check if user is logged in and is admin
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'admin') {
    header('Location: ' . SITE_URL . 'modules/auth/login.php');
    exit();
}

$db = db();
$user = Auth::getCurrentUser();

// Get system statistics
$stats = [
    'total_users' => $db->selectOne("SELECT COUNT(*) as count FROM users")['count'],
    'total_students' => $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'student'")['count'],
    'total_teachers' => $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'teacher'")['count'],
    'total_admins' => $db->selectOne("SELECT COUNT(*) as count FROM users WHERE role = 'admin'")['count'],
    'total_subjects' => $db->selectOne("SELECT COUNT(*) as count FROM subjects")['count'],
    'total_semesters' => $db->selectOne("SELECT COUNT(*) as count FROM semesters")['count'],
    'total_syllabi' => $db->selectOne("SELECT COUNT(*) as count FROM syllabus")['count'],
    'total_quizzes' => $db->selectOne("SELECT COUNT(*) as count FROM quiz_attempts WHERE completed_at IS NOT NULL")['count'],
    'total_resources' => $db->selectOne("SELECT COUNT(*) as count FROM tutor_resources")['count'],
    'total_enrollments' => $db->selectOne("SELECT COUNT(*) as count FROM enrollments")['count']
];

// Get recent users
$recent_users = $db->select("
    SELECT * FROM users 
    ORDER BY created_at DESC 
    LIMIT 5
");

// Get recent syllabus uploads
$recent_syllabi = $db->select("
    SELECT s.*, u.username, u.first_name, u.last_name, sub.code as subject_code, sub.name as subject_name
    FROM syllabus s
    JOIN users u ON s.student_id = u.id
    JOIN subjects sub ON s.subject_id = sub.id
    ORDER BY s.uploaded_at DESC
    LIMIT 5
");

// Get recent quiz attempts
$recent_quizzes = $db->select("
    SELECT qa.*, u.username, u.first_name, u.last_name, sub.code as subject_code
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    JOIN subjects sub ON qa.subject_id = sub.id
    WHERE qa.completed_at IS NOT NULL
    ORDER BY qa.completed_at DESC
    LIMIT 5
");

// Get recent resources
$recent_resources = $db->select("
    SELECT tr.*, u.username, u.first_name, u.last_name, sub.code as subject_code
    FROM tutor_resources tr
    JOIN users u ON tr.tutor_id = u.id
    JOIN subjects sub ON tr.subject_id = sub.id
    ORDER BY tr.uploaded_at DESC
    LIMIT 5
");

// Get subject-wise enrollment statistics
$subject_stats = $db->select("
    SELECT sub.code, sub.name, COUNT(DISTINCT e.student_id) as student_count,
           COUNT(DISTINCT sy.id) as syllabus_count,
           COUNT(DISTINCT qa.id) as quiz_count
    FROM subjects sub
    LEFT JOIN enrollments e ON sub.id = e.subject_id
    LEFT JOIN syllabus sy ON sub.id = sy.subject_id
    LEFT JOIN quiz_attempts qa ON sub.id = qa.subject_id
    GROUP BY sub.id
    ORDER BY student_count DESC
    LIMIT 5
");
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        body {
            background-color: #f8f9fa;
        }
        .stats-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .stats-icon {
            font-size: 2.5rem;
            opacity: 0.7;
        }
        .sidebar-nav .nav-link {
            color: #333;
            padding: 10px 15px;
            margin: 5px 0;
            border-radius: 5px;
            transition: all 0.3s;
        }
        .sidebar-nav .nav-link:hover {
            background: #e9ecef;
            color: #dc3545;
        }
        .sidebar-nav .nav-link.active {
            background: #dc3545;
            color: white;
        }
        .sidebar-nav .nav-link i {
            margin-right: 10px;
            width: 20px;
        }
        .activity-timeline {
            max-height: 400px;
            overflow-y: auto;
        }
        .timeline-item {
            border-left: 3px solid #dc3545;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .stat-value {
            font-size: 2rem;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Top Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger shadow-sm">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="fas fa-user-shield"></i> <?php echo SITE_NAME; ?> - Admin Panel
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="../auth/profile.php"><i class="fas fa-user"></i> My Profile</a></li>
                            <li><a class="dropdown-item" href="../auth/change_password.php"><i class="fas fa-key"></i> Change Password</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="../auth/logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 col-lg-2 d-md-block bg-light sidebar" style="min-height: 100vh; padding: 20px 0;">
                <div class="sidebar-nav">
                    <div class="text-center mb-4">
                        <i class="fas fa-university fa-3x text-danger"></i>
                        <h6 class="mt-2">Admin Portal</h6>
                    </div>
                    <hr>
                    <nav class="nav flex-column">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                        <a class="nav-link" href="manage_users.php">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                      
                        <a class="nav-link" href="manage_subjects.php">
                            <i class="fas fa-book"></i> Manage Subjects
                        </a>
                        <a class="nav-link" href="add_subject.php">
                            <i class="fas fa-plus-circle"></i> Add Subject
                        </a>
                        <a class="nav-link" href="manage_semesters.php">
                            <i class="fas fa-calendar-alt"></i> Manage Semesters
                        </a>
                        <hr>
                       
                        <a class="nav-link" href="analytics.php">
                            <i class="fas fa-chart-line"></i> Analytics
                        </a>
                        
                    </nav>
                </div>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 col-lg-10 ms-sm-auto px-md-4 py-4">
                <!-- Welcome Banner -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="card bg-gradient-danger text-white" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                            <div class="card-body">
                                <div class="row align-items-center">
                                    <div class="col-md-8">
                                        <h2 class="mb-2">Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
                                        <p class="mb-0">Here's what's happening with your platform today.</p>
                                    </div>
                                    <div class="col-md-4 text-end">
                                        <i class="fas fa-chart-line fa-3x opacity-50"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card bg-primary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Users</h6>
                                        <div class="stat-value"><?php echo $stats['total_users']; ?></div>
                                        <small>
                                            <i class="fas fa-user-graduate"></i> <?php echo $stats['total_students']; ?> Students |
                                            <i class="fas fa-chalkboard-teacher"></i> <?php echo $stats['total_teachers']; ?> Teachers
                                        </small>
                                    </div>
                                    <i class="fas fa-users stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card bg-success text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Subjects</h6>
                                        <div class="stat-value"><?php echo $stats['total_subjects']; ?></div>
                                        <small><i class="fas fa-calendar"></i> <?php echo $stats['total_semesters']; ?> Semesters</small>
                                    </div>
                                    <i class="fas fa-book stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card bg-info text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Syllabi Uploaded</h6>
                                        <div class="stat-value"><?php echo $stats['total_syllabi']; ?></div>
                                        <small><i class="fas fa-file-pdf"></i> Processed documents</small>
                                    </div>
                                    <i class="fas fa-file-alt stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card stats-card bg-warning text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Quiz Attempts</h6>
                                        <div class="stat-value"><?php echo $stats['total_quizzes']; ?></div>
                                        <small><i class="fas fa-download"></i> <?php echo $stats['total_resources']; ?> Resources</small>
                                    </div>
                                    <i class="fas fa-tasks stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Second Row Stats -->
                <div class="row mb-4">
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card bg-secondary text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Total Enrollments</h6>
                                        <div class="stat-value"><?php echo $stats['total_enrollments']; ?></div>
                                        <small>Course registrations</small>
                                    </div>
                                    <i class="fas fa-user-check stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card bg-dark text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Teachers</h6>
                                        <div class="stat-value"><?php echo $stats['total_teachers']; ?></div>
                                        <small>Active faculty members</small>
                                    </div>
                                    <i class="fas fa-chalkboard stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4 mb-3">
                        <div class="card stats-card bg-danger text-white h-100">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-center">
                                    <div>
                                        <h6 class="card-title">Active Students</h6>
                                        <div class="stat-value"><?php echo $stats['total_students']; ?></div>
                                        <small>Currently enrolled</small>
                                    </div>
                                    <i class="fas fa-user-friends stats-icon"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity & Charts Row -->
                <div class="row">
                    <!-- Recent Users -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-user-plus text-primary"></i> Recent Registrations</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_users)): ?>
                                    <p class="text-muted text-center">No users found.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>User</th>
                                                    <th>Email</th>
                                                    <th>Role</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_users as $u): ?>
                                                <tr>
                                                    <td>
                                                        <strong><?php echo htmlspecialchars($u['username']); ?></strong>
                                                        <br>
                                                        <small><?php echo htmlspecialchars($u['first_name'] . ' ' . $u['last_name']); ?></small>
                                                    </td>
                                                    <td><?php echo htmlspecialchars($u['email']); ?></td>
                                                    <td>
                                                        <span class="badge <?php echo $u['role'] == 'admin' ? 'bg-danger' : ($u['role'] == 'teacher' ? 'bg-success' : 'bg-primary'); ?>">
                                                            <?php echo ucfirst($u['role']); ?>
                                                        </span>
                                                    </td>
                                                    <td><?php echo date('M d, Y', strtotime($u['created_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                                <a href="manage_users.php" class="btn btn-sm btn-outline-primary">View All Users</a>
                            </div>
                        </div>
                    </div>

                    <!-- Subject Statistics Chart -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-chart-bar text-success"></i> Top Subjects by Enrollment</h5>
                            </div>
                            <div class="card-body">
                                <canvas id="subjectChart" height="250"></canvas>
                                <?php if (!empty($subject_stats)): ?>
                                    <div class="mt-3">
                                        <?php foreach ($subject_stats as $sub): ?>
                                        <div class="mb-2">
                                            <div class="d-flex justify-content-between">
                                                <span><?php echo htmlspecialchars($sub['code']); ?></span>
                                                <span class="text-primary"><?php echo $sub['student_count']; ?> students</span>
                                            </div>
                                            <div class="progress" style="height: 5px;">
                                                <div class="progress-bar bg-success" style="width: <?php echo ($sub['student_count'] / max(array_column($subject_stats, 'student_count'))) * 100; ?>%"></div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Syllabus Uploads -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-file-pdf text-danger"></i> Recent Syllabus Uploads</h5>
                            </div>
                            <div class="card-body activity-timeline">
                                <?php if (empty($recent_syllabi)): ?>
                                    <p class="text-muted text-center">No syllabus uploaded yet.</p>
                                <?php else: ?>
                                    <?php foreach ($recent_syllabi as $syllabus): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo htmlspecialchars($syllabus['first_name'] . ' ' . $syllabus['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($syllabus['subject_code']); ?> - <?php echo htmlspecialchars($syllabus['subject_name']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge <?php echo $syllabus['status'] == 'completed' ? 'bg-success' : 'bg-warning'; ?>">
                                                    <?php echo ucfirst($syllabus['status']); ?>
                                                </span>
                                                <br>
                                                <small><?php echo date('M d, H:i', strtotime($syllabus['uploaded_at'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Quiz Attempts -->
                    <div class="col-md-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-tasks text-info"></i> Recent Quiz Attempts</h5>
                            </div>
                            <div class="card-body activity-timeline">
                                <?php if (empty($recent_quizzes)): ?>
                                    <p class="text-muted text-center">No quiz attempts yet.</p>
                                <?php else: ?>
                                    <?php foreach ($recent_quizzes as $quiz): ?>
                                    <div class="timeline-item">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <strong><?php echo htmlspecialchars($quiz['first_name'] . ' ' . $quiz['last_name']); ?></strong>
                                                <br>
                                                <small class="text-muted"><?php echo htmlspecialchars($quiz['subject_code']); ?></small>
                                            </div>
                                            <div class="text-end">
                                                <span class="badge <?php echo $quiz['score'] >= 70 ? 'bg-success' : ($quiz['score'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                                    <?php echo round($quiz['score'], 1); ?>%
                                                </span>
                                                <br>
                                                <small><?php echo date('M d, H:i', strtotime($quiz['completed_at'])); ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Recent Resources -->
                    <div class="col-md-12 mb-4">
                        <div class="card">
                            <div class="card-header bg-white">
                                <h5 class="mb-0"><i class="fas fa-folder-open text-warning"></i> Recently Added Resources</h5>
                            </div>
                            <div class="card-body">
                                <?php if (empty($recent_resources)): ?>
                                    <p class="text-muted text-center">No resources uploaded yet.</p>
                                <?php else: ?>
                                    <div class="table-responsive">
                                        <table class="table table-hover">
                                            <thead>
                                                <tr>
                                                    <th>Title</th>
                                                    <th>Teacher</th>
                                                    <th>Subject</th>
                                                    <th>Type</th>
                                                    <th>Downloads</th>
                                                    <th>Date</th>
                                                </tr>
                                            </thead>
                                            <tbody>
                                                <?php foreach ($recent_resources as $resource): ?>
                                                <tr>
                                                    <td><?php echo htmlspecialchars($resource['title']); ?></td>
                                                    <td><?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?></td>
                                                    <td><?php echo htmlspecialchars($resource['subject_code']); ?></td>
                                                    <td>
                                                        <span class="badge bg-secondary"><?php echo ucfirst($resource['resource_type']); ?></span>
                                                    </td>
                                                    <td><?php echo $resource['downloads']; ?></td>
                                                    <td><?php echo date('M d, Y', strtotime($resource['uploaded_at'])); ?></td>
                                                </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Subject Chart
        const ctx = document.getElementById('subjectChart').getContext('2d');
        const subjectNames = <?php echo json_encode(array_column($subject_stats, 'code')); ?>;
        const studentCounts = <?php echo json_encode(array_column($subject_stats, 'student_count')); ?>;
        
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: subjectNames,
                datasets: [{
                    label: 'Number of Students',
                    data: studentCounts,
                    backgroundColor: 'rgba(40, 167, 69, 0.5)',
                    borderColor: 'rgba(40, 167, 69, 1)',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        title: {
                            display: true,
                            text: 'Number of Students'
                        }
                    },
                    x: {
                        title: {
                            display: true,
                            text: 'Subjects'
                        }
                    }
                }
            }
        });
        
        // Auto-refresh every 30 seconds (optional)
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>
</body>
</html>