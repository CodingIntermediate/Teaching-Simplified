<?php
class DynamicNLPProcessor {
    
    // Extract clean text from PDF
    public static function extractTextFromPDF($pdf_path) {
        try {
            // Try pdftotext first
            if (self::hasPdftotext()) {
                $output_file = tempnam(sys_get_temp_dir(), 'pdf_text');
                $pdftotext = self::findPdftotext();
                exec('"' . $pdftotext . '" -layout -enc UTF-8 "' . $pdf_path . '" "' . $output_file . '"', $output, $return_var);
                
                if ($return_var === 0 && file_exists($output_file)) {
                    $text = file_get_contents($output_file);
                    unlink($output_file);
                    if (!empty(trim($text))) {
                        return self::cleanText($text);
                    }
                }
            }
            
            // Fallback: Extract readable text from PDF binary
            $content = file_get_contents($pdf_path);
            $text = preg_replace('/[^\x20-\x7E\x0A\x0D]/', ' ', $content);
            $text = preg_replace('/\s+/', ' ', $text);
            return self::cleanText($text);
            
        } catch (Exception $e) {
            return "";
        }
    }
    
    private static function cleanText($text) {
        // Remove PDF artifacts
        $text = preg_replace('/[0-9]+\s+[0-9]+\s+obj/', '', $text);
        $text = preg_replace('/endobj|stream|endstream/', '', $text);
        $text = preg_replace('/[0-9]+\s+[0-9]+\s+R/', '', $text);
        $text = preg_replace('/\/[A-Z][a-z]+/', '', $text);
        
        // Keep only readable sentences
        preg_match_all('/([A-Z][A-Za-z\s,;:\'\"\-\(\)]{20,200}[.!?])/', $text, $matches);
        if (!empty($matches[0])) {
            $text = implode(' ', $matches[0]);
        }
        
        // Remove multiple spaces
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }
    
    private static function hasPdftotext() {
        $paths = ['pdftotext', 'C:\\xampp\\php\\pdftotext.exe', '/usr/bin/pdftotext'];
        foreach ($paths as $path) {
            if (file_exists($path) || (PHP_OS_FAMILY !== 'Windows' && is_executable($path))) {
                return true;
            }
        }
        return false;
    }
    
    private static function findPdftotext() {
        $paths = ['pdftotext', 'C:\\xampp\\php\\pdftotext.exe', '/usr/bin/pdftotext'];
        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }
        return 'pdftotext';
    }
    
    // Extract key concepts from text
    public static function extractKeyConcepts($text) {
        $concepts = [];
        
        // Look for bullet points and numbered lists
        preg_match_all('/(?:^|\n)[\s]*[-•*][\s]*([A-Z][a-zA-Z\s]+)/m', $text, $bullet_matches);
        if (!empty($bullet_matches[1])) {
            $concepts = array_merge($concepts, $bullet_matches[1]);
        }
        
        // Look for headings (words in all caps or with colons)
        preg_match_all('/([A-Z][A-Z\s]+[A-Z]):?/m', $text, $heading_matches);
        if (!empty($heading_matches[1])) {
            foreach ($heading_matches[1] as $heading) {
                $concepts[] = trim($heading);
            }
        }
        
        // Look for defined terms (word followed by colon)
        preg_match_all('/([a-zA-Z\s]+):/', $text, $term_matches);
        if (!empty($term_matches[1])) {
            foreach ($term_matches[1] as $term) {
                if (strlen(trim($term)) < 30 && strlen(trim($term)) > 3) {
                    $concepts[] = trim($term);
                }
            }
        }
        
        return array_unique($concepts);
    }
    
    // Extract sentences that contain important information
    public static function extractImportantSentences($text) {
        $sentences = preg_split('/[.!?]+/', $text);
        $important = [];
        
        // Keywords that indicate important information
        $importance_keywords = [
            'define', 'is', 'are', 'refers to', 'means', 'includes', 'consists of',
            'important', 'key', 'main', 'essential', 'critical', 'fundamental',
            'must', 'should', 'will', 'can', 'able to', 'learn', 'understand'
        ];
        
        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if (strlen($sentence) > 30 && strlen($sentence) < 200) {
                foreach ($importance_keywords as $keyword) {
                    if (stripos($sentence, $keyword) !== false) {
                        $important[] = $sentence;
                        break;
                    }
                }
            }
        }
        
        return array_unique($important);
    }
    
    // Dynamically generate MCQ questions from content
    public static function generateDynamicMCQ($text, $num_questions = 5) {
        $concepts = self::extractKeyConcepts($text);
        $important_sentences = self::extractImportantSentences($text);
        
        $mcq_questions = [];
        
        // Method 1: Create questions from important sentences
        foreach ($important_sentences as $sentence) {
            if (count($mcq_questions) >= $num_questions) break;
            
            // Find key terms in the sentence
            $words = str_word_count($sentence, 1);
            $key_terms = array_filter($words, function($w) {
                return strlen($w) > 5 && !in_array(strtolower($w), ['the', 'and', 'for', 'with', 'this', 'that']);
            });
            
            if (!empty($key_terms)) {
                $key_term = array_shift($key_terms);
                
                // Create question by replacing key term with blank
                $question = str_ireplace($key_term, '______', $sentence);
                
                // Generate distractors from other concepts
                $distractors = [];
                foreach ($concepts as $concept) {
                    if (strtolower($concept) != strtolower($key_term) && !in_array($concept, $distractors)) {
                        $distractors[] = $concept;
                        if (count($distractors) >= 3) break;
                    }
                }
                
                // Add fallback distractors if needed
                $fallback = ['Option A', 'Option B', 'Option C'];
                while (count($distractors) < 3) {
                    $distractors[] = $fallback[count($distractors)];
                }
                
                // Create options
                $options = [$key_term, $distractors[0], $distractors[1], $distractors[2]];
                shuffle($options);
                $correct = chr(65 + array_search($key_term, $options));
                
                $mcq_questions[] = [
                    'question' => $question,
                    'options' => [
                        'A' => $options[0],
                        'B' => $options[1],
                        'C' => $options[2],
                        'D' => $options[3]
                    ],
                    'correct' => $correct,
                    'explanation' => "Based on the syllabus, '{$key_term}' is correct because: " . substr($sentence, 0, 100)
                ];
            }
        }
        
        // Method 2: Create questions from concepts
        if (count($mcq_questions) < $num_questions) {
            foreach ($concepts as $concept) {
                if (count($mcq_questions) >= $num_questions) break;
                if (strlen($concept) < 5 || strlen($concept) > 50) continue;
                
                $mcq_questions[] = [
                    'question' => "What is the definition of '{$concept}' as described in the syllabus?",
                    'options' => [
                        'A' => "A fundamental concept in " . self::getSubjectContext($text),
                        'B' => "An optional topic covered in the course",
                        'C' => "A historical reference only",
                        'D' => "Not relevant to the course"
                    ],
                    'correct' => 'A',
                    'explanation' => "According to the syllabus, '{$concept}' is a key concept in this course."
                ];
            }
        }
        
        return array_slice($mcq_questions, 0, $num_questions);
    }
    
    // Dynamically generate Short Answer questions
    public static function generateDynamicShortAnswer($text, $num_questions = 3) {
        $concepts = self::extractKeyConcepts($text);
        $important_sentences = self::extractImportantSentences($text);
        
        $sa_questions = [];
        
        // Create questions from important sentences
        foreach ($important_sentences as $sentence) {
            if (count($sa_questions) >= $num_questions) break;
            
            // Extract the main subject of the sentence
            $words = str_word_count($sentence, 1);
            $main_topic = '';
            foreach ($words as $word) {
                if (strlen($word) > 5 && ctype_upper(substr($word, 0, 1))) {
                    $main_topic = $word;
                    break;
                }
            }
            
            if (empty($main_topic)) {
                $main_topic = $concepts[count($sa_questions) % count($concepts)] ?? 'this concept';
            }
            
            $sa_questions[] = [
                'question' => "Explain the following statement in your own words: '{$sentence}'",
                'expected_keywords' => self::extractKeywordsFromSentence($sentence)
            ];
        }
        
        // Add questions from concepts if needed
        if (count($sa_questions) < $num_questions) {
            foreach ($concepts as $concept) {
                if (count($sa_questions) >= $num_questions) break;
                if (strlen($concept) > 5 && strlen($concept) < 50) {
                    $sa_questions[] = [
                        'question' => "Explain the concept of '{$concept}' as described in the syllabus. Include its importance and relevance.",
                        'expected_keywords' => strtolower($concept) . ", definition, importance, application"
                    ];
                }
            }
        }
        
        return array_slice($sa_questions, 0, $num_questions);
    }
    
    private static function extractKeywordsFromSentence($sentence) {
        $words = str_word_count($sentence, 1);
        $keywords = array_filter($words, function($w) {
            return strlen($w) > 4 && !in_array(strtolower($w), ['the', 'and', 'for', 'with', 'this', 'that', 'from', 'have', 'will', 'can']);
        });
        return implode(', ', array_slice(array_unique($keywords), 0, 5));
    }
    
    private static function getSubjectContext($text) {
        if (stripos($text, 'computer') !== false) return 'Computer Science';
        if (stripos($text, 'math') !== false) return 'Mathematics';
        if (stripos($text, 'physics') !== false) return 'Physics';
        if (stripos($text, 'business') !== false) return 'Business';
        return 'this subject';
    }
    
    // Main processing function
    public static function processSyllabus($syllabus_id) {
        $db = db();
        
        $syllabus = $db->selectOne("SELECT * FROM syllabus WHERE id = ?", [$syllabus_id]);
        if (!$syllabus) {
            return ['success' => false, 'message' => 'Syllabus not found'];
        }
        
        $db->update('syllabus', ['status' => 'processing'], 'id = ?', [$syllabus_id]);
        
        try {
            // Extract text
            $text = self::extractTextFromPDF($syllabus['file_path']);
            
            if (empty($text) || strlen($text) < 100) {
                throw new Exception("Could not extract enough text from PDF");
            }
            
            $db->update('syllabus', ['extracted_text' => substr($text, 0, 5000)], 'id = ?', [$syllabus_id]);
            
            // Get subject info
            $subject = $db->selectOne("SELECT name FROM subjects WHERE id = ?", [$syllabus['subject_id']]);
            
            // Extract concepts and sentences
            $concepts = self::extractKeyConcepts($text);
            $sentences = self::extractImportantSentences($text);
            
            // Save analysis
            $db->insert('syllabus_analysis', [
                'syllabus_id' => $syllabus_id,
                'topics' => implode('|', array_slice($concepts, 0, 10)),
                'keywords' => implode('|', array_slice($sentences, 0, 5)),
                'summary' => substr($text, 0, 500)
            ]);
            
            // Generate MCQ questions
            $mcq_questions = self::generateDynamicMCQ($text, 5);
            foreach ($mcq_questions as $mcq) {
                $db->insert('mcq_questions', [
                    'subject_id' => $syllabus['subject_id'],
                    'syllabus_id' => $syllabus_id,
                    'question_text' => $mcq['question'],
                    'option_a' => $mcq['options']['A'],
                    'option_b' => $mcq['options']['B'],
                    'option_c' => $mcq['options']['C'],
                    'option_d' => $mcq['options']['D'],
                    'correct_answer' => $mcq['correct'],
                    'explanation' => $mcq['explanation']
                ]);
            }
            
            // Generate Short Answer questions
            $sa_questions = self::generateDynamicShortAnswer($text, 3);
            foreach ($sa_questions as $sa) {
                $db->insert('sa_questions', [
                    'subject_id' => $syllabus['subject_id'],
                    'syllabus_id' => $syllabus_id,
                    'question_text' => $sa['question'],
                    'expected_keywords' => $sa['expected_keywords']
                ]);
            }
            
            $db->update('syllabus', ['status' => 'completed'], 'id = ?', [$syllabus_id]);
            
            return ['success' => true, 'message' => 'Syllabus processed dynamically', 'mcq_count' => count($mcq_questions), 'sa_count' => count($sa_questions)];
            
        } catch (Exception $e) {
            $db->update('syllabus', ['status' => 'failed', 'error_message' => $e->getMessage()], 'id = ?', [$syllabus_id]);
            return ['success' => false, 'message' => $e->getMessage()];
        }
    }
}
?>