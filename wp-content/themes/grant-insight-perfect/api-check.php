<?php
/**
 * AI自動入力 完全診断スクリプト
 * APIの動作状況を徹底的にチェック
 */

// WordPress環境の読み込み
if (!defined('ABSPATH')) {
    require_once 'wp-config.php';
}

echo "<div style='font-family: monospace; background: #f0f0f0; padding: 20px;'>";
echo "<h1>🔍 AI自動入力 完全診断</h1>";

// 1. 基本環境チェック
echo "<h2>📋 1. 基本環境チェック</h2>";

echo "<h3>WordPressの基本情報</h3>";
echo "WordPress Version: " . get_bloginfo('version') . "<br>";
echo "PHP Version: " . PHP_VERSION . "<br>";
echo "Theme: " . get_template() . "<br>";

// 2. ファイル存在確認
echo "<h3>📁 ファイル存在確認</h3>";
$files = [
    'inc/ai-auto-fill.php' => 'メインコントローラー',
    'inc/ai-api-handler.php' => 'APIハンドラー',
    'inc/ai-admin-interface.php' => '管理画面UI'
];

foreach ($files as $file => $description) {
    $exists = file_exists($file);
    $status = $exists ? '✅' : '❌';
    echo "{$status} {$description}: {$file}<br>";
}

// 3. クラス読み込み確認
echo "<h3>🏗️ クラス読み込み確認</h3>";
$classes = [
    'GI_AI_Auto_Fill' => 'メインコントローラー',
    'GI_AI_API_Handler' => 'APIハンドラー'
];

foreach ($classes as $class => $description) {
    $exists = class_exists($class);
    $status = $exists ? '✅' : '❌';
    echo "{$status} {$description}: {$class}<br>";
}

// 4. APIキー設定確認
echo "<h2>🔑 2. APIキー設定確認</h2>";

$api_key = get_option('gi_openai_api_key');
$encrypted_api_key = get_option('gi_openai_api_key_encrypted');

echo "<h3>APIキー状況</h3>";
if (!empty($api_key)) {
    $masked_key = str_repeat('*', strlen($api_key) - 8) . substr($api_key, -8);
    echo "✅ 通常APIキー: {$masked_key}<br>";
} else {
    echo "❌ 通常APIキー: 未設定<br>";
}

if (!empty($encrypted_api_key)) {
    echo "✅ 暗号化APIキー: 設定済み<br>";
} else {
    echo "❌ 暗号化APIキー: 未設定<br>";
}

$has_key = !empty($api_key) || !empty($encrypted_api_key);
if (!$has_key) {
    echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 1px solid #ff0000;'>";
    echo "<strong>🚨 重大な問題: APIキーが設定されていません！</strong><br>";
    echo "WordPress管理画面 > 設定 > AI自動入力設定 でOpenAI APIキーを設定してください。";
    echo "</div>";
} else {
    // 5. API接続テスト
    echo "<h2>🌐 3. API接続テスト</h2>";
    
    if (class_exists('GI_AI_API_Handler')) {
        try {
            echo "<h3>基本接続テスト</h3>";
            $api_handler = new GI_AI_API_Handler();
            $test_result = $api_handler->test_connection();
            
            if ($test_result['success']) {
                echo "✅ API接続成功: {$test_result['message']}<br>";
                
                // 6. 実際の生成テスト
                echo "<h2>🚀 4. 実際の生成テスト</h2>";
                
                // テスト用の投稿データを作成
                $test_data = [
                    'title' => 'IT導入支援助成金制度',
                    'organization' => '中小企業庁',
                    'max_amount' => '450万円',
                    'target_business_type' => '中小企業',
                    'existing_ai_content' => []
                ];
                
                echo "<h3>テスト投稿データ</h3>";
                echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc;'>";
                print_r($test_data);
                echo "</pre>";
                
                // AI概要生成テスト
                echo "<h3>AI概要フィールド生成テスト</h3>";
                echo "プロンプト送信中...<br>";
                
                $generation_result = $api_handler->generate_field_content($test_data, 'ai_summary');
                
                if ($generation_result['success']) {
                    echo "<div style='background: #ddffdd; padding: 15px; margin: 10px 0; border: 1px solid #00aa00;'>";
                    echo "<strong>✅ 生成成功!</strong><br>";
                    echo "<strong>生成内容:</strong><br>";
                    echo "<div style='background: white; padding: 10px; margin: 10px 0; border: 1px solid #ccc;'>";
                    echo htmlspecialchars($generation_result['content']);
                    echo "</div>";
                    echo "<strong>使用トークン:</strong> {$generation_result['tokens_used']}<br>";
                    echo "<strong>処理時間:</strong> " . number_format($generation_result['response_time'], 2) . "秒<br>";
                    echo "</div>";
                    
                    // 内容品質チェック
                    echo "<h3>🎯 生成内容品質チェック</h3>";
                    $content = $generation_result['content'];
                    
                    // 問題パターンチェック
                    $issues = [];
                    if (strpos($content, '、、、') !== false) $issues[] = '不正な記号連続（、、、）を検出';
                    if (strpos($content, '。。。') !== false) $issues[] = '不正な記号連続（。。。）を検出';
                    if (strpos($content, '令和') !== false) $issues[] = '年号（令和）を検出';
                    if (strpos($content, '平成') !== false) $issues[] = '年号（平成）を検出';
                    if (mb_strlen($content) < 30) $issues[] = '内容が短すぎます（30文字未満）';
                    if (mb_strlen($content) > 220) $issues[] = '内容が長すぎます（220文字超過）';
                    
                    // 良い要素チェック
                    $good_points = [];
                    if (preg_match('/(助成金|補助金|支援制度)/', $content)) $good_points[] = '適切なキーワードを含む';
                    if (preg_match('/\d+万円|\d+％/', $content)) $good_points[] = '具体的な金額・率を含む';
                    if (preg_match('/(中小企業|スタートアップ|製造業|IT|サービス業)/', $content)) $good_points[] = '具体的な対象者を含む';
                    
                    if (empty($issues)) {
                        echo "<div style='background: #ddffdd; padding: 10px; border: 1px solid #00aa00;'>";
                        echo "<strong>✅ 品質チェック: 問題なし</strong><br>";
                    } else {
                        echo "<div style='background: #ffdddd; padding: 10px; border: 1px solid #ff0000;'>";
                        echo "<strong>❌ 品質に問題があります:</strong><br>";
                        foreach ($issues as $issue) {
                            echo "• {$issue}<br>";
                        }
                    }
                    
                    if (!empty($good_points)) {
                        echo "<strong>✅ 良いポイント:</strong><br>";
                        foreach ($good_points as $point) {
                            echo "• {$point}<br>";
                        }
                    }
                    echo "</div>";
                    
                } else {
                    echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 1px solid #ff0000;'>";
                    echo "<strong>❌ 生成失敗</strong><br>";
                    echo "<strong>エラー:</strong> {$generation_result['error']}<br>";
                    echo "</div>";
                }
                
            } else {
                echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 1px solid #ff0000;'>";
                echo "<strong>❌ API接続失敗</strong><br>";
                echo "<strong>エラー:</strong> {$test_result['message']}<br>";
                if (isset($test_result['diagnostics'])) {
                    echo "<strong>診断情報:</strong><br>";
                    echo "<pre>" . print_r($test_result['diagnostics'], true) . "</pre>";
                }
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 1px solid #ff0000;'>";
            echo "<strong>❌ テスト中にエラー</strong><br>";
            echo "<strong>エラー内容:</strong> " . $e->getMessage() . "<br>";
            echo "<strong>ファイル:</strong> " . $e->getFile() . ":" . $e->getLine() . "<br>";
            echo "</div>";
        }
    }
}

// 7. WordPressのAJAXハンドラー確認
echo "<h2>⚙️ 5. WordPressのAJAXハンドラー確認</h2>";

global $wp_filter;
$ajax_actions = [
    'wp_ajax_gi_ai_auto_fill',
    'wp_ajax_gi_ai_test_connection'
];

foreach ($ajax_actions as $action) {
    if (isset($wp_filter[$action])) {
        echo "✅ {$action}: 登録済み<br>";
    } else {
        echo "❌ {$action}: 未登録<br>";
    }
}

// 8. 実際の投稿での確認
echo "<h2>📄 6. 実際の投稿データ確認</h2>";

$posts = get_posts([
    'post_type' => 'grant',
    'post_status' => 'draft',
    'numberposts' => 3
]);

if (empty($posts)) {
    echo "❌ 下書きのgrant投稿が見つかりません<br>";
    echo "テスト用にgrant投稿を作成してください。<br>";
} else {
    echo "✅ テスト可能な投稿が見つかりました:<br>";
    foreach ($posts as $post) {
        echo "• ID: {$post->ID} - {$post->post_title}<br>";
    }
    
    // 最初の投稿の詳細確認
    $test_post = $posts[0];
    echo "<h3>投稿 ID:{$test_post->ID} の詳細</h3>";
    
    $fields = ['ai_summary', 'grant_target', 'eligible_expenses'];
    foreach ($fields as $field) {
        $value = get_field($field, $test_post->ID) ?: get_post_meta($test_post->ID, $field, true);
        $status = empty($value) ? '❌ 空' : '✅ 有り';
        echo "• {$field}: {$status}<br>";
    }
}

// まとめ
echo "<h2>📋 7. 診断まとめ</h2>";

$all_checks = [
    $has_key => 'APIキーが設定されている',
    class_exists('GI_AI_Auto_Fill') => '必要なクラスが読み込まれている', 
    isset($test_result) && $test_result['success'] => 'API接続が成功している',
    isset($generation_result) && $generation_result['success'] => 'AI生成が動作している'
];

$passed = array_sum($all_checks);
$total = count($all_checks);

if ($passed === $total) {
    echo "<div style='background: #ddffdd; padding: 15px; border: 1px solid #00aa00;'>";
    echo "<strong>🎉 全てのチェックが通過しました！</strong><br>";
    echo "AI自動入力機能は正常に動作するはずです。<br>";
    echo "もし品質に問題がある場合は、プロンプトの改善が必要かもしれません。";
    echo "</div>";
} else {
    echo "<div style='background: #ffdddd; padding: 15px; border: 1px solid #ff0000;'>";
    echo "<strong>⚠️ 問題が見つかりました</strong><br>";
    echo "通過: {$passed}/{$total}<br>";
    echo "上記の問題を解決してから再テストしてください。";
    echo "</div>";
}

echo "</div>";
?>

<style>
h1, h2, h3 { color: #333; }
pre { font-size: 12px; }
</style>