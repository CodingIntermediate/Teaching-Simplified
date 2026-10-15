<?php
require_once '../../config.php';
Auth::requireRole('teacher');

$db = db();
$user = Auth::getCurrentUser();

// Get filter parameters
$subject_filter = $_GET['subject_id'] ?? '';
$type_filter = $_GET['type'] ?? '';
$search = $_GET['search'] ?? '';

// Build query for resources
$sql = "SELECT r.*, s.name as subject_name, s.code as subject_code 
        FROM tutor_resources r
        JOIN subjects s ON r.subject_id = s.id
        WHERE r.tutor_id = ?";
$params = [$user['id']];

if ($subject_filter) {
    $sql .= " AND r.subject_id = ?";
    $params[] = $subject_filter;
}

if ($type_filter) {
    $sql .= " AND r.resource_type = ?";
    $params[] = $type_filter;
}

if ($search) {
    $sql .= " AND (r.title LIKE ? OR r.description LIKE ? OR r.tags LIKE ?)";
    $like = "%$search%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$sql .= " ORDER BY r.uploaded_at DESC";

$resources = $db->select($sql, $params);

// Get subjects taught by this teacher for filter
$subjects = getTeacherSubjects($user['id']);

// Get statistics
$stats = [
    'total' => count($resources),
    'videos' => count(array_filter($resources, function($r) { return $r['resource_type'] == 'video'; })),
    'pdfs' => count(array_filter($resources, function($r) { return $r['resource_type'] == 'pdf'; })),
    'links' => count(array_filter($resources, function($r) { return $r['resource_type'] == 'link'; })),
    'presentations' => count(array_filter($resources, function($r) { return $r['resource_type'] == 'presentation'; }))
];

// Handle delete
if (isset($_GET['delete'])) {
    $id = $_GET['delete'];
    
    // Get file path before deleting
    $resource = $db->selectOne("SELECT file_path FROM tutor_resources WHERE id = ? AND tutor_id = ?", [$id, $user['id']]);
    
    if ($resource) {
        // Delete file if exists
        if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
            unlink($resource['file_path']);
        }
        
        $db->delete('tutor_resources', 'id = ? AND tutor_id = ?', [$id, $user['id']]);
        setFlash('success', 'Resource deleted successfully');
    } else {
        setFlash('error', 'Resource not found');
    }
    
    redirect('modules/teacher/manage_resources.php');
}

// Handle bulk delete
if (isset($_POST['bulk_delete']) && isset($_POST['selected_resources'])) {
    $selected = $_POST['selected_resources'];
    $deleted = 0;
    
    foreach ($selected as $id) {
        $resource = $db->selectOne("SELECT file_path FROM tutor_resources WHERE id = ? AND tutor_id = ?", [$id, $user['id']]);
        if ($resource) {
            if (!empty($resource['file_path']) && file_exists($resource['file_path'])) {
                unlink($resource['file_path']);
            }
            $db->delete('tutor_resources', 'id = ? AND tutor_id = ?', [$id, $user['id']]);
            $deleted++;
        }
    }
    
    setFlash('success', "$deleted resource(s) deleted successfully");
    redirect('modules/teacher/manage_resources.php');
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Resources - Teacher Portal</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .resource-card {
            transition: all 0.3s;
            margin-bottom: 20px;
            border-left: 4px solid;
            cursor: pointer;
        }
        .resource-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .resource-video { border-left-color: #dc3545; }
        .resource-pdf { border-left-color: #dc3545; }
        .resource-link { border-left-color: #007bff; }
        .resource-presentation { border-left-color: #28a745; }
        .stats-card {
            transition: all 0.3s;
            border: none;
            border-radius: 10px;
        }
        .stats-card:hover {
            transform: translateY(-3px);
        }
        .resource-checkbox {
            position: absolute;
            top: 15px;
            left: 15px;
            z-index: 10;
            transform: scale(1.2);
        }
        .resource-content {
            padding-left: 35px;
        }
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
        }
        .video-container video,
        .video-container iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .embedded-pdf {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: 8px;
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
                        <a class="nav-link" href="dashboard.php">
                            <i class="fas fa-tachometer-alt"></i> Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="manage_resources.php">
                            <i class="fas fa-folder-open"></i> My Resources
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="upload_resource.php">
                            <i class="fas fa-upload"></i> Upload Resource
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
        
        <!-- Header -->
        <div class="row mb-4">
            <div class="col-md-8">
                <h2><i class="fas fa-folder-open"></i> My Learning Resources</h2>
                <p class="text-muted">Manage all the resources you've shared with students</p>
            </div>
            <div class="col-md-4 text-end">
                <a href="upload_resource.php" class="btn btn-success btn-lg">
                    <i class="fas fa-plus"></i> Upload New Resource
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card bg-primary text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Total Resources</h6>
                                <h2 class="mb-0"><?php echo $stats['total']; ?></h2>
                            </div>
                            <i class="fas fa-folder-open fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-danger text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">Videos</h6>
                                <h2 class="mb-0"><?php echo $stats['videos']; ?></h2>
                            </div>
                            <i class="fab fa-youtube fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-info text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">PDF Documents</h6>
                                <h2 class="mb-0"><?php echo $stats['pdfs']; ?></h2>
                            </div>
                            <i class="fas fa-file-pdf fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card bg-success text-white">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h6 class="card-title">External Links</h6>
                                <h2 class="mb-0"><?php echo $stats['links']; ?></h2>
                            </div>
                            <i class="fas fa-link fa-3x opacity-50"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Filter by Subject</label>
                        <select name="subject_id" class="form-select">
                            <option value="">All Subjects</option>
                            <?php foreach ($subjects as $subject): ?>
                            <option value="<?php echo $subject['id']; ?>" <?php echo $subject_filter == $subject['id'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Filter by Type</label>
                        <select name="type" class="form-select">
                            <option value="">All Types</option>
                            <option value="video" <?php echo $type_filter == 'video' ? 'selected' : ''; ?>>Video</option>
                            <option value="pdf" <?php echo $type_filter == 'pdf' ? 'selected' : ''; ?>>PDF</option>
                            <option value="link" <?php echo $type_filter == 'link' ? 'selected' : ''; ?>>Link</option>
                            <option value="presentation" <?php echo $type_filter == 'presentation' ? 'selected' : ''; ?>>Presentation</option>
                        </select>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" name="search" class="form-control" placeholder="Search by title, description or tags..." value="<?php echo htmlspecialchars($search); ?>">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">&nbsp;</label>
                        <div>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Apply Filters
                            </button>
                            <a href="manage_resources.php" class="btn btn-secondary">
                                <i class="fas fa-undo"></i> Reset
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Bulk Actions -->
        <?php if (!empty($resources)): ?>
        <div class="mb-3">
            <button type="button" class="btn btn-sm btn-outline-primary" onclick="selectAll()">
                <i class="fas fa-check-double"></i> Select All
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="deselectAll()">
                <i class="fas fa-times"></i> Deselect All
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" onclick="confirmBulkDelete()">
                <i class="fas fa-trash"></i> Delete Selected
            </button>
        </div>
        <?php endif; ?>

        <!-- Resources List -->
        <?php if (empty($resources)): ?>
            <div class="alert alert-info text-center">
                <i class="fas fa-info-circle fa-2x mb-2"></i>
                <h5>No resources found</h5>
                <p>You haven't uploaded any resources yet. Click the "Upload New Resource" button to get started.</p>
                <a href="upload_resource.php" class="btn btn-primary">Upload Your First Resource</a>
            </div>
        <?php else: ?>
            <form method="POST" id="bulkDeleteForm">
                <div class="row">
                    <?php foreach ($resources as $resource): ?>
                    <div class="col-md-6">
                        <div class="card resource-card resource-<?php echo $resource['resource_type']; ?>">
                            <div class="card-body">
                                <input type="checkbox" name="selected_resources[]" value="<?php echo $resource['id']; ?>" class="resource-checkbox" onchange="updateBulkButton()">
                                <div class="resource-content">
                                    <div class="d-flex justify-content-between align-items-start">
                                        <div>
                                            <h5 class="card-title mb-1">
                                                <?php echo htmlspecialchars($resource['title']); ?>
                                            </h5>
                                            <p class="text-muted small mb-2">
                                                <i class="fas fa-book"></i> Subject: <?php echo htmlspecialchars($resource['subject_code'] . ' - ' . $resource['subject_name']); ?>
                                                <br>
                                                <i class="fas fa-calendar"></i> Uploaded: <?php echo date('M d, Y', strtotime($resource['uploaded_at'])); ?>
                                                <?php if($resource['downloads'] > 0): ?>
                                                    • <i class="fas fa-download"></i> <?php echo $resource['downloads']; ?> downloads
                                                <?php endif; ?>
                                            </p>
                                        </div>
                                        <div>
                                            <?php if($resource['resource_type'] == 'video'): ?>
                                                <span class="badge bg-danger"><i class="fab fa-youtube"></i> Video</span>
                                            <?php elseif($resource['resource_type'] == 'pdf'): ?>
                                                <span class="badge bg-danger"><i class="fas fa-file-pdf"></i> PDF</span>
                                            <?php elseif($resource['resource_type'] == 'link'): ?>
                                                <span class="badge bg-primary"><i class="fas fa-link"></i> Link</span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><i class="fas fa-file-powerpoint"></i> Presentation</span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <?php if (!empty($resource['description'])): ?>
                                        <p class="card-text mt-2"><?php echo htmlspecialchars(substr($resource['description'], 0, 120)); ?>...</p>
                                    <?php endif; ?>
                                    
                                    <?php if (!empty($resource['tags'])): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-tags text-muted"></i>
                                            <?php 
                                            $tags = explode(',', $resource['tags']);
                                            foreach ($tags as $tag): 
                                            ?>
                                                <span class="badge bg-secondary"><?php echo htmlspecialchars(trim($tag)); ?></span>
                                            <?php endforeach; ?>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <div class="mt-2">
                                        <?php if($resource['resource_type'] == 'video' && !empty($resource['file_path'])): ?>
                                            <!-- Video Preview Button -->
                                            <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#videoModal<?php echo $resource['id']; ?>">
                                                <i class="fas fa-play"></i> Preview Video
                                            </button>
                                            <a href="<?php echo SITE_URL . $resource['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        <?php elseif($resource['resource_type'] == 'pdf' && !empty($resource['file_path'])): ?>
                                            <a href="<?php echo SITE_URL . $resource['file_path']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                <i class="fas fa-eye"></i> Preview
                                            </a>
                                            <a href="<?php echo SITE_URL . $resource['file_path']; ?>" class="btn btn-sm btn-success" download>
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                        <?php elseif($resource['external_url']): ?>
                                            <a href="<?php echo $resource['external_url']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                <i class="fas fa-external-link-alt"></i> Open Resource
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="edit_resource.php?id=<?php echo $resource['id']; ?>" class="btn btn-sm btn-info">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="?delete=<?php echo $resource['id']; ?>" class="btn btn-sm btn-danger" onclick="return confirm('Are you sure you want to delete this resource?')">
                                            <i class="fas fa-trash"></i> Delete
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Video Preview Modal -->
                    <?php if($resource['resource_type'] == 'video' && !empty($resource['file_path'])): ?>
                    <div class="modal fade" id="videoModal<?php echo $resource['id']; ?>" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header bg-danger text-white">
                                    <h5 class="modal-title"><i class="fas fa-video"></i> <?php echo htmlspecialchars($resource['title']); ?></h5>
                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                </div>
                                <div class="modal-body">
                                    <div class="video-container">
                                        <video controls autoplay>
                                            <source src="<?php echo SITE_URL . $resource['file_path']; ?>" type="video/mp4">
                                            <source src="<?php echo SITE_URL . $resource['file_path']; ?>" type="video/webm">
                                            <source src="<?php echo SITE_URL . $resource['file_path']; ?>" type="video/ogg">
                                            Your browser does not support the video tag.
                                        </video>
                                    </div>
                                </div>
                                <div class="modal-footer">
                                    <a href="<?php echo SITE_URL . $resource['file_path']; ?>" class="btn btn-success" download>
                                        <i class="fas fa-download"></i> Download Video
                                    </a>
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php endforeach; ?>
                </div>
            </form>
        <?php endif; ?>
    </div>

    <!-- Bulk Delete Confirmation Modal -->
    <div class="modal fade" id="bulkDeleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle"></i> Confirm Bulk Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the selected resources? This action cannot be undone.</p>
                    <p id="selectedCount" class="fw-bold"></p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" onclick="submitBulkDelete()">Delete Selected</button>
                </div>
            </div>
        </div>
    </div>

    <script>
        function selectAll() {
            document.querySelectorAll('.resource-checkbox').forEach(cb => cb.checked = true);
            updateBulkButton();
        }
        
        function deselectAll() {
            document.querySelectorAll('.resource-checkbox').forEach(cb => cb.checked = false);
            updateBulkButton();
        }
        
        function updateBulkButton() {
            const checked = document.querySelectorAll('.resource-checkbox:checked').length;
            const btn = document.querySelector('.btn-outline-danger');
            if (checked > 0) {
                btn.style.display = 'inline-block';
            } else {
                btn.style.display = 'inline-block';
            }
        }
        
        function confirmBulkDelete() {
            const checked = document.querySelectorAll('.resource-checkbox:checked');
            if (checked.length === 0) {
                alert('Please select at least one resource to delete');
                return;
            }
            document.getElementById('selectedCount').innerText = `You have selected ${checked.length} resource(s) to delete.`;
            new bootstrap.Modal(document.getElementById('bulkDeleteModal')).show();
        }
        
        function submitBulkDelete() {
            document.getElementById('bulkDeleteForm').submit();
        }
        
        // Make cards clickable (except for checkboxes and buttons)
        document.querySelectorAll('.resource-card').forEach(card => {
            card.addEventListener('click', function(e) {
                if (e.target.tagName !== 'INPUT' && e.target.tagName !== 'A' && e.target.tagName !== 'BUTTON') {
                    const checkbox = this.querySelector('.resource-checkbox');
                    checkbox.checked = !checkbox.checked;
                    updateBulkButton();
                }
            });
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>