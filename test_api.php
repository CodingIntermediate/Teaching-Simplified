<?php
require_once 'includes/free_quiz_api.php';

$testSyllabus = "CS101 - Introduction to Computer Science
Topics:
- Memory Organization: hierarchy, main memory, cache, auxiliary memory
- CPU: registers, stack organization, instruction formats
- Programming Languages: high-level, assembly, low-level
- Algorithms: flowcharts, problem solving";

$api = new FreeQuizAPI();
$result = $api->generateQuizQuestions($testSyllabus, "Computer Science");

echo "<h2>Free API Test Results</h2>";

if ($result && isset($result['mcq'])) {
    echo "<div style='color:green; font-weight:bold;'>✅ API Working!</div>";
    echo "<h3>Generated " . count($result['mcq']) . " MCQs:</h3>";
    
    foreach ($result['mcq'] as $i => $mcq) {
        echo "<div style='border:1px solid #ccc; margin:10px; padding:10px; border-radius:5px;'>";
        echo "<strong>Q" . ($i+1) . ":</strong> " . htmlspecialchars($mcq['question']) . "<br>";
        echo "<strong>Options:</strong><br>";
        foreach ($mcq['options'] as $opt) {
            echo "&nbsp;&nbsp;• " . htmlspecialchars($opt) . "<br>";
        }
        echo "<strong>Correct:</strong> " . $mcq['correct'] . "<br>";
        echo "<strong>Explanation:</strong> " . htmlspecialchars($mcq['explanation']) . "<br>";
        echo "</div>";
    }
    
    echo "<h3>Short Answer Questions:</h3>";
    foreach ($result['sa'] as $i => $sa) {
        echo "<div style='border:1px solid #ccc; margin:10px; padding:10px; border-radius:5px;'>";
        echo "<strong>SA" . ($i+1) . ":</strong> " . htmlspecialchars($sa['question']) . "<br>";
        echo "<strong>Keywords:</strong> " . htmlspecialchars($sa['keywords']) . "<br>";
        echo "</div>";
    }
} else {
    echo "<div style='color:red'>❌ API Failed. Trying fallback...</div>";
}
?>