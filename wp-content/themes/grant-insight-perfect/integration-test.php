<?php
/**
 * Grant Insight Perfect AI自動入力機能 - 統合テスト
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 * @since 2024.12
 */

// WordPress環境の模擬設定
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__FILE__) . '/');
}
if (!defined('WP_DEBUG')) {
    define('WP_DEBUG', true);
}

// テスト用の関数定義
function get_template_directory() {
    return dirname(__FILE__);
}

function get_template_directory_uri() {
    return 'http://localhost/webapp';
}

function wp_create_nonce($action) {
    return 'test_nonce_' . md5($action);
}

function get_option($option_name, $default = false) {
    $options = array(
        'gi_ai_daily_limit' => 100,
        'gi_ai_auto_save' => 0,
        'gi_ai_notification_email' => 'admin@example.com',
        'gi_openai_api_key' => '',
        'admin_email' => 'admin@example.com'
    );
    
    return isset($options[$option_name]) ? $options[$option_name] : $default;
}

function current_time($format) {
    return date($format);
}

function wp_verify_nonce($nonce, $action) {
    return true; // テスト用
}

function sanitize_text_field($str) {
    return strip_tags(trim($str));
}

function sanitize_email($email) {
    return filter_var($email, FILTER_SANITIZE_EMAIL);
}

function intval($var) {
    return (int) $var;
}

function floatval($var) {
    return (float) $var;
}

function esc_attr($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

function esc_html($text) {
    return htmlspecialchars($text, ENT_NOQUOTES, 'UTF-8');
}

function checked($checked, $current = true, $echo = true) {
    $result = $checked == $current ? 'checked="checked"' : '';
    if (!$echo) return $result;
    echo $result;
}

echo "<!DOCTYPE html>\n<html lang='ja'>\n<head>\n<meta charset='UTF-8'>\n<title>AI自動入力機能 - 統合テスト</title>\n";
echo "<style>body{font-family:sans-serif;margin:20px;} .test-section{margin:20px 0;padding:15px;border:1px solid #ddd;border-radius:5px;} .success{color:green;} .error{color:red;} .warning{color:orange;}</style>\n";
echo "</head>\n<body>\n";

echo "<h1>🧪 Grant Insight Perfect AI自動入力機能 - 統合テスト</h1>\n";

$test_results = array();

// ===================================
// テスト 1: ファイル存在確認
// ===================================
echo "<div class='test-section'>\n<h2>📁 テスト 1: ファイル存在確認</h2>\n";

$required_files = array(
    'inc/ai-admin-interface.php' => 'AI管理画面UI',
    'inc/ai-api-handler.php' => 'ChatGPT API通信クラス',
    'inc/ai-auto-fill.php' => 'AI自動入力メインコントローラー',
    'assets/js/ai-auto-fill.js' => 'JavaScript機能',
    'assets/css/ai-admin-styles.css' => '管理画面CSS'
);

$file_test_passed = true;
foreach ($required_files as $file => $description) {
    if (file_exists($file)) {
        echo "<p class='success'>✅ {$description}: {$file}</p>\n";
    } else {
        echo "<p class='error'>❌ {$description}: {$file} (見つかりません)</p>\n";
        $file_test_passed = false;
    }
}

$test_results['file_existence'] = $file_test_passed;
echo "</div>\n";

// ===================================
// テスト 2: PHPクラス読み込み確認
// ===================================
echo "<div class='test-section'>\n<h2>🔧 テスト 2: PHPクラス読み込み確認</h2>\n";

$class_test_passed = true;

try {
    // AI API Handlerクラスの読み込み
    if (file_exists('inc/ai-api-handler.php')) {
        require_once 'inc/ai-api-handler.php';
        if (class_exists('GI_AI_API_Handler')) {
            echo "<p class='success'>✅ GI_AI_API_Handler クラス読み込み成功</p>\n";
        } else {
            echo "<p class='error'>❌ GI_AI_API_Handler クラスが定義されていません</p>\n";
            $class_test_passed = false;
        }
    }
    
    // AI自動入力メインクラスの読み込み
    if (file_exists('inc/ai-auto-fill.php')) {
        require_once 'inc/ai-auto-fill.php';
        if (class_exists('GI_AI_Auto_Fill')) {
            echo "<p class='success'>✅ GI_AI_Auto_Fill クラス読み込み成功</p>\n";
        } else {
            echo "<p class='error'>❌ GI_AI_Auto_Fill クラスが定義されていません</p>\n";
            $class_test_passed = false;
        }
    }
    
    // AI管理画面クラスの読み込み
    if (file_exists('inc/ai-admin-interface.php')) {
        require_once 'inc/ai-admin-interface.php';
        if (class_exists('GI_AI_Admin_Interface')) {
            echo "<p class='success'>✅ GI_AI_Admin_Interface クラス読み込み成功</p>\n";
        } else {
            echo "<p class='error'>❌ GI_AI_Admin_Interface クラスが定義されていません</p>\n";
            $class_test_passed = false;
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ クラス読み込みエラー: " . $e->getMessage() . "</p>\n";
    $class_test_passed = false;
}

$test_results['class_loading'] = $class_test_passed;
echo "</div>\n";

// ===================================
// テスト 3: クラスインスタンス化テスト
// ===================================
echo "<div class='test-section'>\n<h2>🏗️ テスト 3: クラスインスタンス化テスト</h2>\n";

$instance_test_passed = true;

try {
    if (class_exists('GI_AI_API_Handler')) {
        $api_handler = new GI_AI_API_Handler();
        echo "<p class='success'>✅ GI_AI_API_Handler インスタンス化成功</p>\n";
        
        // メソッド存在確認
        $required_methods = array('generate_field_content', 'test_connection', 'get_usage_statistics');
        foreach ($required_methods as $method) {
            if (method_exists($api_handler, $method)) {
                echo "<p class='success'>✅ メソッド {$method} 存在確認</p>\n";
            } else {
                echo "<p class='error'>❌ メソッド {$method} が見つかりません</p>\n";
                $instance_test_passed = false;
            }
        }
    }
    
    if (class_exists('GI_AI_Auto_Fill')) {
        $auto_fill = new GI_AI_Auto_Fill();
        echo "<p class='success'>✅ GI_AI_Auto_Fill インスタンス化成功</p>\n";
        
        // メソッド存在確認
        $required_methods = array('process_auto_fill', 'process_batch_auto_fill', 'process_rollback');
        foreach ($required_methods as $method) {
            if (method_exists($auto_fill, $method)) {
                echo "<p class='success'>✅ メソッド {$method} 存在確認</p>\n";
            } else {
                echo "<p class='error'>❌ メソッド {$method} が見つかりません</p>\n";
                $instance_test_passed = false;
            }
        }
    }
    
    if (class_exists('GI_AI_Admin_Interface')) {
        $admin_interface = new GI_AI_Admin_Interface();
        echo "<p class='success'>✅ GI_AI_Admin_Interface インスタンス化成功</p>\n";
        
        // メソッド存在確認
        $required_methods = array('add_meta_boxes', 'render_meta_box', 'add_batch_processing_page');
        foreach ($required_methods as $method) {
            if (method_exists($admin_interface, $method)) {
                echo "<p class='success'>✅ メソッド {$method} 存在確認</p>\n";
            } else {
                echo "<p class='error'>❌ メソッド {$method} が見つかりません</p>\n";
                $instance_test_passed = false;
            }
        }
    }
    
} catch (Exception $e) {
    echo "<p class='error'>❌ インスタンス化エラー: " . $e->getMessage() . "</p>\n";
    $instance_test_passed = false;
}

$test_results['instance_creation'] = $instance_test_passed;
echo "</div>\n";

// ===================================
// テスト 4: JavaScript/CSSファイルサイズ確認
// ===================================
echo "<div class='test-section'>\n<h2>📊 テスト 4: アセットファイル確認</h2>\n";

$asset_test_passed = true;

$asset_files = array(
    'assets/js/ai-auto-fill.js' => array('min_size' => 30000, 'description' => 'JavaScript機能'),
    'assets/css/ai-admin-styles.css' => array('min_size' => 15000, 'description' => '管理画面CSS')
);

foreach ($asset_files as $file => $config) {
    if (file_exists($file)) {
        $file_size = filesize($file);
        if ($file_size >= $config['min_size']) {
            echo "<p class='success'>✅ {$config['description']}: {$file} (" . number_format($file_size) . " bytes)</p>\n";
        } else {
            echo "<p class='warning'>⚠️ {$config['description']}: {$file} サイズが小さすぎます (" . number_format($file_size) . " bytes, 最小: " . number_format($config['min_size']) . ")</p>\n";
        }
    } else {
        echo "<p class='error'>❌ {$config['description']}: {$file} (見つかりません)</p>\n";
        $asset_test_passed = false;
    }
}

$test_results['asset_files'] = $asset_test_passed;
echo "</div>\n";

// ===================================
// テスト 5: functions.php統合確認
// ===================================
echo "<div class='test-section'>\n<h2>⚙️ テスト 5: functions.php統合確認</h2>\n";

$integration_test_passed = true;

if (file_exists('functions.php')) {
    $functions_content = file_get_contents('functions.php');
    
    $integration_checks = array(
        'ai-api-handler.php' => 'AI APIハンドラーの読み込み設定',
        'ai-auto-fill.php' => 'AI自動入力メイン機能の読み込み設定',
        'ai-admin-interface.php' => 'AI管理画面UIの読み込み設定',
        'gi_ai_auto_fill_init' => 'AI機能初期化関数',
        'gi_ai_enqueue_admin_assets' => 'AI用アセット読み込み関数'
    );
    
    foreach ($integration_checks as $needle => $description) {
        if (strpos($functions_content, $needle) !== false) {
            echo "<p class='success'>✅ {$description}</p>\n";
        } else {
            echo "<p class='error'>❌ {$description} (見つかりません)</p>\n";
            $integration_test_passed = false;
        }
    }
} else {
    echo "<p class='error'>❌ functions.php ファイルが見つかりません</p>\n";
    $integration_test_passed = false;
}

$test_results['functions_integration'] = $integration_test_passed;
echo "</div>\n";

// ===================================
// テスト結果サマリー
// ===================================
echo "<div class='test-section' style='background-color: #f8f9fa;'>\n<h2>📋 テスト結果サマリー</h2>\n";

$total_tests = count($test_results);
$passed_tests = array_sum($test_results);
$failed_tests = $total_tests - $passed_tests;

echo "<h3>📊 統計</h3>\n";
echo "<ul>\n";
echo "<li><strong>総テスト数:</strong> {$total_tests}</li>\n";
echo "<li><strong class='success'>成功:</strong> {$passed_tests}</li>\n";
echo "<li><strong class='error'>失敗:</strong> {$failed_tests}</li>\n";
echo "<li><strong>成功率:</strong> " . round(($passed_tests / $total_tests) * 100, 1) . "%</li>\n";
echo "</ul>\n";

echo "<h3>📝 詳細結果</h3>\n";
echo "<ul>\n";
foreach ($test_results as $test_name => $result) {
    $status = $result ? "<span class='success'>PASS</span>" : "<span class='error'>FAIL</span>";
    $test_display_name = array(
        'file_existence' => 'ファイル存在確認',
        'class_loading' => 'PHPクラス読み込み',
        'instance_creation' => 'クラスインスタンス化',
        'asset_files' => 'アセットファイル確認',
        'functions_integration' => 'functions.php統合'
    );
    
    echo "<li>{$status} {$test_display_name[$test_name]}</li>\n";
}
echo "</ul>\n";

if ($passed_tests === $total_tests) {
    echo "<div style='padding: 16px; background: #d1e7dd; color: #0f5132; border-radius: 6px; margin-top: 16px;'>\n";
    echo "<h3>🎉 すべてのテストが成功しました！</h3>\n";
    echo "<p>AI自動入力機能は正常に統合されており、使用準備が整っています。</p>\n";
    echo "</div>\n";
} else {
    echo "<div style='padding: 16px; background: #f8d7da; color: #721c24; border-radius: 6px; margin-top: 16px;'>\n";
    echo "<h3>⚠️ 一部のテストが失敗しました</h3>\n";
    echo "<p>失敗したテストを確認し、必要な修正を行ってください。</p>\n";
    echo "</div>\n";
}

echo "<h3>🔗 次のステップ</h3>\n";
echo "<ul>\n";
echo "<li><a href='ai-test-page.html'>JavaScript機能テストページ</a> - ブラウザでの動作確認</li>\n";
echo "<li>WordPress管理画面での実際の動作テスト</li>\n";
echo "<li>OpenAI APIキーの設定とAPI接続テスト</li>\n";
echo "<li>実際の助成金投稿でのAI自動入力テスト</li>\n";
echo "</ul>\n";

echo "</div>\n";

echo "</body>\n</html>\n";
?>