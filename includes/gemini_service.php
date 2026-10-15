<?php
// Load Composer autoloader
require_once __DIR__ . '/../vendor/autoload.php';

use GeminiAPI\Client;
use GeminiAPI\Resources\Parts\TextPart;

class GeminiService {
    private $client;
    
    public function __construct($apiKey) {
        $this->client = new Client($apiKey);
    }
    
    public function generateQuizQuestions($syllabusText, $subjectName) {
        $prompt = $this->buildPrompt($syllabusText, $subjectName);
        
        try {
            $response = $this->client->geminiPro()->generateContent(
                new TextPart($prompt)
            );
            
            $result = $response->text();
            return $this->parseQuizResponse($result);
            
        } catch (Exception $e) {
            error_log("Gemini API Error: " . $e->getMessage());
            return null;
        }
    }
    
    private function buildPrompt($text, $subjectName) {
        return "You are an educational quiz generator. Based on this syllabus for {$subjectName}, create 5 multiple choice questions and 3 short answer questions.

Syllabus content:
" . substr($text, 0, 3000) . "

Return ONLY valid JSON in this exact format:
{
    \"mcq\": [
        {
            \"question\": \"Question text with ______ for blank if fill-in-the-blank style\",
            \"options\": [\"Option A\", \"Option B\", \"Option C\", \"Option D\"],
            \"correct\": \"A\",
            \"explanation\": \"Why this is correct\"
        }
    ],
    \"sa\": [
        {
            \"question\": \"Short answer question text\",
            \"keywords\": \"keyword1, keyword2, keyword3\"
        }
    ]
}

Rules:
- Questions must be directly based on the syllabus content
- Each MCQ must have 4 distinct options
- Short answer questions should test understanding of key concepts
- Return ONLY valid JSON, no other text";
    }
    
    private function parseQuizResponse($response) {
        // Extract JSON from response
        if (preg_match('/\{[\s\S]*\}/', $response, $matches)) {
            $questions = json_decode($matches[0], true);
            if ($questions && isset($questions['mcq'])) {
                return $questions;
            }
        }
        return null;
    }
}
?>