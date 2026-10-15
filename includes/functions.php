<?php
// Helper functions - COMPLETE VERSION

function redirect($url) {
    header("Location: " . SITE_URL . $url);
    exit();
}

function setFlash($key, $message) {
    $_SESSION['flash'][$key] = $message;
}

function getFlash($key) {
    if (isset($_SESSION['flash'][$key])) {
        $message = $_SESSION['flash'][$key];
        unset($_SESSION['flash'][$key]);
        return $message;
    }
    return null;
}

function displayFlash() {
    $output = '';
    $types = ['success', 'error', 'warning', 'info'];
    
    foreach ($types as $type) {
        $message = getFlash($type);
        if ($message) {
            $output .= "<div class='alert alert-{$type} alert-dismissible fade show' role='alert'>
                            {$message}
                            <button type='button' class='btn-close' data-bs-dismiss='alert'></button>
                        </div>";
        }
    }
    
    return $output;
}

function sanitize($input) {
    return htmlspecialchars(strip_tags(trim($input)));
}

function uploadFile($file, $target_dir, $allowed_types = ['pdf']) {
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors = [
            UPLOAD_ERR_INI_SIZE => 'File too large (server limit)',
            UPLOAD_ERR_FORM_SIZE => 'File too large (form limit)',
            UPLOAD_ERR_PARTIAL => 'File only partially uploaded',
            UPLOAD_ERR_NO_FILE => 'No file uploaded',
            UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
            UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
            UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
        ];
        $message = $errors[$file['error']] ?? 'Unknown upload error';
        return ['success' => false, 'message' => $message];
    }
    
    // Check file size (max 10MB)
    if ($file['size'] > 10 * 1024 * 1024) {
        return ['success' => false, 'message' => 'File too large. Max 10MB'];
    }
    
    // Get file extension
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    
    if (!in_array($ext, $allowed_types)) {
        return ['success' => false, 'message' => 'Invalid file type. Allowed: ' . implode(', ', $allowed_types)];
    }
    
    // Create unique filename
    $filename = time() . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
    $target_path = $target_dir . $filename;
    
    // Create directory if not exists
    if (!is_dir($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    if (move_uploaded_file($file['tmp_name'], $target_path)) {
        return ['success' => true, 'filename' => $filename, 'path' => $target_path];
    }
    
    return ['success' => false, 'message' => 'Failed to save file'];
}
function getSubjectsBySemester($semester_id) {
    $db = db();
    return $db->select("SELECT * FROM subjects WHERE semester_id = ? ORDER BY code", [$semester_id]);
}

function getEnrolledSubjects($student_id) {
    $db = db();
    return $db->select("
        SELECT s.*, sem.semester_number, sem.academic_year 
        FROM subjects s
        JOIN enrollments e ON s.id = e.subject_id
        JOIN semesters sem ON s.semester_id = sem.id
        WHERE e.student_id = ? AND e.is_active = 1
        ORDER BY sem.academic_year DESC, sem.semester_number
    ", [$student_id]);
}

function getTeacherSubjects($teacher_id) {
    $db = db();
    return $db->select("
        SELECT s.*, sem.semester_number, sem.academic_year 
        FROM subjects s
        JOIN semesters sem ON s.semester_id = sem.id
        WHERE s.tutor_id = ?
        ORDER BY sem.academic_year DESC
    ", [$teacher_id]);
}

function getSyllabusById($id) {
    $db = db();
    return $db->selectOne("
        SELECT s.*, sub.name as subject_name, sub.code as subject_code,
               u.username, u.first_name, u.last_name
        FROM syllabus s
        JOIN subjects sub ON s.subject_id = sub.id
        JOIN users u ON s.student_id = u.id
        WHERE s.id = ?
    ", [$id]);
}

function getQuizQuestions($syllabus_id) {
    $db = db();
    
    $mcq = $db->select("SELECT * FROM mcq_questions WHERE syllabus_id = ?", [$syllabus_id]);
    $sa = $db->select("SELECT * FROM sa_questions WHERE syllabus_id = ?", [$syllabus_id]);
    
    return ['mcq' => $mcq, 'sa' => $sa];
}

function calculateSAscore($answer, $expected_keywords) {
    $keywords = explode(',', $expected_keywords);
    $answer_lower = strtolower($answer);
    $matched = 0;
    
    foreach ($keywords as $keyword) {
        if (strpos($answer_lower, trim(strtolower($keyword))) !== false) {
            $matched++;
        }
    }
    
    return count($keywords) > 0 ? ($matched / count($keywords)) * 100 : 100;
}

function getResourceSuggestions($syllabus_id) {
    $db = db();
    return $db->select("
        SELECT rs.*, GROUP_CONCAT(DISTINCT tr.title) as matched_resources
        FROM resource_suggestions rs
        LEFT JOIN tutor_resources tr ON rs.syllabus_id = tr.subject_id
        WHERE rs.syllabus_id = ?
        GROUP BY rs.id
    ", [$syllabus_id]);
}

function generateRandomPassword($length = 10) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()';
    return substr(str_shuffle($chars), 0, $length);
}

function getTimeAgo($timestamp) {
    $time_ago = strtotime($timestamp);
    $current_time = time();
    $time_difference = $current_time - $time_ago;
    $seconds = $time_difference;
    
    $minutes = round($seconds / 60);
    $hours = round($seconds / 3600);
    $days = round($seconds / 86400);
    $weeks = round($seconds / 604800);
    $months = round($seconds / 2629440);
    $years = round($seconds / 31553280);
    
    if ($seconds <= 60) {
        return "Just Now";
    } else if ($minutes <= 60) {
        return ($minutes == 1) ? "1 minute ago" : "$minutes minutes ago";
    } else if ($hours <= 24) {
        return ($hours == 1) ? "1 hour ago" : "$hours hours ago";
    } else if ($days <= 7) {
        return ($days == 1) ? "yesterday" : "$days days ago";
    } else if ($weeks <= 4.3) {
        return ($weeks == 1) ? "1 week ago" : "$weeks weeks ago";
    } else if ($months <= 12) {
        return ($months == 1) ? "1 month ago" : "$months months ago";
    } else {
        return ($years == 1) ? "1 year ago" : "$years years ago";
    }
}

function truncateText($text, $length = 100) {
    if (strlen($text) > $length) {
        $text = substr($text, 0, $length) . '...';
    }
    return $text;
}

function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

function getBrowserInfo() {
    $user_agent = $_SERVER['HTTP_USER_AGENT'];
    $browser = "Unknown";
    $platform = "Unknown";
    
    // Browser detection
    if (preg_match('/MSIE/i', $user_agent) && !preg_match('/Opera/i', $user_agent)) {
        $browser = 'Internet Explorer';
    } elseif (preg_match('/Firefox/i', $user_agent)) {
        $browser = 'Mozilla Firefox';
    } elseif (preg_match('/Chrome/i', $user_agent)) {
        $browser = 'Google Chrome';
    } elseif (preg_match('/Safari/i', $user_agent)) {
        $browser = 'Apple Safari';
    } elseif (preg_match('/Opera/i', $user_agent)) {
        $browser = 'Opera';
    } elseif (preg_match('/Netscape/i', $user_agent)) {
        $browser = 'Netscape';
    }
    
    // Platform detection
    if (preg_match('/Windows/i', $user_agent)) {
        $platform = 'Windows';
    } elseif (preg_match('/Linux/i', $user_agent)) {
        $platform = 'Linux';
    } elseif (preg_match('/Mac/i', $user_agent)) {
        $platform = 'Mac';
    } elseif (preg_match('/Android/i', $user_agent)) {
        $platform = 'Android';
    } elseif (preg_match('/iPhone/i', $user_agent)) {
        $platform = 'iPhone';
    }
    
    return ['browser' => $browser, 'platform' => $platform, 'user_agent' => $user_agent];
}

function logActivity($user_id, $action, $details = null) {
    $db = db();
    $db->insert('user_activity_logs', [
        'user_id' => $user_id,
        'action' => $action,
        'details' => $details,
        'ip_address' => $_SERVER['REMOTE_ADDR'],
        'user_agent' => $_SERVER['HTTP_USER_AGENT']
    ]);
}

function getSiteStats() {
    $db = db();
    return [
        'total_users' => $db->selectOne("SELECT COUNT(*) as count FROM users")['count'],
        'total_subjects' => $db->selectOne("SELECT COUNT(*) as count FROM subjects")['count'],
        'total_syllabi' => $db->selectOne("SELECT COUNT(*) as count FROM syllabus")['count'],
        'total_quizzes' => $db->selectOne("SELECT COUNT(*) as count FROM quiz_attempts WHERE completed_at IS NOT NULL")['count']
    ];
}
?>