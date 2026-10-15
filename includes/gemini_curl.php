<?php
class GeminiCurlService {
    private $apiKey;
    
    public function __construct($apiKey = null) {
        // Use your API key
        $this->apiKey = $apiKey ?: 'AIzaSyBkzilqvHS5-9nTW6Bd73lRuu1qr4h-9Ns';
    }
    
    public function generateQuizQuestions($syllabusText, $subjectName) {
        $prompt = $this->buildPrompt($syllabusText, $subjectName);
        
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=" . $this->apiKey;
        
        $data = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $prompt]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 2000
            ]
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $result = json_decode($response, true);
            $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? '';
            return $this->parseQuizResponse($text, $subjectName);
        }
        
        // Log error for debugging
        error_log("Gemini API Error: HTTP $httpCode - $error");
        return null;
    }
    
    private function buildPrompt($text, $subjectName) {
        return "You are an educational quiz generator. Based on this syllabus for {$subjectName}, create 5 multiple choice questions and 3 short answer questions.

Syllabus content:
" . substr($text, 0, 3000) . "

IMPORTANT RULES:
1. Each MCQ must have 4 DIFFERENT meaningful options
2. Questions should be SPECIFIC to the syllabus content
3. Use fill-in-the-blank format with ______ for missing terms
4. Options should be real concepts from the syllabus
5. Short answer questions should ask about key concepts

Return ONLY valid JSON in this exact format, no other text:
{
    \"mcq\": [
        {
            \"question\": \"Question with ______ for blank\",
            \"options\": [\"Option A text\", \"Option B text\", \"Option C text\", \"Option D text\"],
            \"correct\": \"A\",
            \"explanation\": \"Why this is correct based on syllabus\"
        }
    ],
    \"sa\": [
        {
            \"question\": \"Short answer question text\",
            \"keywords\": \"keyword1, keyword2, keyword3\"
        }
    ]
}

Example of GOOD question based on computer science:
{
    \"question\": \"In computer architecture, ______ memory is faster than main memory\",
    \"options\": [\"Cache\", \"Virtual\", \"Secondary\", \"Auxiliary\"],
    \"correct\": \"A\",
    \"explanation\": \"Cache memory is faster than main memory according to computer architecture principles\"
}";
    }
    
    private function parseQuizResponse($response, $subjectName) {
        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $questions = json_decode($matches[0], true);
            if ($questions && isset($questions['mcq']) && count($questions['mcq']) >= 3) {
                return $questions;
            }
        }
        
        // If parsing fails, return null
        return null;
    }
}
?>