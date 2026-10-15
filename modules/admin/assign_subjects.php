<?php
require_once '../../config.php';
Auth::requireRole('admin');

$db = db();
$student_id = $_GET['id'] ?? 0;

$student = $db->selectOne("SELECT * FROM users WHERE id = ? AND role = 'student'", [$student_id]);

if (!$student) {
    setFlash('error', 'Student not found');
    redirect('modules/admin/manage_users.php');
}

// Get all subjects
$subjects = $db->select("
    SELECT s.*, sem.semester_number, sem.academic_year 
    FROM subjects s
    LEFT JOIN semesters sem ON s.semester_id = sem.id
    ORDER BY sem.academic_year DESC, sem.semester_number
");

// Get currently enrolled subjects
$enrolled = $db->select("SELECT subject_id FROM enrollments WHERE student_id = ?", [$student_id]);
$enrolled_ids = array_column($enrolled, 'subject_id');

// Handle enrollment
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Remove all existing enrollments
    $db->delete('enrollments', 'student_id = ?', [$student_id]);
    
    // Add new enrollments
    if (isset($_POST['subjects'])) {
        foreach ($_POST['subjects'] as $subject_id) {
            $db->insert('enrollments', [
                'student_id' => $student_id,
                'subject_id' => $subject_id
            ]);
        }
    }
    
    setFlash('success', 'Subjects assigned successfully');
    redirect('modules/admin/manage_users.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Assign Subjects - Admin Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-danger">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-user-shield"></i> Admin Panel
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_users.php">Manage Users</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <div class="row">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-book"></i> Assign Subjects to: <?php echo htmlspecialchars($student['first_name'] . ' ' . $student['last_name']); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="row">
                                <?php foreach ($subjects as $subject): ?>
                                <div class="col-md-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="form-check">
                                                <input type="checkbox" class="form-check-input" 
                                                       name="subjects[]" value="<?php echo $subject['id']; ?>"
                                                       id="subject_<?php echo $subject['id']; ?>"
                                                       <?php echo in_array($subject['id'], $enrolled_ids) ? 'checked' : ''; ?>>
                                                <label class="form-check-label" for="subject_<?php echo $subject['id']; ?>">
                                                    <strong><?php echo htmlspecialchars($subject['code']); ?></strong><br>
                                                    <small><?php echo htmlspecialchars($subject['name']); ?></small><br>
                                                    <small class="text-muted">
                                                        Semester: <?php echo $subject['semester_number'] ? "Semester {$subject['semester_number']} ({$subject['academic_year']})" : 'Not assigned'; ?>
                                                    </small>
                                                </label>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-danger">Save Assignments</button>
                                <a href="manage_users.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>