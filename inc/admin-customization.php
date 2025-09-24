<?php
/**
 * Grant Insight Perfect - 6. Admin Functions File
 *
 * 管理画面のカスタマイズ（スクリプト読込、投稿一覧へのカラム追加、
 * メタボックス追加、カスタムメニュー追加など）を担当します。
 *
 * @package Grant_Insight_Perfect
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit;
}



/**
 * 管理画面カスタマイズ（強化版）
 */
function gi_admin_init() {
    // 管理画面でのjQuery読み込み
    add_action('admin_enqueue_scripts', function() {
        wp_enqueue_script('jquery');
    });
    
    // 管理画面スタイル
    add_action('admin_head', function() {
        echo '<style>
        .gi-admin-notice {
            border-left: 4px solid #10b981;
            background: #ecfdf5;
            padding: 12px 20px;
            margin: 20px 0;
            border-radius: 4px;
        }
        .gi-admin-notice h3 {
            color: #047857;
            margin: 0 0 8px 0;
            font-size: 16px;
        }
        .gi-admin-notice p {
            color: #065f46;
            margin: 0;
        }
        </style>';
    });
    
    // 投稿一覧カラム追加
    add_filter('manage_grant_posts_columns', 'gi_add_grant_columns');
    
    // AI自動入力設定メニューの追加
    add_action('admin_menu', 'gi_add_ai_settings_menu');
    add_action('manage_grant_posts_custom_column', 'gi_grant_column_content', 10, 2);
}
add_action('admin_init', 'gi_admin_init');

/**
 * 助成金一覧にカスタムカラムを追加
 */
function gi_add_grant_columns($columns) {
    $new_columns = array();
    foreach ($columns as $key => $value) {
        $new_columns[$key] = $value;
        if ($key === 'title') {
            $new_columns['gi_prefecture'] = '都道府県';
            $new_columns['gi_amount'] = '金額';
            $new_columns['gi_organization'] = '実施組織';
            $new_columns['gi_status'] = 'ステータス';
        }
    }
    return $new_columns;
}

/**
 * カスタムカラムに内容を表示
 */
function gi_grant_column_content($column, $post_id) {
    switch ($column) {
        case 'gi_prefecture':
            $prefecture_terms = get_the_terms($post_id, 'grant_prefecture');
            if ($prefecture_terms && !is_wp_error($prefecture_terms)) {
                echo gi_safe_escape($prefecture_terms[0]->name);
            } else {
                echo '－';
            }
            break;
        case 'gi_amount':
            $amount = gi_safe_get_meta($post_id, 'max_amount');
            echo $amount ? gi_safe_escape($amount) . '万円' : '－';
            break;
        case 'gi_organization':
            echo gi_safe_escape(gi_safe_get_meta($post_id, 'organization', '－'));
            break;
        case 'gi_status':
            $status = gi_map_application_status_ui(gi_safe_get_meta($post_id, 'application_status', 'open'));
            $status_labels = array(
                'active' => '<span style="color: #059669;">募集中</span>',
                'upcoming' => '<span style="color: #d97706;">募集予定</span>',
                'closed' => '<span style="color: #dc2626;">募集終了</span>'
            );
            echo $status_labels[$status] ?? $status;
            break;
    }
}

/**
 * 管理画面にサンプルデータ作成ボタンを追加
 */
function gi_add_sample_data_page() {
    add_submenu_page(
        'edit.php?post_type=grant',
        'サンプルデータ作成',
        'サンプルデータ',
        'manage_options',
        'gi-sample-data',
        'gi_sample_data_page_content'
    );
}
add_action('admin_menu', 'gi_add_sample_data_page');

/**
 * サンプルデータページの内容
 */
function gi_sample_data_page_content() {
    if (isset($_POST['create_sample_data']) && check_admin_referer('gi_create_sample_data')) {
        gi_create_sample_grants();
        echo '<div class="notice notice-success"><p>サンプルデータを作成しました。</p></div>';
    }
    
    // 現在の投稿数を確認
    $grant_count = wp_count_posts('grant')->publish;
    ?>
    <div class="wrap">
        <h1>サンプルデータ作成</h1>
        
        <div class="gi-admin-notice">
            <h3>現在の状況</h3>
            <p>現在の助成金投稿数: <strong><?php echo $grant_count; ?>件</strong></p>
        </div>
        
        <?php if ($grant_count == 0): ?>
        <form method="post" action="">
            <?php wp_nonce_field('gi_create_sample_data'); ?>
            <p>サンプルデータを作成すると、テスト用の助成金情報が登録されます。</p>
            <p>
                <input type="submit" name="create_sample_data" class="button button-primary" value="サンプルデータを作成">
            </p>
        </form>
        <?php else: ?>
        <p>すでに投稿データが存在するため、サンプルデータの作成はスキップされました。</p>
        <?php endif; ?>
        
        <h2>都道府県別統計</h2>
        <?php
        $prefectures = get_terms(array(
            'taxonomy' => 'grant_prefecture',
            'hide_empty' => false,
            'orderby' => 'count',
            'order' => 'DESC'
        ));
        
        if (!empty($prefectures) && !is_wp_error($prefectures)): ?>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>都道府県</th>
                    <th>投稿数</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($prefectures as $pref): ?>
                <tr>
                    <td><?php echo esc_html($pref->name); ?></td>
                    <td><?php echo $pref->count; ?>件</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php else: ?>
        <p>都道府県データがありません。</p>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * 管理メニューの追加
 */
function gi_add_admin_menu() {
    // 都道府県データ初期化
    add_management_page(
        '都道府県データ初期化',
        '都道府県データ初期化',
        'manage_options',
        'gi-prefecture-init',
        'gi_add_prefecture_init_button'
    );
    
    // AI設定メニュー追加
    add_menu_page(
        'AI検索設定',
        'AI検索設定',
        'manage_options',
        'gi-ai-settings',
        'gi_ai_settings_page',
        'dashicons-search',
        30
    );
    
    // AI検索統計サブメニュー
    add_submenu_page(
        'gi-ai-settings',
        'AI検索統計',
        '統計・レポート',
        'manage_options',
        'gi-ai-statistics',
        'gi_ai_statistics_page'
    );
    
    // AI一括処理サブメニュー
    add_submenu_page(
        'gi-ai-settings',
        'AI一括処理',
        'バッチ処理',
        'manage_options',
        'gi-ai-batch-processing',
        'gi_ai_batch_processing_page'
    );
}
add_action('admin_menu', 'gi_add_admin_menu');

/**
 * 都道府県データ初期化ページの表示内容
 */
function gi_add_prefecture_init_button() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    if (isset($_POST['init_prefecture_data']) && isset($_POST['prefecture_nonce']) && wp_verify_nonce($_POST['prefecture_nonce'], 'init_prefecture')) {
        // `gi_setup_prefecture_taxonomy_data` は initial-setup.php にある想定
        if (function_exists('gi_setup_prefecture_taxonomy_data')) {
            gi_setup_prefecture_taxonomy_data();
            echo '<div class="notice notice-success"><p>都道府県データを初期化しました。</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>エラー: 初期化関数が見つかりませんでした。</p></div>';
        }
    }
    
    ?>
    <div class="wrap">
        <h2>都道府県データ初期化</h2>
        <form method="post">
            <?php wp_nonce_field('init_prefecture', 'prefecture_nonce'); ?>
            <p>助成金の都道府県データとサンプルデータを初期化します。</p>
            <p class="description">この操作は既存の都道府県タクソノミーに不足しているデータを追加するもので、既存のデータを削除するものではありません。</p>
            <input type="submit" name="init_prefecture_data" class="button button-primary" value="都道府県データを初期化" />
        </form>
    </div>
    <?php
}

/**
 * AI設定ページ（簡易版）
 */
function gi_ai_settings_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // 設定の保存処理
    if (isset($_POST['save_ai_settings']) && wp_verify_nonce($_POST['ai_settings_nonce'], 'gi_ai_settings')) {
        $settings = [
            'enable_ai_search' => isset($_POST['enable_ai_search']) ? 1 : 0,
            'enable_voice_input' => isset($_POST['enable_voice_input']) ? 1 : 0,
            'enable_ai_chat' => isset($_POST['enable_ai_chat']) ? 1 : 0
        ];
        
        update_option('gi_ai_settings', $settings);
        
        // OpenAI APIキーの保存
        if (isset($_POST['openai_api_key'])) {
            $api_key = sanitize_text_field($_POST['openai_api_key']);
            gi_set_openai_api_key($api_key);
        }
        
        echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
    }
    
    // API接続テスト
    $connection_status = '';
    if (isset($_POST['test_connection']) && wp_verify_nonce($_POST['ai_settings_nonce'], 'gi_ai_settings')) {
        $capabilities = gi_check_ai_capabilities();
        if ($capabilities['openai_configured']) {
            $connection_status = '<div class="notice notice-success"><p>✅ OpenAI APIへの接続が正常です！</p></div>';
        } else {
            $connection_status = '<div class="notice notice-error"><p>❌ OpenAI APIキーが設定されていないか、無効です。</p></div>';
        }
    }
    
    // 現在の設定を取得
    $settings = get_option('gi_ai_settings', [
        'enable_ai_search' => 1,
        'enable_voice_input' => 1,
        'enable_ai_chat' => 1
    ]);
    
    // OpenAI APIキーを取得
    $api_key = gi_get_openai_api_key();
    $api_key_display = !empty($api_key) ? str_repeat('*', 20) . substr($api_key, -4) : '';
    ?>
    <div class="wrap">
        <h1>AI検索設定</h1>
        
        <?php echo $connection_status; ?>
        
        <form method="post" action="">
            <?php wp_nonce_field('gi_ai_settings', 'ai_settings_nonce'); ?>
            
            <!-- OpenAI API設定セクション -->
            <h2>🤖 OpenAI API設定</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="openai_api_key">OpenAI APIキー</label>
                    </th>
                    <td>
                        <input type="password" id="openai_api_key" name="openai_api_key" 
                               value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text" 
                               placeholder="sk-..." />
                        <p class="description">
                            OpenAI APIキーを入力してください。
                            <?php if (!empty($api_key_display)): ?>
                                <br><strong>現在の設定:</strong> <code><?php echo esc_html($api_key_display); ?></code>
                            <?php endif; ?>
                            <br>APIキーの取得方法: <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>
                        </p>
                    </td>
                </tr>
                <tr>
                    <th scope="row">接続テスト</th>
                    <td>
                        <input type="submit" name="test_connection" class="button button-secondary" value="API接続をテスト">
                        <p class="description">OpenAI APIへの接続状況をテストします。</p>
                    </td>
                </tr>
            </table>
            
            <!-- AI機能有効化設定 -->
            <h2>🔧 AI機能設定</h2>
            <table class="form-table">
                <tr>
                    <th scope="row">AI検索を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_ai_search" value="1" 
                                <?php checked($settings['enable_ai_search'], 1); ?>>
                            AIによる高度な検索機能を有効にする
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">音声入力を有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_voice_input" value="1" 
                                <?php checked($settings['enable_voice_input'], 1); ?>>
                            音声による検索入力を有効にする
                        </label>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">AIチャットを有効化</th>
                    <td>
                        <label>
                            <input type="checkbox" name="enable_ai_chat" value="1" 
                                <?php checked($settings['enable_ai_chat'], 1); ?>>
                            AIアシスタントとのチャット機能を有効にする
                        </label>
                    </td>
                </tr>
            </table>
            
            <p class="submit">
                <input type="submit" name="save_ai_settings" class="button-primary" value="設定を保存">
            </p>
        </form>
        
        <!-- AI機能ステータス表示 -->
        <div class="gi-admin-notice" style="margin-top: 30px;">
            <h3>🔍 AI機能ステータス</h3>
            <?php
            $capabilities = gi_check_ai_capabilities();
            echo '<ul>';
            echo '<li><strong>OpenAI API:</strong> ' . ($capabilities['openai_configured'] ? '✅ 設定済み' : '❌ 未設定') . '</li>';
            echo '<li><strong>セマンティック検索:</strong> ' . ($capabilities['semantic_search_available'] ? '✅ 利用可能' : '❌ 利用不可') . '</li>';
            echo '<li><strong>音声認識:</strong> ' . ($capabilities['voice_recognition_available'] ? '✅ 利用可能' : '❌ OpenAI API必要') . '</li>';
            echo '<li><strong>AIチャット:</strong> ' . ($capabilities['chat_available'] ? '✅ 利用可能' : '❌ 利用不可') . '</li>';
            echo '</ul>';
            ?>
            <p><strong>注意:</strong> OpenAI APIキーが未設定の場合、基本的なフォールバック機能のみが動作します。</p>
        </div>
        
        <!-- 使用方法ガイド -->
        <div style="background: #f9f9f9; padding: 20px; border-radius: 8px; margin-top: 20px;">
            <h3>📖 使用方法ガイド</h3>
            <ol>
                <li><strong>OpenAI APIキーを取得:</strong> <a href="https://platform.openai.com/api-keys" target="_blank">OpenAI Platform</a>でアカウント作成・APIキー生成</li>
                <li><strong>APIキーを入力:</strong> 上記フォームにAPIキーを入力して保存</li>
                <li><strong>接続テスト:</strong> 「API接続をテスト」ボタンで動作確認</li>
                <li><strong>機能有効化:</strong> 各AI機能のチェックボックスをONにして保存</li>
                <li><strong>フロントページで確認:</strong> サイトのトップページでAI検索機能をテスト</li>
            </ol>
        </div>
        
        <!-- AJAX接続テスト用JavaScript -->
        <script>
        jQuery(document).ready(function($) {
            // フォーム送信時の接続テスト処理
            $('input[name="test_connection"]').click(function(e) {
                e.preventDefault();
                
                var $button = $(this);
                var $statusDiv = $('.gi-admin-notice').last();
                
                // ローディング表示
                $button.val('テスト中...').prop('disabled', true);
                $statusDiv.hide();
                
                // AJAX接続テスト実行
                $.post(ajaxurl, {
                    action: 'gi_test_connection',
                    nonce: '<?php echo wp_create_nonce("gi_ajax_nonce"); ?>'
                }, function(response) {
                    $button.val('API接続をテスト').prop('disabled', false);
                    
                    if (response.success) {
                        $statusDiv.html(
                            '<h3>✅ API接続テスト成功</h3>' +
                            '<p><strong>メッセージ:</strong> ' + response.data.message + '</p>' +
                            '<p><strong>時刻:</strong> ' + response.data.time + '</p>'
                        ).removeClass('notice-error').addClass('notice-success').show();
                    } else {
                        $statusDiv.html(
                            '<h3>❌ API接続テスト失敗</h3>' +
                            '<p><strong>エラー:</strong> ' + (response.data.message || response.data) + '</p>' +
                            '<p><strong>詳細:</strong> ' + (response.data.details || 'なし') + '</p>'
                        ).removeClass('notice-success').addClass('notice-error').show();
                    }
                }).fail(function() {
                    $button.val('API接続をテスト').prop('disabled', false);
                    $statusDiv.html(
                        '<h3>❌ 接続エラー</h3>' +
                        '<p>AJAX リクエストに失敗しました。</p>'
                    ).removeClass('notice-success').addClass('notice-error').show();
                });
            });
            
            // APIキー入力時のマスク処理
            $('#openai_api_key').focus(function() {
                if ($(this).val().indexOf('*') === 0) {
                    $(this).val('');
                }
            });
        });
        </script>
        
        <style>
        .notice {
            padding: 1px 12px;
            margin: 5px 0 15px;
            border-left-width: 4px;
            border-left-style: solid;
        }
        .notice-success {
            border-left-color: #46b450;
            background-color: #fff;
        }
        .notice-error {
            border-left-color: #dc3232;
            background-color: #fff;
        }
        </style>
    </div>
    <?php
}

/**
 * AI統計ページ（簡易版）
 */
function gi_ai_statistics_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    global $wpdb;
    
    // テーブルが存在するかチェック
    $search_table = $wpdb->prefix . 'gi_search_history';
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$search_table'") === $search_table;
    
    if (!$table_exists) {
        ?>
        <div class="wrap">
            <h1>AI検索統計</h1>
            <div class="notice notice-info">
                <p>統計データテーブルがまだ作成されていません。初回の検索実行時に自動的に作成されます。</p>
            </div>
        </div>
        <?php
        return;
    }
    
    // 統計データの取得
    $total_searches = $wpdb->get_var("SELECT COUNT(*) FROM $search_table") ?: 0;
    
    // チャット履歴テーブル
    $chat_table = $wpdb->prefix . 'gi_chat_history';
    $chat_exists = $wpdb->get_var("SHOW TABLES LIKE '$chat_table'") === $chat_table;
    $total_chats = $chat_exists ? $wpdb->get_var("SELECT COUNT(*) FROM $chat_table WHERE message_type = 'user'") : 0;
    
    // 人気の検索キーワード（直近30日）
    $popular_searches = $wpdb->get_results("
        SELECT search_query, COUNT(*) as count 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY search_query 
        ORDER BY count DESC 
        LIMIT 10
    ");
    
    // 時間帯別利用状況（直近7日）
    $hourly_stats = $wpdb->get_results("
        SELECT HOUR(created_at) as hour, COUNT(*) as count 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY HOUR(created_at) 
        ORDER BY hour
    ");
    
    // 日別利用状況（直近30日）
    $daily_stats = $wpdb->get_results("
        SELECT DATE(created_at) as date, COUNT(*) as count 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        GROUP BY DATE(created_at) 
        ORDER BY date DESC
    ");
    
    // 平均検索結果数
    $avg_results = $wpdb->get_var("
        SELECT AVG(results_count) 
        FROM $search_table 
        WHERE created_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
    ") ?: 0;
    
    ?>
    <div class="wrap">
        <h1>AI検索統計</h1>
        
        <!-- 統計サマリー -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 20px; margin: 20px 0;">
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">総検索数</h3>
                <p style="font-size: 32px; font-weight: bold; color: #10b981; margin: 10px 0;">
                    <?php echo number_format($total_searches); ?>
                </p>
                <p style="color: #666; font-size: 12px;">全期間</p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">チャット数</h3>
                <p style="font-size: 32px; font-weight: bold; color: #3b82f6; margin: 10px 0;">
                    <?php echo number_format($total_chats); ?>
                </p>
                <p style="color: #666; font-size: 12px;">AIとの対話数</p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">平均検索結果</h3>
                <p style="font-size: 32px; font-weight: bold; color: #f59e0b; margin: 10px 0;">
                    <?php echo number_format($avg_results, 1); ?>
                </p>
                <p style="color: #666; font-size: 12px;">件/検索</p>
            </div>
            
            <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px;">
                <h3 style="margin-top: 0; color: #333; font-size: 14px;">本日の検索</h3>
                <p style="font-size: 32px; font-weight: bold; color: #8b5cf6; margin: 10px 0;">
                    <?php 
                    $today_searches = $wpdb->get_var("
                        SELECT COUNT(*) FROM $search_table 
                        WHERE DATE(created_at) = CURDATE()
                    ") ?: 0;
                    echo number_format($today_searches);
                    ?>
                </p>
                <p style="color: #666; font-size: 12px;"><?php echo date('Y年m月d日'); ?></p>
            </div>
        </div>
        
        <!-- 人気検索キーワード -->
        <?php if (!empty($popular_searches)): ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="font-size: 18px; margin-top: 0;">人気の検索キーワード（過去30日）</h2>
            <table class="wp-list-table widefat fixed striped" style="margin-top: 15px;">
                <thead>
                    <tr>
                        <th style="width: 50px;">順位</th>
                        <th>検索キーワード</th>
                        <th style="width: 100px;">検索回数</th>
                        <th style="width: 120px;">割合</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $total_month = array_sum(array_column($popular_searches, 'count'));
                    foreach ($popular_searches as $index => $search): 
                        $percentage = ($search->count / $total_month) * 100;
                    ?>
                    <tr>
                        <td><strong><?php echo $index + 1; ?></strong></td>
                        <td>
                            <?php echo esc_html($search->search_query); ?>
                            <?php if ($index < 3): ?>
                                <span style="color: #f59e0b;">🔥</span>
                            <?php endif; ?>
                        </td>
                        <td><?php echo number_format($search->count); ?>回</td>
                        <td>
                            <div style="display: flex; align-items: center; gap: 5px;">
                                <div style="background: #e5e5e5; height: 20px; flex: 1; border-radius: 3px; overflow: hidden;">
                                    <div style="background: #10b981; height: 100%; width: <?php echo $percentage; ?>%;"></div>
                                </div>
                                <span style="font-size: 12px;"><?php echo number_format($percentage, 1); ?>%</span>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
        
        <!-- 時間帯別利用状況 -->
        <?php if (!empty($hourly_stats)): ?>
        <div style="background: #fff; padding: 20px; border: 1px solid #ddd; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="font-size: 18px; margin-top: 0;">時間帯別利用状況（過去7日間）</h2>
            <div style="display: flex; align-items: flex-end; height: 200px; gap: 2px; margin-top: 20px;">
                <?php 
                $max_hour = max(array_column($hourly_stats, 'count'));
                for ($h = 0; $h < 24; $h++):
                    $count = 0;
                    foreach ($hourly_stats as $stat) {
                        if ($stat->hour == $h) {
                            $count = $stat->count;
                            break;
                        }
                    }
                    $height = $max_hour > 0 ? ($count / $max_hour) * 100 : 0;
                ?>
                <div style="flex: 1; display: flex; flex-direction: column; align-items: center;">
                    <div style="background: <?php echo $height > 0 ? '#3b82f6' : '#e5e5e5'; ?>; 
                                width: 100%; 
                                height: <?php echo max($height, 2); ?>%; 
                                border-radius: 2px 2px 0 0;"
                         title="<?php echo $h; ?>時: <?php echo $count; ?>件"></div>
                    <?php if ($h % 3 == 0): ?>
                    <span style="font-size: 10px; margin-top: 5px;"><?php echo $h; ?>時</span>
                    <?php endif; ?>
                </div>
                <?php endfor; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- アクション -->
        <div style="margin-top: 30px;">
            <a href="<?php echo admin_url('admin.php?page=gi-ai-settings'); ?>" class="button button-primary">
                AI設定を確認
            </a>
            <button type="button" class="button" onclick="if(confirm('統計データをリセットしますか？')) location.href='?page=gi-ai-statistics&action=reset&nonce=<?php echo wp_create_nonce('reset_stats'); ?>'">
                統計をリセット
            </button>
        </div>
    </div>
    <?php
    
    // リセット処理
    if (isset($_GET['action']) && $_GET['action'] === 'reset' && wp_verify_nonce($_GET['nonce'], 'reset_stats')) {
        $wpdb->query("TRUNCATE TABLE $search_table");
        if ($chat_exists) {
            $wpdb->query("TRUNCATE TABLE $chat_table");
        }
        echo '<div class="notice notice-success"><p>統計データをリセットしました。</p></div>';
        echo '<script>setTimeout(function(){ location.href="?page=gi-ai-statistics"; }, 2000);</script>';
    }
}

/**
 * 一括下書き投稿作成
 */
function gi_create_bulk_draft_posts($count, $title_template) {
    $created_posts = array();
    
    for ($i = 1; $i <= $count; $i++) {
        $title = str_replace('{number}', $i, $title_template);
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'draft',
            'post_type' => 'grant',
            'post_author' => get_current_user_id()
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // 基本的なメタフィールドを設定
            update_post_meta($post_id, 'grant_organization', '');
            update_post_meta($post_id, 'grant_url', '');
            update_post_meta($post_id, 'max_amount', '');
            update_post_meta($post_id, 'application_deadline', '');
            
            $created_posts[] = $post_id;
        }
    }
    
    return $created_posts;
}

/**
 * テンプレート対応の一括下書き投稿作成
 */
function gi_create_bulk_draft_posts_with_template($count, $title_template, $template_type) {
    $created_posts = array();
    
    // テンプレート定義
    $templates = gi_get_post_templates();
    $template_data = isset($templates[$template_type]) ? $templates[$template_type] : $templates['general'];
    
    for ($i = 1; $i <= $count; $i++) {
        $title = str_replace('{number}', $i, $title_template);
        
        $post_data = array(
            'post_title' => $title,
            'post_content' => '',
            'post_status' => 'draft',
            'post_type' => 'grant',
            'post_author' => get_current_user_id()
        );
        
        $post_id = wp_insert_post($post_data);
        
        if ($post_id && !is_wp_error($post_id)) {
            // テンプレートに基づいてメタフィールドを設定
            foreach ($template_data['fields'] as $field_key => $field_value) {
                update_post_meta($post_id, $field_key, $field_value);
            }
            
            // ACFフィールド（存在する場合）
            if (function_exists('update_field') && isset($template_data['acf_fields'])) {
                foreach ($template_data['acf_fields'] as $field_key => $field_value) {
                    update_field($field_key, $field_value, $post_id);
                }
            }
            
            $created_posts[] = $post_id;
        }
    }
    
    return $created_posts;
}

/**
 * 投稿テンプレート定義
 */
function gi_get_post_templates() {
    return array(
        'general' => array(
            'name' => '汎用助成金',
            'fields' => array(
                'grant_organization' => '経済産業省',
                'max_amount' => '1000万円',
                'grant_category' => '事業支援'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'normal',
                'application_method' => 'online'
            )
        ),
        'startup' => array(
            'name' => '創業・起業支援',
            'fields' => array(
                'grant_organization' => '中小企業庁',
                'max_amount' => '500万円',
                'grant_category' => '創業支援'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'hard',
                'application_method' => 'mixed'
            )
        ),
        'equipment' => array(
            'name' => '設備投資支援',
            'fields' => array(
                'grant_organization' => '経済産業省',
                'max_amount' => '3000万円',
                'grant_category' => '設備投資'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'normal',
                'application_method' => 'online'
            )
        ),
        'research' => array(
            'name' => '研究開発支援',
            'fields' => array(
                'grant_organization' => '科学技術振興機構',
                'max_amount' => '5000万円',
                'grant_category' => '研究開発'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'expert',
                'application_method' => 'mail'
            )
        ),
        'employment' => array(
            'name' => '雇用支援',
            'fields' => array(
                'grant_organization' => '厚生労働省',
                'max_amount' => '200万円',
                'grant_category' => '雇用支援'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'easy',
                'application_method' => 'online'
            )
        ),
        'environment' => array(
            'name' => '環境・省エネ支援',
            'fields' => array(
                'grant_organization' => '環境省',
                'max_amount' => '1500万円',
                'grant_category' => '環境対策'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'normal',
                'application_method' => 'mixed'
            )
        ),
        'regional' => array(
            'name' => '地域活性化',
            'fields' => array(
                'grant_organization' => '地方自治体',
                'max_amount' => '800万円',
                'grant_category' => '地域振興'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'easy',
                'application_method' => 'visit'
            )
        ),
        'digitization' => array(
            'name' => 'DX・IT導入支援',
            'fields' => array(
                'grant_organization' => 'デジタル庁',
                'max_amount' => '1200万円',
                'grant_category' => 'デジタル化'
            ),
            'acf_fields' => array(
                'grant_difficulty' => 'normal',
                'application_method' => 'online'
            )
        )
    );
}

/**
 * AI一括処理ページ
 */
function gi_ai_batch_processing_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // バッチ処理実行
    if (isset($_POST['execute_batch']) && wp_verify_nonce($_POST['gi_batch_nonce'], 'gi_batch_processing')) {
        $selected_posts = isset($_POST['selected_posts']) ? $_POST['selected_posts'] : array();
        $selected_fields = isset($_POST['selected_fields']) ? $_POST['selected_fields'] : array();
        
        if (!empty($selected_posts) && !empty($selected_fields)) {
            // セッションに処理情報を保存
            set_transient('gi_batch_processing_data', array(
                'posts' => $selected_posts,
                'fields' => $selected_fields,
                'user_id' => get_current_user_id(),
                'start_time' => current_time('mysql')
            ), 3600);
            
            echo '<div class="notice notice-success"><p>バッチ処理を開始しました。進捗は下記で確認できます。</p></div>';
        }
    }
    
    // 一括設定適用処理
    if (isset($_POST['apply_bulk_settings']) && wp_verify_nonce($_POST['gi_bulk_settings_nonce'], 'gi_bulk_settings')) {
        $bulk_posts = isset($_POST['bulk_setting_posts']) ? $_POST['bulk_setting_posts'] : array();
        
        if (!empty($bulk_posts)) {
            $settings = array(
                'grant_organization' => sanitize_text_field($_POST['bulk_organization'] ?? ''),
                'max_amount' => sanitize_text_field($_POST['bulk_max_amount'] ?? ''),
                'application_deadline' => sanitize_text_field($_POST['bulk_application_deadline'] ?? ''),
                'grant_url' => esc_url_raw($_POST['bulk_grant_url'] ?? '')
            );
            
            $updated_count = 0;
            foreach ($bulk_posts as $post_id) {
                foreach ($settings as $meta_key => $meta_value) {
                    if (!empty($meta_value)) {
                        update_post_meta($post_id, $meta_key, $meta_value);
                    }
                }
                $updated_count++;
            }
            
            echo '<div class="notice notice-success"><p>' . $updated_count . '件の投稿に設定を適用しました。</p></div>';
        }
    }
    
    // 一括投稿作成処理
    if (isset($_POST['create_bulk_posts']) && wp_verify_nonce($_POST['gi_bulk_create_nonce'], 'gi_bulk_create')) {
        $post_count = intval($_POST['post_count']);
        $post_title_template = sanitize_text_field($_POST['post_title_template']);
        $post_template = sanitize_text_field($_POST['post_template'] ?? 'general');
        
        if ($post_count > 0 && $post_count <= 100) {
            $created_posts = gi_create_bulk_draft_posts_with_template($post_count, $post_title_template, $post_template);
            echo '<div class="notice notice-success"><p>' . count($created_posts) . '件の下書き投稿を作成しました（テンプレート: ' . $post_template . '）。</p></div>';
        }
    }
    
    // 助成金投稿一覧を取得
    $grant_posts = get_posts(array(
        'post_type' => 'grant',
        'post_status' => 'draft',
        'numberposts' => 100,
        'orderby' => 'date',
        'order' => 'DESC'
    ));
    
    // フィールド定義
    $available_fields = array(
        'post_title' => '投稿タイトル',
        'post_content' => '投稿本文',
        'ai_summary' => 'AI概要',
        'grant_target' => '対象者・対象事業',
        'eligible_expenses' => '対象経費',
        'grant_difficulty' => '申請難易度',
        'required_documents' => '必要書類',
        'application_method' => '申請方法',
        'contact_info' => '問い合わせ先',
        'amount_note' => '金額備考',
        'deadline_note' => '締切備考'
    );
    ?>
    
    <div class="wrap">
        <h1>AI一括処理</h1>
        <p>複数の助成金投稿に対してAI自動入力を一括実行します。</p>
        
        <!-- 一括投稿作成セクション -->
        <div class="gi-batch-section">
            <h2>📝 一括投稿作成</h2>
            <form method="post" action="">
                <?php wp_nonce_field('gi_bulk_create', 'gi_bulk_create_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="post_template">投稿テンプレート</label>
                        </th>
                        <td>
                            <select id="post_template" name="post_template">
                                <option value="general">汎用助成金</option>
                                <option value="startup">創業・起業支援</option>
                                <option value="equipment">設備投資支援</option>
                                <option value="research">研究開発支援</option>
                                <option value="employment">雇用支援</option>
                                <option value="environment">環境・省エネ支援</option>
                                <option value="regional">地域活性化</option>
                                <option value="digitization">DX・IT導入支援</option>
                            </select>
                            <p class="description">選択したテンプレートに応じて、最適化されたフィールド値が事前設定されます</p>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">
                            <label for="post_count">作成する投稿数</label>
                        </th>
                        <td>
                            <input type="number" 
                                   id="post_count" 
                                   name="post_count" 
                                   value="10" 
                                   min="1" 
                                   max="100" />
                            <p class="description">一度に作成できる投稿数は最大100件です</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="post_title_template">タイトルテンプレート</label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="post_title_template" 
                                   name="post_title_template" 
                                   value="助成金 #{number}" 
                                   class="regular-text" />
                            <p class="description">{number} は連番に置き換えられます（例：助成金 #1, 助成金 #2...）</p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('一括投稿作成', 'secondary', 'create_bulk_posts'); ?>
            </form>
        </div>
        
        <hr>
        
        <!-- 一括設定適用セクション -->
        <div class="gi-batch-section">
            <h2>⚙️ 一括設定適用</h2>
            <form method="post" action="">
                <?php wp_nonce_field('gi_bulk_settings', 'gi_bulk_settings_nonce'); ?>
                
                <table class="form-table">
                    <tr>
                        <th scope="row">基本情報一括設定</th>
                        <td>
                            <fieldset>
                                <p>選択した投稿に共通の基本情報を一括設定します：</p>
                                
                                <label for="bulk_organization">実施組織:</label><br>
                                <input type="text" id="bulk_organization" name="bulk_organization" class="regular-text" 
                                       placeholder="例: 経済産業省" /><br><br>
                                
                                <label for="bulk_max_amount">最大助成額:</label><br>
                                <input type="text" id="bulk_max_amount" name="bulk_max_amount" class="regular-text" 
                                       placeholder="例: 1000万円" /><br><br>
                                
                                <label for="bulk_application_deadline">申請期限:</label><br>
                                <input type="text" id="bulk_application_deadline" name="bulk_application_deadline" class="regular-text" 
                                       placeholder="例: 2025年3月31日" /><br><br>
                                
                                <label for="bulk_grant_url">公式URL:</label><br>
                                <input type="url" id="bulk_grant_url" name="bulk_grant_url" class="regular-text" 
                                       placeholder="https://example.com" />
                            </fieldset>
                        </td>
                    </tr>
                    
                    <tr>
                        <th scope="row">対象投稿選択</th>
                        <td>
                            <div id="bulk-settings-posts" style="max-height: 200px; overflow-y: auto; border: 1px solid #ddd; padding: 10px;">
                                <?php foreach ($grant_posts as $post): ?>
                                    <label>
                                        <input type="checkbox" name="bulk_setting_posts[]" value="<?php echo $post->ID; ?>" />
                                        <?php echo esc_html($post->post_title ?: '（タイトル未設定 - ID: ' . $post->ID . '）'); ?>
                                    </label><br>
                                <?php endforeach; ?>
                            </div>
                            <p>
                                <button type="button" id="select-all-bulk" class="button button-secondary">全選択</button>
                                <button type="button" id="select-none-bulk" class="button button-secondary">全解除</button>
                            </p>
                        </td>
                    </tr>
                </table>
                
                <?php submit_button('選択した投稿に設定を適用', 'secondary', 'apply_bulk_settings'); ?>
            </form>
        </div>
        
        <hr>
        
        <!-- バッチ処理セクション -->
        <div class="gi-batch-section">
            <h2>🤖 AI一括処理</h2>
            
            <?php if (empty($grant_posts)): ?>
                <div class="notice notice-info">
                    <p>処理対象の下書き投稿がありません。まず上記の「一括投稿作成」で投稿を作成してください。</p>
                </div>
            <?php else: ?>
                <form method="post" action="" id="gi-batch-form">
                    <?php wp_nonce_field('gi_batch_processing', 'gi_batch_nonce'); ?>
                    
                    <!-- フィールド選択 -->
                    <h3>処理対象フィールド</h3>
                    <div class="gi-field-selection">
                        <?php foreach ($available_fields as $field_key => $field_name): ?>
                            <label>
                                <input type="checkbox" 
                                       name="selected_fields[]" 
                                       value="<?php echo esc_attr($field_key); ?>"
                                       <?php checked(in_array($field_key, array('post_title', 'post_content', 'ai_summary', 'grant_target'))); ?> />
                                <?php echo esc_html($field_name); ?>
                            </label><br>
                        <?php endforeach; ?>
                    </div>
                    
                    <h3>処理対象投稿（下書きのみ）</h3>
                    <div class="gi-post-selection">
                        <p>
                            <button type="button" id="select-all" class="button">全選択</button>
                            <button type="button" id="select-none" class="button">全解除</button>
                            <span class="description">選択した投稿：<span id="selected-count">0</span>件</span>
                        </p>
                        
                        <div class="gi-posts-list">
                            <?php foreach ($grant_posts as $post): 
                                $current_title = $post->post_title ?: '（タイトル未設定）';
                                $modified_date = get_the_modified_date('Y/m/d H:i', $post);
                            ?>
                                <label class="gi-post-item">
                                    <input type="checkbox" 
                                           name="selected_posts[]" 
                                           value="<?php echo $post->ID; ?>" 
                                           class="gi-post-checkbox" />
                                    <div class="gi-post-info">
                                        <strong><?php echo esc_html($current_title); ?></strong>
                                        <span class="gi-post-meta">
                                            ID: <?php echo $post->ID; ?> | 
                                            更新: <?php echo $modified_date; ?>
                                        </span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <div class="gi-batch-controls">
                        <?php submit_button('選択した投稿にAI処理を実行', 'primary', 'execute_batch'); ?>
                    </div>
                </form>
                
                <!-- 進捗表示エリア -->
                <div id="gi-batch-progress" style="display: none;">
                    <h3>処理進捗</h3>
                    <div class="gi-progress-bar">
                        <div class="gi-progress-fill" style="width: 0%"></div>
                    </div>
                    <p class="gi-progress-text">0 / 0 件処理中...</p>
                    <div class="gi-batch-results"></div>
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
    .gi-batch-section {
        background: #fff;
        padding: 20px;
        margin-bottom: 20px;
        border: 1px solid #ccd0d4;
        border-radius: 4px;
    }
    
    .gi-field-selection label {
        display: inline-block;
        width: 200px;
        margin-right: 20px;
        margin-bottom: 10px;
    }
    
    .gi-posts-list {
        max-height: 400px;
        overflow-y: auto;
        border: 1px solid #ddd;
        padding: 10px;
        background: #fafafa;
    }
    
    .gi-post-item {
        display: block;
        padding: 10px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
    }
    
    .gi-post-item:hover {
        background: #f0f0f0;
    }
    
    .gi-post-info {
        margin-left: 25px;
    }
    
    .gi-post-meta {
        color: #666;
        font-size: 12px;
    }
    
    .gi-progress-bar {
        width: 100%;
        height: 30px;
        background: #f1f1f1;
        border-radius: 15px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .gi-progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #00a32a, #00d084);
        transition: width 0.3s ease;
    }
    
    .gi-batch-controls {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        // 全選択/全解除
        $('#select-all').click(function() {
            $('.gi-post-checkbox').prop('checked', true);
            updateSelectedCount();
        });
        
        $('#select-none').click(function() {
            $('.gi-post-checkbox').prop('checked', false);
            updateSelectedCount();
        });
        
        // 選択数更新
        $('.gi-post-checkbox').change(updateSelectedCount);
        
        function updateSelectedCount() {
            var count = $('.gi-post-checkbox:checked').length;
            $('#selected-count').text(count);
        }
        
        // 初期カウント
        updateSelectedCount();
        
        // 一括設定の全選択/全解除
        $('#select-all-bulk').click(function() {
            $('input[name="bulk_setting_posts[]"]').prop('checked', true);
        });
        
        $('#select-none-bulk').click(function() {
            $('input[name="bulk_setting_posts[]"]').prop('checked', false);
        });
        
        // バッチ処理フォーム送信
        $('#gi-batch-form').submit(function(e) {
            var selectedPosts = $('.gi-post-checkbox:checked').length;
            var selectedFields = $('input[name="selected_fields[]"]:checked').length;
            
            if (selectedPosts === 0) {
                alert('処理対象の投稿を選択してください。');
                e.preventDefault();
                return false;
            }
            
            if (selectedFields === 0) {
                alert('処理対象のフィールドを選択してください。');
                e.preventDefault();
                return false;
            }
            
            if (!confirm(selectedPosts + '件の投稿に対してAI処理を実行します。よろしいですか？\n\n※この処理には時間がかかる場合があります。')) {
                e.preventDefault();
                return false;
            }
        });
    });
    </script>
    <?php
}

/**
 * 一括投稿作成メニューの追加
 */
function gi_add_ai_settings_menu() {
    add_options_page(
        'AI自動入力設定',
        'AI自動入力',
        'manage_options',
        'gi-ai-settings',
        'gi_render_ai_settings_page'
    );
    
    // AI統計ページも追加
    add_management_page(
        'AI使用統計',
        'AI使用統計',
        'manage_options',
        'gi-ai-usage-stats',
        'gi_render_ai_usage_stats_page'
    );
}

/**
 * AI自動入力設定ページのレンダリング
 */
function gi_render_ai_settings_page() {
    // 設定保存処理
    if (isset($_POST['submit']) && wp_verify_nonce($_POST['gi_ai_settings_nonce'], 'gi_ai_settings')) {
        // APIキーの暗号化保存（特別処理）
        if (!empty($_POST['gi_openai_api_key'])) {
            $api_key = sanitize_text_field($_POST['gi_openai_api_key']);
            if (class_exists('GI_AI_API_Handler')) {
                $save_result = GI_AI_API_Handler::save_encrypted_api_key($api_key);
                if (!$save_result) {
                    echo '<div class="notice notice-error"><p>APIキーの保存に失敗しました。</p></div>';
                }
                // 旧形式のAPIキーを削除
                delete_option('gi_openai_api_key');
            }
        }
        
        // その他の設定
        $settings = array(
            'gi_ai_daily_limit' => intval($_POST['gi_ai_daily_limit'] ?? 100),
            'gi_ai_auto_save' => isset($_POST['gi_ai_auto_save']) ? 1 : 0,
            'gi_ai_notification_email' => sanitize_email($_POST['gi_ai_notification_email'] ?? ''),
            'gi_ai_retry_count' => intval($_POST['gi_ai_retry_count'] ?? 3),
            'gi_ai_timeout' => intval($_POST['gi_ai_timeout'] ?? 30),
            'gi_ai_temperature' => floatval($_POST['gi_ai_temperature'] ?? 0.7),
            'gi_ai_max_tokens' => intval($_POST['gi_ai_max_tokens'] ?? 1000),
            'gi_ai_auto_processing_enabled' => isset($_POST['gi_ai_auto_processing_enabled']) ? 1 : 0,
            'gi_ai_auto_publish_enabled' => isset($_POST['gi_ai_auto_publish_enabled']) ? 1 : 0,
            'gi_ai_auto_publish_delay' => intval($_POST['gi_ai_auto_publish_delay'] ?? 7)
        );
        
        foreach ($settings as $key => $value) {
            update_option($key, $value);
        }
        
        echo '<div class="notice notice-success"><p>設定を保存しました。</p></div>';
    }
    
    // API接続テスト
    if (isset($_POST['test_api_connection']) && wp_verify_nonce($_POST['gi_ai_test_nonce'], 'gi_ai_test')) {
        if (class_exists('GI_AI_API_Handler')) {
            $api_handler = new GI_AI_API_Handler();
            $test_result = $api_handler->test_connection();
            
            $notice_class = $test_result['success'] ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . $notice_class . '"><p>' . esc_html($test_result['message']) . '</p></div>';
        } else {
            echo '<div class="notice notice-error"><p>API Handlerクラスが見つかりません。</p></div>';
        }
    }
    
    // 現在の設定値を取得
    $api_key_display = class_exists('GI_AI_API_Handler') ? GI_AI_API_Handler::get_masked_api_key() : '';
    $daily_limit = get_option('gi_ai_daily_limit', 100);
    $auto_save = get_option('gi_ai_auto_save', 0);
    $notification_email = get_option('gi_ai_notification_email', get_option('admin_email'));
    $retry_count = get_option('gi_ai_retry_count', 3);
    $timeout = get_option('gi_ai_timeout', 30);
    $temperature = get_option('gi_ai_temperature', 0.7);
    $max_tokens = get_option('gi_ai_max_tokens', 1000);
    ?>
    
    <div class="wrap">
        <h1>AI自動入力設定</h1>
        <p>ChatGPT APIを使用した助成金情報の自動入力機能の設定を行います。</p>
        
        <form method="post" action="">
            <?php wp_nonce_field('gi_ai_settings', 'gi_ai_settings_nonce'); ?>
            
            <table class="form-table">
                <tr>
                    <th scope="row">
                        <label for="gi_openai_api_key">OpenAI APIキー</label>
                    </th>
                    <td>
                        <input type="password" 
                               id="gi_openai_api_key" 
                               name="gi_openai_api_key" 
                               value="" 
                               class="regular-text" 
                               placeholder="sk-..." />
                        <p class="description">
                            ChatGPT API利用のためのAPIキーを入力してください。
                            <?php if (!empty($api_key_display)): ?>
                                <br><strong>現在設定済み:</strong> <code><?php echo esc_html($api_key_display); ?></code>
                                <br><small>※新しいキーを入力すると上書きされます</small>
                            <?php endif; ?>
                            <br><a href="https://platform.openai.com/api-keys" target="_blank">OpenAI APIキーを取得</a>
                        </p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gi_ai_daily_limit">日次利用上限</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="gi_ai_daily_limit" 
                               name="gi_ai_daily_limit" 
                               value="<?php echo esc_attr($daily_limit); ?>" 
                               min="1" 
                               max="1000" />
                        <p class="description">1日あたりの最大API呼び出し回数（コスト管理用）</p>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">自動保存モード</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" 
                                       name="gi_ai_auto_save" 
                                       value="1" 
                                       <?php checked($auto_save, 1); ?> />
                                生成後に自動で保存する
                            </label>
                            <p class="description">無効の場合は手動確認後に保存</p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gi_ai_notification_email">エラー通知メール</label>
                    </th>
                    <td>
                        <input type="email" 
                               id="gi_ai_notification_email" 
                               name="gi_ai_notification_email" 
                               value="<?php echo esc_attr($notification_email); ?>" 
                               class="regular-text" />
                        <p class="description">エラー発生時の通知先メールアドレス</p>
                    </td>
                </tr>
            </table>
            
            <h3>🔄 スケジューリング設定</h3>
            <table class="form-table">
                <tr>
                    <th scope="row">自動処理モード</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" 
                                       name="gi_ai_auto_processing_enabled" 
                                       value="1" 
                                       <?php checked(get_option('gi_ai_auto_processing_enabled', 0), 1); ?> />
                                下書き投稿への自動AI処理を有効にする
                            </label>
                            <p class="description">作成から24時間経過した下書き投稿に対して、1時間ごとに自動でAI処理を実行します</p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">自動公開モード</th>
                    <td>
                        <fieldset>
                            <label>
                                <input type="checkbox" 
                                       name="gi_ai_auto_publish_enabled" 
                                       value="1" 
                                       <?php checked(get_option('gi_ai_auto_publish_enabled', 0), 1); ?> />
                                AI処理済み投稿の自動公開を有効にする
                            </label>
                            <p class="description">AI処理が完了した投稿を指定日数後に自動で公開します</p>
                        </fieldset>
                    </td>
                </tr>
                
                <tr>
                    <th scope="row">
                        <label for="gi_ai_auto_publish_delay">公開遅延日数</label>
                    </th>
                    <td>
                        <input type="number" 
                               id="gi_ai_auto_publish_delay" 
                               name="gi_ai_auto_publish_delay" 
                               value="<?php echo esc_attr(get_option('gi_ai_auto_publish_delay', 7)); ?>" 
                               min="1" 
                               max="30" />
                        <span>日</span>
                        <p class="description">AI処理完了後、何日後に自動公開するかを指定</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('設定を保存'); ?>
        </form>
        
        <hr>
        
        <h2>API接続テスト</h2>
        <p>設定したAPIキーでOpenAIサービスに接続できるかテストします。</p>
        <form method="post" action="">
            <?php wp_nonce_field('gi_ai_test', 'gi_ai_test_nonce'); ?>
            <p>
                <?php submit_button('接続テスト実行', 'secondary', 'test_api_connection', false); ?>
            </p>
        </form>
        
        <hr>
        
        <h2>使用統計</h2>
        <?php gi_render_ai_usage_summary(); ?>
        <p>
            <a href="<?php echo admin_url('tools.php?page=gi-ai-usage-stats'); ?>" class="button">
                詳細統計を表示
            </a>
        </p>
    </div>
    <?php
}

/**
 * AI使用統計サマリーの表示
 */
function gi_render_ai_usage_summary() {
    $today_usage = function_exists('gi_get_daily_ai_usage') ? gi_get_daily_ai_usage() : 0;
    $daily_limit = get_option('gi_ai_daily_limit', 100);
    $usage_percentage = $daily_limit > 0 ? ($today_usage / $daily_limit) * 100 : 0;
    ?>
    <div style="margin: 16px 0;">
        <h4 style="margin-bottom: 8px;">本日の利用状況</h4>
        <div style="width: 100%; height: 20px; background: #e1e1e1; border-radius: 10px; overflow: hidden; margin-bottom: 8px;">
            <div style="height: 100%; background: linear-gradient(90deg, #00a32a 0%, #ffb900 70%, #d63638 100%); width: <?php echo min($usage_percentage, 100); ?>%; transition: width 0.3s ease;"></div>
        </div>
        <p style="margin: 0; font-size: 13px; color: #666;">
            <?php echo $today_usage; ?> / <?php echo $daily_limit; ?> 回使用 
            (<?php echo round($usage_percentage, 1); ?>%)
        </p>
        <?php if ($usage_percentage > 80): ?>
            <p style="color: #d63638; font-weight: bold; margin: 8px 0 0 0;">
                ⚠️ 利用上限に近づいています
            </p>
        <?php endif; ?>
    </div>
    <?php
}
