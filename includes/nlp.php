<?php
require_once __DIR__ . '/free_quiz_api.php';
require_once __DIR__ . '/resource_generator.php';

class NLPProcessor {
    
    public static function processSyllabus($syllabus_id) {
        $db = db();
        
        $syllabus = $db->selectOne("SELECT * FROM syllabus WHERE id = ?", [$syllabus_id]);
        if (!$syllabus) {
            return ['success' => false, 'message' => 'Syllabus not found'];
        }
        
        $db->update('syllabus', ['status' => 'processing'], 'id = ?', [$syllabus_id]);
        
        try {
            // Get extracted text
            $text = $syllabus['extracted_text'] ?? '';
            
            if (empty($text) || strlen($text) < 100) {
                $subject = $db->selectOne("SELECT name FROM subjects WHERE id = ?", [$syllabus['subject_id']]);
                $text = "This is a syllabus for {$subject['name']}. It covers: memory organization, CPU architecture, programming languages, algorithms, and data structures.";
            }
            
            // Get subject info
            $subject = $db->selectOne("SELECT name FROM subjects WHERE id = ?", [$syllabus['subject_id']]);
            
            // Use Free API (no quota limits)
            $api = new FreeQuizAPI();
            $questions = $api->generateQuizQuestions($text, $subject['name']);
            
            if ($questions && !empty($questions['mcq'])) {
                // Delete existing questions
                $db->delete('mcq_questions', 'syllabus_id = ?', [$syllabus_id]);
                $db->delete('sa_questions', 'syllabus_id = ?', [$syllabus_id]);
                
                // Save MCQ questions
                foreach ($questions['mcq'] as $mcq) {
                    $options = $mcq['options'] ?? ['Option A', 'Option B', 'Option C', 'Option D'];
                    while (count($options) < 4) {
                        $options[] = 'Option ' . chr(65 + count($options));
                    }
                    
                    $db->insert('mcq_questions', [
                        'subject_id' => $syllabus['subject_id'],
                        'syllabus_id' => $syllabus_id,
                        'question_text' => $mcq['question'],
                        'option_a' => $options[0],
                        'option_b' => $options[1],
                        'option_c' => $options[2],
                        'option_d' => $options[3],
                        'correct_answer' => $mcq['correct'] ?? 'A',
                        'explanation' => $mcq['explanation'] ?? 'Based on the syllabus content.'
                    ]);
                }
                
                // Save Short Answer questions
                foreach ($questions['sa'] as $sa) {
                    $db->insert('sa_questions', [
                        'subject_id' => $syllabus['subject_id'],
                        'syllabus_id' => $syllabus_id,
                        'question_text' => $sa['question'],
                        'expected_keywords' => $sa['keywords'] ?? 'concepts, principles, applications'
                    ]);
                }
                
                $db->update('syllabus', ['status' => 'completed'], 'id = ?', [$syllabus_id]);
                
                return ['success' => true, 'message' => '✅ Syllabus processed successfully!', 'mcq_count' => count($questions['mcq']), 'sa_count' => count($questions['sa'])];
            } else {
                // Fallback to local generation
                return self::processSyllabusLocal($syllabus_id, $text, $subject['name']);
            }
            
        } catch (Exception $e) {
            $db->update('syllabus', ['status' => 'failed', 'error_message' => $e->getMessage()], 'id = ?', [$syllabus_id]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
    
    private static function processSyllabusLocal($syllabus_id, $text, $subject_name) {
        $db = db();
        
        $db->delete('mcq_questions', 'syllabus_id = ?', [$syllabus_id]);
        $db->delete('sa_questions', 'syllabus_id = ?', [$syllabus_id]);
        
        // Extract meaningful terms
        preg_match_all('/\b([A-Z][a-z]{4,}(?:\s+[A-Z][a-z]{4,})?)\b/', $text, $matches);
        $terms = array_unique(array_slice($matches[0] ?? [], 0, 8));
        
        if (empty($terms)) {
            $terms = ['Memory', 'CPU', 'Programming', 'Algorithms', 'Data Structures', 'Variables', 'Functions', 'Loops'];
        }
        
        $subject_short = explode(' ', $subject_name)[0];
        
        // Create MCQs
        for ($i = 0; $i < 5; $i++) {
            $term = $terms[$i % count($terms)];
            $db->insert('mcq_questions', [
                'subject_id' => $db->selectOne("SELECT subject_id FROM syllabus WHERE id = ?", [$syllabus_id])['subject_id'],
                'syllabus_id' => $syllabus_id,
                'question_text' => "What is the role of '{$term}' in {$subject_short}?",
                'option_a' => "A fundamental concept in {$subject_short}",
                'option_b' => "A minor topic mentioned briefly",
                'option_c' => "An advanced topic for later study",
                'option_d' => "A prerequisite concept",
                'correct_answer' => 'A',
                'explanation' => "According to the syllabus, '{$term}' is an important concept."
            ]);
        }
        
        // Create Short Answer questions
        for ($i = 0; $i < 3; $i++) {
            $term = $terms[$i % count($terms)];
            $db->insert('sa_questions', [
                'subject_id' => $db->selectOne("SELECT subject_id FROM syllabus WHERE id = ?", [$syllabus_id])['subject_id'],
                'syllabus_id' => $syllabus_id,
                'question_text' => "Explain the concept of '{$term}' as described in the syllabus.",
                'expected_keywords' => strtolower($term) . ", definition, importance"
            ]);
        }
        
        $db->update('syllabus', ['status' => 'completed'], 'id = ?', [$syllabus_id]);
        
        return ['success' => true, 'message' => 'Syllabus processed (local fallback)', 'mcq_count' => 5, 'sa_count' => 3];
    }
    public static function generateSyllabusResources($syllabus_id) {
    return SyllabusResourceGenerator::generateResourcesFromSyllabus($syllabus_id);
}
}
?>