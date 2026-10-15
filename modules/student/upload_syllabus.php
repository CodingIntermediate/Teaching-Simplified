<?php
require_once '../../config.php';
require_once '../../includes/resource_generator.php';
Auth::requireRole('student');

$db = db();
$user = Auth::getCurrentUser();

// Get enrolled subjects
$subjects = getEnrolledSubjects($user['id']);

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $subject_id = $_POST['subject_id'];
    $syllabus_text = trim($_POST['syllabus_text'] ?? '');
    $file = $_FILES['syllabus_file'] ?? null;
    
    $extracted_text = '';
    $file_path = '';
    
    // Get text from either file upload or textarea
    if (!empty($syllabus_text)) {
        $extracted_text = $syllabus_text;
        $file_path = 'pasted_text_' . time() . '.txt';
    } elseif ($file && $file['error'] == UPLOAD_ERR_OK) {
        // Upload file
        $upload = uploadFile($file, SYLLABUS_DIR, ['pdf', 'txt']);
        
        if ($upload['success']) {
            $file_path = $upload['path'];
            $extracted_text = extractTextFromFile($file_path, $file['name']);
            
            if (empty($extracted_text)) {
                $subject = $db->selectOne("SELECT name FROM subjects WHERE id = ?", [$subject_id]);
                $extracted_text = "Syllabus for " . $subject['name'] . ". 
                
Course Topics:
1. Introduction to " . $subject['name'] . "
2. Core Concepts
3. Advanced Topics
4. Practical Applications
5. Review and Assessment";
                setFlash('warning', 'PDF text extraction limited. Using default topics.');
            }
        } else {
            setFlash('error', $upload['message']);
            redirect('modules/student/upload_syllabus.php');
        }
    } else {
        setFlash('error', 'Please either upload a file or paste syllabus content');
        redirect('modules/student/upload_syllabus.php');
    }
    
    if (empty($extracted_text)) {
        setFlash('error', 'No text content could be extracted');
        redirect('modules/student/upload_syllabus.php');
    }
    
    // Check if already uploaded for this subject
    $existing = $db->selectOne("SELECT id FROM syllabus WHERE student_id = ? AND subject_id = ?", [$user['id'], $subject_id]);
    if ($existing) {
        // Delete existing and replace
        $db->delete('mcq_questions', 'syllabus_id = ?', [$existing['id']]);
        $db->delete('sa_questions', 'syllabus_id = ?', [$existing['id']]);
        $db->delete('resource_suggestions', 'syllabus_id = ?', [$existing['id']]);
        $db->delete('syllabus_analysis', 'syllabus_id = ?', [$existing['id']]);
        $db->delete('syllabus', 'id = ?', [$existing['id']]);
    }
    
    // Save to database
    $syllabus_id = $db->insert('syllabus', [
        'student_id' => $user['id'],
        'subject_id' => $subject_id,
        'file_path' => $file_path,
        'extracted_text' => $extracted_text,
        'status' => 'processing'
    ]);
    
    // STEP 1: Generate Quiz Questions
    require_once '../../includes/nlp.php';
    $quiz_result = NLPProcessor::processSyllabus($syllabus_id);
    
    // STEP 2: Generate Resources from Syllabus
    $resource_result = SyllabusResourceGenerator::generateResourcesFromSyllabus($syllabus_id);
    
    // Update status to completed
    $db->update('syllabus', ['status' => 'completed'], 'id = ?', [$syllabus_id]);
    
    // Prepare success message
    $message = "✅ Syllabus uploaded successfully! ";
    if ($quiz_result['success']) {
        $message .= $quiz_result['message'] . " ";
    }
    if ($resource_result['success']) {
        $message .= count($resource_result['topics']) . " topics identified. Resources generated!";
    }
    
    setFlash('success', $message);
    redirect('modules/student/dashboard.php');
}

// Function to extract text from uploaded file
function extractTextFromFile($file_path, $filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    
    if ($ext == 'txt') {
        return file_get_contents($file_path);
    }
    
    if ($ext == 'pdf') {
        return extractTextFromPDF($file_path);
    }
    
    return '';
}

// Simple PDF text extraction
function extractTextFromPDF($pdf_path) {
    $text = '';
    
    // Try using pdftotext first
    $pdftotext_path = 'C:\\xampp\\php\\pdftotext.exe';
    
    if (file_exists($pdftotext_path)) {
        $output_file = tempnam(sys_get_temp_dir(), 'pdf_text');
        $cmd = '"' . $pdftotext_path . '" -layout -enc UTF-8 "' . $pdf_path . '" "' . $output_file . '" 2>&1';
        exec($cmd, $output, $return_var);
        
        if ($return_var === 0 && file_exists($output_file)) {
            $text = file_get_contents($output_file);
            unlink($output_file);
            if (!empty(trim($text))) {
                return trim($text);
            }
        }
    }
    
    // Fallback: Try PHP PDF extraction
    $content = file_get_contents($pdf_path);
    preg_match_all('/\(([^)]+)\)/', $content, $matches);
    if (!empty($matches[1])) {
        $text = implode(' ', $matches[1]);
        $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $text);
        $text = preg_replace('/\s+/', ' ', $text);
    }
    
    // Clean up
    $text = preg_replace('/[^a-zA-Z0-9\s\.\,\;\:\!\?\-]/', ' ', $text);
    $text = preg_replace('/\s+/', ' ', $text);
    
    return trim($text);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Upload Syllabus - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .upload-area {
            border: 2px dashed #ccc;
            border-radius: 10px;
            padding: 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover, .upload-area.dragover {
            border-color: #007bff;
            background-color: #f8f9fa;
        }
        .file-info {
            margin-top: 10px;
            font-size: 14px;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap"></i> <?php echo SITE_NAME; ?>
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
        <?php echo displayFlash(); ?>
        
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0"><i class="fas fa-upload"></i> Upload Syllabus</h4>
                    </div>
                    <div class="card-body">
                        <form method="POST" enctype="multipart/form-data" id="uploadForm">
                            <div class="mb-3">
                                <label for="subject_id" class="form-label fw-bold">
                                    <i class="fas fa-book"></i> Select Subject
                                </label>
                                <select name="subject_id" id="subject_id" class="form-select" required>
                                    <option value="">-- Choose a subject --</option>
                                    <?php foreach ($subjects as $subject): ?>
                                    <option value="<?php echo $subject['id']; ?>">
                                        <?php echo $subject['code'] . ' - ' . $subject['name']; ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Paste Text Option -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-paste"></i> Option 1: Paste Syllabus Content (Recommended)
                                </label>
                                <textarea name="syllabus_text" class="form-control" rows="6" 
                                    placeholder="Paste your syllabus content here for best results...
                                    
Example:
Course Topics:
1. Vectors and Vector Spaces
2. Matrices and Matrix Operations
3. Linear Transformations
4. Eigenvalues and Eigenvectors
5. Systems of Linear Equations"></textarea>
                                <small class="text-muted">Paste your syllabus text for automatic quiz and resource generation</small>
                            </div>
                            
                            <div class="text-center my-2">
                                <span class="badge bg-secondary">OR</span>
                            </div>
                            
                            <!-- File Upload Option -->
                            <div class="mb-3">
                                <label class="form-label fw-bold">
                                    <i class="fas fa-file-pdf"></i> Option 2: Upload PDF File
                                </label>
                                <div class="upload-area" id="uploadArea">
                                    <i class="fas fa-cloud-upload-alt fa-3x text-muted mb-3"></i>
                                    <p class="mb-2">Drag & drop your PDF file here or click to browse</p>
                                    <input type="file" name="syllabus_file" id="syllabus_file" class="d-none" accept=".pdf,.txt">
                                    <button type="button" class="btn btn-outline-primary btn-sm" onclick="document.getElementById('syllabus_file').click()">
                                        <i class="fas fa-folder-open"></i> Browse Files
                                    </button>
                                    <div class="file-info mt-2" id="fileInfo"></div>
                                </div>
                                <small class="text-muted">
                                    <i class="fas fa-info-circle"></i> 
                                    Upload PDF (Max 10MB). System will extract text and generate content.
                                </small>
                            </div>
                            
                            <button type="submit" class="btn btn-primary btn-lg w-100" id="submitBtn">
                                <i class="fas fa-upload"></i> Upload & Generate Everything
                            </button>
                            <a href="dashboard.php" class="btn btn-secondary w-100 mt-2">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </form>
                    </div>
                </div>
                
                <!-- Info Card -->
                <div class="card mt-4">
                    <div class="card-header bg-info text-white">
                        <h5 class="mb-0"><i class="fas fa-magic"></i> What Gets Generated Automatically</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <i class="fas fa-file-alt fa-2x text-primary mb-2"></i>
                                <h6>Quiz Questions</h6>
                                <small>5 MCQ + 3 Short Answer</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-tags fa-2x text-success mb-2"></i>
                                <h6>Topics Extracted</h6>
                                <small>Key concepts from your syllabus</small>
                            </div>
                            <div class="col-md-4">
                                <i class="fas fa-link fa-2x text-warning mb-2"></i>
                                <h6>Learning Resources</h6>
                                <small>YouTube, Wikipedia, Courses</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('syllabus_file');
        const fileInfo = document.getElementById('fileInfo');
        const submitBtn = document.getElementById('submitBtn');
        
        uploadArea.addEventListener('click', () => fileInput.click());
        
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('dragover');
        });
        
        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('dragover');
        });
        
        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('dragover');
            const files = e.dataTransfer.files;
            if (files.length > 0) {
                fileInput.files = files;
                updateFileInfo(files[0]);
            }
        });
        
        fileInput.addEventListener('change', () => {
            if (fileInput.files.length > 0) {
                updateFileInfo(fileInput.files[0]);
            }
        });
        
        function updateFileInfo(file) {
            const fileSize = (file.size / 1024 / 1024).toFixed(2);
            if (file.type === 'application/pdf' || file.name.endsWith('.pdf')) {
                fileInfo.innerHTML = `<i class="fas fa-check-circle text-success"></i> PDF Selected: ${file.name} (${fileSize} MB)`;
                fileInfo.className = "file-info mt-2 text-success";
            } else if (file.name.endsWith('.txt')) {
                fileInfo.innerHTML = `<i class="fas fa-check-circle text-success"></i> Text file selected: ${file.name} (${fileSize} MB)`;
                fileInfo.className = "file-info mt-2 text-success";
            } else {
                fileInfo.innerHTML = `<i class="fas fa-exclamation-circle text-danger"></i> Please select a PDF or TXT file`;
                fileInfo.className = "file-info mt-2 text-danger";
            }
        }
        
        document.getElementById('uploadForm').addEventListener('submit', function() {
            submitBtn.disabled = true;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing... Generating Quiz & Resources...';
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>