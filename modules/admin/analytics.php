<?php
require_once '../../config.php';
Auth::requireRole('admin');

$db = db();

// Get comprehensive analytics
$analytics = [];

// User growth over time
$user_growth = $db->select("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as new_users,
        SUM(CASE WHEN role = 'student' THEN 1 ELSE 0 END) as new_students,
        SUM(CASE WHEN role = 'teacher' THEN 1 ELSE 0 END) as new_teachers
    FROM users
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month ASC
", []);

// Engagement metrics
$engagement = $db->selectOne("
    SELECT 
        (SELECT COUNT(DISTINCT student_id) FROM quiz_attempts WHERE completed_at IS NOT NULL) as active_students,
        (SELECT COUNT(*) FROM quiz_attempts WHERE completed_at IS NOT NULL) as total_quizzes,
        (SELECT COALESCE(AVG(score), 0) FROM quiz_attempts WHERE completed_at IS NOT NULL) as avg_score,
        (SELECT COUNT(*) FROM syllabus WHERE status = 'completed') as processed_syllabi,
        (SELECT COUNT(*) FROM tutor_resources) as total_resources,
        (SELECT COALESCE(SUM(downloads), 0) FROM tutor_resources) as total_downloads
", []);

// Subject popularity
$subject_popularity = $db->select("
    SELECT s.code, s.name, COUNT(DISTINCT e.student_id) as enrolled_students,
           COUNT(DISTINCT qa.id) as quiz_attempts,
           COALESCE(AVG(qa.score), 0) as avg_score
    FROM subjects s
    LEFT JOIN enrollments e ON s.id = e.subject_id
    LEFT JOIN quiz_attempts qa ON s.id = qa.subject_id AND qa.completed_at IS NOT NULL
    GROUP BY s.id
    ORDER BY enrolled_students DESC
    LIMIT 10
", []);

// Teacher performance - FIXED to handle null scores
$teacher_performance = $db->select("
    SELECT u.id, u.first_name, u.last_name, 
           COUNT(DISTINCT s.id) as subjects_taught,
           COUNT(DISTINCT tr.id) as resources_shared,
           COALESCE(AVG(qa.score), 0) as student_avg_score,
           COUNT(DISTINCT qa.id) as quiz_count
    FROM users u
    LEFT JOIN subjects s ON u.id = s.tutor_id
    LEFT JOIN tutor_resources tr ON u.id = tr.tutor_id
    LEFT JOIN quiz_attempts qa ON s.id = qa.subject_id AND qa.completed_at IS NOT NULL
    WHERE u.role = 'teacher'
    GROUP BY u.id
    ORDER BY student_avg_score DESC, quiz_count DESC
", []);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            transition: all 0.3s;
        }
        .stats-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }
        .teacher-item {
            transition: all 0.2s;
        }
        .teacher-item:hover {
            background-color: #f8f9fa;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-chart-pie"></i> Analytics Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link " href="manage_users.php">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                      
                        <a class="nav-link " href="manage_subjects.php">
                            <i class="fas fa-book"></i> Manage Subjects
                        </a>
                        <a class="nav-link" href="add_subject.php">
                            <i class="fas fa-plus-circle"></i> Add Subject
                        </a>
                        <a class="nav-link" href="manage_semesters.php">
                            <i class="fas fa-calendar-alt"></i> Manage Semesters
                        </a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Key Metrics -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <h6>Active Students</h6>
                        <h2><?php echo $engagement['active_students'] ?? 0; ?></h2>
                        <small>Have taken quizzes</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <h6>Total Quizzes</h6>
                        <h2><?php echo $engagement['total_quizzes'] ?? 0; ?></h2>
                        <small>Completed attempts</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <h6>Average Score</h6>
                        <h2><?php echo round($engagement['avg_score'] ?? 0, 1); ?>%</h2>
                        <small>Across all subjects</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-warning text-white">
                    <div class="card-body">
                        <h6>Resource Downloads</h6>
                        <h2><?php echo $engagement['total_downloads'] ?? 0; ?></h2>
                        <small>Total resource accesses</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- User Growth Chart -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header">
                        <h6>User Growth (Last 6 Months)</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="userGrowthChart" height="100"></canvas>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="row">
            <!-- Subject Popularity -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>Most Popular Subjects</h6>
                    </div>
                    <div class="card-body">
                        <canvas id="subjectChart" height="300"></canvas>
                    </div>
                </div>
            </div>
            
            <!-- Teacher Performance -->
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6>Teacher Performance Rankings</h6>
                    </div>
                    <div class="card-body" style="max-height: 400px; overflow-y: auto;">
                        <?php if (empty($teacher_performance)): ?>
                            <div class="alert alert-info">No teachers found.</div>
                        <?php else: ?>
                            <div class="list-group">
                                <?php foreach ($teacher_performance as $index => $teacher): 
                                    $avg_score = round($teacher['student_avg_score'] ?? 0, 1);
                                ?>
                                <div class="list-group-item teacher-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="badge bg-primary me-2">#<?php echo $index + 1; ?></span>
                                            <strong><?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name']); ?></strong>
                                            <br>
                                            <small class="text-muted">
                                                <i class="fas fa-book"></i> <?php echo $teacher['subjects_taught']; ?> subjects |
                                                <i class="fas fa-file"></i> <?php echo $teacher['resources_shared']; ?> resources
                                                <?php if ($teacher['quiz_count'] > 0): ?>
                                                    | <i class="fas fa-tasks"></i> <?php echo $teacher['quiz_count']; ?> quizzes
                                                <?php else: ?>
                                                    | <span class="text-muted">No quizzes yet</span>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div class="text-end">
                                            <?php if ($avg_score > 0): ?>
                                                <span class="badge <?php echo $avg_score >= 70 ? 'bg-success' : ($avg_score >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                                    Avg Score: <?php echo $avg_score; ?>%
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">No data</span>
                                            <?php endif; ?>
                                        </div>
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

    <script>
        // User Growth Chart
        const months = <?php echo json_encode(array_column($user_growth, 'month')); ?>;
        const students = <?php echo json_encode(array_column($user_growth, 'new_students')); ?>;
        const teachers = <?php echo json_encode(array_column($user_growth, 'new_teachers')); ?>;
        
        if (months.length > 0) {
            new Chart(document.getElementById('userGrowthChart'), {
                type: 'line',
                data: {
                    labels: months,
                    datasets: [
                        {
                            label: 'New Students',
                            data: students,
                            borderColor: 'rgb(54, 162, 235)',
                            backgroundColor: 'rgba(54, 162, 235, 0.2)',
                            tension: 0.1,
                            fill: true
                        },
                        {
                            label: 'New Teachers',
                            data: teachers,
                            borderColor: 'rgb(255, 99, 132)',
                            backgroundColor: 'rgba(255, 99, 132, 0.2)',
                            tension: 0.1,
                            fill: true
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            title: {
                                display: true,
                                text: 'Number of Users'
                            }
                        },
                        x: {
                            title: {
                                display: true,
                                text: 'Month'
                            }
                        }
                    }
                }
            });
        }
        
        // Subject Chart
        const subjects = <?php echo json_encode(array_column($subject_popularity, 'code')); ?>;
        const enrollments = <?php echo json_encode(array_column($subject_popularity, 'enrolled_students')); ?>;
        
        if (subjects.length > 0) {
            new Chart(document.getElementById('subjectChart'), {
                type: 'bar',
                data: {
                    labels: subjects,
                    datasets: [{
                        label: 'Enrolled Students',
                        data: enrollments,
                        backgroundColor: 'rgba(75, 192, 192, 0.7)',
                        borderColor: 'rgba(75, 192, 192, 1)',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'top'
                        }
                    },
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
                                text: 'Subject'
                            }
                        }
                    }
                }
            });
        }
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>