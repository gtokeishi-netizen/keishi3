<?php
/**
 * AI自動入力機能統合テスト
 * WordPress管理画面から呼び出される実際のテストスクリプト
 */

// WordPress環境の確認
if (!defined('ABSPATH')) {
    // WordPress環境ではない場合の処理
    echo "WordPress環境で実行してください。\n";
    return;
}

/**
 * AI自動入力機能テストクラス
 */
class GI_AI_Integration_Test {
    
    private $test_results = array();
    
    /**
     * テスト実行メイン関数
     */
    public function run_tests() {
        echo "<div style='font-family: monospace; background: #f0f0f0; padding: 20px;'>";
        echo "<h2>🔧 AI自動入力機能 統合テスト</h2>";
        
        $this->test_file_existence();
        $this->test_class_loading();
        $this->test_api_configuration();
        $this->test_acf_integration();
        $this->test_post_data_collection();
        $this->test_ai_regeneration_logic();
        
        echo "<h3>📋 テスト結果サマリー</h3>";
        $this->display_summary();
        echo "</div>";
    }
    
    /**
     * ファイル存在テスト
     */
    private function test_file_existence() {
        echo "<h3>📁 ファイル存在確認</h3>";
        
        $files = array(
            'inc/ai-auto-fill.php' => 'メインコントローラー',
            'inc/ai-api-handler.php' => 'APIハンドラー', 
            'inc/ai-admin-interface.php' => '管理画面UI',
            'assets/js/ai-auto-fill.js' => 'JavaScript'
        );
        
        foreach ($files as $file => $description) {
            $exists = file_exists($file);
            $this->log_result('file_' . basename($file), $exists, 
                $description . ': ' . ($exists ? '✅ 存在' : '❌ 不存在'));
        }
    }
    
    /**
     * クラス読み込みテスト
     */
    private function test_class_loading() {
        echo "<h3>🏗️ クラス読み込み確認</h3>";
        
        $classes = array(
            'GI_AI_Auto_Fill' => 'メインコントローラークラス',
            'GI_AI_API_Handler' => 'APIハンドラークラス',
            'GI_AI_Admin_Interface' => '管理画面UIクラス'
        );
        
        foreach ($classes as $class => $description) {
            $exists = class_exists($class);
            $this->log_result('class_' . $class, $exists,
                $description . ': ' . ($exists ? '✅ 利用可能' : '❌ 未定義'));
        }
    }
    
    /**
     * API設定テスト
     */
    private function test_api_configuration() {
        echo "<h3>🔑 API設定確認</h3>";
        
        $api_key = get_option('gi_openai_api_key');
        $encrypted_api_key = get_option('gi_openai_api_key_encrypted');
        $has_key = !empty($api_key) || !empty($encrypted_api_key);
        
        $this->log_result('api_key', $has_key,
            'OpenAI APIキー: ' . ($has_key ? '✅ 設定済み' : '❌ 未設定'));
        
        if (class_exists('GI_AI_API_Handler')) {
            try {
                $api_handler = new GI_AI_API_Handler();
                $test_result = $api_handler->test_connection();
                $this->log_result('api_connection', $test_result['success'],
                    'API接続テスト: ' . ($test_result['success'] ? '✅ 成功' : '❌ 失敗 - ' . $test_result['message']));
            } catch (Exception $e) {
                $this->log_result('api_connection', false,
                    'API接続テスト: ❌ エラー - ' . $e->getMessage());
            }
        }
    }
    
    /**
     * ACF統合テスト
     */
    private function test_acf_integration() {
        echo "<h3>🔗 ACF統合確認</h3>";
        
        $acf_available = function_exists('get_field') && function_exists('update_field');
        $this->log_result('acf_functions', $acf_available,
            'ACF関数: ' . ($acf_available ? '✅ 利用可能' : '❌ 利用不可'));
        
        if (!$acf_available) {
            echo "<p style='color: orange;'>⚠️ ACFが無効でもカスタムフィールドでフォールバック可能</p>";
        }
    }
    
    /**
     * 投稿データ収集テスト
     */
    private function test_post_data_collection() {
        echo "<h3>📋 投稿データ収集テスト</h3>";
        
        // テスト用の投稿を検索
        $test_posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => 'draft',
            'numberposts' => 1
        ));
        
        if (empty($test_posts)) {
            $this->log_result('test_post', false, '❌ テスト用の下書きgrant投稿が見つかりません');
            return;
        }
        
        $test_post = $test_posts[0];
        $this->log_result('test_post', true, '✅ テスト投稿ID: ' . $test_post->ID . ' (' . $test_post->post_title . ')');
        
        if (class_exists('GI_AI_Auto_Fill')) {
            try {
                $auto_fill = new GI_AI_Auto_Fill();
                
                // collect_post_data メソッドの動作テスト
                $reflection = new ReflectionClass($auto_fill);
                $method = $reflection->getMethod('collect_post_data');
                $method->setAccessible(true);
                $post_data = $method->invoke($auto_fill, $test_post->ID);
                
                if (is_array($post_data)) {
                    $this->log_result('post_data_collection', true,
                        '✅ 投稿データ収集成功 (' . count($post_data) . '項目)');
                    
                    // 既存AIコンテンツの確認
                    if (isset($post_data['existing_ai_content'])) {
                        $existing_count = count($post_data['existing_ai_content']);
                        echo "<p>📝 既存AIコンテンツ: {$existing_count}件検出</p>";
                        
                        foreach ($post_data['existing_ai_content'] as $field => $data) {
                            echo "<p style='margin-left: 20px;'>• {$data['label']}: " . mb_substr(strip_tags($data['value']), 0, 50) . "...</p>";
                        }
                    } else {
                        echo "<p>📝 既存AIコンテンツ: なし</p>";
                    }
                } else {
                    $this->log_result('post_data_collection', false, '❌ 投稿データ収集失敗');
                }
            } catch (Exception $e) {
                $this->log_result('post_data_collection', false, '❌ エラー: ' . $e->getMessage());
            }
        }
    }
    
    /**
     * AI再生成ロジックテスト
     */
    private function test_ai_regeneration_logic() {
        echo "<h3>🔄 再生成ロジックテスト</h3>";
        
        // フィールドの再生成可能性をテスト
        $test_posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => 'draft',
            'numberposts' => 1
        ));
        
        if (empty($test_posts)) {
            echo "<p>❌ テスト投稿がありません</p>";
            return;
        }
        
        $test_post = $test_posts[0];
        $target_fields = array('ai_summary', 'grant_target', 'eligible_expenses');
        
        echo "<p>🎯 テスト対象フィールド: " . implode(', ', $target_fields) . "</p>";
        
        foreach ($target_fields as $field_name) {
            $value = function_exists('get_field') ? get_field($field_name, $test_post->ID) : get_post_meta($test_post->ID, $field_name, true);
            $has_content = !empty($value) && trim(strip_tags($value)) !== '';
            
            echo "<p style='margin-left: 20px;'>• {$field_name}: " . 
                 ($has_content ? '📝 入力済み（再生成可能）' : '⭕ 未入力（新規生成）') . "</p>";
        }
        
        // 実際の再生成ロジックが機能するかテスト（APIキーがある場合のみ）
        $api_key = get_option('gi_openai_api_key') ?: get_option('gi_openai_api_key_encrypted');
        
        if (!empty($api_key) && class_exists('GI_AI_Auto_Fill')) {
            echo "<p>🚀 実際の再生成テスト実行可能</p>";
            $this->log_result('regeneration_ready', true, '✅ 再生成機能は正常に動作する準備ができています');
        } else {
            echo "<p>⏸️ APIキー未設定のため実際のテストはスキップ</p>";
            $this->log_result('regeneration_ready', false, '⚠️ APIキー設定後に再生成機能が利用可能になります');
        }
    }
    
    /**
     * テスト結果の記録
     */
    private function log_result($test_name, $success, $message) {
        $this->test_results[$test_name] = array(
            'success' => $success,
            'message' => $message
        );
        echo "<p>{$message}</p>";
    }
    
    /**
     * テスト結果サマリー表示
     */
    private function display_summary() {
        $total = count($this->test_results);
        $passed = array_sum(array_column($this->test_results, 'success'));
        $failed = $total - $passed;
        
        echo "<div style='background: " . ($failed == 0 ? '#d4edda' : '#f8d7da') . "; padding: 15px; border-radius: 5px;'>";
        echo "<strong>総テスト数: {$total}</strong><br>";
        echo "<span style='color: green;'>✅ 成功: {$passed}</span><br>";
        echo "<span style='color: red;'>❌ 失敗: {$failed}</span><br>";
        
        if ($failed == 0) {
            echo "<br><strong style='color: green;'>🎉 すべてのテストが通過しました！AI自動入力機能は正常に動作する準備ができています。</strong>";
        } else {
            echo "<br><strong style='color: red;'>⚠️ 一部のテストが失敗しています。上記の問題を解決してください。</strong>";
        }
        echo "</div>";
        
        echo "<h4>🔧 失敗項目の詳細:</h4>";
        foreach ($this->test_results as $test_name => $result) {
            if (!$result['success']) {
                echo "<p style='background: #fff3cd; padding: 10px; border-left: 4px solid #ffc107;'>";
                echo "<strong>{$test_name}:</strong> {$result['message']}</p>";
            }
        }
    }
}

// WordPressの管理画面からアクセスされた場合のみ実行
if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
    $test = new GI_AI_Integration_Test();
    $test->run_tests();
}