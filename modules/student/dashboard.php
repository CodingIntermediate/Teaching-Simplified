<?php
//session_start();
require_once '../../config.php';

// Check if user is logged in and is student
if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'student') {
    header('Location: ' . SITE_URL . 'modules/auth/login.php');
    exit();
}

$db = db();
$user = Auth::getCurrentUser();

// Get enrolled subjects with syllabus and quiz status
$subjects = $db->select("
    SELECT s.*, 
           sem.semester_number, 
           sem.academic_year,
           CONCAT(u.first_name, ' ', u.last_name) as teacher_name,
           (SELECT id FROM syllabus WHERE subject_id = s.id AND student_id = ? ORDER BY id DESC LIMIT 1) as syllabus_id,
           (SELECT status FROM syllabus WHERE subject_id = s.id AND student_id = ? ORDER BY id DESC LIMIT 1) as syllabus_status,
           (SELECT COUNT(*) FROM quiz_attempts WHERE subject_id = s.id AND student_id = ? AND completed_at IS NOT NULL) as quiz_taken,
           (SELECT score FROM quiz_attempts WHERE subject_id = s.id AND student_id = ? AND completed_at IS NOT NULL ORDER BY completed_at DESC LIMIT 1) as last_score,
           (SELECT COUNT(*) FROM tutor_resources WHERE subject_id = s.id) as resources_count
    FROM subjects s
    JOIN enrollments e ON s.id = e.subject_id
    JOIN semesters sem ON s.semester_id = sem.id
    LEFT JOIN users u ON s.tutor_id = u.id
    WHERE e.student_id = ? AND e.is_active = 1
    ORDER BY sem.academic_year DESC, sem.semester_number
", [$user['id'], $user['id'], $user['id'], $user['id'], $user['id']]);

// Get recent quiz scores (last 5 attempts)
$recent_quizzes = $db->select("
    SELECT qa.*, 
           s.name as subject_name, 
           s.code as subject_code,
           (SELECT COUNT(*) FROM mcq_answers WHERE attempt_id = qa.id) as mcq_count,
           (SELECT COUNT(*) FROM sa_answers WHERE attempt_id = qa.id) as sa_count
    FROM quiz_attempts qa
    JOIN subjects s ON qa.subject_id = s.id
    WHERE qa.student_id = ? AND qa.completed_at IS NOT NULL
    ORDER BY qa.completed_at DESC
    LIMIT 5
", [$user['id']]);

// Get best scores for each subject (to show improvement)
$best_scores = [];
foreach ($subjects as $subject) {
    if ($subject['syllabus_id']) {
        $best = $db->selectOne("SELECT MAX(score) as best_score FROM quiz_attempts WHERE subject_id = ? AND student_id = ? AND completed_at IS NOT NULL", 
                                [$subject['id'], $user['id']]);
        $best_scores[$subject['id']] = round($best['best_score'] ?? 0, 1);
    }
}

// Get statistics
$stats = [
    'total_subjects' => count($subjects),
    'completed_quizzes' => $db->selectOne("SELECT COUNT(*) as count FROM quiz_attempts WHERE student_id = ? AND completed_at IS NOT NULL", [$user['id']])['count'],
    'avg_score' => round($db->selectOne("SELECT AVG(score) as avg FROM quiz_attempts WHERE student_id = ? AND completed_at IS NOT NULL", [$user['id']])['avg'] ?? 0, 1),
    'syllabi_uploaded' => $db->selectOne("SELECT COUNT(*) as count FROM syllabus WHERE student_id = ?", [$user['id']])['count'],
    'total_resources' => $db->selectOne("SELECT COUNT(*) as count FROM tutor_resources tr JOIN enrollments e ON tr.subject_id = e.subject_id WHERE e.student_id = ?", [$user['id']])['count']
];

// Get recent syllabus uploads
$recent_syllabi = $db->select("
    SELECT sy.*, s.name as subject_name, s.code as subject_code
    FROM syllabus sy
    JOIN subjects s ON sy.subject_id = s.id
    WHERE sy.student_id = ?
    ORDER BY sy.uploaded_at DESC
    LIMIT 5
", [$user['id']]);

// Get announcements (if any)
$announcements = $db->select("
    SELECT * FROM announcements 
    WHERE target_role = 'student' OR target_role = 'all'
    ORDER BY created_at DESC 
    LIMIT 3
", []);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .stats-card {
            transition: all 0.3s ease;
            cursor: pointer;
            border: none;
            border-radius: 15px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .subject-card {
            transition: all 0.3s ease;
            margin-bottom: 20px;
            border-radius: 10px;
            border-left: 4px solid #007bff;
        }
        .subject-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .best-score {
            font-size: 12px;
            color: #28a745;
        }
        .attempt-badge {
            font-size: 11px;
            padding: 2px 6px;
        }
        .welcome-banner {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            color: white;
        }
        .quick-action-btn {
            padding: 15px;
            border-radius: 10px;
            transition: all 0.3s;
        }
        .quick-action-btn:hover {
            transform: translateY(-3px);
        }
        .activity-timeline {
            max-height: 400px;
            overflow-y: auto;
        }
        .timeline-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        .score-high {
            color: #28a745;
            font-weight: bold;
        }
        .score-medium {
            color: #ffc107;
            font-weight: bold;
        }
        .score-low {
            color: #dc3545;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm">
        <div class="container">
            <a class="navbar-brand" href="#">
                <i class="fas fa-graduation-cap"></i> Student Portal
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

    <div class="container mt-4">
        <!-- Welcome Banner -->
        <div class="welcome-banner">
            <div class="row align-items-center">
                <div class="col-md-8">
                    <h2>Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>! 👋</h2>
                    <p class="mb-0">Continue your learning journey. Upload syllabus, take quizzes, and track your progress.</p>
                </div>
                <div class="col-md-4 text-end">
                    <i class="fas fa-chalkboard-user fa-4x opacity-50"></i>
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
                                <h6 class="card-title">Enrolled Subjects</h6>
                                <h2 class="mb-0"><?php echo $stats['total_subjects']; ?></h2>
                            </div>
                            <i class="fas fa-book fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-success text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Quizzes Taken</h6>
                                <h2 class="mb-0"><?php echo $stats['completed_quizzes']; ?></h2>
                            </div>
                            <i class="fas fa-tasks fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-info text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Average Score</h6>
                                <h2 class="mb-0"><?php echo $stats['avg_score']; ?>%</h2>
                            </div>
                            <i class="fas fa-chart-line fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card stats-card bg-warning text-white h-100">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Resources Available</h6>
                                <h2 class="mb-0"><?php echo $stats['total_resources']; ?></h2>
                            </div>
                            <i class="fas fa-folder-open fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-bolt text-warning"></i> Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4 mb-2">
                                <a href="upload_syllabus.php" class="btn btn-outline-primary w-100 quick-action-btn">
                                    <i class="fas fa-upload fa-2x d-block mb-2"></i>
                                    Upload Syllabus
                                </a>
                            </div>
                            <div class="col-md-4 mb-2">
                                <a href="#" class="btn btn-outline-success w-100 quick-action-btn" data-bs-toggle="modal" data-bs-target="#subjectsModal">
                                    <i class="fas fa-book fa-2x d-block mb-2"></i>
                                    View Subjects
                                </a>
                            </div>
                            <div class="col-md-4 mb-2">
                                <a href="../auth/profile.php" class="btn btn-outline-info w-100 quick-action-btn">
                                    <i class="fas fa-user-edit fa-2x d-block mb-2"></i>
                                    Update Profile
                                </a>
                            </div>
                            <div class="col-md-3 mb-2">
    <a href="chatbot.php" class="btn btn-outline-info w-100 quick-action-btn">
        <i class="fas fa-robot fa-2x d-block mb-2"></i>
        AI Study Assistant
    </a>
</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- My Subjects Section -->
        <div class="row">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-book-open text-primary"></i> My Enrolled Subjects</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($subjects)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You are not enrolled in any subjects yet. Please contact your administrator.
                            </div>
                        <?php else: ?>
                            <?php foreach ($subjects as $subject): ?>
                            <div class="card subject-card position-relative">
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-8">
                                            <h5 class="card-title">
                                                <?php echo htmlspecialchars($subject['code']); ?> - <?php echo htmlspecialchars($subject['name']); ?>
                                            </h5>
                                            <p class="card-text text-muted small">
                                                <i class="fas fa-chalkboard-teacher"></i> Teacher: <?php echo htmlspecialchars($subject['teacher_name'] ?? 'Not assigned'); ?><br>
                                                <i class="fas fa-calendar"></i> Semester: <?php echo $subject['academic_year']; ?> - Semester <?php echo $subject['semester_number']; ?><br>
                                                <i class="fas fa-star"></i> Credits: <?php echo $subject['credits']; ?>
                                            </p>
                                            <div class="mt-2">
                                                <?php if ($subject['resources_count'] > 0): ?>
                                                <span class="badge bg-info me-1">
                                                    <i class="fas fa-folder"></i> <?php echo $subject['resources_count']; ?> Resources
                                                </span>
                                                <?php endif; ?>
                                                <?php if ($subject['quiz_taken'] > 0): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-check-circle"></i> <?php echo $subject['quiz_taken']; ?> Attempt(s)
                                                </span>
                                                <?php endif; ?>
                                                <?php if (isset($best_scores[$subject['id']]) && $best_scores[$subject['id']] > 0): ?>
                                                <span class="badge bg-info">
                                                    <i class="fas fa-trophy"></i> Best: <?php echo $best_scores[$subject['id']]; ?>%
                                                </span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <div class="col-md-4 text-end">
                                            <?php if (!$subject['syllabus_id'] || $subject['syllabus_status'] != 'completed'): ?>
                                                <a href="upload_syllabus.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-primary btn-sm w-100 mb-2">
                                                    <i class="fas fa-upload"></i> Upload Syllabus
                                                </a>
                                            <?php else: ?>
                                                <!-- ALWAYS show Take Quiz button - multiple attempts allowed -->
                                                <a href="take_quiz.php?syllabus_id=<?php echo $subject['syllabus_id']; ?>" class="btn btn-success btn-sm w-100 mb-2">
                                                    <i class="fas fa-play"></i> Take Quiz 
                                                    <?php if ($subject['quiz_taken'] > 0): ?>
                                                        (Attempt #<?php echo $subject['quiz_taken'] + 1; ?>)
                                                    <?php endif; ?>
                                                </a>
                                                <a href="view_results.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-info btn-sm w-100 mb-2">
                                                    <i class="fas fa-chart-bar"></i> View All Results
                                                </a>
                                                <a href="view_resources.php?syllabus_id=<?php echo $subject['syllabus_id']; ?>" class="btn btn-warning btn-sm w-100">
                                                    <i class="fas fa-book"></i> Resources
                                                </a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <?php if ($subject['last_score']): ?>
                                    <div class="mt-2 pt-2 border-top">
                                        <small>Last Score: 
                                            <strong class="<?php echo $subject['last_score'] >= 70 ? 'score-high' : ($subject['last_score'] >= 50 ? 'score-medium' : 'score-low'); ?>">
                                                <?php echo round($subject['last_score'], 1); ?>%
                                            </strong>
                                        </small>
                                        <?php if (isset($best_scores[$subject['id']]) && $best_scores[$subject['id']] > $subject['last_score']): ?>
                                        <small class="best-score ms-2">
                                            <i class="fas fa-arrow-up"></i> Best: <?php echo $best_scores[$subject['id']]; ?>%
                                        </small>
                                        <?php endif; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Sidebar -->
            <div class="col-md-4">
                <!-- Recent Quiz Results -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-history text-info"></i> Recent Quiz Results</h5>
                    </div>
                    <div class="card-body activity-timeline">
                        <?php if (empty($recent_quizzes)): ?>
                            <p class="text-muted text-center">No quizzes taken yet.</p>
                        <?php else: ?>
                            <?php foreach ($recent_quizzes as $quiz): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between align-items-start">
                                    <div>
                                        <strong><?php echo htmlspecialchars($quiz['subject_code']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y h:i A', strtotime($quiz['completed_at'])); ?></small>
                                    </div>
                                    <div class="text-end">
                                        <span class="badge <?php echo $quiz['score'] >= 70 ? 'bg-success' : ($quiz['score'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo round($quiz['score'], 1); ?>%
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Recent Syllabus Uploads -->
                <div class="card mb-4">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-file-pdf text-danger"></i> Recent Uploads</h5>
                    </div>
                    <div class="card-body activity-timeline">
                        <?php if (empty($recent_syllabi)): ?>
                            <p class="text-muted text-center">No syllabus uploaded yet.</p>
                        <?php else: ?>
                            <?php foreach ($recent_syllabi as $syllabus): ?>
                            <div class="timeline-item">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <strong><?php echo htmlspecialchars($syllabus['subject_code']); ?></strong>
                                        <br>
                                        <small class="text-muted"><?php echo date('M d, Y', strtotime($syllabus['uploaded_at'])); ?></small>
                                    </div>
                                    <div>
                                        <span class="badge <?php echo $syllabus['status'] == 'completed' ? 'bg-success' : 'bg-warning'; ?>">
                                            <?php echo ucfirst($syllabus['status']); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Tips Section -->
                <div class="card">
                    <div class="card-header bg-white">
                        <h5 class="mb-0"><i class="fas fa-lightbulb text-warning"></i> Study Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled">
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Upload syllabus to generate quizzes</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Take quizzes multiple times to improve</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Track your best score and progress</li>
                            <li class="mb-2"><i class="fas fa-check-circle text-success"></i> Access recommended resources for each topic</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Subjects Modal -->
    <div class="modal fade" id="subjectsModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="fas fa-book"></i> My Enrolled Subjects</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Code</th>
                                    <th>Subject Name</th>
                                    <th>Teacher</th>
                                    <th>Semester</th>
                                    <th>Attempts</th>
                                    <th>Best Score</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($subjects as $subject): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                    <td><?php echo htmlspecialchars($subject['teacher_name'] ?? 'Not assigned'); ?></td>
                                    <td>Semester <?php echo $subject['semester_number']; ?><br><small><?php echo $subject['academic_year']; ?></small></td>
                                    <td><?php echo $subject['quiz_taken']; ?> time(s)</td>
                                    <td>
                                        <?php if (isset($best_scores[$subject['id']]) && $best_scores[$subject['id']] > 0): ?>
                                            <span class="badge bg-success"><?php echo $best_scores[$subject['id']]; ?>%</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Not taken</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <!-- Add this to the Quick Actions row -->

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script>
        // Auto-refresh dashboard every 60 seconds (optional)
        setTimeout(function() {
            location.reload();
        }, 60000);
        
        // Tooltip initialization
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });
    </script>
</body>
</html>