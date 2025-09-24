<?php
/**
 * Grant Insight Perfect - Functions File Loader (Cleanup Edition)
 * 
 * ファイル整理により不要ファイルを削除、8個に整理
 * - 重複ファイル削除（ajax-functions系の3ファイル → 1ファイル）
 * - ファイル名をわかりやすくリネーム
 * - 機能別にファイルを整理・最適化
 * 
 * @package Grant_Insight_Perfect
 * @version 8.1.0
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}

// テーマバージョン定数（ファイル整理版）
if (!defined('GI_THEME_VERSION')) {
    define('GI_THEME_VERSION', '8.1.0');
}
if (!defined('GI_THEME_PREFIX')) {
    define('GI_THEME_PREFIX', 'gi_');
}

// 機能ファイルの読み込み
$inc_dir = get_template_directory() . '/inc/';

// 整理されたファイル構成（10ファイル → 8ファイル、リネーム済み + AI自動入力機能追加）
$required_files = array(
    'theme-foundation.php',     // テーマ設定、投稿タイプ、タクソノミー統合（旧：core-setup.php）
    'card-rendering.php',       // カードレンダリング、テンプレート、モバイル最適化統合（旧：display-functions.php）
    'data-processing.php',      // ヘルパー関数、パフォーマンス最適化統合（旧：data-functions.php）
    'search-integration.php',   // AI機能・検索履歴統合（旧：ai-functions.php）
    'ajax-handlers.php',        // AJAX処理（旧：3-ajax-functions.php）
    'admin-customization.php',  // 管理画面機能（旧：6-admin-functions.php）
    'fields-configuration.php', // ACF設定とフィールド定義統合（旧：acf-setup.php）
    'external-importer.php',    // Jグランツ・インポーター機能（旧：grant-insight-jgrants-importer.php）
    // AI自動入力機能（新規追加）
    'ai-api-handler.php',       // ChatGPT API通信クラス
    'ai-auto-fill.php',        // AI自動入力メインコントローラー
    'ai-admin-interface.php'    // AI自動入力管理画面UI
);

// 各ファイルを安全に読み込み
foreach ($required_files as $file) {
    $file_path = $inc_dir . $file;
    if (file_exists($file_path)) {
        require_once $file_path;
    } else {
        // デバッグモードの場合はエラーログに記録
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Grant Insight Theme: Required file not found - ' . $file_path);
        }
    }
}

// 統一カードレンダラーは display-functions.php に統合済み
// テンプレートファイルのチェック
$card_unified_path = get_template_directory() . '/template-parts/grant-card-unified.php';
if (file_exists($card_unified_path)) {
    require_once $card_unified_path;
} else {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Grant Insight Theme: grant-card-unified.php not found at ' . $card_unified_path);
    }
}

// グローバルで使えるヘルパー関数
if (!function_exists('gi_render_card')) {
    function gi_render_card($post_id, $view = 'grid') {
        if (class_exists('GrantCardRenderer')) {
            $renderer = GrantCardRenderer::getInstance();
            return $renderer->render($post_id, $view);
        }
        
        // フォールバック
        return '<div class="grant-card-error">カードレンダラーが利用できません</div>';
    }
}

/**
 * AI自動入力機能の初期化
 */
function gi_ai_auto_fill_init() {
    // AI自動入力機能が利用可能かチェック
    if (class_exists('GI_AI_Auto_Fill') && class_exists('GI_AI_API_Handler') && class_exists('GI_AI_Admin_Interface')) {
        // AI機能が正常にロードされた場合の処理
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Grant Insight AI Auto Fill: All AI modules loaded successfully');
        }
        
        // 管理画面でのみCSSとJSを読み込み
        if (is_admin()) {
            add_action('admin_enqueue_scripts', 'gi_ai_enqueue_admin_assets');
        }
    } else {
        // AI機能が利用できない場合の警告
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('Grant Insight AI Auto Fill: One or more AI modules failed to load');
        }
        
        // 管理者向けの通知を追加
        if (is_admin() && current_user_can('manage_options')) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-warning"><p>';
                echo '<strong>AI自動入力機能:</strong> 一部のモジュールが正常にロードされませんでした。ファイルの存在を確認してください。';
                echo '</p></div>';
            });
        }
    }
}
add_action('init', 'gi_ai_auto_fill_init', 20);

/**
 * AI自動入力用のアセット読み込み
 */
function gi_ai_enqueue_admin_assets($hook) {
    // 投稿編集画面とAI設定画面でのみ読み込み
    if ($hook !== 'post.php' && $hook !== 'post-new.php' && $hook !== 'settings_page_gi-ai-settings') {
        return;
    }
    
    global $post;
    // grant投稿タイプ以外では読み込まない
    if (isset($post) && $post->post_type !== 'grant') {
        return;
    }
    
    // AI自動入力用のJavaScript
    wp_enqueue_script(
        'gi-ai-auto-fill',
        get_template_directory_uri() . '/assets/js/ai-auto-fill.js',
        array('jquery', 'wp-util'),
        GI_THEME_VERSION,
        true
    );
    
    // AI自動入力用のCSS（管理画面用スタイルは各クラスで処理）
    wp_enqueue_style(
        'gi-ai-admin-styles',
        get_template_directory_uri() . '/assets/css/ai-admin-styles.css',
        array(),
        GI_THEME_VERSION
    );
    
    // JavaScript用の設定値を渡す
    wp_localize_script('gi-ai-auto-fill', 'gi_ai_ajax', array(
        'ajax_url' => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('gi_ai_auto_fill_nonce'),
        'strings' => array(
            'processing' => 'AI処理中...',
            'completed' => '処理完了',
            'error' => 'エラーが発生しました',
            'confirm_process' => 'AI自動入力を実行しますか？',
            'confirm_rollback' => 'ロールバックを実行しますか？すべての変更が元に戻ります。',
            'no_fields_selected' => '処理対象のフィールドを選択してください',
            'daily_limit_reached' => '本日の利用上限に達しました'
        ),
        'daily_usage' => function_exists('gi_get_daily_ai_usage') ? gi_get_daily_ai_usage() : 0,
        'daily_limit' => get_option('gi_ai_daily_limit', 100),
        'debug' => defined('WP_DEBUG') && WP_DEBUG
    ));
}

/**
 * 日次AI使用量を取得するヘルパー関数
 */
function gi_get_daily_ai_usage() {
    global $wpdb;
    
    $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
    
    // テーブルが存在するかチェック
    if ($wpdb->get_var("SHOW TABLES LIKE '$usage_table'") !== $usage_table) {
        return 0;
    }
    
    $today = current_time('Y-m-d');
    
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $usage_table 
         WHERE DATE(timestamp) = %s AND success = 1",
        $today
    ));
    
    return intval($count);
}

/**
 * テーマの最終初期化
 */
function gi_final_init() {  // ✅ 修正
    if (defined('WP_DEBUG') && WP_DEBUG) {
        error_log('Grant Insight Theme v' . GI_THEME_VERSION . ': File cleanup completed, 8 organized files + AI modules loaded successfully');
    }
}
add_action('wp_loaded', 'gi_final_init', 999);

// 以下のコードはそのまま...


/**
 * クリーンアップ処理
 */
function gi_theme_cleanup() {
    // オプションの削除
    delete_option('gi_login_attempts');
    
    // モバイル最適化キャッシュのクリア
    delete_option('gi_mobile_cache');
    
    // トランジェントのクリア
    delete_transient('gi_site_stats_v2');
    
    // オブジェクトキャッシュのフラッシュ（存在する場合のみ）
    if (function_exists('wp_cache_flush')) {
        wp_cache_flush();
    }
}
add_action('switch_theme', 'gi_theme_cleanup');



/**
 * スクリプトにdefer属性を追加（改善版）
 */
if (!function_exists('gi_add_defer_attribute')) {
    function gi_add_defer_attribute($tag, $handle, $src) {
        // 管理画面では処理しない
        if (is_admin()) {
            return $tag;
        }
        
        // WordPressコアスクリプトは除外
        if (strpos($src, 'wp-includes/js/') !== false) {
            return $tag;
        }
        
        // 既にdefer/asyncがある場合はスキップ
        if (strpos($tag, 'defer') !== false || strpos($tag, 'async') !== false) {
            return $tag;
        }
        
        // 特定のハンドルにのみdeferを追加
        $defer_handles = array(
            'gi-main-js',
            'gi-frontend-js',
            'gi-mobile-enhanced'
        );
        
        if (in_array($handle, $defer_handles)) {
            return str_replace('<script ', '<script defer ', $tag);
        }
        
        return $tag;
    }
}

// フィルターの重複登録を防ぐ
remove_filter('script_loader_tag', 'gi_add_defer_attribute', 10);
add_filter('script_loader_tag', 'gi_add_defer_attribute', 10, 3);

// モバイル専用テンプレート切り替えは削除（統合されました）

/**
 * モバイル用AJAX エンドポイント - さらに読み込み
 */
function gi_ajax_load_more_grants() {
    check_ajax_referer('gi_ajax_nonce', 'nonce');
    
    $page = intval($_POST['page'] ?? 1);
    $posts_per_page = 10;
    
    $args = [
        'post_type' => 'grant',
        'posts_per_page' => $posts_per_page,
        'post_status' => 'publish',
        'paged' => $page,
        'orderby' => 'date',
        'order' => 'DESC'
    ];
    
    $query = new WP_Query($args);
    
    if (!$query->have_posts()) {
        wp_send_json_error('No more posts found');
    }
    
    ob_start();
    
    while ($query->have_posts()): $query->the_post();
        echo gi_render_mobile_card(get_the_ID());
    endwhile;
    
    wp_reset_postdata();
    
    $html = ob_get_clean();
    
    wp_send_json_success([
        'html' => $html,
        'page' => $page,
        'max_pages' => $query->max_num_pages,
        'found_posts' => $query->found_posts
    ]);
}
add_action('wp_ajax_gi_load_more_grants', 'gi_ajax_load_more_grants');
add_action('wp_ajax_nopriv_gi_load_more_grants', 'gi_ajax_load_more_grants');

/**
 * テーマのアクティベーションチェック
 */
function gi_theme_activation_check() {
    // PHP バージョンチェック
    if (version_compare(PHP_VERSION, '7.4', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-error"><p>';
            echo 'Grant Insight テーマはPHP 7.4以上が必要です。現在のバージョン: ' . PHP_VERSION;
            echo '</p></div>';
        });
    }
    
    // WordPress バージョンチェック
    global $wp_version;
    if (version_compare($wp_version, '5.8', '<')) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-warning"><p>';
            echo 'Grant Insight テーマはWordPress 5.8以上を推奨します。';
            echo '</p></div>';
        });
    }
    
    // 必須プラグインチェック（ACFなど）
    if (!class_exists('ACF') && is_admin()) {
        add_action('admin_notices', function() {
            echo '<div class="notice notice-info"><p>';
            echo 'Grant Insight テーマの全機能を利用するには、Advanced Custom Fields (ACF) プラグインのインストールを推奨します。';
            echo '</p></div>';
        });
    }
}
add_action('after_setup_theme', 'gi_theme_activation_check');

/**
 * エラーハンドリング用のグローバル関数
 */
if (!function_exists('gi_log_error')) {
    function gi_log_error($message, $context = array()) {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $log_message = '[Grant Insight Error] ' . $message;
            if (!empty($context)) {
                $log_message .= ' | Context: ' . print_r($context, true);
            }
            error_log($log_message);
        }
    }
}

/**
 * テーマ設定のデフォルト値を取得
 */
if (!function_exists('gi_get_theme_option')) {
    function gi_get_theme_option($option_name, $default = null) {
        $theme_options = get_option('gi_theme_options', array());
        
        if (isset($theme_options[$option_name])) {
            return $theme_options[$option_name];
        }
        
        return $default;
    }
}

/**
 * テーマ設定を保存
 */
if (!function_exists('gi_update_theme_option')) {
    function gi_update_theme_option($option_name, $value) {
        $theme_options = get_option('gi_theme_options', array());
        $theme_options[$option_name] = $value;
        
        return update_option('gi_theme_options', $theme_options);
    }
}



/**
 * テーマのバージョンアップグレード処理
 */
function gi_theme_version_upgrade() {
    $current_version = get_option('gi_installed_version', '0.0.0');
    
    if (version_compare($current_version, GI_THEME_VERSION, '<')) {
        // バージョンアップグレード処理
        
        // 6.2.0 -> 6.2.1 のアップグレード
        if (version_compare($current_version, '6.2.1', '<')) {
            // キャッシュのクリア
            gi_theme_cleanup();
        }
        
        // 6.2.1 -> 6.2.2 のアップグレード
        if (version_compare($current_version, '6.2.2', '<')) {
            // 新しいメタフィールドの追加など
            flush_rewrite_rules();
        }
        
        // バージョン更新
        update_option('gi_installed_version', GI_THEME_VERSION);
        
        // アップグレード完了通知
        if (is_admin()) {
            add_action('admin_notices', function() {
                echo '<div class="notice notice-success is-dismissible"><p>';
                echo 'Grant Insight テーマが v' . GI_THEME_VERSION . ' にアップグレードされました。';
                echo '</p></div>';
            });
        }
    }
}
add_action('init', 'gi_theme_version_upgrade');

/**
 * データベーステーブル作成
 */
function gi_create_database_tables() {
    global $wpdb;
    
    $charset_collate = $wpdb->get_charset_collate();
    
    // AI検索履歴テーブル
    $search_history_table = $wpdb->prefix . 'gi_search_history';
    $sql1 = "CREATE TABLE IF NOT EXISTS $search_history_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        session_id varchar(255) NOT NULL,
        user_id bigint(20) unsigned DEFAULT NULL,
        search_query text NOT NULL,
        search_filter varchar(50) DEFAULT NULL,
        results_count int(11) DEFAULT 0,
        clicked_results text DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY session_id (session_id),
        KEY user_id (user_id),
        KEY created_at (created_at)
    ) $charset_collate;";
    

    
    // ユーザー設定テーブル
    $user_preferences_table = $wpdb->prefix . 'gi_user_preferences';
    $sql4 = "CREATE TABLE IF NOT EXISTS $user_preferences_table (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        user_id bigint(20) unsigned NOT NULL,
        preference_key varchar(100) NOT NULL,
        preference_value text DEFAULT NULL,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY user_preference (user_id, preference_key)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql1);
    dbDelta($sql4);
    
    // バージョン管理
    update_option('gi_db_version', '1.0.0');
}

// テーマ有効化時にテーブル作成
add_action('after_switch_theme', 'gi_create_database_tables');

// 既存のインストールでもテーブル作成を確認
add_action('init', function() {
    $db_version = get_option('gi_db_version', '0');
    if (version_compare($db_version, '1.0.0', '<')) {
        gi_create_database_tables();
    }
});

// 検索履歴関数は inc/ai-functions.php に移動





/**
 * AJAXハンドラーの登録確認
 */