<?php
// Chatbot API Handler - Using Groq API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');
require_once __DIR__ . '/../../api_config.php';

$apiKey = GROQ_API_KEY;
define('GROQ_API_URL', 'https://api.groq.com/openai/v1/chat/completions');

// System prompt for study assistant
define('SYSTEM_PROMPT', 'You are a helpful AI Study Assistant for university students. You help students understand their course material, explain concepts, provide study tips, and answer questions about their syllabus. Always be encouraging, educational, and supportive. Keep responses clear, concise, and educational. If asked about topics outside of the course context, politely redirect to the course material.');

// Get input
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? '';
$history = $input['history'] ?? [];

if (empty($message)) {
    echo json_encode(['response' => 'Please enter a message.']);
    exit;
}

function callGroqAPI($message, $history) {
    $apiKey = GROQ_API_KEY;
    $url = GROQ_API_URL;
    
    // Build messages array
    $messages = [];
    
    // Add system prompt
    $messages[] = [
        'role' => 'system',
        'content' => SYSTEM_PROMPT
    ];
    
    // Add conversation history (limit to last 10 messages)
    $recent = array_slice($history, -10);
    foreach ($recent as $item) {
        if ($item['role'] == 'user') {
            $messages[] = [
                'role' => 'user',
                'content' => $item['content']
            ];
        } elseif ($item['role'] == 'bot') {
            $messages[] = [
                'role' => 'assistant',
                'content' => $item['content']
            ];
        }
    }
    
    // Add current message
    $messages[] = [
        'role' => 'user',
        'content' => $message
    ];
    
    // Try different Groq models
    $models = [
        'llama-3.3-70b-versatile',  // Best for quality
        'mixtral-8x7b-32768',       // Good for reasoning
        'gemma2-9b-it'              // Fast and efficient
    ];
    
    foreach ($models as $model) {
        $result = makeGroqRequest($url, $apiKey, $messages, $model);
        if ($result !== null) {
            return $result;
        }
    }
    
    return null;
}

function makeGroqRequest($url, $apiKey, $messages, $model) {
    $data = [
        'messages' => $messages,
        'model' => $model,
        'temperature' => 0.7,
        'max_tokens' => 800,
        'top_p' => 0.95,
        'stream' => false
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'Authorization: Bearer ' . $apiKey
    ]);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    // Log for debugging
    error_log("Groq API ($model): HTTP $httpCode");
    
    if ($httpCode == 200 && $response) {
        $result = json_decode($response, true);
        if (isset($result['choices'][0]['message']['content'])) {
            return $result['choices'][0]['message']['content'];
        }
    } else {
        error_log("Groq API Error ($model): HTTP $httpCode - $error");
        if ($response) {
            error_log("Response: " . substr($response, 0, 500));
        }
    }
    
    return null;
}

// Get response
$response = callGroqAPI($message, $history);

if (empty($response)) {
    $response = "I'm having trouble connecting right now. Please try again in a moment. In the meantime, you can review your course materials or ask your teacher for help.";
}

echo json_encode(['response' => $response]);
?>