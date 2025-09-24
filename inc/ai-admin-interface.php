<?php
/**
 * Grant Insight Perfect AI自動入力機能 - 管理画面UI
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 * @since 2024.12
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

class GI_AI_Admin_Interface {
    
    private $field_definitions;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->init_field_definitions();
        
        // フックの登録
        add_action('add_meta_boxes', array($this, 'add_meta_boxes'));
        add_action('admin_footer', array($this, 'add_admin_scripts'));
        add_action('admin_head', array($this, 'add_admin_styles'));
        add_action('wp_ajax_gi_ai_preview_content', array($this, 'preview_generated_content'));
        add_action('wp_ajax_gi_ai_apply_content', array($this, 'apply_generated_content'));
        
        // バッチ処理用のページ
        add_action('admin_menu', array($this, 'add_batch_processing_page'));
    }
    
    /**
     * フィールド定義の初期化
     */
    private function init_field_definitions() {
        $this->field_definitions = array(
            'post_title' => array(
                'label' => '投稿タイトル',
                'type' => 'text',
                'priority' => 'high',
                'max_length' => 40,
                'description' => '魅力的で分かりやすい投稿タイトルを生成'
            ),
            'post_content' => array(
                'label' => '投稿本文',
                'type' => 'wysiwyg',
                'priority' => 'high',
                'max_length' => 1200,
                'description' => '詳細で構造化された本文コンテンツを生成'
            ),
            'ai_summary' => array(
                'label' => 'AI概要',
                'type' => 'textarea',
                'priority' => 'high',
                'max_length' => 200,
                'description' => '助成金の概要を200文字程度で要約'
            ),
            'grant_target' => array(
                'label' => '対象者・対象事業',
                'type' => 'wysiwyg',
                'priority' => 'high',
                'max_length' => 1000,
                'description' => '対象事業者・事業内容を構造化'
            ),
            'eligible_expenses' => array(
                'label' => '対象経費',
                'type' => 'wysiwyg',
                'priority' => 'high',
                'max_length' => 800,
                'description' => '対象経費をリスト化'
            ),
            'grant_difficulty' => array(
                'label' => '申請難易度',
                'type' => 'select',
                'priority' => 'medium',
                'options' => array(
                    'easy' => '易しい',
                    'normal' => '普通', 
                    'hard' => '難しい',
                    'expert' => '専門的'
                ),
                'description' => '申請難易度を4段階で評価'
            ),
            'required_documents' => array(
                'label' => '必要書類',
                'type' => 'wysiwyg',
                'priority' => 'medium',
                'max_length' => 600,
                'description' => '必要書類をリスト化'
            ),
            'application_method' => array(
                'label' => '申請方法',
                'type' => 'select',
                'priority' => 'medium',
                'options' => array(
                    'online' => 'オンライン申請',
                    'mail' => '郵送申請',
                    'visit' => '窓口申請',
                    'mixed' => '複合申請'
                ),
                'description' => '申請方法を判定'
            ),
            'contact_info' => array(
                'label' => '問い合わせ先',
                'type' => 'textarea',
                'priority' => 'low',
                'max_length' => 400,
                'description' => '問い合わせ先の推測情報'
            ),
            'amount_note' => array(
                'label' => '金額備考',
                'type' => 'textarea',
                'priority' => 'low',
                'max_length' => 500,
                'description' => '金額に関する補足説明'
            ),
            'deadline_note' => array(
                'label' => '締切備考',
                'type' => 'textarea',
                'priority' => 'low',
                'max_length' => 300,
                'description' => '締切に関する補足情報'
            )
        );
    }
    
    /**
     * メタボックスの追加
     */
    public function add_meta_boxes() {
        add_meta_box(
            'gi-ai-auto-fill',
            'AI自動入力',
            array($this, 'render_meta_box'),
            'grant',
            'side',
            'high'
        );
        
        add_meta_box(
            'gi-ai-preview',
            'AI生成プレビュー',
            array($this, 'render_preview_meta_box'),
            'grant',
            'normal',
            'high'
        );
    }
    
    /**
     * メインメタボックスのレンダリング
     */
    public function render_meta_box($post) {
        // nonce フィールド
        wp_nonce_field('gi_ai_meta_box_nonce', 'gi_ai_meta_box_nonce');
        
        // 投稿ステータスチェック
        $is_draft = $post->post_status === 'draft';
        $can_process = $is_draft && current_user_can('edit_post', $post->ID);
        
        // 利用状況の取得
        $daily_usage = $this->get_daily_usage();
        $daily_limit = get_option('gi_ai_daily_limit', 100);
        $usage_percentage = ($daily_usage / $daily_limit) * 100;
        
        ?>
        <div id="gi-ai-auto-fill-container">
            
            <!-- 利用状況表示 -->
            <div class="gi-ai-usage-status">
                <h4>本日の利用状況</h4>
                <div class="gi-ai-usage-bar">
                    <div class="gi-ai-usage-fill" style="width: <?php echo min($usage_percentage, 100); ?>%"></div>
                </div>
                <p class="gi-ai-usage-text">
                    <?php echo $daily_usage; ?> / <?php echo $daily_limit; ?> 回 
                    (<?php echo round($usage_percentage, 1); ?>%)
                </p>
            </div>
            
            <?php if (!$can_process): ?>
                <div class="notice notice-warning inline">
                    <p>
                        <?php if (!$is_draft): ?>
                            AI自動入力は下書き状態の投稿のみ利用可能です。
                        <?php else: ?>
                            この機能を使用する権限がありません。
                        <?php endif; ?>
                    </p>
                </div>
            <?php else: ?>
                
                <!-- フィールド選択 -->
                <div class="gi-ai-field-selection">
                    <h4>対象フィールド選択</h4>
                    <div class="gi-ai-priority-sections">
                        
                        <!-- 高優先度フィールド -->
                        <div class="gi-ai-priority-section">
                            <h5 class="gi-ai-priority-title high">
                                <span class="priority-badge high">高</span>
                                重要フィールド
                            </h5>
                            <?php $this->render_field_checkboxes('high'); ?>
                        </div>
                        
                        <!-- 中優先度フィールド -->
                        <div class="gi-ai-priority-section">
                            <h5 class="gi-ai-priority-title medium">
                                <span class="priority-badge medium">中</span>
                                補助フィールド
                            </h5>
                            <?php $this->render_field_checkboxes('medium'); ?>
                        </div>
                        
                        <!-- 低優先度フィールド -->
                        <div class="gi-ai-priority-section">
                            <h5 class="gi-ai-priority-title low">
                                <span class="priority-badge low">低</span>
                                詳細フィールド
                            </h5>
                            <?php $this->render_field_checkboxes('low'); ?>
                        </div>
                    </div>
                    
                    <!-- 一括選択ボタン -->
                    <div class="gi-ai-bulk-select">
                        <button type="button" class="button" id="gi-ai-select-all">すべて選択</button>
                        <button type="button" class="button" id="gi-ai-select-none">選択解除</button>
                        <button type="button" class="button" id="gi-ai-select-high">高優先度のみ</button>
                    </div>
                </div>
                
                <!-- 処理オプション -->
                <div class="gi-ai-options">
                    <h4>処理オプション</h4>
                    <label class="gi-ai-option">
                        <input type="checkbox" id="gi-ai-skip-filled" checked>
                        既に入力済みのフィールドはスキップする
                    </label>
                    <label class="gi-ai-option">
                        <input type="checkbox" id="gi-ai-preview-mode" checked>
                        結果をプレビューしてから適用する
                    </label>
                </div>
                
                <!-- 実行ボタン -->
                <div class="gi-ai-actions">
                    <button type="button" id="gi-ai-execute-btn" class="button button-primary button-large">
                        <span class="dashicons dashicons-admin-generic"></span>
                        AI自動入力を実行
                    </button>
                    
                    <div id="gi-ai-progress" class="gi-ai-progress hidden">
                        <div class="gi-ai-progress-bar">
                            <div class="gi-ai-progress-fill"></div>
                        </div>
                        <p class="gi-ai-progress-text">処理中...</p>
                        <button type="button" id="gi-ai-cancel-btn" class="button">キャンセル</button>
                    </div>
                </div>
                
                <!-- ロールバック -->
                <div class="gi-ai-rollback">
                    <h4>ロールバック</h4>
                    <p class="description">最後に実行したAI処理を元に戻します。</p>
                    <button type="button" id="gi-ai-rollback-btn" class="button">
                        <span class="dashicons dashicons-undo"></span>
                        ロールバック実行
                    </button>
                </div>
                
            <?php endif; ?>
            
        </div>
        <?php
    }
    
    /**
     * フィールドチェックボックスの描画
     */
    private function render_field_checkboxes($priority) {
        global $post;
        
        foreach ($this->field_definitions as $field_name => $field_info) {
            if ($field_info['priority'] !== $priority) {
                continue;
            }
            
            // タイトル・本文フィールドの特別処理
            if ($field_name === 'post_title') {
                $current_value = $post->post_title;
            } elseif ($field_name === 'post_content') {
                $current_value = $post->post_content;
            } else {
                $current_value = get_field($field_name, $post->ID);
            }
            $has_content = !empty($current_value) && trim(strip_tags($current_value)) !== '';
            $is_default_selected = in_array($field_name, $this->get_default_fields());
            
            ?>
            <div class="gi-ai-field-item">
                <label class="gi-ai-field-label <?php echo $has_content ? 'has-content' : ''; ?>">
                    <input type="checkbox" 
                           name="gi_ai_fields[]" 
                           value="<?php echo esc_attr($field_name); ?>"
                           class="gi-ai-field-checkbox"
                           <?php checked($is_default_selected && !$has_content); ?>
                           <?php disabled($has_content); ?>>
                    
                    <span class="field-name"><?php echo esc_html($field_info['label']); ?></span>
                    
                    <?php if ($has_content): ?>
                        <span class="field-status filled">入力済み</span>
                    <?php else: ?>
                        <span class="field-status empty">未入力</span>
                    <?php endif; ?>
                    
                    <?php if (isset($field_info['max_length'])): ?>
                        <span class="field-limit">(最大<?php echo $field_info['max_length']; ?>文字)</span>
                    <?php endif; ?>
                </label>
                
                <div class="gi-ai-field-description">
                    <?php echo esc_html($field_info['description']); ?>
                </div>
            </div>
            <?php
        }
    }
    
    /**
     * プレビューメタボックスのレンダリング
     */
    public function render_preview_meta_box($post) {
        ?>
        <div id="gi-ai-preview-container" class="hidden">
            <div class="gi-ai-preview-header">
                <h4>AI生成結果プレビュー</h4>
                <div class="gi-ai-preview-actions">
                    <button type="button" id="gi-ai-apply-all-btn" class="button button-primary">
                        すべて適用
                    </button>
                    <button type="button" id="gi-ai-cancel-preview-btn" class="button">
                        キャンセル
                    </button>
                </div>
            </div>
            
            <div id="gi-ai-preview-content">
                <!-- 動的に生成されるプレビューコンテンツ -->
            </div>
        </div>
        <?php
    }
    
    /**
     * バッチ処理ページの追加
     */
    public function add_batch_processing_page() {
        add_submenu_page(
            'edit.php?post_type=grant',
            'AI一括処理',
            'AI一括処理',
            'edit_posts',
            'gi-ai-batch',
            array($this, 'render_batch_page')
        );
    }
    
    /**
     * バッチ処理ページのレンダリング
     */
    public function render_batch_page() {
        // 下書き投稿の取得
        $draft_posts = get_posts(array(
            'post_type' => 'grant',
            'post_status' => 'draft',
            'numberposts' => -1,
            'orderby' => 'date',
            'order' => 'DESC'
        ));
        
        ?>
        <div class="wrap">
            <h1>AI一括処理</h1>
            <p>下書き状態の助成金投稿に対してAI自動入力を一括実行できます。</p>
            
            <?php if (empty($draft_posts)): ?>
                <div class="notice notice-info">
                    <p>処理可能な下書き投稿が見つかりません。</p>
                </div>
            <?php else: ?>
                
                <form id="gi-ai-batch-form">
                    <?php wp_nonce_field('gi_ai_batch_nonce', 'gi_ai_batch_nonce'); ?>
                    
                    <!-- 投稿選択 -->
                    <div class="gi-ai-batch-posts">
                        <h2>処理対象投稿</h2>
                        <div class="gi-ai-batch-controls">
                            <button type="button" id="gi-ai-batch-select-all" class="button">すべて選択</button>
                            <button type="button" id="gi-ai-batch-select-none" class="button">選択解除</button>
                            <span class="gi-ai-selection-count">選択: <span id="gi-ai-selected-count">0</span>件</span>
                        </div>
                        
                        <div class="gi-ai-posts-list">
                            <?php foreach ($draft_posts as $post): ?>
                                <div class="gi-ai-post-item">
                                    <label>
                                        <input type="checkbox" name="batch_posts[]" value="<?php echo $post->ID; ?>" class="gi-ai-batch-post-checkbox">
                                        <strong><?php echo esc_html($post->post_title ?: '(無題)'); ?></strong>
                                        <span class="gi-ai-post-meta">
                                            ID: <?php echo $post->ID; ?> | 
                                            更新: <?php echo get_the_modified_date('Y/m/d H:i', $post); ?>
                                        </span>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- フィールド選択 -->
                    <div class="gi-ai-batch-fields">
                        <h2>処理対象フィールド</h2>
                        <div class="gi-ai-field-groups">
                            <?php
                            $priorities = array('high' => '高優先度', 'medium' => '中優先度', 'low' => '低優先度');
                            foreach ($priorities as $priority => $label):
                            ?>
                                <div class="gi-ai-field-group">
                                    <h3 class="gi-ai-group-title <?php echo $priority; ?>">
                                        <label>
                                            <input type="checkbox" class="gi-ai-priority-toggle" data-priority="<?php echo $priority; ?>" <?php checked($priority === 'high'); ?>>
                                            <?php echo $label; ?>フィールド
                                        </label>
                                    </h3>
                                    
                                    <div class="gi-ai-field-list">
                                        <?php foreach ($this->field_definitions as $field_name => $field_info): ?>
                                            <?php if ($field_info['priority'] === $priority): ?>
                                                <label class="gi-ai-batch-field-label">
                                                    <input type="checkbox" 
                                                           name="batch_fields[]" 
                                                           value="<?php echo esc_attr($field_name); ?>"
                                                           class="gi-ai-batch-field-checkbox priority-<?php echo $priority; ?>"
                                                           <?php checked($priority === 'high'); ?>>
                                                    <?php echo esc_html($field_info['label']); ?>
                                                </label>
                                            <?php endif; ?>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- オプション -->
                    <div class="gi-ai-batch-options">
                        <h2>処理オプション</h2>
                        <label class="gi-ai-batch-option">
                            <input type="checkbox" name="skip_filled" checked>
                            既に入力済みのフィールドはスキップする
                        </label>
                        <label class="gi-ai-batch-option">
                            <input type="checkbox" name="continue_on_error" checked>
                            エラーが発生しても他の投稿の処理を継続する
                        </label>
                        <label class="gi-ai-batch-option">
                            <input type="checkbox" name="auto_save">
                            処理完了後に自動保存する
                        </label>
                    </div>
                    
                    <!-- 実行ボタン -->
                    <div class="gi-ai-batch-execute">
                        <button type="button" id="gi-ai-batch-execute-btn" class="button button-primary button-large">
                            <span class="dashicons dashicons-admin-generic"></span>
                            一括処理を開始
                        </button>
                    </div>
                </form>
                
                <!-- 進捗表示 -->
                <div id="gi-ai-batch-progress" class="gi-ai-batch-progress hidden">
                    <h2>処理進捗</h2>
                    <div class="gi-ai-progress-info">
                        <div class="gi-ai-progress-bar-container">
                            <div class="gi-ai-progress-bar">
                                <div class="gi-ai-progress-fill"></div>
                            </div>
                            <span class="gi-ai-progress-percentage">0%</span>
                        </div>
                        <div class="gi-ai-progress-details">
                            <span id="gi-ai-progress-current">0</span> / 
                            <span id="gi-ai-progress-total">0</span> 件処理中
                        </div>
                    </div>
                    
                    <div class="gi-ai-progress-log">
                        <h3>処理ログ</h3>
                        <div id="gi-ai-progress-log-content"></div>
                    </div>
                    
                    <div class="gi-ai-progress-actions">
                        <button type="button" id="gi-ai-batch-pause-btn" class="button">一時停止</button>
                        <button type="button" id="gi-ai-batch-cancel-btn" class="button">キャンセル</button>
                    </div>
                </div>
                
                <!-- 結果表示 -->
                <div id="gi-ai-batch-results" class="gi-ai-batch-results hidden">
                    <h2>処理結果</h2>
                    <div id="gi-ai-batch-results-content"></div>
                </div>
                
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * プレビューコンテンツの生成（AJAX）
     */
    public function preview_generated_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'gi_ai_auto_fill_nonce')) {
            wp_die('セキュリティチェックに失敗しました');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $generated_content = $_POST['generated_content'];
        
        if (!is_array($generated_content)) {
            wp_send_json_error('無効なデータです');
        }
        
        $preview_html = $this->generate_preview_html($generated_content, $post_id);
        
        wp_send_json_success(array(
            'preview_html' => $preview_html
        ));
    }
    
    /**
     * プレビューHTMLの生成
     */
    private function generate_preview_html($generated_content, $post_id) {
        $html = '';
        
        foreach ($generated_content as $field_name => $content) {
            if (!isset($this->field_definitions[$field_name])) {
                continue;
            }
            
            $field_info = $this->field_definitions[$field_name];
            
            // 現在値の取得（タイトル・本文の特別処理）
            if ($field_name === 'post_title') {
                $current_value = get_post($post_id)->post_title;
            } elseif ($field_name === 'post_content') {
                $current_value = get_post($post_id)->post_content;
            } else {
                $current_value = get_field($field_name, $post_id);
            }
            
            $char_count = mb_strlen(strip_tags($content), 'UTF-8');
            $max_length = isset($field_info['max_length']) ? $field_info['max_length'] : null;
            
            $html .= '<div class="gi-ai-preview-field">';
            $html .= '<div class="gi-ai-preview-field-header">';
            $html .= '<h5 class="gi-ai-preview-field-title">' . esc_html($field_info['label']) . '</h5>';
            
            if ($max_length) {
                $html .= '<span class="gi-ai-char-count ' . ($char_count > $max_length ? 'over-limit' : '') . '">';
                $html .= $char_count . ' / ' . $max_length . ' 文字';
                $html .= '</span>';
            }
            
            $html .= '<div class="gi-ai-preview-actions">';
            $html .= '<button type="button" class="button gi-ai-apply-field-btn" data-field="' . esc_attr($field_name) . '">適用</button>';
            $html .= '<button type="button" class="button gi-ai-edit-field-btn" data-field="' . esc_attr($field_name) . '">編集</button>';
            $html .= '<button type="button" class="button gi-ai-reject-field-btn" data-field="' . esc_attr($field_name) . '">却下</button>';
            $html .= '</div>';
            
            $html .= '</div>';
            
            // 現在の値との比較
            if (!empty($current_value)) {
                $html .= '<div class="gi-ai-current-value">';
                $html .= '<h6>現在の値:</h6>';
                $html .= '<div class="gi-ai-current-content">' . wp_kses_post($current_value) . '</div>';
                $html .= '</div>';
            }
            
            // 生成された値
            $html .= '<div class="gi-ai-generated-value">';
            $html .= '<h6>AI生成値:</h6>';
            $html .= '<div class="gi-ai-generated-content" data-field="' . esc_attr($field_name) . '">';
            
            if ($field_info['type'] === 'wysiwyg') {
                $html .= '<div class="gi-ai-wysiwyg-content">' . wp_kses_post($content) . '</div>';
            } else {
                $html .= '<textarea class="gi-ai-textarea-content" rows="4">' . esc_textarea($content) . '</textarea>';
            }
            
            $html .= '</div>';
            $html .= '</div>';
            
            $html .= '</div>'; // .gi-ai-preview-field
        }
        
        return $html;
    }
    
    /**
     * 生成コンテンツの適用（AJAX）
     */
    public function apply_generated_content() {
        if (!wp_verify_nonce($_POST['nonce'], 'gi_ai_auto_fill_nonce')) {
            wp_die('セキュリティチェックに失敗しました');
        }
        
        if (!current_user_can('edit_posts')) {
            wp_send_json_error('権限がありません');
        }
        
        $post_id = intval($_POST['post_id']);
        $field_data = $_POST['field_data'];
        
        if (!is_array($field_data)) {
            wp_send_json_error('無効なデータです');
        }
        
        $updated_fields = array();
        
        foreach ($field_data as $field_name => $content) {
            if (isset($this->field_definitions[$field_name])) {
                // タイトル・本文フィールドの特別処理
                if ($field_name === 'post_title') {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_title' => $content
                    ));
                } elseif ($field_name === 'post_content') {
                    wp_update_post(array(
                        'ID' => $post_id,
                        'post_content' => $content
                    ));
                } else {
                    update_field($field_name, $content, $post_id);
                }
                $updated_fields[] = $field_name;
            }
        }
        
        wp_send_json_success(array(
            'updated_fields' => $updated_fields,
            'message' => count($updated_fields) . '件のフィールドを更新しました'
        ));
    }
    
    /**
     * デフォルト選択フィールドの取得
     */
    private function get_default_fields() {
        $default = get_option('gi_ai_default_fields', array('post_title', 'ai_summary', 'grant_target'));
        
        if (is_string($default)) {
            $default = json_decode($default, true);
        }
        
        return is_array($default) ? $default : array('post_title', 'ai_summary', 'grant_target');
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
     * 管理画面用JavaScript
     */
    public function add_admin_scripts() {
        global $post;
        
        if (!$post || $post->post_type !== 'grant') {
            return;
        }
        
        // 外部スクリプトが既にエンキューされている場合はインラインJavaScriptをスキップ
        if (wp_script_is('gi-ai-auto-fill', 'enqueued')) {
            return;
        }
        
        ?>
        <script type="text/javascript">
        jQuery(document).ready(function($) {
            
            // AI自動入力の実行
            $('#gi-ai-execute-btn').on('click', function() {
                var selectedFields = [];
                $('.gi-ai-field-checkbox:checked').each(function() {
                    selectedFields.push($(this).val());
                });
                
                if (selectedFields.length === 0) {
                    alert(gi_ai_ajax.strings.no_fields_selected);
                    return;
                }
                
                if (!confirm(gi_ai_ajax.strings.confirm_process)) {
                    return;
                }
                
                executeAIProcess(selectedFields);
            });
            
            // AI処理の実行
            function executeAIProcess(fields) {
                $('#gi-ai-progress').removeClass('hidden');
                $('#gi-ai-execute-btn').prop('disabled', true);
                
                var data = {
                    action: 'gi_ai_auto_fill',
                    nonce: gi_ai_ajax.nonce,
                    post_id: <?php echo $post->ID; ?>,
                    target_fields: fields
                };
                
                $.post(gi_ai_ajax.ajax_url, data, function(response) {
                    $('#gi-ai-progress').addClass('hidden');
                    $('#gi-ai-execute-btn').prop('disabled', false);
                    
                    if (response.success) {
                        showSuccessMessage('処理が完了しました。' + response.data.updated_fields.length + '件のフィールドを更新しました。');
                        
                        // プレビューモードの場合
                        if ($('#gi-ai-preview-mode').is(':checked')) {
                            showPreview(response.data.updated_fields);
                        } else {
                            // ページをリロードしてフィールドの更新を反映
                            location.reload();
                        }
                    } else {
                        showErrorMessage('エラー: ' + response.data);
                    }
                }).fail(function() {
                    $('#gi-ai-progress').addClass('hidden');
                    $('#gi-ai-execute-btn').prop('disabled', false);
                    showErrorMessage('通信エラーが発生しました。');
                });
            }
            
            // プレビュー表示
            function showPreview(generatedContent) {
                var data = {
                    action: 'gi_ai_preview_content',
                    nonce: gi_ai_ajax.nonce,
                    post_id: <?php echo $post->ID; ?>,
                    generated_content: generatedContent
                };
                
                $.post(gi_ai_ajax.ajax_url, data, function(response) {
                    if (response.success) {
                        $('#gi-ai-preview-content').html(response.data.preview_html);
                        $('#gi-ai-preview-container').removeClass('hidden');
                        
                        // プレビューボックスにスクロール
                        $('html, body').animate({
                            scrollTop: $('#gi-ai-preview-container').offset().top - 100
                        }, 500);
                    }
                });
            }
            
            // ロールバック
            $('#gi-ai-rollback-btn').on('click', function() {
                if (!confirm(gi_ai_ajax.strings.confirm_rollback)) {
                    return;
                }
                
                var data = {
                    action: 'gi_ai_rollback',
                    nonce: gi_ai_ajax.nonce,
                    post_id: <?php echo $post->ID; ?>
                };
                
                $.post(gi_ai_ajax.ajax_url, data, function(response) {
                    if (response.success) {
                        showSuccessMessage('ロールバックが完了しました。');
                        setTimeout(function() {
                            location.reload();
                        }, 1500);
                    } else {
                        showErrorMessage('ロールバックに失敗しました: ' + response.data);
                    }
                });
            });
            
            // 一括選択ボタン
            $('#gi-ai-select-all').on('click', function() {
                $('.gi-ai-field-checkbox:not(:disabled)').prop('checked', true);
            });
            
            $('#gi-ai-select-none').on('click', function() {
                $('.gi-ai-field-checkbox').prop('checked', false);
            });
            
            $('#gi-ai-select-high').on('click', function() {
                $('.gi-ai-field-checkbox').prop('checked', false);
                $('.gi-ai-field-checkbox:not(:disabled)').each(function() {
                    var fieldName = $(this).val();
                    var highPriorityFields = ['post_title', 'post_content', 'ai_summary', 'grant_target', 'eligible_expenses'];
                    if (highPriorityFields.includes(fieldName)) {
                        $(this).prop('checked', true);
                    }
                });
            });
            
            // プレビュー操作
            $(document).on('click', '.gi-ai-apply-field-btn', function() {
                var fieldName = $(this).data('field');
                var content = $('.gi-ai-generated-content[data-field="' + fieldName + '"]').find('textarea, .gi-ai-wysiwyg-content').text();
                
                applyFieldContent(fieldName, content);
            });
            
            $(document).on('click', '.gi-ai-reject-field-btn', function() {
                var fieldName = $(this).data('field');
                $(this).closest('.gi-ai-preview-field').remove();
            });
            
            // フィールドコンテンツの適用
            function applyFieldContent(fieldName, content) {
                var data = {
                    action: 'gi_ai_apply_content',
                    nonce: gi_ai_ajax.nonce,
                    post_id: <?php echo $post->ID; ?>,
                    field_data: {}
                };
                data.field_data[fieldName] = content;
                
                $.post(gi_ai_ajax.ajax_url, data, function(response) {
                    if (response.success) {
                        showSuccessMessage('フィールド「' + fieldName + '」を更新しました。');
                    } else {
                        showErrorMessage('更新に失敗しました: ' + response.data);
                    }
                });
            }
            
            // メッセージ表示
            function showSuccessMessage(message) {
                showMessage(message, 'success');
            }
            
            function showErrorMessage(message) {
                showMessage(message, 'error');
            }
            
            function showMessage(message, type) {
                var messageHtml = '<div class="notice notice-' + type + ' is-dismissible"><p>' + message + '</p></div>';
                $('.wrap h1').after(messageHtml);
                
                // 3秒後に自動削除
                setTimeout(function() {
                    $('.notice').fadeOut(500, function() {
                        $(this).remove();
                    });
                }, 3000);
            }
        });
        </script>
        <?php
    }
    
    /**
     * 管理画面用CSS
     */
    public function add_admin_styles() {
        global $post;
        
        if (!$post || $post->post_type !== 'grant') {
            return;
        }
        
        ?>
        <style type="text/css">
        /* AI自動入力 メタボックススタイル */
        #gi-ai-auto-fill-container {
            font-size: 13px;
        }
        
        .gi-ai-usage-status {
            margin-bottom: 20px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .gi-ai-usage-status h4 {
            margin: 0 0 8px 0;
            font-size: 13px;
        }
        
        .gi-ai-usage-bar {
            width: 100%;
            height: 8px;
            background: #e1e1e1;
            border-radius: 4px;
            overflow: hidden;
            margin-bottom: 5px;
        }
        
        .gi-ai-usage-fill {
            height: 100%;
            background: linear-gradient(90deg, #00a32a 0%, #ffb900 70%, #d63638 100%);
            transition: width 0.3s ease;
        }
        
        .gi-ai-usage-text {
            margin: 0;
            font-size: 12px;
            color: #666;
        }
        
        .gi-ai-priority-section {
            margin-bottom: 15px;
        }
        
        .gi-ai-priority-title {
            margin: 0 0 8px 0;
            padding: 5px 0;
            border-bottom: 1px solid #ddd;
            display: flex;
            align-items: center;
            font-size: 13px;
            font-weight: 600;
        }
        
        .priority-badge {
            display: inline-block;
            width: 20px;
            height: 20px;
            line-height: 20px;
            text-align: center;
            border-radius: 3px;
            color: white;
            font-size: 11px;
            font-weight: bold;
            margin-right: 8px;
        }
        
        .priority-badge.high { background: #d63638; }
        .priority-badge.medium { background: #ffb900; }
        .priority-badge.low { background: #00a32a; }
        
        .gi-ai-field-item {
            margin-bottom: 8px;
        }
        
        .gi-ai-field-label {
            display: flex;
            align-items: center;
            padding: 6px 8px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            cursor: pointer;
            transition: all 0.2s;
        }
        
        .gi-ai-field-label:hover {
            background: #f0f8ff;
            border-color: #0073aa;
        }
        
        .gi-ai-field-label.has-content {
            background: #f0f6fc;
            border-color: #c3c4c7;
        }
        
        .gi-ai-field-checkbox {
            margin-right: 8px !important;
        }
        
        .field-name {
            flex: 1;
            font-weight: 500;
        }
        
        .field-status {
            font-size: 11px;
            padding: 2px 6px;
            border-radius: 2px;
            margin-left: 8px;
        }
        
        .field-status.filled {
            background: #d1e7dd;
            color: #0f5132;
        }
        
        .field-status.empty {
            background: #f8d7da;
            color: #721c24;
        }
        
        .field-limit {
            font-size: 11px;
            color: #666;
            margin-left: 8px;
        }
        
        .gi-ai-field-description {
            font-size: 11px;
            color: #666;
            margin: 4px 0 0 28px;
            line-height: 1.3;
        }
        
        .gi-ai-bulk-select {
            margin: 15px 0;
            padding-top: 15px;
            border-top: 1px solid #ddd;
        }
        
        .gi-ai-bulk-select .button {
            margin-right: 5px;
            font-size: 12px;
        }
        
        .gi-ai-options {
            margin: 20px 0;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .gi-ai-options h4 {
            margin: 0 0 10px 0;
            font-size: 13px;
        }
        
        .gi-ai-option {
            display: block;
            margin-bottom: 5px;
            font-size: 12px;
        }
        
        .gi-ai-option input {
            margin-right: 6px;
        }
        
        .gi-ai-actions {
            margin-top: 20px;
        }
        
        #gi-ai-execute-btn {
            width: 100%;
            height: auto;
            padding: 10px;
            font-size: 14px;
            font-weight: 600;
        }
        
        #gi-ai-execute-btn .dashicons {
            margin-right: 5px;
        }
        
        .gi-ai-progress {
            margin-top: 15px;
            padding: 15px;
            background: #f0f8ff;
            border: 1px solid #0073aa;
            border-radius: 4px;
        }
        
        .gi-ai-progress-bar {
            width: 100%;
            height: 20px;
            background: #e1e1e1;
            border-radius: 10px;
            overflow: hidden;
            margin-bottom: 10px;
        }
        
        .gi-ai-progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #0073aa, #005177);
            width: 0%;
            transition: width 0.3s ease;
        }
        
        .gi-ai-progress-text {
            margin: 0 0 10px 0;
            font-weight: 600;
            text-align: center;
        }
        
        .gi-ai-rollback {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        
        .gi-ai-rollback h4 {
            margin: 0 0 8px 0;
            font-size: 13px;
        }
        
        .gi-ai-rollback .description {
            margin-bottom: 10px;
            font-size: 12px;
            color: #666;
        }
        
        #gi-ai-rollback-btn .dashicons {
            margin-right: 5px;
        }
        
        /* プレビューメタボックススタイル */
        .gi-ai-preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #0073aa;
        }
        
        .gi-ai-preview-header h4 {
            margin: 0;
        }
        
        .gi-ai-preview-actions .button {
            margin-left: 5px;
        }
        
        .gi-ai-preview-field {
            margin-bottom: 25px;
            padding: 15px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        
        .gi-ai-preview-field-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid #eee;
        }
        
        .gi-ai-preview-field-title {
            margin: 0;
            font-size: 14px;
            font-weight: 600;
        }
        
        .gi-ai-char-count {
            font-size: 12px;
            color: #666;
        }
        
        .gi-ai-char-count.over-limit {
            color: #d63638;
            font-weight: bold;
        }
        
        .gi-ai-preview-actions .button {
            font-size: 11px;
            padding: 2px 8px;
            height: auto;
            margin-left: 3px;
        }
        
        .gi-ai-current-value,
        .gi-ai-generated-value {
            margin-bottom: 15px;
        }
        
        .gi-ai-current-value h6,
        .gi-ai-generated-value h6 {
            margin: 0 0 8px 0;
            font-size: 12px;
            font-weight: 600;
            color: #666;
        }
        
        .gi-ai-current-content,
        .gi-ai-generated-content {
            padding: 10px;
            background: #f8f9fa;
            border: 1px solid #e1e1e1;
            border-radius: 3px;
        }
        
        .gi-ai-textarea-content {
            width: 100%;
            border: 1px solid #ddd;
            border-radius: 3px;
            padding: 8px;
            font-size: 13px;
            resize: vertical;
        }
        
        /* バッチ処理ページスタイル */
        .gi-ai-batch-posts,
        .gi-ai-batch-fields,
        .gi-ai-batch-options {
            margin-bottom: 30px;
        }
        
        .gi-ai-batch-controls {
            margin-bottom: 15px;
            padding: 10px;
            background: #f8f9fa;
            border-radius: 4px;
        }
        
        .gi-ai-selection-count {
            margin-left: 15px;
            font-weight: 600;
        }
        
        .gi-ai-posts-list {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid #ddd;
            border-radius: 4px;
            padding: 10px;
        }
        
        .gi-ai-post-item {
            margin-bottom: 8px;
            padding: 8px;
            background: #fff;
            border: 1px solid #e1e1e1;
            border-radius: 3px;
        }
        
        .gi-ai-post-item label {
            cursor: pointer;
            display: block;
        }
        
        .gi-ai-post-meta {
            font-size: 12px;
            color: #666;
            margin-left: 20px;
        }
        
        .gi-ai-field-group {
            margin-bottom: 20px;
        }
        
        .gi-ai-group-title {
            margin-bottom: 10px;
            font-size: 14px;
        }
        
        .gi-ai-field-list {
            margin-left: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 8px;
        }
        
        .gi-ai-batch-field-label {
            display: block;
            padding: 5px 8px;
            background: #fff;
            border: 1px solid #ddd;
            border-radius: 3px;
            font-size: 13px;
            cursor: pointer;
        }
        
        .gi-ai-batch-field-label:hover {
            background: #f0f8ff;
            border-color: #0073aa;
        }
        
        .gi-ai-batch-option {
            display: block;
            margin-bottom: 8px;
        }
        
        .gi-ai-batch-execute {
            text-align: center;
            margin-top: 30px;
        }
        
        #gi-ai-batch-execute-btn {
            padding: 15px 30px;
            font-size: 16px;
        }
        
        /* 隠し状態 */
        .hidden {
            display: none !important;
        }
        
        /* レスポンシブ対応 */
        @media (max-width: 782px) {
            .gi-ai-field-list {
                grid-template-columns: 1fr;
            }
            
            .gi-ai-preview-field-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .gi-ai-preview-actions {
                margin-top: 10px;
            }
        }
        </style>
        <?php
    }
}

// インスタンス化
if (class_exists('GI_AI_Admin_Interface')) {
    new GI_AI_Admin_Interface();
}