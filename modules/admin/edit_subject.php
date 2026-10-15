<?php
require_once '../../config.php';
Auth::requireRole('admin');

$db = db();
$subject_id = $_GET['id'] ?? 0;

$subject = $db->selectOne("SELECT * FROM subjects WHERE id = ?", [$subject_id]);

if (!$subject) {
    setFlash('error', 'Subject not found');
    redirect('modules/admin/manage_subjects.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $data = [
        'code' => $_POST['code'],
        'name' => $_POST['name'],
        'description' => $_POST['description'],
        'credits' => $_POST['credits'],
        'semester_id' => $_POST['semester_id'] ?: null,
        'tutor_id' => $_POST['tutor_id'] ?: null
    ];
    
    $db->update('subjects', $data, 'id = ?', [$subject_id]);
    setFlash('success', 'Subject updated successfully');
    redirect('modules/admin/manage_subjects.php');
}

// Get semesters and teachers for dropdowns
$semesters = $db->select("SELECT * FROM semesters ORDER BY academic_year DESC, semester_number");
$teachers = $db->select("SELECT id, first_name, last_name, username FROM users WHERE role = 'teacher' AND is_active = 1");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Subject - Admin Panel</title>
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
                <a class="nav-link" href="manage_subjects.php">Manage Subjects</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-edit"></i> Edit Subject: <?php echo htmlspecialchars($subject['code']); ?></h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="code" class="form-label">Subject Code *</label>
                                <input type="text" class="form-control" name="code" value="<?php echo htmlspecialchars($subject['code']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Subject Name *</label>
                                <input type="text" class="form-control" name="name" value="<?php echo htmlspecialchars($subject['name']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($subject['description']); ?></textarea>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="credits" class="form-label">Credits *</label>
                                    <input type="number" class="form-control" name="credits" value="<?php echo $subject['credits']; ?>" required>
                                </div>
                                
                                <div class="col-md-6 mb-3">
                                    <label for="semester_id" class="form-label">Semester</label>
                                    <select name="semester_id" class="form-select">
                                        <option value="">Select Semester</option>
                                        <?php foreach ($semesters as $semester): ?>
                                        <option value="<?php echo $semester['id']; ?>" <?php echo $subject['semester_id'] == $semester['id'] ? 'selected' : ''; ?>>
                                            Semester <?php echo $semester['semester_number']; ?> (<?php echo $semester['academic_year']; ?>)
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tutor_id" class="form-label">Assigned Teacher</label>
                                <select name="tutor_id" class="form-select">
                                    <option value="">Select Teacher</option>
                                    <?php foreach ($teachers as $teacher): ?>
                                    <option value="<?php echo $teacher['id']; ?>" <?php echo $subject['tutor_id'] == $teacher['id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($teacher['first_name'] . ' ' . $teacher['last_name'] . ' (' . $teacher['username'] . ')'); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn btn-danger">Update Subject</button>
                            <a href="manage_subjects.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>