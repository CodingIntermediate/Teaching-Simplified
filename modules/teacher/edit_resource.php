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
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $tags = trim($_POST['tags'] ?? '');
    $resource_type = $_POST['resource_type'] ?? 'pdf';
    
    // Validate
    if (empty($title)) {
        setFlash('error', 'Title is required');
        redirect('modules/teacher/edit_resource.php?id=' . $resource_id);
    }
    
    $update_data = [
        'title' => $title,
        'description' => $description,
        'tags' => $tags,
        'resource_type' => $resource_type
    ];
    
    // Handle file upload if new file is provided
    $has_file = isset($_FILES['resource_file']) && $_FILES['resource_file']['error'] == 0 && $_FILES['resource_file']['size'] > 0;
    
    if ($has_file) {
        $allowed = ['pdf', 'mp4', 'webm', 'ogg', 'ppt', 'pptx', 'doc', 'docx', 'jpg', 'jpeg', 'png'];
        $upload = uploadFile($_FILES['resource_file'], RESOURCE_DIR, $allowed);
        
        if ($upload['success']) {
            // Delete old file if exists
            if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
                unlink($resource['file_path']);
            }
            
            $update_data['file_path'] = 'assets/uploads/resources/' . $upload['filename'];
            $update_data['external_url'] = null;
        } else {
            setFlash('error', $upload['message']);
            redirect('modules/teacher/edit_resource.php?id=' . $resource_id);
        }
    }
    
    // Handle external URL update
    if ($resource_type == 'link') {
        $external_url = trim($_POST['external_url'] ?? '');
        if (!empty($external_url)) {
            $update_data['external_url'] = $external_url;
            // Delete old file if exists
            if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
                unlink($resource['file_path']);
            }
            $update_data['file_path'] = null;
        } else {
            setFlash('error', 'External URL is required for link type');
            redirect('modules/teacher/edit_resource.php?id=' . $resource_id);
        }
    }
    
    // If not a link, ensure we have a file
    if ($resource_type != 'link' && empty($update_data['file_path']) && empty($resource['file_path'])) {
        setFlash('error', 'Please upload a file for this resource type');
        redirect('modules/teacher/edit_resource.php?id=' . $resource_id);
    }
    
    $db->update('tutor_resources', $update_data, 'id = ?', [$resource_id]);
    setFlash('success', 'Resource updated successfully!');
    redirect('modules/teacher/manage_resources.php');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Resource - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .preview-box {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            border: 1px solid #dee2e6;
        }
        .current-file {
            background: #e9ecef;
            padding: 8px 12px;
            border-radius: 5px;
            display: inline-block;
        }
    </style>
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
                        <form method="POST" enctype="multipart/form-data" id="editForm">
                            <div class="mb-3">
                                <label for="title" class="form-label">Resource Title *</label>
                                <input type="text" class="form-control" id="title" name="title" value="<?php echo htmlspecialchars($resource['title'] ?? ''); ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($resource['description'] ?? ''); ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="resource_type" class="form-label">Resource Type *</label>
                                <select name="resource_type" id="resource_type" class="form-select" required>
                                    <option value="pdf" <?php echo ($resource['resource_type'] ?? '') == 'pdf' ? 'selected' : ''; ?>>PDF Document</option>
                                    <option value="video" <?php echo ($resource['resource_type'] ?? '') == 'video' ? 'selected' : ''; ?>>Video</option>
                                    <option value="presentation" <?php echo ($resource['resource_type'] ?? '') == 'presentation' ? 'selected' : ''; ?>>Presentation</option>
                                    <option value="link" <?php echo ($resource['resource_type'] ?? '') == 'link' ? 'selected' : ''; ?>>External Link</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="file_upload_div" style="<?php echo ($resource['resource_type'] ?? '') == 'link' ? 'display:none;' : 'display:block;'; ?>">
                                <label class="form-label">Upload New File</label>
                                <input type="file" class="form-control" id="resource_file" name="resource_file">
                                <small class="text-muted">Leave empty to keep current file. Allowed: PDF, MP4, WebM, OGG, PPT, DOC (Max 10MB)</small>
                            </div>
                            
                            <div id="current_file_div" style="<?php echo ($resource['resource_type'] ?? '') == 'link' ? 'display:none;' : 'display:block;'; ?>">
                                <?php if (!empty($resource['file_path'])): ?>
                                <div class="preview-box mb-3">
                                    <strong>Current File:</strong>
                                    <span class="current-file">
                                        <i class="fas fa-file"></i> 
                                        <?php echo htmlspecialchars(basename($resource['file_path'])); ?>
                                    </span>
                                    <?php 
                                    $file_ext = pathinfo($resource['file_path'], PATHINFO_EXTENSION);
                                    if ($file_ext == 'pdf'): ?>
                                        <a href="<?php echo SITE_URL . $resource['file_path']; ?>" target="_blank" class="btn btn-sm btn-primary ms-2">
                                            <i class="fas fa-eye"></i> Preview
                                        </a>
                                    <?php elseif (in_array($file_ext, ['mp4', 'webm', 'ogg'])): ?>
                                        <a href="<?php echo SITE_URL . $resource['file_path']; ?>" target="_blank" class="btn btn-sm btn-danger ms-2">
                                            <i class="fas fa-play"></i> Play Video
                                        </a>
                                    <?php endif; ?>
                                    <a href="<?php echo SITE_URL . $resource['file_path']; ?>" class="btn btn-sm btn-success ms-1" download>
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="mb-3" id="url_div" style="<?php echo ($resource['resource_type'] ?? '') == 'link' ? 'display:block;' : 'display:none;'; ?>">
                                <label for="external_url" class="form-label">External URL</label>
                                <input type="url" class="form-control" id="external_url" name="external_url" placeholder="https://..." value="<?php echo htmlspecialchars($resource['external_url'] ?? ''); ?>">
                                <small class="text-muted">Update the external URL for link resources</small>
                            </div>
                            
                            <div class="mb-3">
                                <label for="tags" class="form-label">Tags (comma-separated)</label>
                                <input type="text" class="form-control" id="tags" name="tags" value="<?php echo htmlspecialchars($resource['tags'] ?? ''); ?>">
                                <small class="text-muted">Helps students find this resource</small>
                            </div>
                            
                            <!-- Hidden field to ensure the form works even if URL is empty -->
                            <input type="hidden" id="external_url_hidden" name="external_url_hidden" value="">
                            
                            <div class="d-flex gap-2">
                                <button type="submit" class="btn btn-success" id="submitBtn">
                                    <i class="fas fa-save"></i> Update Resource
                                </button>
                                <a href="manage_resources.php" class="btn btn-secondary">Cancel</a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Toggle fields based on resource type
        document.getElementById('resource_type').addEventListener('change', function() {
            var type = this.value;
            var fileDiv = document.getElementById('file_upload_div');
            var urlDiv = document.getElementById('url_div');
            var currentFileDiv = document.getElementById('current_file_div');
            
            if (type == 'link') {
                fileDiv.style.display = 'none';
                urlDiv.style.display = 'block';
                currentFileDiv.style.display = 'none';
                // Make URL field required when showing
                document.getElementById('external_url').required = true;
            } else {
                fileDiv.style.display = 'block';
                urlDiv.style.display = 'none';
                currentFileDiv.style.display = 'block';
                // Remove required when hidden
                document.getElementById('external_url').required = false;
            }
        });
        
        // Fix for invalid form control - remove required from hidden fields on submit
        document.getElementById('editForm').addEventListener('submit', function(e) {
            var urlField = document.getElementById('external_url');
            var urlDiv = document.getElementById('url_div');
            
            // If URL div is hidden, remove required attribute
            if (urlDiv.style.display === 'none') {
                urlField.required = false;
            }
            
            var btn = document.getElementById('submitBtn');
            btn.disabled = true;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Updating...';
        });
        
        // Also handle the case when page loads with link type
        document.addEventListener('DOMContentLoaded', function() {
            var type = document.getElementById('resource_type').value;
            var urlField = document.getElementById('external_url');
            if (type === 'link') {
                urlField.required = true;
            } else {
                urlField.required = false;
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>