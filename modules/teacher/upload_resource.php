<?php
require_once '../../config.php';
Auth::requireRole('teacher');

$db = db();
$user = Auth::getCurrentUser();

// Get subjects taught by this teacher
$subjects = getTeacherSubjects($user['id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $resource_type = $_POST['resource_type'];
    $tags = $_POST['tags'];
    
    $resource_data = [
        'tutor_id' => $user['id'],
        'subject_id' => $subject_id,        'title' => $title,
        'description' => $description,
        'resource_type' => $resource_type,
        'tags' => $tags
    ];
    
    // Handle file upload or URL
    if ($resource_type == 'link') {
        $resource_data['external_url'] = $_POST['external_url'];
    } else {
        if (isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] == 0) {
            $allowed = ['pdf', 'mp4', 'ppt', 'pptx', 'doc', 'docx'];
            $upload = uploadFile($_FILES['resource_file'], RESOURCE_DIR, $allowed);
            
            if ($upload['success']) {
                // Store ONLY the relative path (not full Windows path)
                $resource_data['file_path'] = 'assets/uploads/resources/' . $upload['filename'];
            } else {
                setFlash('error', $upload['message']);
                redirect('modules/teacher/upload_resource.php');
            }
        }
    }
    
    $db->insert('tutor_resources', $resource_data);
    setFlash('success', 'Resource uploaded successfully!');
    redirect('modules/teacher/manage_resources.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Resource - Teacher Portal</title>
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
                <span class="nav-link">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
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
                        <h5><i class="fas fa-upload"></i> Upload Learning Resource</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data">
                            <div class="mb-3">
                                <label for="subject_id" class="form-label">Subject *</label>
                                <select name="subject_id" id="subject_id" class="form-select" required>
                                    <option value="">Select Subject</option>
                                    <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>" <?php echo (isset($_GET['subject_id']) && $_GET['subject_id'] == $subject['id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="title" class="form-label">Resource Title *</label>
                                <input type="text" class="form-control" name="title" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" name="description" rows="3"></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="resource_type" class="form-label">Resource Type *</label>
                                <select name="resource_type" id="resource_type" class="form-select" required>
                                    <option value="pdf">PDF Document</option>
                                    <option value="video">Video</option>
                                    <option value="presentation">Presentation</option>
                                    <option value="link">External Link</option>
                                </select>
                            </div>
                            
                            <div id="file_upload_div" class="mb-3">
                                <label for="resource_file" class="form-label">File</label>
                                <input type="file" class="form-control" name="resource_file">
                                <small class="text-muted">Allowed: PDF, MP4, PPT, DOC (Max 10MB)</small>
                            </div>
                            
                            <div id="url_div" class="mb-3" style="display: none;">
                                <label for="external_url" class="form-label">External URL</label>
                                <input type="url" class="form-control" name="external_url" placeholder="https://...">
                            </div>
                            
                            <div class="mb-3">
                                <label for="tags" class="form-label">Tags (comma-separated)</label>
                                <input type="text" class="form-control" name="tags" placeholder="e.g., tutorial, beginner, advanced">
                                <small class="text-muted">Helps students find relevant resources</small>
                            </div>
                            
                            <button type="submit" class="btn btn-success">Upload Resource</button>
                            <a href="dashboard.php" class="btn btn-secondary">Cancel</a>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.getElementById('resource_type').addEventListener('change', function() {
            var type = this.value;
            var fileDiv = document.getElementById('file_upload_div');
            var urlDiv = document.getElementById('url_div');
            
            if (type == 'link') {
                fileDiv.style.display = 'none';
                urlDiv.style.display = 'block';
            } else {
                fileDiv.style.display = 'block';
                urlDiv.style.display = 'none';
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>