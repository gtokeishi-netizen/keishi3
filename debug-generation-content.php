<?php
/**
 * AI生成内容のデバッグスクリプト
 * 実際に何が生成されているか、どんなデータが渡されているかを確認
 */

// WordPressロード
if (!defined('ABSPATH')) {
    require_once 'wp-config.php';
}

/**
 * AI生成内容デバッグクラス  
 */
class GI_AI_Generation_Debug {
    
    public function debug_generation_process($post_id = null) {
        echo "<div style='font-family: monospace; background: #f0f0f0; padding: 20px; max-width: 1200px;'>";
        echo "<h2>🔍 AI生成内容デバッグレポート</h2>";
        
        // テスト投稿の取得
        if (!$post_id) {
            $test_posts = get_posts(array(
                'post_type' => 'grant',
                'post_status' => 'draft',
                'numberposts' => 1
            ));
            
            if (empty($test_posts)) {
                echo "<p style='color: red;'>❌ テスト用のgrant投稿が見つかりません</p>";
                echo "</div>";
                return;
            }
            
            $post_id = $test_posts[0]->ID;
        }
        
        $post = get_post($post_id);
        echo "<h3>📝 対象投稿: {$post->post_title} (ID: {$post_id})</h3>";
        
        // 1. 基本情報の確認
        $this->debug_post_basic_info($post_id);
        
        // 2. 収集される投稿データの確認
        $this->debug_collected_post_data($post_id);
        
        // 3. 各フィールドのプロンプト確認
        $this->debug_field_prompts($post_id);
        
        // 4. 実際のAPI呼び出しテスト（APIキーがある場合）
        $this->debug_actual_api_call($post_id);
        
        echo "</div>";
    }
    
    private function debug_post_basic_info($post_id) {
        echo "<h3>📊 基本投稿情報</h3>";
        $post = get_post($post_id);
        
        echo "<table style='border: 1px solid #ccc; border-collapse: collapse; width: 100%;'>";
        echo "<tr style='background: #e0e0e0;'><th style='border: 1px solid #ccc; padding: 8px;'>項目</th><th style='border: 1px solid #ccc; padding: 8px;'>値</th></tr>";
        
        $basic_fields = array(
            'post_title' => $post->post_title,
            'post_content' => mb_substr(strip_tags($post->post_content), 0, 100) . '...',
            'post_status' => $post->post_status,
            'grant_organization' => get_field('grant_organization', $post_id) ?: get_post_meta($post_id, 'grant_organization', true),
            'grant_max_amount' => get_field('grant_max_amount', $post_id) ?: get_post_meta($post_id, 'grant_max_amount', true),
            'grant_official_url' => get_field('grant_official_url', $post_id) ?: get_post_meta($post_id, 'grant_official_url', true),
        );
        
        foreach ($basic_fields as $key => $value) {
            $status = empty($value) ? '❌ 空' : '✅ 有り';
            $display_value = empty($value) ? '(空)' : htmlspecialchars(mb_substr($value, 0, 80));
            echo "<tr><td style='border: 1px solid #ccc; padding: 8px;'>{$key}</td><td style='border: 1px solid #ccc; padding: 8px;'>{$status} {$display_value}</td></tr>";
        }
        
        echo "</table>";
    }
    
    private function debug_collected_post_data($post_id) {
        echo "<h3>🗂️ 収集された投稿データ</h3>";
        
        if (!class_exists('GI_AI_Auto_Fill')) {
            echo "<p style='color: red;'>❌ GI_AI_Auto_Fill クラスが利用できません</p>";
            return;
        }
        
        try {
            $auto_fill = new GI_AI_Auto_Fill();
            $reflection = new ReflectionClass($auto_fill);
            $method = $reflection->getMethod('collect_post_data');
            $method->setAccessible(true);
            $post_data = $method->invoke($auto_fill, $post_id);
            
            echo "<h4>📋 収集されたデータ:</h4>";
            echo "<pre style='background: white; padding: 10px; border: 1px solid #ccc; overflow-x: auto;'>";
            print_r($post_data);
            echo "</pre>";
            
            // 既存AIコンテンツの詳細表示
            if (isset($post_data['existing_ai_content'])) {
                echo "<h4>🧠 既存AIコンテンツ（コンテキスト用）:</h4>";
                foreach ($post_data['existing_ai_content'] as $field => $data) {
                    echo "<div style='background: #e8f4f8; padding: 10px; margin: 5px 0; border-left: 4px solid #0073aa;'>";
                    echo "<strong>{$data['label']} ({$field}):</strong><br>";
                    echo htmlspecialchars(mb_substr($data['value'], 0, 200)) . "...";
                    echo "</div>";
                }
            } else {
                echo "<p>ℹ️ 既存AIコンテンツなし（初回生成または他フィールドが空）</p>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ エラー: " . $e->getMessage() . "</p>";
        }
    }
    
    private function debug_field_prompts($post_id) {
        echo "<h3>📝 フィールド別プロンプト</h3>";
        
        if (!class_exists('GI_AI_Auto_Fill') || !class_exists('GI_AI_API_Handler')) {
            echo "<p style='color: red;'>❌ 必要なクラスが利用できません</p>";
            return;
        }
        
        try {
            // 投稿データ収集
            $auto_fill = new GI_AI_Auto_Fill();
            $reflection_auto = new ReflectionClass($auto_fill);
            $collect_method = $reflection_auto->getMethod('collect_post_data');
            $collect_method->setAccessible(true);
            $post_data = $collect_method->invoke($auto_fill, $post_id);
            
            // API Handler でプロンプト生成
            $api_handler = new GI_AI_API_Handler();
            $reflection_api = new ReflectionClass($api_handler);
            
            $test_fields = array('ai_summary', 'grant_target', 'eligible_expenses');
            
            foreach ($test_fields as $field_name) {
                echo "<h4>🎯 フィールド: {$field_name}</h4>";
                
                try {
                    $format_method = $reflection_api->getMethod('format_base_info');
                    $format_method->setAccessible(true);
                    $base_info = $format_method->invoke($api_handler, $post_data);
                    
                    $prompt_method = $reflection_api->getMethod('get_field_specific_prompt');
                    $prompt_method->setAccessible(true);
                    $field_prompt = $prompt_method->invoke($api_handler, $field_name, $post_data);
                    
                    echo "<div style='background: white; padding: 10px; border: 1px solid #ccc; margin: 10px 0;'>";
                    echo "<h5>📄 基本情報部分:</h5>";
                    echo "<pre style='font-size: 12px; white-space: pre-wrap;'>" . htmlspecialchars($base_info) . "</pre>";
                    
                    echo "<h5>🎯 フィールド固有プロンプト:</h5>";
                    echo "<pre style='font-size: 12px; white-space: pre-wrap;'>" . htmlspecialchars($field_prompt) . "</pre>";
                    echo "</div>";
                    
                } catch (Exception $e) {
                    echo "<p style='color: red;'>❌ {$field_name} のプロンプト生成エラー: " . $e->getMessage() . "</p>";
                }
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ プロンプトデバッグエラー: " . $e->getMessage() . "</p>";
        }
    }
    
    private function debug_actual_api_call($post_id) {
        echo "<h3>🚀 実際のAPI呼び出しテスト</h3>";
        
        $api_key = get_option('gi_openai_api_key') ?: get_option('gi_openai_api_key_encrypted');
        
        if (empty($api_key)) {
            echo "<p style='color: orange;'>⚠️ APIキー未設定のため、実際の生成テストはスキップします</p>";
            return;
        }
        
        if (!class_exists('GI_AI_Auto_Fill') || !class_exists('GI_AI_API_Handler')) {
            echo "<p style='color: red;'>❌ 必要なクラスが利用できません</p>";
            return;
        }
        
        try {
            // 投稿データ収集
            $auto_fill = new GI_AI_Auto_Fill();
            $reflection_auto = new ReflectionClass($auto_fill);
            $collect_method = $reflection_auto->getMethod('collect_post_data');
            $collect_method->setAccessible(true);
            $post_data = $collect_method->invoke($auto_fill, $post_id);
            
            // APIテスト（ai_summaryフィールド）
            $api_handler = new GI_AI_API_Handler();
            echo "<h4>🧪 ai_summary フィールド生成テスト</h4>";
            
            $result = $api_handler->generate_field_content($post_data, 'ai_summary');
            
            if ($result['success']) {
                echo "<div style='background: #d4edda; padding: 15px; border: 1px solid #c3e6cb; border-radius: 5px;'>";
                echo "<h5>✅ 生成成功!</h5>";
                echo "<p><strong>生成内容:</strong></p>";
                echo "<div style='background: white; padding: 10px; border: 1px solid #ccc;'>";
                echo htmlspecialchars($result['content']);
                echo "</div>";
                echo "<p><strong>使用トークン数:</strong> {$result['tokens_used']}</p>";
                echo "<p><strong>処理時間:</strong> {$result['response_time']}秒</p>";
                echo "</div>";
            } else {
                echo "<div style='background: #f8d7da; padding: 15px; border: 1px solid #f5c6cb; border-radius: 5px;'>";
                echo "<h5>❌ 生成失敗</h5>";
                echo "<p><strong>エラー:</strong> " . htmlspecialchars($result['error']) . "</p>";
                echo "</div>";
            }
            
        } catch (Exception $e) {
            echo "<p style='color: red;'>❌ API呼び出しテストエラー: " . $e->getMessage() . "</p>";
        }
    }
}

// 実行
if (is_admin() || (defined('WP_CLI') && WP_CLI)) {
    $debug = new GI_AI_Generation_Debug();
    
    // URLパラメータでpost_idが指定されている場合はそれを使用
    $post_id = isset($_GET['post_id']) ? intval($_GET['post_id']) : null;
    $debug->debug_generation_process($post_id);
    
    echo "<hr><p><strong>使用方法:</strong> このスクリプトにURLパラメータ <code>?post_id=123</code> を付けることで特定の投稿をテストできます。</p>";
}
?>