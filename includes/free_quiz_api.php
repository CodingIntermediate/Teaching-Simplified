<?php
class FreeQuizAPI {
    
    public function generateQuizQuestions($syllabusText, $subjectName) {
        $prompt = $this->buildPrompt($syllabusText, $subjectName);
        
        // Multiple free API endpoints (no API key needed)
        $apis = [
            [
                'name' => 'Free GPT API',
                'url' => 'https://free-unoficial-gpt4o-mini-api-g70n.onrender.com/chat/',
                'method' => 'POST',
                'data' => ['query' => $prompt]
            ],
            [
                'name' => 'Pollinations AI',
                'url' => 'https://text.pollinations.ai/',
                'method' => 'GET',
                'data' => $prompt
            ]
        ];
        
        foreach ($apis as $api) {
            $result = $this->callAPI($api, $prompt);
            if ($result && isset($result['mcq']) && count($result['mcq']) >= 3) {
                return $result;
            }
        }
        
        return null;
    }
    
    private function callAPI($api, $prompt) {
        $ch = curl_init();
        
        if ($api['method'] == 'POST') {
            curl_setopt($ch, CURLOPT_URL, $api['url']);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($api['data']));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        } else {
            $url = $api['url'] . urlencode($prompt);
            curl_setopt($ch, CURLOPT_URL, $url);
        }
        
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200 && $response) {
            $result = json_decode($response, true);
            $text = $result['response'] ?? $result['message'] ?? $response;
            return $this->parseQuizResponse($text);
        }
        
        return null;
    }
    
    private function buildPrompt($text, $subjectName) {
        return "You are an educational quiz generator. Based on this syllabus for {$subjectName}, create 5 multiple choice questions and 3 short answer questions.

Syllabus content:
" . substr($text, 0, 2500) . "

Return ONLY valid JSON in this exact format, no other text:
{
    \"mcq\": [
        {
            \"question\": \"Question text\",
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
}";
    }
    
    private function parseQuizResponse($response) {
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