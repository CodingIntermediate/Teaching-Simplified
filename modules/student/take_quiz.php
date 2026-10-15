<?php
require_once '../../config.php';
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

// Get random questions from database - 3 MCQ and 2 SA
function getRandomQuizQuestions($db, $syllabus_id) {
    $questions = ['mcq' => [], 'sa' => []];
    
    // Get 3 random MCQ questions
    $mcq = $db->select("
        SELECT * FROM mcq_questions 
        WHERE syllabus_id = ? 
        ORDER BY RAND() 
        LIMIT 3
    ", [$syllabus_id]);
    
    // Get 2 random Short Answer questions
    $sa = $db->select("
        SELECT * FROM sa_questions 
        WHERE syllabus_id = ? 
        ORDER BY RAND() 
        LIMIT 2
    ", [$syllabus_id]);
    
    $questions['mcq'] = $mcq;
    $questions['sa'] = $sa;
    
    return $questions;
}

// Get random questions from database
$questions = getRandomQuizQuestions($db, $syllabus_id);

// Check if questions exist
if (empty($questions['mcq']) && empty($questions['sa'])) {
    setFlash('error', 'No questions found for this syllabus. Please upload syllabus again.');
    redirect('modules/student/dashboard.php');
}

// Get previous attempts count
$attempts_count = $db->selectOne("SELECT COUNT(*) as count FROM quiz_attempts WHERE syllabus_id = ? AND student_id = ?", 
                                    [$syllabus_id, $user['id']])['count'];

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Create attempt
    $attempt_id = $db->insert('quiz_attempts', [
        'student_id' => $user['id'],
        'subject_id' => $syllabus['subject_id'],
        'syllabus_id' => $syllabus_id,
        'started_at' => date('Y-m-d H:i:s')
    ]);
    
    // Process MCQ answers
    $mcq_correct = 0;
    foreach ($questions['mcq'] as $mcq) {
        $answer_key = 'mcq_' . $mcq['id'];
        $selected = $_POST[$answer_key] ?? '';
        $is_correct = ($selected == $mcq['correct_answer']);
        
        if ($is_correct) $mcq_correct++;
        
        $db->insert('mcq_answers', [
            'attempt_id' => $attempt_id,
            'question_id' => $mcq['id'],
            'selected_answer' => $selected,
            'is_correct' => $is_correct ? 1 : 0
        ]);
    }
    
    // Process short answers
    $sa_scores = [];
    foreach ($questions['sa'] as $sa) {
        $answer_key = 'sa_' . $sa['id'];
        $answer_text = $_POST[$answer_key] ?? '';
        $score = calculateSAscore($answer_text, $sa['expected_keywords']);
        $sa_scores[] = $score;
        
        $db->insert('sa_answers', [
            'attempt_id' => $attempt_id,
            'question_id' => $sa['id'],
            'answer_text' => $answer_text,
            'score' => $score
        ]);
    }
    
    // Calculate total score (60% MCQ, 40% SA)
    $mcq_percentage = count($questions['mcq']) > 0 ? ($mcq_correct / count($questions['mcq'])) * 60 : 0;
    $sa_percentage = count($sa_scores) > 0 ? (array_sum($sa_scores) / count($sa_scores)) * 0.4 : 0;
    $total_score = $mcq_percentage + $sa_percentage;
    
    // Update attempt
    $db->update('quiz_attempts', [
        'completed_at' => date('Y-m-d H:i:s'),
        'score' => $total_score
    ], 'id = ?', [$attempt_id]);
    
    setFlash('success', "Quiz completed! Your score: " . number_format($total_score, 1) . "% (Attempt #" . ($attempts_count + 1) . ")");
    redirect('modules/student/view_results.php?attempt_id=' . $attempt_id);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Take Quiz - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .quiz-question {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #007bff;
        }
        .timer {
            font-size: 24px;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .timer.warning {
            color: #dc3545;
            animation: blink 1s infinite;
        }
        .attempt-badge {
            position: absolute;
            top: 20px;
            right: 20px;
        }
        @keyframes blink {
            50% { opacity: 0.5; }
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>">
                <i class="fas fa-graduation-cap"></i> Take Quiz
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
        <div class="row">
            <div class="col-md-12">
                <div class="card position-relative">
                    <div class="attempt-badge">
                        <span class="badge bg-info">
                            <i class="fas fa-history"></i> Attempt #<?php echo $attempts_count + 1; ?>
                        </span>
                    </div>
                    <div class="card-header bg-primary text-white">
                        <h4 class="mb-0">
                            <i class="fas fa-question-circle"></i> 
                            Quiz: <?php echo $syllabus['subject_code'] . ' - ' . $syllabus['subject_name']; ?>
                        </h4>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> 
                            Total Questions: <?php echo count($questions['mcq']) + count($questions['sa']); ?> 
                            (<?php echo count($questions['mcq']); ?> MCQ + <?php echo count($questions['sa']); ?> Short Answer)
                            <?php if ($attempts_count > 0): ?>
                            <br><i class="fas fa-chart-line"></i> You have taken this quiz <?php echo $attempts_count; ?> time(s) before. Try to beat your best score!
                            <?php endif; ?>
                            <br><i class="fas fa-random"></i> Questions are randomly selected each attempt!
                        </div>
                        
                        <div class="timer" id="timer">
                            <i class="fas fa-hourglass-half"></i> Time Remaining: <span id="time">30:00</span>
                        </div>
                        
                        <form method="POST" id="quizForm">
                            <?php if (!empty($questions['mcq'])): ?>
                                <h4 class="mt-3"><i class="fas fa-check-circle"></i> Multiple Choice Questions</h4>
                                <?php foreach ($questions['mcq'] as $index => $mcq): ?>
                                <div class="quiz-question">
                                    <h5><span class="badge bg-primary me-2"><?php echo $index + 1; ?></span> <?php echo htmlspecialchars($mcq['question_text']); ?></h5>
                                    <div class="mt-3 ms-4">
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" 
                                                   name="mcq_<?php echo $mcq['id']; ?>" 
                                                   value="A" id="mcq_<?php echo $mcq['id']; ?>_a" required>
                                            <label class="form-check-label" for="mcq_<?php echo $mcq['id']; ?>_a">
                                                <strong>A)</strong> <?php echo htmlspecialchars($mcq['option_a']); ?>
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" 
                                                   name="mcq_<?php echo $mcq['id']; ?>" 
                                                   value="B" id="mcq_<?php echo $mcq['id']; ?>_b">
                                            <label class="form-check-label" for="mcq_<?php echo $mcq['id']; ?>_b">
                                                <strong>B)</strong> <?php echo htmlspecialchars($mcq['option_b']); ?>
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" 
                                                   name="mcq_<?php echo $mcq['id']; ?>" 
                                                   value="C" id="mcq_<?php echo $mcq['id']; ?>_c">
                                            <label class="form-check-label" for="mcq_<?php echo $mcq['id']; ?>_c">
                                                <strong>C)</strong> <?php echo htmlspecialchars($mcq['option_c']); ?>
                                            </label>
                                        </div>
                                        <div class="form-check mb-2">
                                            <input class="form-check-input" type="radio" 
                                                   name="mcq_<?php echo $mcq['id']; ?>" 
                                                   value="D" id="mcq_<?php echo $mcq['id']; ?>_d">
                                            <label class="form-check-label" for="mcq_<?php echo $mcq['id']; ?>_d">
                                                <strong>D)</strong> <?php echo htmlspecialchars($mcq['option_d']); ?>
                                            </label>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <?php if (!empty($questions['sa'])): ?>
                                <h4 class="mt-4"><i class="fas fa-pen-alt"></i> Short Answer Questions</h4>
                                <?php foreach ($questions['sa'] as $index => $sa): ?>
                                <div class="quiz-question">
                                    <h5><span class="badge bg-success me-2"><?php echo count($questions['mcq']) + $index + 1; ?></span> <?php echo htmlspecialchars($sa['question_text']); ?></h5>
                                    <div class="mt-3">
                                        <textarea class="form-control" 
                                                  name="sa_<?php echo $sa['id']; ?>" 
                                                  rows="4" 
                                                  placeholder="Type your answer here..."
                                                  required></textarea>
                                        <small class="text-muted mt-2 d-block">
                                            <i class="fas fa-lightbulb"></i> Expected keywords: <?php echo htmlspecialchars($sa['expected_keywords']); ?>
                                        </small>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            
                            <div class="text-center mt-4">
                                <button type="submit" class="btn btn-success btn-lg px-5" onclick="return confirm('Are you sure you want to submit your quiz?')">
                                    <i class="fas fa-check-circle"></i> Submit Quiz
                                </button>
                                <a href="dashboard.php" class="btn btn-danger btn-lg px-5">
                                    <i class="fas fa-times-circle"></i> Cancel
                                </a>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Timer functionality (30 minutes)
        let timeLeft = 30 * 60;
        const timerElement = document.getElementById('time');
        
        function updateTimer() {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerElement.textContent = `${minutes.toString().padStart(2, '0')}:${seconds.toString().padStart(2, '0')}`;
            
            if (timeLeft <= 0) {
                clearInterval(timerInterval);
                alert('Time is up! Submitting your quiz...');
                document.getElementById('quizForm').submit();
            } else if (timeLeft <= 300) {
                document.querySelector('.timer').classList.add('warning');
            }
            
            timeLeft--;
        }
        
        const timerInterval = setInterval(updateTimer, 1000);
        
        // Warn before leaving page
        window.addEventListener('beforeunload', function(e) {
            e.preventDefault();
            e.returnValue = 'Are you sure you want to leave? Your progress will be lost.';
        });
        
        document.getElementById('quizForm').addEventListener('submit', function() {
            window.removeEventListener('beforeunload', function() {});
        });
    </script>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>