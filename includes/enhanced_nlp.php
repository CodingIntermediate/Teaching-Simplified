<?php
require_once __DIR__ . '/nlp.php';

class EnhancedNLPProcessor extends NLPProcessor {
    
    // Get combined resource suggestions (internal + external)
    public static function getCombinedResourceSuggestions($syllabus_id, $subject_id, $keywords, $subject_name) {
        $db = db();
        
        // Get internal resources from database
        $internal_resources = self::getInternalResources($subject_id, $keywords);
        
        // Get external resources
        $external_resources = self::getExternalResources($keywords, $subject_name);
        
        return [
            'internal' => $internal_resources,
            'external' => $external_resources
        ];
    }
    
    // Get resources uploaded by teachers
    private static function getInternalResources($subject_id, $search_terms) {
        $db = db();
        
        if (empty($search_terms)) {
            return [];
        }
        
        // Build search query
        $search_conditions = [];
        $params = [$subject_id];
        
        foreach ($search_terms as $term) {
            if (strlen($term) > 2) {
                $search_conditions[] = "(title LIKE ? OR description LIKE ? OR tags LIKE ?)";
                $like_term = "%{$term}%";
                $params[] = $like_term;
                $params[] = $like_term;
                $params[] = $like_term;
            }
        }
        
        if (empty($search_conditions)) {
            return [];
        }
        
        $sql = "SELECT * FROM tutor_resources 
                WHERE subject_id = ? AND (" . implode(' OR ', $search_conditions) . ")
                ORDER BY downloads DESC, uploaded_at DESC
                LIMIT 10";
        
        return $db->select($sql, $params);
    }
    
    // Generate external resource links (no API required)
    private static function getExternalResources($search_terms, $subject_name) {
        $resources = [];
        
        // Working external resource links
        $external_sources = [
            'youtube' => [
                'url' => 'https://www.youtube.com/results?search_query=%s',
                'icon' => 'fab fa-youtube',
                'color' => '#FF0000',
                'name' => 'YouTube'
            ],
            'google_scholar' => [
                'url' => 'https://scholar.google.com/scholar?q=%s',
                'icon' => 'fas fa-graduation-cap',
                'color' => '#4285F4',
                'name' => 'Google Scholar'
            ],
            'coursera' => [
                'url' => 'https://www.coursera.org/search?query=%s',
                'icon' => 'fas fa-university',
                'color' => '#2A73FF',
                'name' => 'Coursera'
            ],
            'edx' => [
                'url' => 'https://www.edx.org/search?q=%s',
                'icon' => 'fas fa-chalkboard-teacher',
                'color' => '#02262E',
                'name' => 'edX'
            ],
            'wikipedia' => [
                'url' => 'https://en.wikipedia.org/w/index.php?search=%s',
                'icon' => 'fab fa-wikipedia-w',
                'color' => '#000000',
                'name' => 'Wikipedia'
            ],
            'khan_academy' => [
                'url' => 'https://www.khanacademy.org/search?page_search_query=%s',
                'icon' => 'fas fa-graduation-cap',
                'color' => '#1C8B9C',
                'name' => 'Khan Academy'
            ],
            'github' => [
                'url' => 'https://github.com/search?q=%s',
                'icon' => 'fab fa-github',
                'color' => '#333333',
                'name' => 'GitHub'
            ],
            'stackoverflow' => [
                'url' => 'https://stackoverflow.com/search?q=%s',
                'icon' => 'fab fa-stack-overflow',
                'color' => '#F58025',
                'name' => 'Stack Overflow'
            ]
        ];
        
        // For each search term, generate resource links
        foreach (array_slice($search_terms, 0, 5) as $term) {
            if (strlen($term) < 3) continue;
            
            $encoded_term = urlencode($term);
            $term_resources = [];
            
            foreach ($external_sources as $key => $source) {
                $term_resources[] = [
                    'title' => $source['name'] . " - " . htmlspecialchars($term),
                    'url' => sprintf($source['url'], $encoded_term),
                    'source' => $source['name'],
                    'type' => 'external',
                    'icon' => $source['icon'],
                    'color' => $source['color']
                ];
            }
            
            $resources[$term] = [
                'general' => $term_resources
            ];
        }
        
        return $resources;
    }
}

// Helper function to get free learning resources
function getFreeLearningResources($topic) {
    $resources = [
        [
            'title' => 'YouTube Tutorials',
            'url' => 'https://www.youtube.com/results?search_query=' . urlencode($topic . ' tutorial'),
            'description' => 'Video tutorials on ' . $topic
        ],
        [
            'title' => 'Google Scholar',
            'url' => 'https://scholar.google.com/scholar?q=' . urlencode($topic),
            'description' => 'Academic papers and research on ' . $topic
        ],
        [
            'title' => 'Wikipedia',
            'url' => 'https://en.wikipedia.org/w/index.php?search=' . urlencode($topic),
            'description' => 'Comprehensive encyclopedia article'
        ],
        [
            'title' => 'Coursera Courses',
            'url' => 'https://www.coursera.org/search?query=' . urlencode($topic),
            'description' => 'Free online courses from top universities'
        ],
        [
            'title' => 'GitHub Resources',
            'url' => 'https://github.com/search?q=' . urlencode($topic),
            'description' => 'Code examples and open source projects'
        ],
        [
            'title' => 'Stack Overflow Q&A',
            'url' => 'https://stackoverflow.com/search?q=' . urlencode($topic),
            'description' => 'Questions and answers from developers'
        ]
    ];
    
    return $resources;
}
?>