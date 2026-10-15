<?php
require_once '../../config.php';
Auth::requireRole('admin');

$db = db();

// Handle delete
if (isset($_GET['delete'])) {
    $db->delete('subjects', 'id = ?', [$_GET['delete']]);
    setFlash('success', 'Subject deleted successfully');
    redirect('modules/admin/manage_subjects.php');
}

// Get all subjects with details
$subjects = $db->select("
    SELECT s.*, sem.semester_number, sem.academic_year, 
           CONCAT(u.first_name, ' ', u.last_name) as tutor_name
    FROM subjects s
    LEFT JOIN semesters sem ON s.semester_id = sem.id
    LEFT JOIN users u ON s.tutor_id = u.id
    ORDER BY sem.academic_year DESC, sem.semester_number, s.code
");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Subjects - Admin Panel</title>
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
                <span class="nav-link">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>

               
                        
                        <a class="nav-link " href="manage_users.php">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                      
                        <a class="nav-link active" href="manage_subjects.php">
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
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <div class="card">
            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                <h5 class="mb-0"><i class="fas fa-book"></i> Manage Subjects</h5>
                <a href="add_subject.php" class="btn btn-light btn-sm">
                    <i class="fas fa-plus"></i> Add Subject
                </a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Code</th>
                                <th>Name</th>
                                <th>Credits</th>
                                <th>Semester</th>
                                <th>Tutor</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($subjects as $subject): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($subject['code']); ?></td>
                                <td><?php echo htmlspecialchars($subject['name']); ?></td>
                                <td><?php echo $subject['credits']; ?></td>
                                <td>
                                    <?php if ($subject['semester_number']): ?>
                                        Semester <?php echo $subject['semester_number']; ?> (<?php echo $subject['academic_year']; ?>)
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                 </td>
                                <td>
                                    <?php if ($subject['tutor_name']): ?>
                                        <?php echo htmlspecialchars($subject['tutor_name']); ?>
                                    <?php else: ?>
                                        <span class="text-muted">Not assigned</span>
                                    <?php endif; ?>
                                 </td>
                                <td><?php echo date('M d, Y', strtotime($subject['created_at'])); ?> </td>
                                <td>
                                    <a href="edit_subject.php?id=<?php echo $subject['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <a href="?delete=<?php echo $subject['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
                                        <i class="fas fa-trash"></i>
                                    </a>
                                 </td>
                             </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>