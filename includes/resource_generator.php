<?php
class SyllabusResourceGenerator {
    
    /**
     * Main function - generates resources dynamically from syllabus text
     */
    public static function generateResourcesFromSyllabus($syllabus_id) {
        $db = db();
        
        // Get syllabus data
        $syllabus = $db->selectOne("SELECT * FROM syllabus WHERE id = ?", [$syllabus_id]);
        if (!$syllabus) {
            return ['success' => false, 'message' => 'Syllabus not found'];
        }
        
        // Get extracted text
        $text = $syllabus['extracted_text'];
        if (empty($text)) {
            return ['success' => false, 'message' => 'No text extracted from syllabus'];
        }
        
        // Get subject info
        $subject = $db->selectOne("SELECT name, code FROM subjects WHERE id = ?", [$syllabus['subject_id']]);
        
        // Dynamically extract topics from syllabus
        $topics = self::extractTopicsFromSyllabus($text);
        $keywords = self::extractKeywordsFromSyllabus($text);
        $modules = self::extractModulesFromSyllabus($text);
        
        // Generate resources for each extracted topic
        $generated_resources = [];
        foreach ($topics as $topic) {
            $resources = self::generateResourcesForTopic($topic, $subject['name']);
            $generated_resources[] = [
                'topic' => $topic,
                'resources' => $resources
            ];
        }
        
        // Save to database
        self::saveToDatabase($syllabus_id, $generated_resources, $topics, $keywords, $modules);
        
        return [
            'success' => true,
            'topics' => $topics,
            'keywords' => $keywords,
            'modules' => $modules,
            'resources' => $generated_resources
        ];
    }
    
    /**
     * Extract topics dynamically from syllabus text
     */
    private static function extractTopicsFromSyllabus($text) {
        $topics = [];
        
        // Pattern 1: Look for numbered topics (1., 2., 3. etc)
        preg_match_all('/(\d+)[\.\)]\s*([A-Z][A-Za-z0-9\s\-\(\)]{10,80})/', $text, $numbered);
        foreach ($numbered[2] as $topic) {
            $topic = trim(preg_replace('/\s+/', ' ', $topic));
            if (strlen($topic) > 5 && strlen($topic) < 100) {
                $topics[] = $topic;
            }
        }
        
        // Pattern 2: Look for bullet points (•, -, *)
        preg_match_all('/[•\-*]\s*([A-Z][A-Za-z0-9\s\-\(\)]{10,80})/', $text, $bullets);
        foreach ($bullets[1] as $topic) {
            $topic = trim(preg_replace('/\s+/', ' ', $topic));
            if (strlen($topic) > 5 && strlen($topic) < 100) {
                $topics[] = $topic;
            }
        }
        
        // Pattern 3: Look for headings (words ending with colon)
        preg_match_all('/([A-Z][A-Za-z\s]{10,60}):/', $text, $headings);
        foreach ($headings[1] as $topic) {
            $topic = trim($topic);
            if (strlen($topic) > 5 && strlen($topic) < 80) {
                $topics[] = $topic;
            }
        }
        
        // Pattern 4: Look for "Topic X:" or "Module X:" patterns
        preg_match_all('/(?:Topic|Module|Chapter|Unit)\s*\d+[:\-]?\s*([A-Z][A-Za-z0-9\s\-\(\)]{10,80})/i', $text, $module_topics);
        foreach ($module_topics[1] as $topic) {
            $topic = trim($topic);
            if (strlen($topic) > 5 && strlen($topic) < 100) {
                $topics[] = $topic;
            }
        }
        
        // Pattern 5: Look for key phrases in sentences
        if (empty($topics)) {
            $sentences = preg_split('/[.!?]+/', $text);
            foreach ($sentences as $sentence) {
                $sentence = trim($sentence);
                if (strlen($sentence) > 30 && strlen($sentence) < 150 && preg_match('/[A-Z]/', $sentence)) {
                    $topics[] = $sentence;
                    if (count($topics) >= 6) break;
                }
            }
        }
        
        // Remove duplicates and limit
        $topics = array_unique($topics);
        return array_slice($topics, 0, 8);
    }
    
    /**
     * Extract keywords dynamically from syllabus text
     */
    private static function extractKeywordsFromSyllabus($text) {
        $keywords = [];
        
        // Look for capitalized technical terms
        preg_match_all('/\b([A-Z][a-z]{4,}(?:\s+[A-Z][a-z]{4,})?)\b/', $text, $matches);
        $skip_words = ['This', 'That', 'These', 'Those', 'There', 'Their', 'About', 'Which', 'What', 'When', 'Where', 'How', 'Will', 'With', 'From', 'Have', 'Been'];
        
        foreach ($matches[1] as $term) {
            if (!in_array($term, $skip_words) && strlen($term) > 3 && !in_array($term, $keywords)) {
                $keywords[] = $term;
            }
        }
        
        // Look for words in quotes or bold
        preg_match_all('/"([^"]+)"|\*\*([^*]+)\*\*/', $text, $quoted);
        foreach ($quoted[1] as $term) {
            if (!empty($term) && strlen($term) > 3 && !in_array($term, $keywords)) {
                $keywords[] = $term;
            }
        }
        
        return array_slice($keywords, 0, 15);
    }
    
    /**
     * Extract module names dynamically
     */
    private static function extractModulesFromSyllabus($text) {
        $modules = [];
        preg_match_all('/(?:MODULE|MOD|UNIT|CHAPTER)\s*(\d+)[:\-]?\s*([A-Z][A-Za-z0-9\s\-\(\)]{10,80})/i', $text, $matches);
        
        for ($i = 0; $i < count($matches[0]); $i++) {
            $modules[] = "Module " . $matches[1][$i] . ": " . trim($matches[2][$i]);
        }
        
        return array_slice($modules, 0, 5);
    }
    
    /**
     * Generate resources for a specific topic (dynamic)
     */
    private static function generateResourcesForTopic($topic, $subject_name) {
        $encoded_topic = urlencode($topic);
        $encoded_subject = urlencode($subject_name);
        
        // Clean topic for better search results
        $search_topic = preg_replace('/[^\w\s]/', '', $topic);
        $encoded_search = urlencode($search_topic);
        
        return [
            'videos' => [
                [
                    'title' => "YouTube: {$topic} Tutorial",
                    'url' => "https://www.youtube.com/results?search_query=" . urlencode($topic . " tutorial " . $subject_name),
                    'source' => 'YouTube',
                    'type' => 'video',
                    'icon' => 'fab fa-youtube',
                    'color' => '#FF0000'
                ],
                [
                    'title' => "YouTube: {$topic} Lecture",
                    'url' => "https://www.youtube.com/results?search_query=" . urlencode($topic . " lecture"),
                    'source' => 'YouTube',
                    'type' => 'video',
                    'icon' => 'fab fa-youtube',
                    'color' => '#FF0000'
                ],
                [
                    'title' => "YouTube: {$subject_name} {$topic}",
                    'url' => "https://www.youtube.com/results?search_query=" . urlencode($subject_name . " " . $topic),
                    'source' => 'YouTube',
                    'type' => 'video',
                    'icon' => 'fab fa-youtube',
                    'color' => '#FF0000'
                ]
            ],
            'reading' => [
                [
                    'title' => "Wikipedia: {$topic}",
                    'url' => "https://en.wikipedia.org/w/index.php?search=" . $encoded_topic,
                    'source' => 'Wikipedia',
                    'type' => 'article',
                    'icon' => 'fab fa-wikipedia-w',
                    'color' => '#000000'
                ],
                [
                    'title' => "Google Scholar: {$topic}",
                    'url' => "https://scholar.google.com/scholar?q=" . $encoded_topic,
                    'source' => 'Google Scholar',
                    'type' => 'academic',
                    'icon' => 'fas fa-graduation-cap',
                    'color' => '#4285F4'
                ],
                [
                    'title' => "GeeksforGeeks: {$topic}",
                    'url' => "https://www.geeksforgeeks.org/search/?q=" . $encoded_topic,
                    'source' => 'GeeksforGeeks',
                    'type' => 'tutorial',
                    'icon' => 'fas fa-code',
                    'color' => '#0F9D58'
                ]
            ],
            'courses' => [
                [
                    'title' => "Coursera: {$topic}",
                    'url' => "https://www.coursera.org/search?query=" . $encoded_topic,
                    'source' => 'Coursera',
                    'type' => 'course',
                    'icon' => 'fas fa-university',
                    'color' => '#2A73FF'
                ],
                [
                    'title' => "edX: {$topic}",
                    'url' => "https://www.edx.org/search?q=" . $encoded_topic,
                    'source' => 'edX',
                    'type' => 'course',
                    'icon' => 'fas fa-chalkboard-teacher',
                    'color' => '#02262E'
                ],
                [
                    'title' => "Khan Academy: {$topic}",
                    'url' => "https://www.khanacademy.org/search?page_search_query=" . $encoded_topic,
                    'source' => 'Khan Academy',
                    'type' => 'course',
                    'icon' => 'fas fa-graduation-cap',
                    'color' => '#1C8B9C'
                ]
            ],
            'practice' => [
                [
                    'title' => "Stack Overflow: {$topic}",
                    'url' => "https://stackoverflow.com/search?q=" . $encoded_topic,
                    'source' => 'Stack Overflow',
                    'type' => 'forum',
                    'icon' => 'fab fa-stack-overflow',
                    'color' => '#F58025'
                ],
                [
                    'title' => "MIT OCW: {$topic}",
                    'url' => "https://ocw.mit.edu/search/?q=" . $encoded_topic,
                    'source' => 'MIT OpenCourseWare',
                    'type' => 'courseware',
                    'icon' => 'fas fa-building',
                    'color' => '#8B0000'
                ]
            ]
        ];
    }
    
    /**
     * Save to database
     */
    private static function saveToDatabase($syllabus_id, $resources, $topics, $keywords, $modules) {
        $db = db();
        
        // Save analysis
        $existing = $db->selectOne("SELECT id FROM syllabus_analysis WHERE syllabus_id = ?", [$syllabus_id]);
        
        $analysis_data = [
            'syllabus_id' => $syllabus_id,
            'topics' => implode('|', $topics),
            'keywords' => implode('|', $keywords),
            'summary' => "Modules: " . implode(', ', $modules) . ". Topics: " . count($topics)
        ];
        
        if ($existing) {
            $db->update('syllabus_analysis', $analysis_data, 'syllabus_id = ?', [$syllabus_id]);
        } else {
            $db->insert('syllabus_analysis', $analysis_data);
        }
        
        // Save resource suggestions
        $db->delete('resource_suggestions', 'syllabus_id = ?', [$syllabus_id]);
        
        foreach ($resources as $resource_group) {
            $db->insert('resource_suggestions', [
                'syllabus_id' => $syllabus_id,
                'topic' => substr($resource_group['topic'], 0, 200),
                'video_search_terms' => implode('|', ['YouTube', 'Lectures']),
                'document_search_terms' => implode('|', ['Wikipedia', 'Scholar'])
            ]);
        }
    }
}
?>