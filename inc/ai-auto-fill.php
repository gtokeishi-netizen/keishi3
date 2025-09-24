<?php
/**
 * Grant Insight Perfect AI自動入力機能 - メインコントローラー
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 * @since 2024.12
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class GI_AI_Auto_Fill {
    
    private $api_handler;
    private $max_daily_requests = 100;
    private $version = '1.0.0';
    private $db_version = '1.0';
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->api_handler = new GI_AI_API_Handler();
        
        // フックの登録
        add_action('wp_ajax_gi_ai_auto_fill', array($this, 'process_auto_fill'));
        add_action('wp_ajax_gi_ai_batch_process', array($this, 'process_batch_auto_fill'));
        add_action('wp_ajax_gi_ai_get_progress', array($this, 'get_progress_status'));
        add_action('wp_ajax_gi_ai_rollback', array($this, 'process_rollback'));
        add_action('admin_enqueue_scripts', array($this, 'enqueue_scripts'));
        add_action('admin_init', array($this, 'init_database'));
        add_action('wp_loaded', array($this, 'check_daily_limit_reset'));
        
        // 設定関連フック
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('admin_init', array($this, 'register_settings'));
        
        // ダッシュボードウィジェット
        add_action('wp_dashboard_setup', array($this, 'add_dashboard_widget'));
        
        // クリーンアップ処理
        add_action('wp_scheduled_delete', array($this, 'cleanup_old_logs'));
        
        // スケジューリング機能
        add_action('gi_ai_scheduled_processing', array($this, 'run_scheduled_processing'));
        add_action('gi_ai_auto_publish', array($this, 'run_auto_publish'));
        
        // スケジュール設定
        if (!wp_next_scheduled('gi_ai_scheduled_processing')) {
            wp_schedule_event(time(), 'hourly', 'gi_ai_scheduled_processing');
        }
        
        if (!wp_next_scheduled('gi_ai_auto_publish')) {
            wp_schedule_event(time(), 'daily', 'gi_ai_auto_publish');
        }
    }
    
    /**
     * データベースの初期化
     */
    public function init_database() {
        $installed_version = get_option('gi_ai_db_version');
        
        if ($installed_version !== $this->db_version) {
            $this->create_database_tables();
            update_option('gi_ai_db_version', $this->db_version);
        }
    }
    
    /**
     * データベーステーブルの作成
     */
    private function create_database_tables() {
        global $wpdb;
        
        $charset_collate = $wpdb->get_charset_collate();
        
        // 使用ログテーブル
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        $usage_sql = "CREATE TABLE $usage_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) UNSIGNED NOT NULL,
            post_id bigint(20) UNSIGNED NOT NULL,
            fields_processed text NOT NULL,
            tokens_used int(11) DEFAULT 0,
            processing_time float DEFAULT 0,
            success tinyint(1) DEFAULT 0,
            error_message text,
            ip_address varchar(45),
            user_agent text,
            PRIMARY KEY (id),
            KEY user_id (user_id),
            KEY post_id (post_id),
            KEY timestamp (timestamp)
        ) $charset_collate;";
        
        // バックアップテーブル
        $backup_table = $wpdb->prefix . 'gi_ai_backup';
        $backup_sql = "CREATE TABLE $backup_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            post_id bigint(20) UNSIGNED NOT NULL,
            field_name varchar(255) NOT NULL,
            original_value longtext,
            new_value longtext,
            backup_timestamp datetime DEFAULT CURRENT_TIMESTAMP,
            user_id bigint(20) UNSIGNED NOT NULL,
            is_restored tinyint(1) DEFAULT 0,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY user_id (user_id),
            KEY backup_timestamp (backup_timestamp)
        ) $charset_collate;";
        
        // 設定テーブル
        $settings_table = $wpdb->prefix . 'gi_ai_settings';
        $settings_sql = "CREATE TABLE $settings_table (
            id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            option_name varchar(191) NOT NULL,
            option_value longtext,
            autoload varchar(20) NOT NULL DEFAULT 'yes',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            UNIQUE KEY option_name (option_name)
        ) $charset_collate;";
        
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($usage_sql);
        dbDelta($backup_sql);
        dbDelta($settings_sql);
        
        // デフォルト設定の挿入
        $this->insert_default_settings();
    }
    
    /**
     * デフォルト設定の挿入
     */
    private function insert_default_settings() {
        $default_settings = array(
            'gi_ai_daily_limit' => 100,
            'gi_ai_default_fields' => json_encode(array('ai_summary', 'grant_target', 'eligible_expenses')),
            'gi_ai_auto_save' => 0,
            'gi_ai_notification_email' => get_option('admin_email'),
            'gi_ai_retry_count' => 3,
            'gi_ai_timeout' => 30,
            'gi_ai_temperature' => 0.7,
            'gi_ai_max_tokens' => 1000
        );
        
        foreach ($default_settings as $key => $value) {
            if (get_option($key) === false) {
                add_option($key, $value);
            }
        }
    }
    
    /**
     * スクリプトとスタイルの読み込み
     */
    public function enqueue_scripts($hook) {
        if ($hook !== 'post.php' && $hook !== 'post-new.php' && $hook !== 'settings_page_gi-ai-settings') {
            return;
        }
        
        global $post;
        if (isset($post) && $post->post_type !== 'grant') {
            return;
        }
        
        wp_enqueue_script(
            'gi-ai-auto-fill',
            get_template_directory_uri() . '/assets/js/ai-auto-fill.js',
            array('jquery', 'wp-util'),
            $this->version,
            true
        );
        
        wp_enqueue_style(
            'gi-ai-admin-styles',
            get_template_directory_uri() . '/assets/css/ai-admin-styles.css',
            array(),
            $this->version
        );
        
        // JavaScript用の変数を渡す
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
            'daily_usage' => $this->get_daily_usage(),
            'daily_limit' => get_option('gi_ai_daily_limit', 100)
        ));
    }
    
    /**
     * メインのAI自動入力処理
     */
    public function process_auto_fill() {
        try {
            // nonce検証
            if (!wp_verify_nonce($_POST['nonce'], 'gi_ai_auto_fill_nonce')) {
                wp_die('セキュリティチェックに失敗しました');
            }
            
            // 権限チェック
            if (!current_user_can('edit_posts')) {
                wp_send_json_error('権限がありません');
            }
            
            $post_id = intval($_POST['post_id']);
            $target_fields = isset($_POST['target_fields']) ? $_POST['target_fields'] : array();
            
            // 投稿の存在確認
            $post = get_post($post_id);
            if (!$post) {
                wp_send_json_error('指定された投稿が見つかりません');
            }
            
            // 投稿タイプ確認
            if ($post->post_type !== 'grant') {
                wp_send_json_error('対象外の投稿タイプです');
            }
            
            // 下書き状態の確認
            if ($post->post_status !== 'draft') {
                wp_send_json_error('下書き状態の投稿のみ処理可能です');
            }
            
            // 日次制限チェック
            if (!$this->check_daily_limit()) {
                wp_send_json_error('本日の利用上限に達しました');
            }
            
            // フィールド選択チェック
            if (empty($target_fields) || !is_array($target_fields)) {
                wp_send_json_error('処理対象のフィールドを選択してください');
            }
            
            // AI処理実行
            $start_time = microtime(true);
            $result = $this->execute_ai_fill($post_id, $target_fields);
            $processing_time = microtime(true) - $start_time;
            
            if ($result['success']) {
                // 使用ログの記録
                $this->log_usage($post_id, $target_fields, $result, $processing_time);
                
                wp_send_json_success(array(
                    'message' => '処理が完了しました',
                    'updated_fields' => $result['updated_fields'],
                    'processing_time' => round($processing_time, 2),
                    'tokens_used' => $result['total_tokens']
                ));
            } else {
                // エラーログの記録
                $this->log_usage($post_id, $target_fields, $result, $processing_time);
                wp_send_json_error($result['message']);
            }
            
        } catch (Exception $e) {
            error_log('GI AI Auto Fill Error: ' . $e->getMessage());
            wp_send_json_error('予期しないエラーが発生しました');
        }
    }
    
    /**
     * AI処理の実行
     */
    private function execute_ai_fill($post_id, $target_fields) {
        $post = get_post($post_id);
        $updated_fields = array();
        $total_tokens = 0;
        $errors = array();
        
        // 投稿データの収集
        $post_data = $this->collect_post_data($post_id);
        
        // バックアップの作成
        $this->create_backup($post_id, $target_fields);
        
        // フィールド別処理
        foreach ($target_fields as $field_name) {
            try {
                // タイトル・本文フィールドの特別処理
                if ($field_name === 'post_title') {
                    $current_value = $post->post_title;
                } elseif ($field_name === 'post_content') {
                    $current_value = $post->post_content;
                } else {
                    // ACFフィールドの処理
                    $current_value = get_field($field_name, $post_id);
                }
                
                if (!empty($current_value) && trim(strip_tags($current_value)) !== '') {
                    continue; // 既に値が入力されている場合はスキップ
                }
                
                // AI生成実行
                $api_result = $this->api_handler->generate_field_content($post_data, $field_name);
                
                if ($api_result['success']) {
                    // 生成されたコンテンツの検証
                    $validation_result = $this->validate_generated_content($field_name, $api_result['content']);
                    
                    if ($validation_result['valid']) {
                        // フィールドの更新
                        if ($field_name === 'post_title') {
                            // タイトル更新
                            wp_update_post(array(
                                'ID' => $post_id,
                                'post_title' => $api_result['content']
                            ));
                        } elseif ($field_name === 'post_content') {
                            // 本文更新
                            wp_update_post(array(
                                'ID' => $post_id,
                                'post_content' => $api_result['content']
                            ));
                        } else {
                            // ACFフィールド更新
                            update_field($field_name, $api_result['content'], $post_id);
                        }
                        
                        $updated_fields[$field_name] = $api_result['content'];
                        $total_tokens += $api_result['tokens_used'];
                    } else {
                        $errors[$field_name] = $validation_result['error'];
                    }
                } else {
                    $errors[$field_name] = $api_result['error'];
                }
                
                // API制限対応の待機
                if (count($target_fields) > 1) {
                    sleep(1);
                }
                
            } catch (Exception $e) {
                $errors[$field_name] = 'フィールド処理エラー: ' . $e->getMessage();
            }
        }
        
        // 結果の判定
        $success = !empty($updated_fields);
        
        return array(
            'success' => $success,
            'updated_fields' => $updated_fields,
            'errors' => $errors,
            'total_tokens' => $total_tokens,
            'message' => $success ? '処理完了' : '処理に失敗しました: ' . implode(', ', $errors)
        );
    }
    
    /**
     * 投稿データの収集
     */
    private function collect_post_data($post_id) {
        $post = get_post($post_id);
        
        $data = array(
            'title' => $post->post_title,
            'content' => $post->post_content,
            'excerpt' => $post->post_excerpt,
            'post_id' => $post_id
        );
        
        // ACFフィールドの取得
        $acf_fields = array(
            'organization' => 'grant_organization',
            'official_url' => 'grant_official_url',
            'max_amount' => 'grant_max_amount',
            'min_amount' => 'grant_min_amount',
            'grant_period_start' => 'grant_period_start',
            'grant_period_end' => 'grant_period_end',
            'application_deadline' => 'application_deadline',
            'target_business_type' => 'target_business_type',
            'target_region' => 'target_region'
        );
        
        foreach ($acf_fields as $key => $field_name) {
            $value = get_field($field_name, $post_id);
            if ($value) {
                $data[$key] = $value;
            }
        }
        
        return $data;
    }
    
    /**
     * 生成コンテンツの検証
     */
    private function validate_generated_content($field_name, $content) {
        // 基本的な文字数チェック
        $field_limits = array(
            'ai_summary' => 200,
            'grant_target' => 1000,
            'eligible_expenses' => 800,
            'required_documents' => 600,
            'contact_info' => 400,
            'amount_note' => 500,
            'deadline_note' => 300
        );
        
        // 文字数チェック
        if (isset($field_limits[$field_name])) {
            $char_count = mb_strlen(strip_tags($content), 'UTF-8');
            if ($char_count > $field_limits[$field_name]) {
                return array(
                    'valid' => false,
                    'error' => "文字数上限({$field_limits[$field_name]}文字)を超えています: {$char_count}文字"
                );
            }
        }
        
        // HTMLタグチェック（wysiwyg フィールド用）
        $html_fields = array('grant_target', 'eligible_expenses', 'required_documents');
        if (in_array($field_name, $html_fields)) {
            if (!$this->validate_html_content($content)) {
                return array(
                    'valid' => false,
                    'error' => '不正なHTMLタグが含まれています'
                );
            }
        }
        
        // 選択肢フィールドの値チェック
        if ($field_name === 'grant_difficulty') {
            $allowed_values = array('easy', 'normal', 'hard', 'expert');
            if (!in_array($content, $allowed_values)) {
                return array(
                    'valid' => false,
                    'error' => '無効な難易度値です'
                );
            }
        }
        
        if ($field_name === 'application_method') {
            $allowed_values = array('online', 'mail', 'visit', 'mixed');
            if (!in_array($content, $allowed_values)) {
                return array(
                    'valid' => false,
                    'error' => '無効な申請方法です'
                );
            }
        }
        
        // NGワードチェック
        if ($this->contains_inappropriate_content($content)) {
            return array(
                'valid' => false,
                'error' => '不適切なコンテンツが検出されました'
            );
        }
        
        return array('valid' => true);
    }
    
    /**
     * HTMLコンテンツの検証
     */
    private function validate_html_content($content) {
        // 許可されたHTMLタグ
        $allowed_tags = array(
            'p', 'ul', 'ol', 'li', 'strong', 'em', 'br', 'a', 'span'
        );
        
        // DOMDocumentを使用した検証
        libxml_use_internal_errors(true);
        $dom = new DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        
        $errors = libxml_get_errors();
        if (!empty($errors)) {
            return false;
        }
        
        // タグの検証
        $xpath = new DOMXPath($dom);
        $all_elements = $xpath->query('//*');
        
        foreach ($all_elements as $element) {
            if (!in_array(strtolower($element->tagName), $allowed_tags)) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * 不適切コンテンツの検出
     */
    private function contains_inappropriate_content($content) {
        $ng_words = array(
            // 基本的なNGワード（実際の運用では設定ファイル等で管理）
            '詐欺', '違法', '危険'
        );
        
        foreach ($ng_words as $ng_word) {
            if (strpos($content, $ng_word) !== false) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * バックアップの作成
     */
    private function create_backup($post_id, $target_fields) {
        global $wpdb;
        
        $backup_table = $wpdb->prefix . 'gi_ai_backup';
        $user_id = get_current_user_id();
        
        foreach ($target_fields as $field_name) {
            $current_value = get_field($field_name, $post_id);
            
            $wpdb->insert(
                $backup_table,
                array(
                    'post_id' => $post_id,
                    'field_name' => $field_name,
                    'original_value' => $current_value,
                    'user_id' => $user_id,
                    'backup_timestamp' => current_time('mysql')
                ),
                array('%d', '%s', '%s', '%d', '%s')
            );
        }
    }
    
    /**
     * ロールバック処理
     */
    public function process_rollback() {
        if (!wp_verify_nonce($_POST['nonce'], 'gi_ai_auto_fill_nonce')) {
            wp_die('セキュリティチェックに失敗しました');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        
        global $wpdb;
        $backup_table = $wpdb->prefix . 'gi_ai_backup';
        
        // 最新のバックアップを取得
        $backups = $wpdb->get_results($wpdb->prepare(
            "SELECT * FROM $backup_table 
             WHERE post_id = %d AND is_restored = 0 
             ORDER BY backup_timestamp DESC",
            $post_id
        ));
        
        if (empty($backups)) {
            wp_send_json_error('復元可能なバックアップが見つかりません');
        }
        
        $restored_fields = array();
        foreach ($backups as $backup) {
            // フィールドを元の値に復元
            update_field($backup->field_name, $backup->original_value, $post_id);
            $restored_fields[] = $backup->field_name;
            
            // バックアップを復元済みにマーク
            $wpdb->update(
                $backup_table,
                array('is_restored' => 1),
                array('id' => $backup->id),
                array('%d'),
                array('%d')
            );
        }
        
        wp_send_json_success(array(
            'message' => 'ロールバックが完了しました',
            'restored_fields' => $restored_fields
        ));
    }
    
    /**
     * 日次制限のチェック
     */
    private function check_daily_limit() {
        $daily_usage = $this->get_daily_usage();
        $daily_limit = get_option('gi_ai_daily_limit', 100);
        
        return $daily_usage < $daily_limit;
    }
    
    /**
     * 日次使用量の取得
     */
    private function get_daily_usage() {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        $today = current_time('Y-m-d');
        
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table 
             WHERE DATE(timestamp) = %s AND success = 1",
            $today
        ));
        
        return intval($count);
    }
    
    /**
     * 日次制限のリセット確認
     */
    public function check_daily_limit_reset() {
        $last_reset = get_option('gi_ai_last_reset_date');
        $today = current_time('Y-m-d');
        
        if ($last_reset !== $today) {
            delete_transient('gi_ai_daily_usage_cache');
            update_option('gi_ai_last_reset_date', $today);
        }
    }
    
    /**
     * 使用ログの記録
     */
    private function log_usage($post_id, $fields, $result, $processing_time) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'user_id' => get_current_user_id(),
            'post_id' => $post_id,
            'fields_processed' => json_encode($fields),
            'tokens_used' => isset($result['total_tokens']) ? $result['total_tokens'] : 0,
            'processing_time' => $processing_time,
            'success' => $result['success'] ? 1 : 0,
            'error_message' => $result['success'] ? null : $result['message'],
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
        );
        
        $wpdb->insert(
            $usage_table,
            $log_data,
            array('%s', '%d', '%d', '%s', '%d', '%f', '%d', '%s', '%s', '%s')
        );
    }
    
    /**
     * バッチ処理
     */
    public function process_batch_auto_fill() {
        if (!wp_verify_nonce($_POST['nonce'], 'gi_ai_auto_fill_nonce')) {
            wp_die('セキュリティチェックに失敗しました');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_ids = isset($_POST['post_ids']) ? $_POST['post_ids'] : array();
        $target_fields = isset($_POST['target_fields']) ? $_POST['target_fields'] : array();
        
        if (empty($post_ids) || !is_array($post_ids)) {
            wp_send_json_error('処理対象の投稿を選択してください');
        }
        
        $results = $this->process_batch($post_ids, $target_fields);
        
        wp_send_json_success(array(
            'message' => 'バッチ処理が完了しました',
            'results' => $results
        ));
    }
    
    /**
     * バッチ処理の実行
     */
    private function process_batch($post_ids, $target_fields) {
        $results = array();
        $total = count($post_ids);
        
        foreach ($post_ids as $index => $post_id) {
            // 進捗更新
            $this->update_batch_progress($index + 1, $total);
            
            // 個別処理
            $result = $this->execute_ai_fill($post_id, $target_fields);
            $results[$post_id] = $result;
            
            // API制限対応の待機
            if ($index < $total - 1) {
                sleep(2);
            }
            
            // 中断チェック
            if ($this->should_stop_batch_processing()) {
                break;
            }
        }
        
        return $results;
    }
    
    /**
     * バッチ処理の進捗更新
     */
    private function update_batch_progress($current, $total) {
        set_transient('gi_ai_batch_progress', array(
            'current' => $current,
            'total' => $total,
            'percentage' => round(($current / $total) * 100)
        ), 300);
    }
    
    /**
     * バッチ処理停止判定
     */
    private function should_stop_batch_processing() {
        return get_transient('gi_ai_batch_stop') === 'true';
    }
    
    /**
     * 進捗状況の取得
     */
    public function get_progress_status() {
        $progress = get_transient('gi_ai_batch_progress');
        
        if ($progress) {
            wp_send_json_success($progress);
        } else {
            wp_send_json_error('進捗情報が見つかりません');
        }
    }
    
    /**
     * 管理画面メニューの追加
     */
    public function add_admin_menu() {
        add_options_page(
            'AI自動入力設定',
            'AI自動入力設定',
            'manage_options',
            'gi-ai-settings',
            array($this, 'render_settings_page')
        );
    }
    
    /**
     * 設定の登録
     */
    public function register_settings() {
        register_setting('gi_ai_settings', 'gi_openai_api_key');
        register_setting('gi_ai_settings', 'gi_ai_daily_limit');
        register_setting('gi_ai_settings', 'gi_ai_default_fields');
        register_setting('gi_ai_settings', 'gi_ai_auto_save');
        register_setting('gi_ai_settings', 'gi_ai_notification_email');
        register_setting('gi_ai_settings', 'gi_ai_retry_count');
        register_setting('gi_ai_settings', 'gi_ai_timeout');
        register_setting('gi_ai_settings', 'gi_ai_temperature');
        register_setting('gi_ai_settings', 'gi_ai_max_tokens');
    }
    
    /**
     * 設定画面のレンダリング
     */
    public function render_settings_page() {
        if (isset($_POST['test_api_connection'])) {
            $test_result = $this->test_api_connection();
        }
        ?>
        <div class="wrap">
            <h1>AI自動入力設定</h1>
            
            <?php if (isset($test_result)): ?>
                <div class="notice notice-<?php echo $test_result['success'] ? 'success' : 'error'; ?>">
                    <p><?php echo esc_html($test_result['message']); ?></p>
                </div>
            <?php endif; ?>
            
            <form method="post" action="options.php">
                <?php
                settings_fields('gi_ai_settings');
                do_settings_sections('gi_ai_settings');
                ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">OpenAI APIキー</th>
                        <td>
                            <input type="password" name="gi_openai_api_key" 
                                   value="<?php echo esc_attr(get_option('gi_openai_api_key')); ?>" 
                                   class="regular-text" placeholder="sk-..." />
                            <p class="description">ChatGPT API利用のためのAPIキーを入力してください。</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">日次利用上限</th>
                        <td>
                            <input type="number" name="gi_ai_daily_limit" 
                                   value="<?php echo esc_attr(get_option('gi_ai_daily_limit', 100)); ?>" 
                                   min="1" max="1000" />
                            <p class="description">1日あたりの最大API呼び出し回数（コスト管理用）</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">自動保存モード</th>
                        <td>
                            <label>
                                <input type="checkbox" name="gi_ai_auto_save" value="1" 
                                       <?php checked(get_option('gi_ai_auto_save', 0), 1); ?> />
                                生成後に自動で保存する
                            </label>
                            <p class="description">無効の場合は手動確認後に保存</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">エラー通知メール</th>
                        <td>
                            <input type="email" name="gi_ai_notification_email" 
                                   value="<?php echo esc_attr(get_option('gi_ai_notification_email', get_option('admin_email'))); ?>" 
                                   class="regular-text" />
                            <p class="description">エラー発生時の通知先メールアドレス</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button(); ?>
            </form>
            
            <h2>API接続テスト</h2>
            <form method="post">
                <p>
                    <input type="submit" name="test_api_connection" class="button" value="接続テスト実行" />
                    <span class="description">OpenAI APIとの接続をテストします</span>
                </p>
            </form>
            
            <h2>統計情報</h2>
            <?php $this->render_statistics(); ?>
        </div>
        <?php
    }
    
    /**
     * API接続テスト
     */
    private function test_api_connection() {
        $api_key = get_option('gi_openai_api_key');
        
        if (empty($api_key)) {
            return array(
                'success' => false,
                'message' => 'APIキーが設定されていません。'
            );
        }
        
        $test_result = $this->api_handler->test_connection();
        
        return $test_result;
    }
    
    /**
     * 統計情報の表示
     */
    private function render_statistics() {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        
        // 今日の使用量
        $today_usage = $this->get_daily_usage();
        $daily_limit = get_option('gi_ai_daily_limit', 100);
        
        // 今月の統計
        $this_month = current_time('Y-m');
        $monthly_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT COUNT(*) as total_requests, 
                    SUM(tokens_used) as total_tokens,
                    AVG(processing_time) as avg_processing_time,
                    SUM(success) as successful_requests
             FROM $usage_table 
             WHERE DATE_FORMAT(timestamp, '%%Y-%%m') = %s",
            $this_month
        ));
        
        ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>項目</th>
                    <th>値</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>本日の使用量</td>
                    <td><?php echo $today_usage; ?> / <?php echo $daily_limit; ?> 回</td>
                </tr>
                <tr>
                    <td>今月の総リクエスト数</td>
                    <td><?php echo $monthly_stats->total_requests ?? 0; ?> 回</td>
                </tr>
                <tr>
                    <td>今月の成功率</td>
                    <td>
                        <?php 
                        if ($monthly_stats->total_requests > 0) {
                            $success_rate = ($monthly_stats->successful_requests / $monthly_stats->total_requests) * 100;
                            echo round($success_rate, 1) . '%';
                        } else {
                            echo 'N/A';
                        }
                        ?>
                    </td>
                </tr>
                <tr>
                    <td>今月の総トークン使用量</td>
                    <td><?php echo number_format($monthly_stats->total_tokens ?? 0); ?> トークン</td>
                </tr>
                <tr>
                    <td>平均処理時間</td>
                    <td><?php echo round($monthly_stats->avg_processing_time ?? 0, 2); ?> 秒</td>
                </tr>
            </tbody>
        </table>
        <?php
    }
    
    /**
     * ダッシュボードウィジェットの追加
     */
    public function add_dashboard_widget() {
        wp_add_dashboard_widget(
            'gi_ai_dashboard_widget',
            'AI自動入力 - 利用状況',
            array($this, 'render_dashboard_widget')
        );
    }
    
    /**
     * ダッシュボードウィジェットの表示
     */
    public function render_dashboard_widget() {
        $today_usage = $this->get_daily_usage();
        $daily_limit = get_option('gi_ai_daily_limit', 100);
        $usage_percentage = ($today_usage / $daily_limit) * 100;
        
        ?>
        <div class="gi-ai-dashboard-widget">
            <p><strong>本日の利用状況</strong></p>
            <div class="gi-ai-usage-bar">
                <div class="gi-ai-usage-progress" style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
            </div>
            <p><?php echo $today_usage; ?> / <?php echo $daily_limit; ?> 回使用 
               (<?php echo round($usage_percentage, 1); ?>%)</p>
            
            <?php if ($usage_percentage > 80): ?>
                <div class="notice notice-warning inline">
                    <p>利用上限に近づいています。</p>
                </div>
            <?php endif; ?>
        </div>
        
        <style>
        .gi-ai-usage-bar {
            width: 100%;
            height: 20px;
            background-color: #f1f1f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        .gi-ai-usage-progress {
            height: 100%;
            background-color: #0073aa;
            transition: width 0.3s ease;
        }
        </style>
        <?php
    }
    
    /**
     * 古いログのクリーンアップ
     */
    public function cleanup_old_logs() {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        $backup_table = $wpdb->prefix . 'gi_ai_backup';
        
        // 3ヶ月以前のログを削除
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $usage_table WHERE timestamp < DATE_SUB(NOW(), INTERVAL 3 MONTH)"
        ));
        
        // 1ヶ月以前の復元済みバックアップを削除
        $wpdb->query($wpdb->prepare(
            "DELETE FROM $backup_table WHERE backup_timestamp < DATE_SUB(NOW(), INTERVAL 1 MONTH) AND is_restored = 1"
        ));
    }
    
    /**
     * スケジュールされた処理の実行
     */
    public function run_scheduled_processing() {
        // スケジューリング設定を取得
        $auto_processing_enabled = get_option('gi_ai_auto_processing_enabled', 0);
        
        if (!$auto_processing_enabled) {
            return;
        }
        
        // 処理対象の投稿を取得（下書きで、作成から24時間以上経過）
        $posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => 'draft',
            'numberposts' => 10,
            'date_query' => array(
                array(
                    'before' => '24 hours ago'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => '_gi_ai_processed',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        if (empty($posts)) {
            return;
        }
        
        // デフォルトフィールドを取得
        $default_fields = json_decode(get_option('gi_ai_default_fields', '["ai_summary", "grant_target"]'), true);
        
        foreach ($posts as $post) {
            // 日次制限をチェック
            if (!$this->check_daily_limit()) {
                break;
            }
            
            // AI処理実行
            $result = $this->execute_ai_fill($post->ID, $default_fields);
            
            // 処理済みマークを追加
            update_post_meta($post->ID, '_gi_ai_processed', current_time('mysql'));
            
            // スケジュール処理のログ
            $this->log_scheduled_processing($post->ID, $result);
            
            // API制限対応
            sleep(2);
        }
    }
    
    /**
     * 自動公開処理
     */
    public function run_auto_publish() {
        $auto_publish_enabled = get_option('gi_ai_auto_publish_enabled', 0);
        
        if (!$auto_publish_enabled) {
            return;
        }
        
        // 自動公開の条件を満たす投稿を取得
        $publish_delay_days = get_option('gi_ai_auto_publish_delay', 7);
        
        $posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => 'draft',
            'numberposts' => 20,
            'date_query' => array(
                array(
                    'before' => $publish_delay_days . ' days ago'
                )
            ),
            'meta_query' => array(
                array(
                    'key' => '_gi_ai_processed',
                    'compare' => 'EXISTS'
                ),
                array(
                    'key' => '_gi_ai_auto_published',
                    'compare' => 'NOT EXISTS'
                )
            )
        ));
        
        foreach ($posts as $post) {
            // 必須フィールドの確認
            if ($this->validate_post_for_publish($post->ID)) {
                // 投稿を公開
                wp_update_post(array(
                    'ID' => $post->ID,
                    'post_status' => 'publish'
                ));
                
                // 自動公開済みマーク
                update_post_meta($post->ID, '_gi_ai_auto_published', current_time('mysql'));
                
                // ログ記録
                error_log("AI Auto Publish: Post ID {$post->ID} automatically published");
            }
        }
    }
    
    /**
     * 投稿の公開準備状況を検証
     */
    private function validate_post_for_publish($post_id) {
        // タイトルチェック
        $post = get_post($post_id);
        if (empty($post->post_title) || trim($post->post_title) === '') {
            return false;
        }
        
        // 必須フィールドチェック
        $required_fields = array('ai_summary', 'grant_target');
        foreach ($required_fields as $field) {
            $value = get_field($field, $post_id);
            if (empty($value) || trim(strip_tags($value)) === '') {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * スケジュール処理のログ記録
     */
    private function log_scheduled_processing($post_id, $result) {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        
        $log_data = array(
            'timestamp' => current_time('mysql'),
            'user_id' => 0, // システム処理
            'post_id' => $post_id,
            'fields_processed' => json_encode($result['updated_fields'] ?? array()),
            'tokens_used' => $result['total_tokens'] ?? 0,
            'processing_time' => $result['processing_time'] ?? 0,
            'success' => $result['success'] ? 1 : 0,
            'error_message' => $result['success'] ? 'Scheduled processing' : $result['message'],
            'ip_address' => 'scheduled',
            'user_agent' => 'AI Scheduler'
        );
        
        $wpdb->insert(
            $usage_table,
            $log_data,
            array('%s', '%d', '%d', '%s', '%d', '%f', '%d', '%s', '%s', '%s')
        );
    }
}

// インスタンス化
if (class_exists('GI_AI_Auto_Fill')) {
    new GI_AI_Auto_Fill();
}