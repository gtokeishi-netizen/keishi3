<?php
/**
 * WordPress AI System Diagnostic Script
 * しっかりチェック - Thorough system verification
 */

// Check if we can access WordPress functions
if (file_exists('wp-config.php')) {
    // Load WordPress environment
    define('WP_USE_THEMES', false);
    require_once('wp-config.php');
    require_once(ABSPATH . 'wp-load.php');
    
    echo "<div style='font-family: monospace; background: #f0f0f0; padding: 20px; max-width: 1200px;'>";
    echo "<h1>🔍 WordPress AI システム診断 - しっかりチェック</h1>";
    echo "<p><strong>診断時刻:</strong> " . current_time('Y-m-d H:i:s') . "</p>";
    
    // === 1. 基本環境チェック ===
    echo "<h2>📋 1. 基本環境チェック</h2>";
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>項目</th><th>値</th><th>ステータス</th></tr>";
    
    // WordPress情報
    echo "<tr><td>WordPress Version</td><td>" . get_bloginfo('version') . "</td><td>✅</td></tr>";
    echo "<tr><td>PHP Version</td><td>" . PHP_VERSION . "</td><td>✅</td></tr>";
    echo "<tr><td>Theme</td><td>" . get_template() . "</td><td>✅</td></tr>";
    echo "<tr><td>Current User</td><td>" . wp_get_current_user()->user_login . "</td><td>✅</td></tr>";
    echo "</table>";
    
    // === 2. ファイル存在確認 ===
    echo "<h2>📁 2. ファイル存在確認</h2>";
    $critical_files = [
        'inc/ai-auto-fill.php' => 'メインAIコントローラー',
        'inc/ai-api-handler.php' => 'APIハンドラー',
        'inc/ai-admin-interface.php' => '管理画面インターフェース',
        'assets/js/ai-auto-fill.js' => 'JavaScript フロントエンド'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>ファイル</th><th>説明</th><th>存在</th><th>サイズ</th></tr>";
    
    foreach ($critical_files as $file => $description) {
        $exists = file_exists($file);
        $size = $exists ? filesize($file) : 0;
        $status = $exists ? '✅' : '❌';
        echo "<tr><td>{$file}</td><td>{$description}</td><td>{$status}</td><td>" . 
             ($exists ? number_format($size) . ' bytes' : 'N/A') . "</td></tr>";
    }
    echo "</table>";
    
    // === 3. クラス読み込み確認 ===
    echo "<h2>🏗️ 3. クラス読み込み確認</h2>";
    
    // Load the classes
    if (file_exists('inc/ai-auto-fill.php')) {
        require_once('inc/ai-auto-fill.php');
    }
    if (file_exists('inc/ai-api-handler.php')) {
        require_once('inc/ai-api-handler.php');
    }
    
    $required_classes = [
        'GI_AI_Auto_Fill' => 'メインコントローラークラス',
        'GI_AI_API_Handler' => 'APIハンドラークラス'
    ];
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>クラス</th><th>説明</th><th>読み込み</th><th>インスタンス化</th></tr>";
    
    foreach ($required_classes as $class => $description) {
        $class_exists = class_exists($class);
        $can_instantiate = false;
        
        if ($class_exists) {
            try {
                $instance = new $class();
                $can_instantiate = true;
            } catch (Exception $e) {
                $can_instantiate = false;
            }
        }
        
        $status1 = $class_exists ? '✅' : '❌';
        $status2 = $can_instantiate ? '✅' : '❌';
        echo "<tr><td>{$class}</td><td>{$description}</td><td>{$status1}</td><td>{$status2}</td></tr>";
    }
    echo "</table>";
    
    // === 4. APIキー設定確認 ===
    echo "<h2>🔑 4. APIキー設定確認</h2>";
    
    $api_key = get_option('gi_openai_api_key');
    $encrypted_api_key = get_option('gi_openai_api_key_encrypted');
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>設定項目</th><th>ステータス</th><th>詳細</th></tr>";
    
    if (!empty($api_key)) {
        $masked_key = str_repeat('*', max(0, strlen($api_key) - 8)) . substr($api_key, -8);
        echo "<tr><td>通常APIキー</td><td>✅ 設定済み</td><td>{$masked_key}</td></tr>";
    } else {
        echo "<tr><td>通常APIキー</td><td>❌ 未設定</td><td>-</td></tr>";
    }
    
    if (!empty($encrypted_api_key)) {
        echo "<tr><td>暗号化APIキー</td><td>✅ 設定済み</td><td>暗号化データあり</td></tr>";
    } else {
        echo "<tr><td>暗号化APIキー</td><td>❌ 未設定</td><td>-</td></tr>";
    }
    echo "</table>";
    
    $has_api_key = !empty($api_key) || !empty($encrypted_api_key);
    
    if (!$has_api_key) {
        echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
        echo "<h3>🚨 重大な問題：APIキーが設定されていません！</h3>";
        echo "<p>WordPressの管理画面で「設定 > AI自動入力設定」からOpenAI APIキーを設定してください。</p>";
        echo "</div>";
    }
    
    // === 5. 実際のAPI接続テスト ===
    if ($has_api_key && class_exists('GI_AI_API_Handler')) {
        echo "<h2>🌐 5. API接続テスト</h2>";
        
        try {
            echo "<p>🔄 APIハンドラーインスタンス作成中...</p>";
            $api_handler = new GI_AI_API_Handler();
            
            echo "<p>🔄 API接続テスト実行中...</p>";
            $test_result = $api_handler->test_connection();
            
            if ($test_result['success']) {
                echo "<div style='background: #ddffdd; padding: 15px; margin: 10px 0; border: 2px solid #00aa00;'>";
                echo "<h3>✅ API接続成功！</h3>";
                echo "<p><strong>結果:</strong> {$test_result['message']}</p>";
                echo "</div>";
                
                // === 6. 実際の生成テスト ===
                echo "<h2>🚀 6. AI生成テスト</h2>";
                
                $test_post_data = [
                    'title' => 'IT導入支援助成金制度テスト',
                    'organization' => '中小企業庁',
                    'max_amount' => '450万円',
                    'target_business_type' => '中小企業・個人事業主',
                    'existing_ai_content' => []
                ];
                
                echo "<h3>📝 テスト用投稿データ</h3>";
                echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; font-size: 12px;'>";
                print_r($test_post_data);
                echo "</pre>";
                
                // AI概要フィールドの生成テスト
                echo "<h3>🎯 AI概要フィールド生成テスト</h3>";
                echo "<p>🔄 プロンプト生成・API呼び出し中...</p>";
                
                $start_time = microtime(true);
                $generation_result = $api_handler->generate_field_content($test_post_data, 'ai_summary');
                $end_time = microtime(true);
                $generation_time = $end_time - $start_time;
                
                if ($generation_result['success']) {
                    echo "<div style='background: #ddffdd; padding: 15px; margin: 10px 0; border: 2px solid #00aa00;'>";
                    echo "<h4>✅ 生成成功！</h4>";
                    
                    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                    echo "<tr><th>項目</th><th>値</th></tr>";
                    echo "<tr><td>処理時間</td><td>" . number_format($generation_time, 2) . "秒</td></tr>";
                    echo "<tr><td>使用トークン</td><td>" . $generation_result['tokens_used'] . "</td></tr>";
                    echo "<tr><td>生成文字数</td><td>" . mb_strlen($generation_result['content']) . "文字</td></tr>";
                    echo "</table>";
                    
                    echo "<h4>📄 生成された内容:</h4>";
                    echo "<div style='background: white; padding: 15px; border: 1px solid #ccc; font-size: 14px;'>";
                    echo "<strong>内容:</strong><br>" . nl2br(htmlspecialchars($generation_result['content']));
                    echo "</div>";
                    
                    // === 7. 品質分析 ===
                    echo "<h3>🎯 生成内容品質分析</h3>";
                    $content = $generation_result['content'];
                    
                    // 問題点チェック
                    $issues = [];
                    if (strpos($content, '、、、') !== false) $issues[] = '不正な記号連続（、、、）を検出';
                    if (strpos($content, '。。。') !== false) $issues[] = '不正な記号連続（。。。）を検出';
                    if (strpos($content, '令和') !== false) $issues[] = '年号（令和）を検出';
                    if (strpos($content, '平成') !== false) $issues[] = '年号（平成）を検出';
                    if (mb_strlen($content) < 30) $issues[] = '内容が短すぎ（30文字未満）';
                    if (mb_strlen($content) > 220) $issues[] = '内容が長すぎ（220文字超過）';
                    
                    // 良い点チェック
                    $good_points = [];
                    if (preg_match('/(助成金|補助金|支援制度)/', $content)) $good_points[] = '適切なキーワードを含む';
                    if (preg_match('/\d+万円|\d+％/', $content)) $good_points[] = '具体的な金額・率を含む';
                    if (preg_match('/(中小企業|スタートアップ|製造業|IT|サービス業)/', $content)) $good_points[] = '具体的な対象者を含む';
                    if (preg_match('/(設備|システム|研修|開発)/', $content)) $good_points[] = '具体的な用途を含む';
                    
                    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                    echo "<tr><th>品質項目</th><th>結果</th><th>詳細</th></tr>";
                    
                    if (empty($issues)) {
                        echo "<tr style='background: #ddffdd;'><td>問題点</td><td>✅ なし</td><td>品質に問題なし</td></tr>";
                    } else {
                        echo "<tr style='background: #ffdddd;'><td>問題点</td><td>❌ " . count($issues) . "件</td><td>" . implode('<br>', $issues) . "</td></tr>";
                    }
                    
                    if (!empty($good_points)) {
                        echo "<tr style='background: #ddffdd;'><td>良いポイント</td><td>✅ " . count($good_points) . "件</td><td>" . implode('<br>', $good_points) . "</td></tr>";
                    }
                    
                    echo "</table>";
                    
                    echo "</div>";
                    
                } else {
                    echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
                    echo "<h4>❌ 生成失敗</h4>";
                    echo "<p><strong>エラー:</strong> " . htmlspecialchars($generation_result['error']) . "</p>";
                    echo "<p><strong>処理時間:</strong> " . number_format($generation_time, 2) . "秒</p>";
                    echo "</div>";
                }
                
            } else {
                echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
                echo "<h3>❌ API接続失敗</h3>";
                echo "<p><strong>エラー:</strong> {$test_result['message']}</p>";
                if (isset($test_result['debug_info'])) {
                    echo "<h4>🔍 デバッグ情報:</h4>";
                    echo "<pre>" . print_r($test_result['debug_info'], true) . "</pre>";
                }
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
            echo "<h3>❌ テスト実行エラー</h3>";
            echo "<p><strong>エラー:</strong> " . htmlspecialchars($e->getMessage()) . "</p>";
            echo "<p><strong>ファイル:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>";
            echo "</div>";
        }
    }
    
    // === 8. 診断まとめ ===
    echo "<h2>📋 8. 診断まとめ</h2>";
    
    $check_results = [];
    $check_results[] = ['項目' => 'ファイル存在', '結果' => count($critical_files) === count(array_filter($critical_files, function($file) { return file_exists(array_search($file, $critical_files)); }))];
    $check_results[] = ['項目' => 'クラス読み込み', '結果' => class_exists('GI_AI_Auto_Fill') && class_exists('GI_AI_API_Handler')];
    $check_results[] = ['項目' => 'APIキー設定', '結果' => $has_api_key];
    $check_results[] = ['項目' => 'API接続', '結果' => isset($test_result) && $test_result['success']];
    $check_results[] = ['項目' => 'AI生成', '結果' => isset($generation_result) && $generation_result['success']];
    
    $passed_checks = array_sum(array_column($check_results, '結果'));
    $total_checks = count($check_results);
    
    echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
    echo "<tr><th>チェック項目</th><th>結果</th></tr>";
    
    foreach ($check_results as $check) {
        $status = $check['結果'] ? '✅ 成功' : '❌ 失敗';
        $bg_color = $check['結果'] ? '#ddffdd' : '#ffdddd';
        echo "<tr style='background: {$bg_color};'><td>{$check['項目']}</td><td>{$status}</td></tr>";
    }
    
    echo "</table>";
    
    if ($passed_checks === $total_checks) {
        echo "<div style='background: #ddffdd; padding: 20px; margin: 20px 0; border: 3px solid #00aa00;'>";
        echo "<h3>🎉 すべてのテストが成功しました！</h3>";
        echo "<p><strong>結果:</strong> {$passed_checks}/{$total_checks} 項目で合格</p>";
        echo "<p>AI自動入力機能は正常に動作しています。品質に関する問題がある場合は、プロンプトの調整が必要かもしれません。</p>";
        echo "</div>";
    } else {
        echo "<div style='background: #ffdddd; padding: 20px; margin: 20px 0; border: 3px solid #ff0000;'>";
        echo "<h3>⚠️ 問題が検出されました</h3>";
        echo "<p><strong>結果:</strong> {$passed_checks}/{$total_checks} 項目で合格</p>";
        echo "<p>上記の失敗項目を確認し、問題を解決してから再テストしてください。</p>";
        echo "</div>";
    }
    
    echo "</div>";
    
} else {
    echo "<h1>❌ WordPress環境が見つかりません</h1>";
    echo "<p>wp-config.php が見つかりません。WordPressのルートディレクトリで実行してください。</p>";
}
?>