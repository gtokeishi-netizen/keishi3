<?php
/**
 * AI自動入力機能 統合検証スクリプト
 * 
 * このスクリプトは現在の実装状況を詳細にチェックし、
 * 実際に動作するかどうかを検証します。
 */

// WordPressの読み込み
$wp_load_path = __DIR__ . '/wp-load.php';
if (file_exists($wp_load_path)) {
    require_once $wp_load_path;
} else {
    die("WordPress not found. Please ensure this script is in the WordPress root directory.\n");
}

echo "=== Grant Insight Perfect AI自動入力機能 統合検証 ===\n\n";

// 1. 基本ファイル存在確認
echo "1. ファイル存在確認:\n";
$required_files = [
    'inc/ai-api-handler.php',
    'inc/ai-auto-fill.php', 
    'inc/ai-admin-interface.php',
    'inc/fields-configuration.php'
];

foreach ($required_files as $file) {
    $path = __DIR__ . '/' . $file;
    $exists = file_exists($path) ? '✅' : '❌';
    echo "  {$exists} {$file}\n";
}

echo "\n";

// 2. クラス存在確認
echo "2. クラス存在確認:\n";
$required_classes = [
    'GI_AI_API_Handler',
    'GI_AI_Auto_Fill',
    'GI_AI_Admin_Interface'
];

foreach ($required_classes as $class) {
    $exists = class_exists($class) ? '✅' : '❌';
    echo "  {$exists} {$class}\n";
}

echo "\n";

// 3. 関数存在確認
echo "3. WordPress関数確認:\n";
$required_functions = [
    'get_field',
    'update_field',
    'wp_insert_post',
    'wp_update_post'
];

foreach ($required_functions as $func) {
    $exists = function_exists($func) ? '✅' : '❌';
    echo "  {$exists} {$func}()\n";
}

echo "\n";

// 4. データベーステーブル確認
echo "4. データベーステーブル確認:\n";
global $wpdb;

$expected_tables = [
    $wpdb->prefix . 'gi_ai_usage_log',
    $wpdb->prefix . 'gi_ai_backup', 
    $wpdb->prefix . 'gi_ai_settings'
];

foreach ($expected_tables as $table) {
    $exists = $wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table;
    $status = $exists ? '✅' : '❌';
    echo "  {$status} {$table}\n";
}

echo "\n";

// 5. 投稿タイプ確認
echo "5. 投稿タイプ確認:\n";
$grant_post_type = get_post_type_object('grant');
$exists = $grant_post_type !== null ? '✅' : '❌';
echo "  {$exists} grant投稿タイプ\n";

echo "\n";

// 6. ACFフィールド確認（ACFが有効な場合）
echo "6. ACFフィールド確認:\n";
if (function_exists('acf_get_field_groups')) {
    $field_groups = acf_get_field_groups();
    $grant_group_found = false;
    
    foreach ($field_groups as $group) {
        if (strpos($group['title'], 'Grant') !== false || strpos($group['title'], '助成金') !== false) {
            $grant_group_found = true;
            echo "  ✅ フィールドグループ: " . $group['title'] . "\n";
            
            // フィールド詳細確認
            $fields = acf_get_fields($group['key']);
            $target_fields = ['ai_summary', 'grant_target', 'eligible_expenses', 'grant_difficulty'];
            
            foreach ($target_fields as $target_field) {
                $found = false;
                foreach ($fields as $field) {
                    if ($field['name'] === $target_field) {
                        echo "    ✅ {$target_field}\n";
                        $found = true;
                        break;
                    }
                }
                if (!$found) {
                    echo "    ❌ {$target_field}\n";
                }
            }
        }
    }
    
    if (!$grant_group_found) {
        echo "  ❌ Grant関連のフィールドグループが見つかりません\n";
    }
} else {
    echo "  ❌ ACFプラグインが無効または未インストール\n";
}

echo "\n";

// 7. WordPress設定確認
echo "7. WordPress設定確認:\n";
$ai_settings = [
    'gi_openai_api_key_encrypted',
    'gi_ai_daily_limit',
    'gi_ai_auto_save',
    'gi_ai_db_version'
];

foreach ($ai_settings as $setting) {
    $value = get_option($setting);
    $exists = $value !== false ? '✅' : '❌';
    $display_value = $setting === 'gi_openai_api_key_encrypted' ? '(暗号化済み)' : $value;
    echo "  {$exists} {$setting}: {$display_value}\n";
}

echo "\n";

// 8. 簡単なAPI統合テスト
echo "8. API統合テスト:\n";
if (class_exists('GI_AI_API_Handler')) {
    try {
        $api_handler = new GI_AI_API_Handler();
        echo "  ✅ API Handlerインスタンス作成成功\n";
        
        // テストデータでのプロンプト生成
        $test_data = [
            'title' => 'テスト助成金',
            'organization' => 'テスト省庁',
            'max_amount' => '1000万円'
        ];
        
        // プロンプト生成テスト
        $reflection = new ReflectionClass($api_handler);
        $method = $reflection->getMethod('build_prompt');
        $method->setAccessible(true);
        
        $prompt_result = $method->invoke($api_handler, $test_data, 'ai_summary');
        
        if ($prompt_result) {
            echo "  ✅ プロンプト生成成功\n";
        } else {
            echo "  ❌ プロンプト生成失敗\n";
        }
        
    } catch (Exception $e) {
        echo "  ❌ API Handlerエラー: " . $e->getMessage() . "\n";
    }
} else {
    echo "  ❌ GI_AI_API_Handlerクラスが見つかりません\n";
}

echo "\n";

// 9. 管理画面メニュー確認
echo "9. 管理画面メニュー確認:\n";
if (function_exists('gi_ai_batch_processing_page')) {
    echo "  ✅ バッチ処理ページ関数\n";
} else {
    echo "  ❌ バッチ処理ページ関数\n";
}

if (function_exists('gi_render_ai_settings_page')) {
    echo "  ✅ AI設定ページ関数\n";
} else {
    echo "  ❌ AI設定ページ関数\n";
}

echo "\n";

// 10. 総合判定
echo "10. 総合判定:\n";
echo "この検証結果を基に、AI自動入力機能の動作準備状況を確認してください。\n";
echo "❌マークがある項目については、該当機能が正常に動作しない可能性があります。\n\n";

// メモリ使用量とパフォーマンス
echo "検証完了時のメモリ使用量: " . round(memory_get_usage(true) / 1024 / 1024, 2) . " MB\n";
echo "ピークメモリ使用量: " . round(memory_get_peak_usage(true) / 1024 / 1024, 2) . " MB\n";

echo "\n=== 検証完了 ===\n";