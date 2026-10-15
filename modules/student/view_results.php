<?php
require_once '../../config.php';
Auth::requireRole('student');

$db = db();
$user = Auth::getCurrentUser();

$attempt_id = $_GET['attempt_id'] ?? 0;
$subject_id = $_GET['subject_id'] ?? 0;

// If specific attempt ID is provided, show that attempt
if ($attempt_id) {
    $attempt = $db->selectOne("
        SELECT qa.*, s.name as subject_name, s.code as subject_code,
               u.first_name, u.last_name
        FROM quiz_attempts qa
        JOIN subjects s ON qa.subject_id = s.id
        JOIN users u ON qa.student_id = u.id
        WHERE qa.id = ? AND qa.student_id = ?
    ", [$attempt_id, $user['id']]);
    
    if (!$attempt) {
        setFlash('error', 'Quiz attempt not found');
        redirect('modules/student/dashboard.php');
    }
    
    // Get answers for this attempt
    $mcq_answers = $db->select("
        SELECT ma.*, mq.question_text, mq.option_a, mq.option_b, mq.option_c, mq.option_d,
               mq.correct_answer, mq.explanation
        FROM mcq_answers ma
        JOIN mcq_questions mq ON ma.question_id = mq.id
        WHERE ma.attempt_id = ?
    ", [$attempt_id]);
    
    $sa_answers = $db->select("
        SELECT sa.*, sq.question_text, sq.expected_keywords
        FROM sa_answers sa
        JOIN sa_questions sq ON sa.question_id = sq.id
        WHERE sa.attempt_id = ?
    ", [$attempt_id]);
    
    // Get all attempts for this subject to show in sidebar
    $all_attempts = $db->select("
        SELECT id, score, completed_at, created_at
        FROM quiz_attempts
        WHERE student_id = ? AND subject_id = ? AND completed_at IS NOT NULL
        ORDER BY completed_at DESC
    ", [$user['id'], $attempt['subject_id']]);
    
} elseif ($subject_id) {
    // Show ALL attempts for this subject
    $subject = $db->selectOne("SELECT name, code FROM subjects WHERE id = ?", [$subject_id]);
    
    if (!$subject) {
        setFlash('error', 'Subject not found');
        redirect('modules/student/dashboard.php');
    }
    
    // Get all attempts for this subject
    $all_attempts = $db->select("
        SELECT qa.*, 
               (SELECT COUNT(*) FROM mcq_answers WHERE attempt_id = qa.id) as mcq_count,
               (SELECT COUNT(*) FROM sa_answers WHERE attempt_id = qa.id) as sa_count
        FROM quiz_attempts qa
        WHERE qa.student_id = ? AND qa.subject_id = ? AND qa.completed_at IS NOT NULL
        ORDER BY qa.completed_at DESC
    ", [$user['id'], $subject_id]);
    
    if (empty($all_attempts)) {
        setFlash('error', 'No quiz attempts found for this subject');
        redirect('modules/student/dashboard.php');
    }
    
    // If there's only one attempt, show it directly
    if (count($all_attempts) == 1) {
        header("Location: view_results.php?attempt_id=" . $all_attempts[0]['id']);
        exit();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Quiz Results - <?php echo SITE_NAME; ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .score-card {
            text-align: center;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .score-circle {
            width: 150px;
            height: 150px;
            border-radius: 50%;
            background: conic-gradient(#28a745 0deg, #e9ecef 0deg);
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
            position: relative;
        }
        .score-inner {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: white;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
        }
        .correct-answer {
            background-color: #d4edda;
            border-left: 4px solid #28a745;
        }
        .wrong-answer {
            background-color: #f8d7da;
            border-left: 4px solid #dc3545;
        }
        .attempt-card {
            cursor: pointer;
            transition: all 0.2s;
        }
        .attempt-card:hover {
            transform: translateX(5px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .attempt-card.active {
            border-left: 4px solid #007bff;
            background-color: #f0f8ff;
        }
        .best-score {
            background-color: #d4edda;
            border: 1px solid #28a745;
        }
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="<?php echo SITE_URL; ?>"><?php echo SITE_NAME; ?></a>
            <div class="navbar-nav ms-auto">
                <span class="nav-link">Welcome, <?php echo $_SESSION['full_name']; ?></span>
                <a class="nav-link" href="dashboard.php">Dashboard</a>
                <a class="nav-link" href="../auth/logout.php">Logout</a>
            </div>
        </div>
    </nav>

    <div class="container mt-4">
        <?php echo displayFlash(); ?>
        
        <?php if (isset($all_attempts) && count($all_attempts) > 1 && !isset($attempt)): ?>
            <!-- Show all attempts list -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0">
                        <i class="fas fa-history"></i> 
                        All Quiz Attempts: <?php echo htmlspecialchars($subject['code'] . ' - ' . $subject['name']); ?>
                    </h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <?php 
                        $best_score = 0;
                        foreach ($all_attempts as $att):
                            if ($att['score'] > $best_score) $best_score = $att['score'];
                        endforeach;
                        ?>
                        <div class="alert alert-info mb-4">
                            <i class="fas fa-chart-line"></i> 
                            You have taken this quiz <strong><?php echo count($all_attempts); ?></strong> time(s)
                            <?php if ($best_score > 0): ?>
                                | Best Score: <strong><?php echo round($best_score, 1); ?>%</strong>
                            <?php endif; ?>
                        </div>
                        
                        <?php foreach ($all_attempts as $index => $att): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card attempt-card <?php echo $att['score'] == $best_score && $best_score > 0 ? 'best-score' : ''; ?>" 
                                 onclick="window.location.href='view_results.php?attempt_id=<?php echo $att['id']; ?>'">
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h5 class="mb-1">Attempt #<?php echo count($all_attempts) - $index; ?></h5>
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> <?php echo date('F d, Y h:i A', strtotime($att['completed_at'])); ?>
                                            </small>
                                        </div>
                                        <div class="text-center">
                                            <div class="score-circle small-circle" style="width: 70px; height: 70px; background: conic-gradient(#28a745 0deg <?php echo ($att['score'] / 100) * 360; ?>deg, #e9ecef <?php echo ($att['score'] / 100) * 360; ?>deg 360deg);">
                                                <div class="score-inner" style="width: 55px; height: 55px;">
                                                    <strong style="font-size: 16px;"><?php echo round($att['score'], 1); ?>%</strong>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mt-2">
                                        <span class="badge bg-info"><?php echo $att['mcq_count']; ?> MCQ</span>
                                        <span class="badge bg-warning"><?php echo $att['sa_count']; ?> SA</span>
                                        <?php if ($att['score'] == $best_score && $best_score > 0): ?>
                                            <span class="badge bg-success"><i class="fas fa-trophy"></i> Best Score</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="text-center mt-3">
                        <a href="take_quiz.php?syllabus_id=<?php echo $all_attempts[0]['syllabus_id']; ?>" class="btn btn-success">
                            <i class="fas fa-play"></i> Take Quiz Again
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </div>
            </div>
        <?php elseif (isset($attempt)): ?>
            <!-- Show specific attempt details -->
            <div class="row">
                <div class="col-md-9">
                    <!-- Score Card -->
                    <div class="card score-card bg-light">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start">
                                <h3><?php echo htmlspecialchars($attempt['subject_code'] . ' - ' . $attempt['subject_name']); ?></h3>
                                <a href="view_results.php?subject_id=<?php echo $attempt['subject_id']; ?>" class="btn btn-sm btn-outline-primary">
                                    <i class="fas fa-list"></i> View All Attempts
                                </a>
                            </div>
                            <div class="score-circle" style="background: conic-gradient(#28a745 0deg <?php echo ($attempt['score'] / 100) * 360; ?>deg, #e9ecef <?php echo ($attempt['score'] / 100) * 360; ?>deg 360deg);">
                                <div class="score-inner">
                                    <h1 class="mb-0"><?php echo round($attempt['score'], 1); ?>%</h1>
                                    <small>Score</small>
                                </div>
                            </div>
                            <div class="row mt-4">
                                <div class="col-md-3">
                                    <div class="alert alert-info">
                                        <i class="fas fa-calendar"></i> Completed: <?php echo date('F d, Y h:i A', strtotime($attempt['completed_at'])); ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="alert alert-success">
                                        <i class="fas fa-check-circle"></i> MCQ: <?php echo count(array_filter($mcq_answers, function($a) { return $a['is_correct']; })); ?>/<?php echo count($mcq_answers); ?>
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="alert alert-warning">
                                        <i class="fas fa-pen"></i> SA: <?php echo count($sa_answers) > 0 ? round(array_sum(array_column($sa_answers, 'score')) / count($sa_answers), 1) : 0; ?>% avg
                                    </div>
                                </div>
                                <div class="col-md-3">
                                    <div class="alert <?php echo $attempt['score'] >= 70 ? 'alert-success' : ($attempt['score'] >= 50 ? 'alert-warning' : 'alert-danger'); ?>">
                                        <i class="fas fa-trophy"></i> 
                                        <?php 
                                        if ($attempt['score'] >= 70) echo "Excellent!";
                                        elseif ($attempt['score'] >= 50) echo "Good! Keep practicing!";
                                        else echo "Needs improvement. Review the material!";
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- MCQ Answers -->
                    <div class="card mt-4">
                        <div class="card-header bg-primary text-white">
                            <h5><i class="fas fa-question-circle"></i> Multiple Choice Questions</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($mcq_answers as $index => $answer): ?>
                            <div class="card mb-3 <?php echo $answer['is_correct'] ? 'correct-answer' : 'wrong-answer'; ?>">
                                <div class="card-body">
                                    <h6>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></h6>
                                    <div class="row mt-2">
                                        <div class="col-md-6">
                                            <small>Your answer: <strong><?php echo $answer['selected_answer']; ?></strong></small>
                                        </div>
                                        <div class="col-md-6">
                                            <small>Correct answer: <strong><?php echo $answer['correct_answer']; ?></strong></small>
                                        </div>
                                    </div>
                                    <?php if (!$answer['is_correct'] && $answer['explanation']): ?>
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-info-circle"></i> <?php echo htmlspecialchars($answer['explanation']); ?>
                                        </small>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Short Answer Responses -->
                    <div class="card mt-4">
                        <div class="card-header bg-info text-white">
                            <h5><i class="fas fa-pen-alt"></i> Short Answer Questions</h5>
                        </div>
                        <div class="card-body">
                            <?php foreach ($sa_answers as $index => $answer): ?>
                            <div class="card mb-3">
                                <div class="card-body">
                                    <h6>Question <?php echo $index + 1; ?>: <?php echo htmlspecialchars($answer['question_text']); ?></h6>
                                    <div class="mt-2">
                                        <strong>Your answer:</strong>
                                        <p class="mt-1"><?php echo nl2br(htmlspecialchars($answer['answer_text'] ?: '(No answer provided)')); ?></p>
                                    </div>
                                    <div class="mt-2">
                                        <span class="badge bg-info">Score: <?php echo round($answer['score'], 1); ?>%</span>
                                        <small class="text-muted ms-2">
                                            Expected keywords: <?php echo htmlspecialchars($answer['expected_keywords']); ?>
                                        </small>
                                    </div>
                                    <?php if (!empty($answer['feedback'])): ?>
                                    <div class="mt-2 alert alert-info">
                                        <i class="fas fa-comment"></i> <?php echo htmlspecialchars($answer['feedback']); ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="text-center mt-4">
                        <a href="take_quiz.php?syllabus_id=<?php echo $attempt['syllabus_id']; ?>" class="btn btn-success">
                            <i class="fas fa-play"></i> Take Quiz Again
                        </a>
                        <a href="view_results.php?subject_id=<?php echo $attempt['subject_id']; ?>" class="btn btn-info">
                            <i class="fas fa-list"></i> View All Attempts
                        </a>
                        <a href="view_resources.php?syllabus_id=<?php echo $attempt['syllabus_id']; ?>" class="btn btn-warning">
                            <i class="fas fa-book"></i> View Resources
                        </a>
                        <a href="dashboard.php" class="btn btn-secondary">Back to Dashboard</a>
                    </div>
                </div>
                
                <!-- Sidebar with attempt history -->
                <div class="col-md-3">
                    <div class="card">
                        <div class="card-header bg-primary text-white">
                            <h6 class="mb-0"><i class="fas fa-history"></i> Attempt History</h6>
                        </div>
                        <div class="card-body p-0">
                            <div class="list-group list-group-flush">
                                <?php foreach ($all_attempts as $att): ?>
                                <a href="view_results.php?attempt_id=<?php echo $att['id']; ?>" 
                                   class="list-group-item list-group-item-action <?php echo $att['id'] == $attempt_id ? 'active' : ''; ?>">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <small>Attempt #<?php echo count($all_attempts) - array_search($att['id'], array_column($all_attempts, 'id')) + 1; ?></small>
                                            <br>
                                            <small class="text-muted"><?php echo date('M d, H:i', strtotime($att['completed_at'])); ?></small>
                                        </div>
                                        <span class="badge <?php echo $att['score'] >= 70 ? 'bg-success' : ($att['score'] >= 50 ? 'bg-warning' : 'bg-danger'); ?>">
                                            <?php echo round($att['score'], 1); ?>%
                                        </span>
                                    </div>
                                </a>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <style>
        .small-circle {
            width: 70px;
            height: 70px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
        }
        .small-circle .score-inner {
            width: 55px;
            height: 55px;
        }
    </style>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>