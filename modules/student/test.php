<?php
require_once __DIR__ . '/../../api_config.php';

$apiKey = GCP_API_KEY;
$testMessage = "What is the capital of France? Answer in one sentence.";

echo "<h2>Testing Gemini with Correct Models</h2>";

$models = [
    'gemini-2.5-flash',
    'gemini-2.0-flash',
    'gemini-flash-latest'
];

foreach ($models as $model) {
    echo "<h3>Testing: $model</h3>";
    
    $url = "https://generativelanguage.googleapis.com/v1/models/{$model}:generateContent?key=" . $apiKey;
    
    $data = [
        'contents' => [
            [
                'parts' => [
                    ['text' => $testMessage]
                ]
            ]
        ],
        'generationConfig' => [
            'temperature' => 0.7,
            'maxOutputTokens' => 100
        ]
    ];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    echo "HTTP Code: $httpCode<br>";
    
    if ($httpCode == 200) {
        $result = json_decode($response, true);
        $text = $result['candidates'][0]['content']['parts'][0]['text'] ?? 'No text';
        echo "<span style='color:green'>✅ SUCCESS!</span><br>";
        echo "Response: " . htmlspecialchars($text) . "<br>";
        break;
    } else {
        echo "<span style='color:red'>❌ FAILED</span><br>";
        echo "Error: " . htmlspecialchars($response) . "<br>";
    }
    echo "<hr>";
}
?>