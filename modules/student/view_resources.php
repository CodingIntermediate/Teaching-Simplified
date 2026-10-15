<?php
require_once '../../config.php';
require_once '../../includes/resource_generator.php';

Auth::requireRole('student');

$db = db();
$user = Auth::getCurrentUser();
$syllabus_id = $_GET['syllabus_id'] ?? 0;

// Get syllabus info
$syllabus = getSyllabusById($syllabus_id);

if (!$syllabus || $syllabus['student_id'] != $user['id']) {
    setFlash('error', 'Invalid syllabus');
    redirect('modules/student/dashboard.php');
}

// Get teacher-uploaded resources for this subject
$teacher_resources = $db->select("
    SELECT tr.*, u.first_name, u.last_name 
    FROM tutor_resources tr
    JOIN users u ON tr.tutor_id = u.id
    WHERE tr.subject_id = ? 
    ORDER BY tr.uploaded_at DESC
", [$syllabus['subject_id']]);

// Check if analysis exists, if not generate it
$analysis = $db->selectOne("SELECT * FROM syllabus_analysis WHERE syllabus_id = ?", [$syllabus_id]);

if (!$analysis) {
    $generator = new SyllabusResourceGenerator();
    $result = $generator->generateResourcesFromSyllabus($syllabus_id);
    
    if ($result['success']) {
        $topics = $result['topics'];
        $keywords = $result['keywords'];
        $resources_data = $result['resources'];
    } else {
        $topics = [];
        $keywords = [];
        $resources_data = [];
    }
} else {
    $topics = explode('|', $analysis['topics']);
    $keywords = explode('|', $analysis['keywords']);
    $resources_data = [];
    $resource_suggestions = $db->select("SELECT * FROM resource_suggestions WHERE syllabus_id = ?", [$syllabus_id]);
}

// Handle file download request
if (isset($_GET['download'])) {
    $file_id = (int)$_GET['download'];
    $resource = $db->selectOne("SELECT * FROM tutor_resources WHERE id = ?", [$file_id]);
    
    if ($resource && !empty($resource['file_path'])) {
        $file_path = $resource['file_path'];
        
        if (strpos($file_path, 'C:\\') !== false || strpos($file_path, 'xampp') !== false) {
            $filename = basename($file_path);
            $file_path = 'assets/uploads/resources/' . $filename;
        }
        
        $full_path = __DIR__ . '/../../' . $file_path;
        $full_path = str_replace('\\', '/', $full_path);
        
        if (file_exists($full_path)) {
            $db->query("UPDATE tutor_resources SET downloads = downloads + 1 WHERE id = ?", [$file_id]);
            
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . basename($full_path) . '"');
            header('Content-Length: ' . filesize($full_path));
            header('Cache-Control: private, max-age=0, must-revalidate');
            readfile($full_path);
            exit;
        } else {
            error_log("File not found: " . $full_path);
            setFlash('error', 'File not found. Please contact teacher.');
            redirect("view_resources.php?syllabus_id=$syllabus_id");
        }
    } else {
        setFlash('error', 'Resource not found');
        redirect("view_resources.php?syllabus_id=$syllabus_id");
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Learning Resources - <?php echo htmlspecialchars($syllabus['subject_code']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .topic-card {
            margin-bottom: 25px;
            border: none;
            border-radius: 10px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }
        .topic-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.15);
        }
        .topic-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }
        .teacher-header {
            background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            color: white;
            padding: 15px;
            border-radius: 10px 10px 0 0;
        }
        .keyword-badge {
            background: #e9ecef;
            padding: 8px 15px;
            border-radius: 25px;
            font-size: 13px;
            margin: 5px;
            display: inline-block;
        }
        .extracted-topics {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .teacher-resource-card {
            border-left: 4px solid #28a745;
            margin-bottom: 15px;
            transition: all 0.2s;
        }
        .teacher-resource-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .nav-tabs .nav-link {
            color: #495057;
            font-weight: 500;
        }
        .nav-tabs .nav-link.active {
            color: #007bff;
            border-bottom: 2px solid #007bff;
        }
        .embedded-pdf {
            width: 100%;
            height: 500px;
            border: none;
            border-radius: 8px;
        }
        .video-container {
            position: relative;
            padding-bottom: 56.25%;
            height: 0;
            overflow: hidden;
            border-radius: 8px;
            background: #000;
        }
        .video-container video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
        }
        .video-thumbnail {
            position: relative;
            cursor: pointer;
        }
        .video-thumbnail .play-icon {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-size: 4rem;
            color: white;
            text-shadow: 0 0 20px rgba(0,0,0,0.5);
            opacity: 0.8;
            transition: all 0.3s;
        }
        .video-thumbnail:hover .play-icon {
            opacity: 1;
            transform: translate(-50%, -50%) scale(1.1);
        }
        .video-thumbnail img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            border-radius: 8px;
        }
        .video-controls {
            position: absolute;
            bottom: 10px;
            left: 10px;
            right: 10px;
            background: rgba(0,0,0,0.5);
            padding: 5px 10px;
            border-radius: 5px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .video-controls .progress-bar {
            flex: 1;
            height: 5px;
            background: rgba(255,255,255,0.3);
            border-radius: 3px;
            margin: 0 10px;
            cursor: pointer;
        }
        .video-controls .progress-bar .progress-fill {
            height: 100%;
            background: #007bff;
            border-radius: 3px;
            width: 0%;
            transition: width 0.1s;
        }
        .video-modal-body {
            padding: 0 !important;
            background: #000;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap"></i> Learning Resources
            </a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link text-white">
                    <i class="fas fa-user"></i> <?php echo $_SESSION['full_name']; ?>
                </span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <!-- Header -->
        <div class="card bg-primary text-white mb-4">
            <div class="card-body">
                <h3><i class="fas fa-chalkboard-teacher"></i> Learning Resources</h3>
                <p class="mb-0">Subject: <strong><?php echo htmlspecialchars($syllabus['subject_code'] . ' - ' . $syllabus['subject_name']); ?></strong></p>
                <p class="mb-0">Resources from teachers and automatically generated from your syllabus</p>
            </div>
        </div>

        <!-- Tabs -->
        <ul class="nav nav-tabs mb-4" id="resourceTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                    <i class="fas fa-globe"></i> All Resources
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="teacher-tab" data-bs-toggle="tab" data-bs-target="#teacher" type="button" role="tab">
                    <i class="fas fa-chalkboard-teacher"></i> Teacher Resources
                    <?php if (count($teacher_resources) > 0): ?>
                        <span class="badge bg-success"><?php echo count($teacher_resources); ?></span>
                    <?php endif; ?>
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="auto-tab" data-bs-toggle="tab" data-bs-target="#auto" type="button" role="tab">
                    <i class="fas fa-robot"></i> Auto-Generated Resources
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link" id="topics-tab" data-bs-toggle="tab" data-bs-target="#topics" type="button" role="tab">
                    <i class="fas fa-tags"></i> Topics & Keywords
                </button>
            </li>
        </ul>

        <div class="tab-content">
            <!-- All Resources Tab -->
            <div class="tab-pane fade show active" id="all" role="tabpanel">
                <!-- Teacher Resources Section -->
                <?php if (!empty($teacher_resources)): ?>
                <div class="card mb-4">
                    <div class="teacher-header">
                        <h5 class="mb-0"><i class="fas fa-chalkboard-teacher"></i> Resources Shared by Teachers</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($teacher_resources as $resource): ?>
                            <div class="col-md-6">
                                <div class="card teacher-resource-card">
                                    <div class="card-body">
                                        <div class="d-flex">
                                            <div class="me-3">
                                                <?php if($resource['resource_type'] == 'video'): ?>
                                                    <i class="fas fa-video fa-2x text-danger"></i>
                                                <?php elseif($resource['resource_type'] == 'pdf'): ?>
                                                    <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                                <?php elseif($resource['resource_type'] == 'link'): ?>
                                                    <i class="fas fa-link fa-2x text-primary"></i>
                                                <?php else: ?>
                                                    <i class="fas fa-file-alt fa-2x text-info"></i>
                                                <?php endif; ?>
                                            </div>
                                            <div class="flex-grow-1">
                                                <h6 class="card-title mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                                <p class="card-text text-muted small mb-1">
                                                    <i class="fas fa-user"></i> Uploaded by: <?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?>
                                                    <br>
                                                    <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['uploaded_at'])); ?>
                                                    <?php if($resource['downloads'] > 0): ?>
                                                        • <i class="fas fa-download"></i> <?php echo $resource['downloads']; ?> downloads
                                                    <?php endif; ?>
                                                </p>
                                                <?php if (!empty($resource['description'])): ?>
                                                    <p class="small"><?php echo htmlspecialchars(substr($resource['description'], 0, 100)); ?>...</p>
                                                <?php endif; ?>
                                                <div class="mt-2">
                                                    <?php if($resource['resource_type'] == 'pdf' && !empty($resource['file_path'])): ?>
                                                        <a href="?download=<?php echo $resource['id']; ?>&syllabus_id=<?php echo $syllabus_id; ?>" class="btn btn-sm btn-success">
                                                            <i class="fas fa-download"></i> Download PDF
                                                        </a>
                                                        <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#pdfModal<?php echo $resource['id']; ?>">
                                                            <i class="fas fa-eye"></i> View PDF
                                                        </button>
                                                    <?php elseif($resource['resource_type'] == 'video' && !empty($resource['file_path'])): ?>
                                                        <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#videoModal<?php echo $resource['id']; ?>">
                                                            <i class="fas fa-play"></i> Play Video
                                                        </button>
                                                        <a href="?download=<?php echo $resource['id']; ?>&syllabus_id=<?php echo $syllabus_id; ?>" class="btn btn-sm btn-success">
                                                            <i class="fas fa-download"></i> Download Video
                                                        </a>
                                                    <?php elseif($resource['resource_type'] == 'video' && !empty($resource['external_url'])): ?>
                                                        <a href="<?php echo $resource['external_url']; ?>" class="btn btn-sm btn-danger" target="_blank">
                                                            <i class="fab fa-youtube"></i> Watch Video
                                                        </a>
                                                    <?php elseif($resource['resource_type'] == 'link' && !empty($resource['external_url'])): ?>
                                                        <a href="<?php echo $resource['external_url']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                            <i class="fas fa-external-link-alt"></i> Open Link
                                                        </a>
                                                    <?php endif; ?>
                                                    <?php if (!empty($resource['tags'])): ?>
                                                        <span class="badge bg-secondary ms-2"><?php echo htmlspecialchars($resource['tags']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- PDF Modal -->
                            <?php if($resource['resource_type'] == 'pdf' && !empty($resource['file_path'])): 
                                $preview_path = $resource['file_path'];
                                if (strpos($preview_path, 'C:\\') !== false || strpos($preview_path, 'xampp') !== false) {
                                    $preview_path = 'assets/uploads/resources/' . basename($preview_path);
                                }
                            ?>
                            <div class="modal fade" id="pdfModal<?php echo $resource['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content">
                                        <div class="modal-header bg-primary text-white">
                                            <h5 class="modal-title"><?php echo htmlspecialchars($resource['title']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body p-0">
                                            <iframe src="<?php echo SITE_URL . $preview_path; ?>" class="embedded-pdf"></iframe>
                                        </div>
                                        <div class="modal-footer">
                                            <a href="?download=<?php echo $resource['id']; ?>&syllabus_id=<?php echo $syllabus_id; ?>" class="btn btn-success">
                                                <i class="fas fa-download"></i> Download
                                            </a>
                                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <!-- Video Modal -->
                            <?php if($resource['resource_type'] == 'video' && !empty($resource['file_path'])): 
                                $video_path = $resource['file_path'];
                                if (strpos($video_path, 'C:\\') !== false || strpos($video_path, 'xampp') !== false) {
                                    $video_path = 'assets/uploads/resources/' . basename($video_path);
                                }
                            ?>
                            <div class="modal fade" id="videoModal<?php echo $resource['id']; ?>" tabindex="-1">
                                <div class="modal-dialog modal-xl">
                                    <div class="modal-content bg-dark">
                                        <div class="modal-header bg-danger text-white">
                                            <h5 class="modal-title"><i class="fas fa-video"></i> <?php echo htmlspecialchars($resource['title']); ?></h5>
                                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                        </div>
                                        <div class="modal-body video-modal-body">
                                            <div class="video-container">
                                                <video id="videoPlayer<?php echo $resource['id']; ?>" controls autoplay>
                                                    <source src="<?php echo SITE_URL . $video_path; ?>" type="video/mp4">
                                                    <source src="<?php echo SITE_URL . $video_path; ?>" type="video/webm">
                                                    <source src="<?php echo SITE_URL . $video_path; ?>" type="video/ogg">
                                                    Your browser does not support the video tag.
                                                </video>
                                            </div>
                                        </div>
                                        <div class="modal-footer bg-dark">
                                            <a href="?download=<?php echo $resource['id']; ?>&syllabus_id=<?php echo $syllabus_id; ?>" class="btn btn-success">
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
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info mb-4">
                    <i class="fas fa-info-circle"></i> No teacher resources uploaded yet for this subject.
                </div>
                <?php endif; ?>

                <!-- Auto-Generated Resources Section -->
                <div class="card">
                    <div class="topic-header">
                        <h5 class="mb-0"><i class="fas fa-robot"></i> Auto-Generated Resources</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php if (!empty($resources_data)): ?>
                                <?php foreach ($resources_data as $resource_group): ?>
                                <div class="col-md-6">
                                    <div class="card topic-card mb-3">
                                        <div class="card-header bg-light">
                                            <strong><?php echo htmlspecialchars(substr($resource_group['topic'], 0, 60)); ?></strong>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <a href="https://www.youtube.com/results?search_query=<?php echo urlencode($resource_group['topic']); ?> tutorial" target="_blank" class="btn btn-outline-danger btn-sm">
                                                    <i class="fab fa-youtube"></i> Watch Tutorials
                                                </a>
                                                <a href="https://en.wikipedia.org/w/index.php?search=<?php echo urlencode($resource_group['topic']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="fab fa-wikipedia-w"></i> Wikipedia
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php elseif (!empty($topics)): ?>
                                <?php foreach ($topics as $topic): ?>
                                <div class="col-md-6">
                                    <div class="card topic-card mb-3">
                                        <div class="card-header bg-light">
                                            <strong><?php echo htmlspecialchars(substr($topic, 0, 60)); ?></strong>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-2">
                                                <a href="https://www.youtube.com/results?search_query=<?php echo urlencode($topic); ?> tutorial" target="_blank" class="btn btn-outline-danger btn-sm">
                                                    <i class="fab fa-youtube"></i> Tutorials
                                                </a>
                                                <a href="https://en.wikipedia.org/w/index.php?search=<?php echo urlencode($topic); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                                    <i class="fab fa-wikipedia-w"></i> Wikipedia
                                                </a>
                                                <a href="https://scholar.google.com/scholar?q=<?php echo urlencode($topic); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                                    <i class="fas fa-graduation-cap"></i> Scholar
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <p class="text-muted text-center">No auto-generated resources available yet. Upload a syllabus with text content to generate resources.</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Teacher Resources Tab (Same as above but only teacher resources) -->
            <div class="tab-pane fade" id="teacher" role="tabpanel">
                <?php if (!empty($teacher_resources)): ?>
                    <div class="row">
                        <?php foreach ($teacher_resources as $resource): ?>
                        <div class="col-md-6">
                            <div class="card teacher-resource-card">
                                <div class="card-body">
                                    <div class="d-flex">
                                        <div class="me-3">
                                            <?php if($resource['resource_type'] == 'video'): ?>
                                                <i class="fas fa-video fa-2x text-danger"></i>
                                            <?php elseif($resource['resource_type'] == 'pdf'): ?>
                                                <i class="fas fa-file-pdf fa-2x text-danger"></i>
                                            <?php elseif($resource['resource_type'] == 'link'): ?>
                                                <i class="fas fa-link fa-2x text-primary"></i>
                                            <?php else: ?>
                                                <i class="fas fa-file-alt fa-2x text-info"></i>
                                            <?php endif; ?>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="card-title mb-1"><?php echo htmlspecialchars($resource['title']); ?></h6>
                                            <p class="card-text text-muted small mb-1">
                                                <i class="fas fa-user"></i> Uploaded by: <?php echo htmlspecialchars($resource['first_name'] . ' ' . $resource['last_name']); ?>
                                                <br>
                                                <i class="fas fa-calendar"></i> <?php echo date('M d, Y', strtotime($resource['uploaded_at'])); ?>
                                            </p>
                                            <?php if (!empty($resource['description'])): ?>
                                                <p class="small"><?php echo htmlspecialchars(substr($resource['description'], 0, 150)); ?></p>
                                            <?php endif; ?>
                                            <div class="mt-2">
                                                <?php if($resource['resource_type'] == 'pdf' && !empty($resource['file_path'])): ?>
                                                    <a href="?download=<?php echo $resource['id']; ?>&syllabus_id=<?php echo $syllabus_id; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-download"></i> Download PDF
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#pdfModal<?php echo $resource['id']; ?>">
                                                        <i class="fas fa-eye"></i> View PDF
                                                    </button>
                                                <?php elseif($resource['resource_type'] == 'video' && !empty($resource['file_path'])): ?>
                                                    <button type="button" class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#videoModal<?php echo $resource['id']; ?>">
                                                        <i class="fas fa-play"></i> Play Video
                                                    </button>
                                                    <a href="?download=<?php echo $resource['id']; ?>&syllabus_id=<?php echo $syllabus_id; ?>" class="btn btn-sm btn-success">
                                                        <i class="fas fa-download"></i> Download Video
                                                    </a>
                                                <?php elseif($resource['resource_type'] == 'video' && !empty($resource['external_url'])): ?>
                                                    <a href="<?php echo $resource['external_url']; ?>" class="btn btn-sm btn-danger" target="_blank">
                                                        <i class="fab fa-youtube"></i> Watch Video
                                                    </a>
                                                <?php elseif($resource['resource_type'] == 'link' && !empty($resource['external_url'])): ?>
                                                    <a href="<?php echo $resource['external_url']; ?>" class="btn btn-sm btn-primary" target="_blank">
                                                        <i class="fas fa-external-link-alt"></i> Open Link
                                                    </a>
                                                <?php endif; ?>
                                                <?php if (!empty($resource['tags'])): ?>
                                                    <span class="badge bg-secondary ms-2"><i class="fas fa-tags"></i> <?php echo htmlspecialchars($resource['tags']); ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> No teacher resources uploaded yet for this subject.
                    </div>
                <?php endif; ?>
            </div>

            <!-- Auto-Generated Resources Tab -->
            <div class="tab-pane fade" id="auto" role="tabpanel">
                <!-- Same as before... -->
                <div class="row">
                    <?php if (!empty($resources_data)): ?>
                        <?php foreach ($resources_data as $resource_group): ?>
                        <div class="col-md-6">
                            <div class="card topic-card mb-3">
                                <div class="topic-header">
                                    <h6 class="mb-0"><?php echo htmlspecialchars(substr($resource_group['topic'], 0, 70)); ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <a href="https://www.youtube.com/results?search_query=<?php echo urlencode($resource_group['topic']); ?> tutorial" target="_blank" class="btn btn-outline-danger btn-sm">
                                            <i class="fab fa-youtube"></i> Tutorials
                                        </a>
                                        <a href="https://en.wikipedia.org/w/index.php?search=<?php echo urlencode($resource_group['topic']); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-wikipedia-w"></i> Wikipedia
                                        </a>
                                        <a href="https://www.coursera.org/search?query=<?php echo urlencode($resource_group['topic']); ?>" target="_blank" class="btn btn-outline-success btn-sm">
                                            <i class="fas fa-university"></i> Courses
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php elseif (!empty($topics)): ?>
                        <?php foreach ($topics as $topic): ?>
                        <div class="col-md-6">
                            <div class="card topic-card mb-3">
                                <div class="topic-header">
                                    <h6 class="mb-0"><?php echo htmlspecialchars(substr($topic, 0, 70)); ?></h6>
                                </div>
                                <div class="card-body">
                                    <div class="mb-2">
                                        <a href="https://www.youtube.com/results?search_query=<?php echo urlencode($topic); ?> tutorial" target="_blank" class="btn btn-outline-danger btn-sm">
                                            <i class="fab fa-youtube"></i> Tutorials
                                        </a>
                                        <a href="https://en.wikipedia.org/w/index.php?search=<?php echo urlencode($topic); ?>" target="_blank" class="btn btn-outline-primary btn-sm">
                                            <i class="fab fa-wikipedia-w"></i> Wikipedia
                                        </a>
                                        <a href="https://scholar.google.com/scholar?q=<?php echo urlencode($topic); ?>" target="_blank" class="btn btn-outline-secondary btn-sm">
                                            <i class="fas fa-graduation-cap"></i> Scholar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12">
                            <div class="alert alert-warning">
                                <i class="fas fa-exclamation-triangle"></i> No auto-generated resources available. Please upload a syllabus with text content.
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Topics & Keywords Tab -->
            <div class="tab-pane fade" id="topics" role="tabpanel">
                <!-- Same as before... -->
                <?php if (!empty($topics)): ?>
                <div class="extracted-topics">
                    <h5><i class="fas fa-list-ul"></i> Topics Extracted from Your Syllabus</h5>
                    <div class="row">
                        <?php foreach ($topics as $topic): ?>
                        <div class="col-md-6 mb-2">
                            <span class="keyword-badge">
                                <i class="fas fa-hashtag"></i> 
                                <?php echo htmlspecialchars(substr($topic, 0, 80)); ?>
                            </span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($keywords)): ?>
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-tags"></i> Key Concepts from Your Syllabus</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach ($keywords as $keyword): ?>
                            <?php if (strlen($keyword) > 2 && !in_array($keyword, ['This', 'That', 'These', 'Those'])): ?>
                            <span class="keyword-badge">
                                <i class="fas fa-hashtag"></i> <?php echo htmlspecialchars($keyword); ?>
                            </span>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Video Modal JS to handle play/pause -->
    <script>
        // When video modal is hidden, pause the video
        document.addEventListener('hidden.bs.modal', function (event) {
            var modal = event.target;
            var video = modal.querySelector('video');
            if (video) {
                video.pause();
            }
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>