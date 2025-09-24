<?php
/**
 * API Connection Test Script
 * 
 * This script tests the API connection functionality to help diagnose
 * the "API呼び出しに失敗しました" error.
 */

// WordPress environment setup
define('WP_USE_THEMES', false);
require_once('wp-load.php');

echo "=== API Connection Test ===\n\n";

// Test 1: Check if classes are available
echo "1. Checking class availability:\n";
if (class_exists('GI_AI_API_Handler')) {
    echo "✅ GI_AI_API_Handler class exists\n";
} else {
    echo "❌ GI_AI_API_Handler class not found\n";
}

if (class_exists('GI_OpenAI_Integration')) {
    echo "✅ GI_OpenAI_Integration class exists\n";
} else {
    echo "❌ GI_OpenAI_Integration class not found\n";
}

// Test 2: Check API key configuration
echo "\n2. Checking API key configuration:\n";

// Check old system
$old_api_key = get_option('gi_openai_api_key', '');
if (!empty($old_api_key)) {
    echo "✅ Old system API key is configured (length: " . strlen($old_api_key) . ")\n";
} else {
    echo "⚠️  Old system API key not set\n";
}

// Check new system
$encrypted_key = get_option('gi_openai_api_key_encrypted', '');
if (!empty($encrypted_key)) {
    echo "✅ New system encrypted API key exists\n";
} else {
    echo "⚠️  New system encrypted API key not set\n";
}

// Test 3: Test new API handler connection
echo "\n3. Testing new API handler connection:\n";
if (class_exists('GI_AI_API_Handler')) {
    try {
        $api_handler = gi_ai_get_api_handler();
        if ($api_handler) {
            echo "✅ API handler instance created successfully\n";
            
            // Test connection
            $test_result = $api_handler->test_connection();
            echo "Connection test result:\n";
            echo "- Success: " . ($test_result['success'] ? 'Yes' : 'No') . "\n";
            echo "- Message: " . $test_result['message'] . "\n";
        } else {
            echo "❌ Failed to create API handler instance\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception during API handler test: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Cannot test - GI_AI_API_Handler class not available\n";
}

// Test 4: Test old system connection
echo "\n4. Testing old system connection:\n";
if (class_exists('GI_OpenAI_Integration')) {
    try {
        $old_integration = GI_OpenAI_Integration::getInstance();
        if ($old_integration) {
            echo "✅ Old integration instance created successfully\n";
            
            if (method_exists($old_integration, 'test_connection')) {
                $test_result = $old_integration->test_connection();
                echo "Old system connection test result:\n";
                echo "- Success: " . ($test_result['success'] ? 'Yes' : 'No') . "\n";
                echo "- Message: " . $test_result['message'] . "\n";
            } else {
                echo "⚠️  test_connection method not available in old system\n";
            }
        } else {
            echo "❌ Failed to create old integration instance\n";
        }
    } catch (Exception $e) {
        echo "❌ Exception during old system test: " . $e->getMessage() . "\n";
    }
} else {
    echo "❌ Cannot test - GI_OpenAI_Integration class not available\n";
}

// Test 5: Check database tables
echo "\n5. Checking database tables:\n";
global $wpdb;

$required_tables = array(
    $wpdb->prefix . 'gi_ai_usage_log',
    $wpdb->prefix . 'gi_ai_backup',
    $wpdb->prefix . 'gi_ai_settings'
);

foreach ($required_tables as $table) {
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table'") == $table;
    if ($table_exists) {
        echo "✅ Table $table exists\n";
    } else {
        echo "❌ Table $table missing\n";
    }
}

// Test 6: Check WordPress settings
echo "\n6. Checking WordPress settings:\n";

$settings = array(
    'gi_ai_daily_limit' => get_option('gi_ai_daily_limit', 'not set'),
    'gi_ai_max_tokens' => get_option('gi_ai_max_tokens', 'not set'),
    'gi_ai_temperature' => get_option('gi_ai_temperature', 'not set'),
    'gi_ai_retry_count' => get_option('gi_ai_retry_count', 'not set'),
    'gi_ai_timeout' => get_option('gi_ai_timeout', 'not set')
);

foreach ($settings as $key => $value) {
    echo "- $key: $value\n";
}

echo "\n=== Test Complete ===\n";
?>