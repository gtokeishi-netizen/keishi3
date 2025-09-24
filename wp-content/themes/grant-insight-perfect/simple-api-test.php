<?php
/**
 * Simple API Test to verify OpenAI connectivity
 */

// Set API endpoint and key
$api_key = 'YOUR_API_KEY_HERE'; // We'll need to get this from WordPress options
$api_endpoint = 'https://api.openai.com/v1/chat/completions';

echo "<h1>Simple API Test</h1>";
echo "<p>Testing OpenAI API connectivity...</p>";

// Test data
$test_data = array(
    'model' => 'gpt-4o-mini',
    'messages' => array(
        array(
            'role' => 'system',
            'content' => 'You are a helpful assistant. Respond briefly.'
        ),
        array(
            'role' => 'user',
            'content' => 'Test message. Please respond with "API connection working" and nothing else.'
        )
    ),
    'max_tokens' => 50,
    'temperature' => 0.7
);

echo "<h2>Request Data:</h2>";
echo "<pre>" . json_encode($test_data, JSON_PRETTY_PRINT) . "</pre>";

// Make cURL request since wp_remote_request is not available outside WordPress
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $api_endpoint);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($test_data));
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
    'Authorization: Bearer ' . $api_key,
    'Content-Type: application/json'
));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

echo "<h2>Attempting API Call...</h2>";

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "<h3 style='color: red;'>cURL Error:</h3>";
    echo "<p>" . htmlspecialchars($error) . "</p>";
} else {
    echo "<h3>HTTP Response Code: " . $http_code . "</h3>";
    
    if ($http_code == 200) {
        $data = json_decode($response, true);
        if ($data && isset($data['choices'][0]['message']['content'])) {
            echo "<h3 style='color: green;'>✅ SUCCESS!</h3>";
            echo "<p><strong>AI Response:</strong> " . htmlspecialchars($data['choices'][0]['message']['content']) . "</p>";
            echo "<p><strong>Tokens Used:</strong> " . ($data['usage']['total_tokens'] ?? 'unknown') . "</p>";
        } else {
            echo "<h3 style='color: orange;'>⚠️ Unexpected Response Format</h3>";
            echo "<pre>" . htmlspecialchars($response) . "</pre>";
        }
    } else {
        echo "<h3 style='color: red;'>❌ HTTP Error " . $http_code . "</h3>";
        echo "<pre>" . htmlspecialchars($response) . "</pre>";
    }
}

echo "<hr>";
echo "<h2>Next Steps:</h2>";
echo "<ul>";
echo "<li>If you see '✅ SUCCESS!', the API is working correctly</li>";
echo "<li>If you see an error, check your API key and network connection</li>";
echo "<li>If HTTP 401, your API key is invalid</li>";
echo "<li>If HTTP 429, you're being rate limited</li>";
echo "<li>If cURL error, check your network/firewall settings</li>";
echo "</ul>";
?>