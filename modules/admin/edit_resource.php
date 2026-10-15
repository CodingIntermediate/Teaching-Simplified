<?php
require_once '../../config.php';
Auth::requireRole('teacher');

$db = db();
$user = Auth::getCurrentUser();
$resource_id = $_GET['id'] ?? 0;

$resource = $db->selectOne("SELECT * FROM tutor_resources WHERE id = ? AND tutor_id = ?", [$resource_id, $user['id']]);

if (!$resource) {
    setFlash('error', 'Resource not found');
    redirect('modules/teacher/manage_resources.php');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $update_data = [
        'title' => $_POST['title'],
        'description' => $_POST['description'],
        'tags' => $_POST['tags']
    ];
    
    $db->update('tutor_resources', $update_data, 'id = ?', [$resource_id]);
    setFlash('success', 'Resource updated successfully');
    redirect('modules/teacher/manage_resources.php');
}

$subjects = getTeacherSubjects($user['id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resource - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-success">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-chalkboard-teacher"></i> Teacher Portal
            </a>
            <div class="navbar-nav ms-auto">
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="manage_resources.php">My Resources</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5><i class="fas fa-edit"></i> Edit Resource</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <div class="mb-3">
                                <label for="title" class="form-label">Resource Title *</label>
                                <input type="text" class="form-control" name="title" value="<?php echo htmlspecialchars($resource['title']); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"><?php echo htmlspecialchars($resource['description']); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tags" class="form-label">Tags (comma-separated)</label>
                                <input type="text" class="form-control" name="tags" value="<?php echo htmlspecialchars($resource['tags']); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-success">Update Resource</button>
                            <a href="manage_resources.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>