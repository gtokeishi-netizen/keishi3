<?php
/**
 * AI自動入力デバッグスクリプト
 * 既存フィールドの再生成機能をテスト
 */

// WordPress環境の読み込み
require_once 'wp-config.php';

// エラー表示設定
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('log_errors', 1);

echo "<h1>AI自動入力デバッグ - 再生成機能テスト</h1>\n";

// 必要なクラスファイルを読み込み
$ai_auto_fill_file = './inc/ai-auto-fill.php';
$ai_api_handler_file = './inc/ai-api-handler.php';
$ai_admin_interface_file = './inc/ai-admin-interface.php';

echo "<h2>1. ファイル存在確認</h2>\n";
echo "ai-auto-fill.php: " . (file_exists($ai_auto_fill_file) ? "✓存在" : "✗不存在") . "\n<br>";
echo "ai-api-handler.php: " . (file_exists($ai_api_handler_file) ? "✓存在" : "✗不存在") . "\n<br>";
echo "ai-admin-interface.php: " . (file_exists($ai_admin_interface_file) ? "✓存在" : "✗不存在") . "\n<br>";

// クラス定義前にファイルをインクルード
if (file_exists($ai_api_handler_file)) {
    include_once $ai_api_handler_file;
}

if (file_exists($ai_auto_fill_file)) {
    include_once $ai_auto_fill_file;
}

if (file_exists($ai_admin_interface_file)) {
    include_once $ai_admin_interface_file;
}

echo "<h2>2. クラス存在確認</h2>\n";
echo "GI_AI_API_Handler: " . (class_exists('GI_AI_API_Handler') ? "✓存在" : "✗不存在") . "\n<br>";
echo "GI_AI_Auto_Fill: " . (class_exists('GI_AI_Auto_Fill') ? "✓存在" : "✗不存在") . "\n<br>";
echo "GI_AI_Admin_Interface: " . (class_exists('GI_AI_Admin_Interface') ? "✓存在" : "✗不存在") . "\n<br>";

// APIキー設定確認
echo "<h2>3. API設定確認</h2>\n";
$api_key = get_option('gi_openai_api_key');
$encrypted_api_key = get_option('gi_openai_api_key_encrypted');
echo "通常APIキー: " . (!empty($api_key) ? "✓設定済み" : "✗未設定") . "\n<br>";
echo "暗号化APIキー: " . (!empty($encrypted_api_key) ? "✓設定済み" : "✗未設定") . "\n<br>";

// ACF確認
echo "<h2>4. ACF確認</h2>\n";
echo "ACF関数 get_field: " . (function_exists('get_field') ? "✓利用可能" : "✗利用不可") . "\n<br>";
echo "ACF関数 update_field: " . (function_exists('update_field') ? "✓利用可能" : "✗利用不可") . "\n<br>";

// grant投稿タイプの確認
echo "<h2>5. 投稿タイプ確認</h2>\n";
$grant_posts = get_posts([
    'post_type' => 'grant',
    'post_status' => 'draft', 
    'numberposts' => 5
]);

echo "draft状態のgrant投稿: " . count($grant_posts) . "件\n<br>";

if (!empty($grant_posts)) {
    $test_post = $grant_posts[0];
    echo "テスト対象投稿ID: " . $test_post->ID . "\n<br>";
    echo "投稿タイトル: " . $test_post->post_title . "\n<br>";
    
    // フィールドの値を確認
    echo "<h2>6. 既存フィールド確認</h2>\n";
    $target_fields = ['ai_summary', 'grant_target', 'eligible_expenses'];
    
    foreach ($target_fields as $field_name) {
        $value = get_field($field_name, $test_post->ID);
        $has_content = !empty($value) && trim(strip_tags($value)) !== '';
        echo "フィールド {$field_name}: " . ($has_content ? "✓入力済み" : "✗空欄") . "\n<br>";
        if ($has_content) {
            echo "　内容: " . mb_substr(strip_tags($value), 0, 100) . "...\n<br>";
        }
    }
    
    // 実際のAI処理をテスト
    if (class_exists('GI_AI_Auto_Fill') && !empty($api_key || $encrypted_api_key)) {
        echo "<h2>7. AI処理テスト</h2>\n";
        
        try {
            // AI処理インスタンスを作成
            $ai_auto_fill = new GI_AI_Auto_Fill();
            
            // 投稿データ収集テスト
            echo "投稿データ収集テスト...\n<br>";
            $collect_method = new ReflectionMethod($ai_auto_fill, 'collect_post_data');
            $collect_method->setAccessible(true);
            $post_data = $collect_method->invoke($ai_auto_fill, $test_post->ID);
            
            if ($post_data) {
                echo "✓投稿データ収集成功\n<br>";
                echo "収集データ項目: " . count($post_data) . "件\n<br>";
                if (isset($post_data['existing_ai_content'])) {
                    echo "✓既存AIコンテンツ検出: " . count($post_data['existing_ai_content']) . "件\n<br>";
                } else {
                    echo "✗既存AIコンテンツ未検出\n<br>";
                }
            } else {
                echo "✗投稿データ収集失敗\n<br>";
            }
            
            // API処理テスト
            if (class_exists('GI_AI_API_Handler')) {
                echo "API処理テスト...\n<br>";
                $api_handler = new GI_AI_API_Handler();
                
                if ($post_data) {
                    $result = $api_handler->generate_field_content($post_data, 'ai_summary');
                    
                    if ($result['success']) {
                        echo "✓AI生成成功\n<br>";
                        echo "生成内容: " . mb_substr($result['content'], 0, 100) . "...\n<br>";
                        echo "使用トークン: " . $result['tokens_used'] . "\n<br>";
                    } else {
                        echo "✗AI生成失敗: " . $result['error'] . "\n<br>";
                    }
                }
            }
            
        } catch (Exception $e) {
            echo "✗テスト中にエラー: " . $e->getMessage() . "\n<br>";
            echo "エラーファイル: " . $e->getFile() . ":" . $e->getLine() . "\n<br>";
        }
    } else {
        echo "<h2>7. AI処理テスト</h2>\n";
        echo "✗テスト不可: 必要なクラスまたはAPIキーが不足\n<br>";
    }
}

echo "<h2>8. 実行完了</h2>\n";
echo "デバッグ完了時刻: " . date('Y-m-d H:i:s') . "\n<br>";