<?php
require_once '../../config.php';
Auth::requireRole('admin');

$db = db();

// Handle actions
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_semester'])) {
    $db->insert('semesters', [
        'semester_number' => $_POST['semester_number'],
        'academic_year' => $_POST['academic_year'],
        'start_date' => $_POST['start_date'],
        'end_date' => $_POST['end_date'],
        'is_active' => isset($_POST['is_active']) ? 1 : 0
    ]);
    setFlash('success', 'Semester added successfully');
    redirect('modules/admin/manage_semesters.php');
}

if (isset($_GET['delete'])) {
    $db->delete('semesters', 'id = ?', [$_GET['delete']]);
    setFlash('success', 'Semester deleted successfully');
    redirect('modules/admin/manage_semesters.php');
}

if (isset($_GET['toggle'])) {
    $semester = $db->selectOne("SELECT is_active FROM semesters WHERE id = ?", [$_GET['toggle']]);
    $db->update('semesters', ['is_active' => !$semester['is_active']], 'id = ?', [$_GET['toggle']]);
    setFlash('success', 'Semester status updated');
    redirect('modules/admin/manage_semesters.php');
}

$semesters = $db->select("SELECT * FROM semesters ORDER BY academic_year DESC, semester_number");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Semesters - Admin Panel</title>
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
                <a class="nav-link " href="manage_users.php">
                            <i class="fas fa-users"></i> Manage Users
                        </a>
                      
                        <a class="nav-link " href="manage_subjects.php">
                            <i class="fas fa-book"></i> Manage Subjects
                        </a>
                        <a class="nav-link" href="add_subject.php">
                            <i class="fas fa-plus-circle"></i> Add Subject
                        </a>
                        <a class="nav-link active" href="manage_semesters.php">
                            <i class="fas fa-calendar-alt"></i> Manage Semesters
                        </a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <div class="row">
            <div class="col-md-5">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-plus"></i> Add New Semester</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Semester Number</label>
                                <select name="semester_number" class="form-select" required>
                                    <?php for($i = 1; $i <= 8; $i++): ?>
                                    <option value="<?php echo $i; ?>">Semester <?php echo $i; ?></option>
                                    <?php endfor; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Academic Year</label>
                                <input type="text" class="form-control" name="academic_year" placeholder="2024-2025" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Start Date</label>
                                    <input type="date" class="form-control" name="start_date" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">End Date</label>
                                    <input type="date" class="form-control" name="end_date" required>
                                </div>
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="is_active" checked>
                                <label class="form-check-label">Active Semester</label>
                            </div>
                            
                            <button type="submit" name="add_semester" class="btn btn-danger w-100">Add Semester</button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-7">
                <div class="card">
                    <div class="card-header bg-danger text-white">
                        <h5><i class="fas fa-calendar"></i> Semesters List</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Semester</th>
                                        <th>Academic Year</th>
                                        <th>Start Date</th>
                                        <th>End Date</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($semesters as $semester): ?>
                                    <tr>
                                        <td>Semester <?php echo $semester['semester_number']; ?></td>
                                        <td><?php echo $semester['academic_year']; ?></td>
                                        <td><?php echo date('M d, Y', strtotime($semester['start_date'])); ?></td>
                                        <td><?php echo date('M d, Y', strtotime($semester['end_date'])); ?></td>
                                        <td>
                                            <span class="badge <?php echo $semester['is_active'] ? 'bg-success' : 'bg-secondary'; ?>">
                                                <?php echo $semester['is_active'] ? 'Active' : 'Inactive'; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="?toggle=<?php echo $semester['id']; ?>" class="btn btn-sm btn-warning">
                                                <i class="fas fa-toggle-<?php echo $semester['is_active'] ? 'off' : 'on'; ?>"></i>
                                            </a>
                                            <a href="?delete=<?php echo $semester['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure?')">
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
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>