<?php
require_once '../../config.php';
Auth::requireRole('teacher');

$db = db();
$user = Auth::getCurrentUser();

// Get subjects taught by this teacher
$subjects = getTeacherSubjects($user['id']);

// Get statistics
$total_students = 0;
$total_resources = 0;
$total_quizzes = 0;

foreach ($subjects as $subject) {
    $stats = $db->selectOne("
        SELECT 
            (SELECT COUNT(*) FROM enrollments WHERE subject_id = ?) as students,
            (SELECT COUNT(*) FROM tutor_resources WHERE subject_id = ?) as resources,
            (SELECT COUNT(*) FROM quiz_attempts WHERE subject_id = ? AND completed_at IS NOT NULL) as quizzes
    ", [$subject['id'], $subject['id'], $subject['id']]);
    
    $total_students += $stats['students'];
    $total_resources += $stats['resources'];
    $total_quizzes += $stats['quizzes'];
}

// Get recent resources
$recent_resources = $db->select("
    SELECT * FROM tutor_resources 
    WHERE tutor_id = ? 
    ORDER BY uploaded_at DESC 
    LIMIT 5
", [$user['id']]);

// Get recent quiz attempts
$recent_quizzes = $db->select("
    SELECT qa.*, u.first_name, u.last_name, s.name as subject_name, s.code as subject_code
    FROM quiz_attempts qa
    JOIN users u ON qa.student_id = u.id
    JOIN subjects s ON qa.subject_id = s.id
    WHERE s.tutor_id = ? AND qa.completed_at IS NOT NULL
    ORDER BY qa.completed_at DESC
    LIMIT 10
", [$user['id']]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Teacher Dashboard - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .stats-card {
            transition: all 0.3s;
            cursor: pointer;
            border: none;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .subject-card {
            margin-bottom: 20px;
            transition: all 0.3s;
        }
        .subject-card:hover {
            transform: translateX(5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teacher Portal
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link active" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_resource.php">
                            <i class="fas fa-upload"></i> Upload Resource
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="manage_resources.php">
                            <i class="fas fa-folder-open"></i> My Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../auth/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <!-- Welcome Section -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card bg-gradient-success text-white" style="background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);">
                    <div class="card-body">
                        <h3>Welcome, Professor <?php echo htmlspecialchars($user['first_name'] . ' ' . $user['last_name']); ?>!</h3>
                        <p>Manage your subjects, upload learning resources, and track student progress.</p>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Stats Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Subjects</h6>
                                <h2 class="mb-0"><?php echo count($subjects); ?></h2>
                            </div>
                            <i class="fas fa-book fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Students</h6>
                                <h2 class="mb-0"><?php echo $total_students; ?></h2>
                            </div>
                            <i class="fas fa-users fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Resources</h6>
                                <h2 class="mb-0"><?php echo $total_resources; ?></h2>
                            </div>
                            <i class="fas fa-folder-open fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-center">
                            <div>
                                <h6 class="card-title">Quizzes Taken</h6>
                                <h2 class="mb-0"><?php echo $total_quizzes; ?></h2>
                            </div>
                            <i class="fas fa-tasks fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- My Subjects -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-chalkboard"></i> My Subjects</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($subjects)): ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> You are not assigned to any subjects yet.
                            </div>
                        <?php else: ?>
                            <?php foreach ($subjects as $subject): ?>
                            <div class="card subject-card">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1">
                                                <?php echo htmlspecialchars($subject['code']); ?> - <?php echo htmlspecialchars($subject['name']); ?>
                                            </h5>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-calendar"></i> Semester: <?php echo $subject['academic_year']; ?> - Semester <?php echo $subject['semester_number']; ?>
                                                <br>
                                                <i class="fas fa-star"></i> Credits: <?php echo $subject['credits']; ?>
                                            </p>
                                        </div>
                                        <div class="btn-group-vertical">
                                            <a href="upload_resource.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-primary mb-1">
                                                <i class="fas fa-upload"></i> Upload
                                            </a>
                                            <a href="manage_resources.php?subject_id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-info">
                                                <i class="fas fa-folder-open"></i> Resources
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Recent Activity -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-history"></i> Recent Quiz Attempts</h5>
                    </div>
                    <div class="card-body" style="max-height: 500px; overflow-y: auto;">
                        <?php if (empty($recent_quizzes)): ?>
                            <p class="text-muted">No quiz attempts yet.</p>
                        <?php else: ?>
                            <?php foreach ($recent_quizzes as $quiz): ?>
                            <div class="border-bottom pb-2 mb-2">
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
                                        <small class="text-muted"><?php echo date('M d, H:i', strtotime($quiz['completed_at'])); ?></small>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Recent Resources -->
                <div class="card mt-3">
                    <div class="card-header bg-info text-white">
                        <h5><i class="fas fa-file-alt"></i> Recently Uploaded Resources</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_resources)): ?>
                            <p class="text-muted">No resources uploaded yet.</p>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($recent_resources as $resource): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between">
                                        <div>
                                            <i class="fas fa-file-<?php echo $resource['resource_type'] == 'video' ? 'video' : 'pdf'; ?>"></i>
                                            <?php echo htmlspecialchars($resource['title']); ?>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d, Y', strtotime($resource['uploaded_at'])); ?></small>
                                        </div>
                                        <span class="badge bg-secondary"><?php echo $resource['downloads']; ?> downloads</span>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>