<?php
require_once 'config.php';
require_once 'includes/resource_generator.php';

$db = db();

// Get all completed syllabi
$syllabi = $db->select("
    SELECT s.*, sub.name as subject_name 
    FROM syllabus s
    JOIN subjects sub ON s.subject_id = sub.id
    WHERE s.status = 'completed'
");

echo "<h2>Regenerating Resources for All Syllabi</h2>";

foreach ($syllabi as $syllabus) {
    echo "<h3>Processing: {$syllabus['subject_name']}</h3>";
    
    $generator = new SyllabusResourceGenerator();
    $result = $generator->generateResourcesFromSyllabus($syllabus['id']);
    
    if ($result['success']) {
        echo "<span style='color:green'>✅ Extracted " . count($result['topics']) . " topics</span><br>";
        echo "<span style='color:blue'>📚 Topics: " . implode(', ', array_slice($result['topics'], 0, 3)) . "...</span><br>";
    } else {
        echo "<span style='color:red'>❌ Failed: " . $result['message'] . "</span><br>";
    }
    echo "<br>";
}

echo "<hr>";
echo "<a href='modules/student/dashboard.php'>Go to Dashboard</a>";
?>