<?php
/**
 * Grant Insight Perfect AI自動入力機能 - ChatGPT API通信クラス
 * 
 * @package Grant_Insight_Perfect
 * @version 1.0.0
 * @since 2024.12
 */

// セキュリティチェック
if (!defined('ABSPATH')) {
    exit('Direct access denied.');
}

if (!class_exists('GI_AI_API_Handler')) {
class GI_AI_API_Handler {
    
    private $api_key;
    private $api_endpoint = 'https://api.openai.com/v1/chat/completions';
    private $model = 'gpt-4o-mini';
    private $max_tokens = 1000;
    private $temperature = 0.7;
    private $retry_count = 3;
    private $timeout = 30;
    
    /**
     * コンストラクタ
     */
    public function __construct() {
        $this->api_key = $this->get_decrypted_api_key();
        
        // 設定値の取得と型変換、デフォルト値の保証
        $this->max_tokens = max(1, intval(get_option('gi_ai_max_tokens', 1000)));
        $this->temperature = max(0.0, min(2.0, floatval(get_option('gi_ai_temperature', 0.7))));
        $this->retry_count = max(1, intval(get_option('gi_ai_retry_count', 3)));
        $this->timeout = max(5, intval(get_option('gi_ai_timeout', 30)));
        
        // デフォルト値が設定されていない場合は保存
        $this->init_default_options();
        
        // APIキーの検証
        if (empty($this->api_key)) {
            add_action('admin_notices', array($this, 'api_key_missing_notice'));
        }
    }
    
    /**
     * デフォルトオプションの初期化
     */
    private function init_default_options() {
        $defaults = array(
            'gi_ai_max_tokens' => 1000,
            'gi_ai_temperature' => 0.7,
            'gi_ai_retry_count' => 3,
            'gi_ai_timeout' => 30
            // 'gi_ai_daily_limit' => 100  // 制限機能を無効化
        );
        
        foreach ($defaults as $option_name => $default_value) {
            if (get_option($option_name) === false) {
                update_option($option_name, $default_value);
            }
        }
    }
    
    /**
     * APIキー未設定通知
     */
    public function api_key_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p>
                <strong>AI自動入力機能:</strong> 
                OpenAI APIキーが設定されていません。
                <a href="<?php echo admin_url('options-general.php?page=gi-ai-settings'); ?>">設定画面</a>
                でAPIキーを入力してください。
            </p>
        </div>
        <?php
    }
    
    /**
     * APIキーの暗号化保存
     * 
     * @param string $api_key 暗号化するAPIキー
     * @return bool 保存成功/失敗
     */
    public static function save_encrypted_api_key($api_key) {
        if (empty($api_key)) {
            return delete_option('gi_openai_api_key_encrypted');
        }
        
        $salt = wp_salt('secure_auth');
        $iv = substr(hash('sha256', $salt), 0, 16);
        
        $encrypted = openssl_encrypt(
            $api_key, 
            'AES-256-CBC', 
            $salt, 
            0, 
            $iv
        );
        
        if ($encrypted === false) {
            return false;
        }
        
        return update_option('gi_openai_api_key_encrypted', base64_encode($encrypted));
    }
    
    /**
     * APIキーの復号化取得
     * 
     * @return string|false 復号化されたAPIキーまたはfalse
     */
    private function get_decrypted_api_key() {
        $encrypted = get_option('gi_openai_api_key_encrypted');
        
        if (empty($encrypted)) {
            // 旧形式のAPIキーをチェック（後方互換性）
            return get_option('gi_openai_api_key', '');
        }
        
        $salt = wp_salt('secure_auth');
        $iv = substr(hash('sha256', $salt), 0, 16);
        
        $decrypted = openssl_decrypt(
            base64_decode($encrypted), 
            'AES-256-CBC', 
            $salt, 
            0, 
            $iv
        );
        
        return $decrypted;
    }
    
    /**
     * APIキーの表示用マスク（最後の4文字のみ表示）
     * 
     * @return string マスクされたAPIキー
     */
    public static function get_masked_api_key() {
        $api_key = (new self())->get_decrypted_api_key();
        
        if (empty($api_key)) {
            return '';
        }
        
        if (strlen($api_key) <= 4) {
            return str_repeat('*', strlen($api_key));
        }
        
        return str_repeat('*', strlen($api_key) - 4) . substr($api_key, -4);
    }
    
    /**
     * フィールドコンテンツの生成
     * 
     * @param array $post_data 投稿データ
     * @param string $target_field 対象フィールド名
     * @return array 生成結果
     */
    public function generate_field_content($post_data, $target_field) {
        error_log('AI API Handler: generate_field_content called for field: ' . $target_field);
        
        if (empty($this->api_key)) {
            error_log('AI API Handler: API key is empty');
            return array(
                'success' => false,
                'error' => 'APIキーが設定されていません',
                'tokens_used' => 0
            );
        }
        
        try {
            error_log('AI API Handler: Building prompt for ' . $target_field);
            $prompt = $this->build_prompt($post_data, $target_field);
            
            if (!$prompt) {
                error_log('AI API Handler: Failed to build prompt');
                return array(
                    'success' => false,
                    'error' => 'プロンプトの生成に失敗しました',
                    'tokens_used' => 0
                );
            }
            
            error_log('AI API Handler: Making API request for ' . $target_field);
            $api_response = $this->make_api_request($prompt);
            
            if ($api_response['success']) {
                error_log('AI API Handler: API request successful for ' . $target_field);
                $content = $this->extract_content_from_response($api_response['data'], $target_field);
                
                return array(
                    'success' => true,
                    'content' => $content,
                    'tokens_used' => $api_response['tokens_used'],
                    'response_time' => $api_response['response_time']
                );
            } else {
                error_log('AI API Handler: API request failed for ' . $target_field . ': ' . $api_response['error']);
                return array(
                    'success' => false,
                    'error' => $api_response['error'],
                    'tokens_used' => 0
                );
            }
            
        } catch (Exception $e) {
            error_log('GI AI API Handler Error: ' . $e->getMessage() . ' at ' . $e->getFile() . ':' . $e->getLine());
            
            return array(
                'success' => false,
                'error' => '予期しないエラーが発生しました: ' . $e->getMessage(),
                'tokens_used' => 0
            );
        }
    }
    
    /**
     * プロンプトの構築
     * 
     * @param array $post_data 投稿データ
     * @param string $target_field 対象フィールド名
     * @return string|false プロンプト文字列
     */
    private function build_prompt($post_data, $target_field) {
        error_log('AI API Handler: build_prompt called for field: ' . $target_field);
        
        $system_prompt = $this->get_system_prompt();
        $field_prompt = $this->get_field_specific_prompt($target_field, $post_data);
        
        if (!$field_prompt) {
            error_log('AI API Handler: Failed to get field specific prompt for ' . $target_field);
            return false;
        }
        
        error_log('AI API Handler: Prompt built successfully for ' . $target_field);
        
        return array(
            'system' => $system_prompt,
            'user' => $field_prompt
        );
    }
    
    /**
     * システムプロンプトの取得（SEO最適化・コンテキスト対応版）
     * 
     * @return string システムプロンプト
     */
    private function get_system_prompt() {
        return "あなたは助成金・補助金の専門知識とSEOライティングスキルを持つAIアシスタントです。

以下の要件に従って、助成金情報の各フィールドを適切に生成してください：

【基本方針】
- 正確で実用的な情報を提供する
- 日本の助成金制度の一般的な傾向に基づく
- 自然で読みやすい日本語を使用する
- 文字数制限を必ず遵守する
- 不自然な文字や記号の連続使用は避ける

【SEO最適化要件】
- 検索されやすいキーワードを自然に含める（「助成金」「補助金」「支援制度」等）
- 読者の検索意図に合致した具体的な情報を提供する
- タイトルや見出しにはキーワードを効果的に配置する
- ユーザーが求める実用的な情報を優先する
- 地域名、業種名、金額などの具体的な検索キーワードを適切に使用する

【コンテキスト活用指針】
- 既存の入力済み情報がある場合は、その内容と一貫性を保つ
- 他のフィールドの情報との整合性を重視する
- 既存コンテンツの質を向上させる形で新しい内容を生成する
- 重複する情報は避け、相互補完的な内容にする
- 全体として統一感のある助成金情報を構築する

【文章品質基準】
- 「令和○年度」「平成○年度」などの年号は使用しない
- 「、、、」「。。。」などの記号の連続は使用しない
- 適切な敬語と丁寧語を使用する
- 読者にとって分かりやすい表現を心がける
- 具体的で実用的な内容にする
- SEOに効果的な自然なキーワード使用を心がける

【出力形式】
- 各フィールドの内容のみを出力
- 余計な説明や前置きは一切不要
- 指定された文字数制限を厳格に遵守
- HTMLタグは必要最小限に留める

【避けるべき表現】
- 年号の記載（令和、平成など）
- 記号の連続使用（、、、　。。。　…… など）
- 過度に硬い官僚的表現
- 曖昧で具体性に欠ける表現
- キーワードの不自然な詰め込み
- 既存コンテンツとの矛盾や重複";
    }
    
    /**
     * フィールド別プロンプトの取得
     * 
     * @param string $field_name フィールド名
     * @param array $post_data 投稿データ
     * @return string|false フィールド別プロンプト
     */
    private function get_field_specific_prompt($field_name, $post_data) {
        $base_info = $this->format_base_info($post_data);
        
        switch ($field_name) {
            case 'ai_summary':
                return $this->get_summary_prompt($base_info);
                
            case 'grant_target':
                return $this->get_target_prompt($base_info);
                
            case 'eligible_expenses':
                return $this->get_expenses_prompt($base_info);
                
            case 'grant_difficulty':
                return $this->get_difficulty_prompt($base_info);
                
            case 'required_documents':
                return $this->get_documents_prompt($base_info);
                
            case 'application_method':
                return $this->get_method_prompt($base_info);
                
            case 'contact_info':
                return $this->get_contact_prompt($base_info);
                
            case 'amount_note':
                return $this->get_amount_note_prompt($base_info);
                
            case 'deadline_note':
                return $this->get_deadline_note_prompt($base_info);
                
            case 'post_title':
                return $this->get_title_prompt($base_info);
                
            case 'post_content':
                return $this->get_content_prompt($base_info);
                
            default:
                return false;
        }
    }
    
    /**
     * 基本情報のフォーマット（コンテキスト拡張版）
     * 既存フィールドの内容をコンテキストとして活用し、より質の高いAI生成を実現
     */
    private function format_base_info($post_data) {
        $info = array();
        
        if (!empty($post_data['title'])) {
            $info[] = "助成金名: " . $post_data['title'];
        }
        
        if (!empty($post_data['organization'])) {
            $info[] = "実施組織: " . $post_data['organization'];
        }
        
        if (!empty($post_data['max_amount'])) {
            $info[] = "最大助成額: " . $post_data['max_amount'];
        }
        
        if (!empty($post_data['min_amount'])) {
            $info[] = "最小助成額: " . $post_data['min_amount'];
        }
        
        if (!empty($post_data['target_business_type'])) {
            $info[] = "対象事業: " . $post_data['target_business_type'];
        }
        
        if (!empty($post_data['target_region'])) {
            $info[] = "対象地域: " . $post_data['target_region'];
        }
        
        if (!empty($post_data['official_url'])) {
            $info[] = "公式URL: " . $post_data['official_url'];
        }
        
        if (!empty($post_data['content'])) {
            $content_summary = mb_substr(strip_tags($post_data['content']), 0, 200);
            $info[] = "詳細: " . $content_summary . (mb_strlen($post_data['content']) > 200 ? '...' : '');
        }
        
        // 【新機能】既存のAI生成コンテンツをコンテキストとして追加
        if (!empty($post_data['existing_ai_content'])) {
            $info[] = "\n=== 既存の入力済み情報（コンテキスト参照用） ===";
            
            foreach ($post_data['existing_ai_content'] as $field_name => $field_data) {
                $label = $field_data['label'];
                $value = $field_data['value'];
                
                // 長すぎるHTMLコンテンツは要約
                if (strlen($value) > 300) {
                    $clean_value = strip_tags($value);
                    if (strlen($clean_value) > 300) {
                        $clean_value = mb_substr($clean_value, 0, 300, 'UTF-8') . '...';
                    }
                    $value = $clean_value;
                } else {
                    $value = strip_tags($value);
                }
                
                $info[] = "{$label}: {$value}";
            }
            
            $info[] = "=== 上記既存情報を参考に、一貫性のある内容で生成してください ===\n";
        }
        
        return implode("\n", $info);
    }
    
    /**
     * 概要プロンプト（SEO・コンテキスト対応版）
     */
    private function get_summary_prompt($base_info) {
        return "以下の助成金について、SEOを考慮した200文字以内の要約を作成してください：

{$base_info}

【要求事項】
- 助成金の目的・特徴を明確に記載
- 主な対象者を具体的に記述
- 支援内容の概要を分かりやすく説明
- 特に魅力的なポイントを強調
- 「助成金」「補助金」「支援制度」等の検索キーワードを自然に含める
- 既存の入力済み情報がある場合は、その内容と整合性を保つ
- ユーザーの検索意図（資金調達、事業支援等）に合致した表現を使用

【SEO配慮】
- 検索されやすい具体的な業種名・地域名・金額を含める
- 読者が求める実用的な情報を優先
- 自然で読みやすい文章構成

200文字以内で、SEO効果の高い魅力的な要約を作成してください。";
    }
    
    /**
     * 対象者プロンプト（SEO・コンテキスト対応版）
     */
    private function get_target_prompt($base_info) {
        return "以下の助成金について、対象者・対象事業をSEOを意識したHTML形式で構造化してください：

{$base_info}

【構造化要件】
- <ul><li>形式でリスト化
- 事業規模、業種、地域などの条件を明確に記載
- 既存の入力済み情報がある場合は、その内容との整合性を重視
- 検索キーワードとなる具体的な業種名・企業規模・地域名を含める
- 1000文字以内

【SEO配慮】
- 「中小企業」「スタートアップ」「個人事業主」等の検索されやすいキーワードを使用
- 具体的な業種名（製造業、IT関連業、飲食業等）を自然に含める
- 地域限定の場合は具体的な自治体名を記載
- ユーザーが「自分が対象か」を判断しやすい具体的な条件を提示

【コンテキスト活用】
- 他フィールドの既存情報と矛盾しない内容
- 助成金の特性に合致した対象者設定
- より詳細で実用的な情報を提供

例：
<ul>
<li><strong>対象者：</strong>中小企業者・個人事業主</li>
<li><strong>業種：</strong>製造業、情報通信業、サービス業等</li>
<li><strong>地域：</strong>全国（一部地域限定の場合は具体的に記載）</li>
<li><strong>規模：</strong>従業員数○名以下、年売上高○億円以下</li>
</ul>";
    }
    
    /**
     * 対象経費プロンプト
     */
    private function get_expenses_prompt($base_info) {
        return "以下の助成金について、対象経費をHTML形式でリスト化してください：

{$base_info}

以下の点に注意して作成してください：
- <ul><li>形式でリスト化
- 一般的な助成金で対象となる経費を参考
- 具体的で分かりやすい項目名
- 800文字以内

一般的な対象経費例：
- 設備投資費
- 人件費
- 外注費
- 研修費
- 広告宣伝費
等を参考に、この助成金に適した項目を生成してください。";
    }
    
    /**
     * 申請難易度プロンプト
     */
    private function get_difficulty_prompt($base_info) {
        return "以下の助成金について、申請難易度を4段階で評価してください：

{$base_info}

以下の基準で判定し、該当する値のみを出力してください：
- easy: 申請書類が少なく、要件が明確
- normal: 一般的な申請書類と要件
- hard: 詳細な事業計画や実績が必要
- expert: 専門的な知識や複雑な手続きが必要

回答は「easy」「normal」「hard」「expert」のいずれか一つの値のみを出力してください。";
    }
    
    /**
     * 必要書類プロンプト
     */
    private function get_documents_prompt($base_info) {
        return "以下の助成金について、必要書類をHTML形式でリスト化してください：

{$base_info}

以下の点に注意して作成してください：
- <ul><li>形式でリスト化
- 一般的な助成金申請で必要な書類を参考
- 実在する書類名を使用
- 600文字以内

一般的な必要書類例：
- 申請書
- 事業計画書
- 決算書（直近2期分）
- 登記簿謄本
- 見積書
等を参考に、この助成金に適した書類リストを生成してください。";
    }
    
    /**
     * 申請方法プロンプト
     */
    private function get_method_prompt($base_info) {
        return "以下の助成金について、申請方法を判定してください：

{$base_info}

以下の選択肢から最も適切なものを一つ選んで、その値のみを出力してください：
- online: オンライン申請システムでの申請
- mail: 郵送による申請
- visit: 窓口への持参申請
- mixed: 複数の方法が利用可能

回答は「online」「mail」「visit」「mixed」のいずれか一つの値のみを出力してください。";
    }
    
    /**
     * 問い合わせ先プロンプト
     */
    private function get_contact_prompt($base_info) {
        return "以下の助成金について、問い合わせ先情報を推測して記載してください：

{$base_info}

以下の点に注意して作成してください：
- 実施組織に基づいた推測情報
- 一般的な問い合わせ先の形式
- 実在性は問わない（推測ベース）
- 400文字以内

例：
○○課 助成金担当
電話：03-0000-0000
メール：info@example.go.jp
受付時間：平日9:00-17:00";
    }
    
    /**
     * 金額備考プロンプト
     */
    private function get_amount_note_prompt($base_info) {
        return "以下の助成金について、金額に関する補足説明を500文字以内で作成してください：

{$base_info}

以下の点を含めて説明してください：
- 助成率や上限額の詳細
- 対象経費による金額の違い
- 申請時期による変動の可能性
- その他金額に関する重要な注意事項

500文字以内で、分かりやすい日本語で記述してください。";
    }
    
    /**
     * 締切備考プロンプト
     */
    private function get_deadline_note_prompt($base_info) {
        return "以下の助成金について、締切に関する補足情報を300文字以内で作成してください：

{$base_info}

以下の点を含めて説明してください：
- 申請期間の詳細
- 複数回の募集がある場合の情報
- 締切に関する注意事項
- 申請前の準備期間の目安

300文字以内で、分かりやすい日本語で記述してください。";
    }
    
    /**
     * タイトルプロンプト（SEO・コンテキスト対応版）
     */
    private function get_title_prompt($base_info) {
        return "以下の助成金について、SEOを意識した魅力的なタイトルを40文字以内で作成してください：

{$base_info}

【要求事項】
- 助成金の特徴や目的を明確に表現
- 「助成金」「補助金」等のキーワードを自然に含める
- 対象者や金額などの具体的情報を含める
- 検索されやすく、クリックしたくなるタイトル
- 既存情報がある場合は整合性を保つ

【SEO配慮】
- 具体的な業種名・地域名・金額を含める
- ユーザーが検索しそうなフレーズを使用
- 読みやすく、理解しやすい表現

40文字以内で、検索ランキングとクリック率を向上させるタイトルを作成してください。";
    }
    
    /**
     * 本文プロンプト（SEO・コンテキスト対応版）
     */
    private function get_content_prompt($base_info) {
        return "以下の助成金について、SEOを意識した詳細な本文コンテンツをHTML形式で1,200文字以内で作成してください：

{$base_info}

【構造化要件】
- <h2>、<h3>タグで適切な見出し構造を作る
- <p>タグで段落を明確に分ける
- <ul>、<ol>、<li>タグで情報をリスト化
- <strong>、<em>タグで重要ポイントを強調

【コンテンツ要件】
- 助成金の概要と目的の詳細説明
- 対象者・対象事業の具体的な条件
- 助成金額や率の詳細
- 申請手順や必要書類の案内
- 注意事項やポイント
- 問い合わせ先情報

【SEO配慮】
- 「助成金」「補助金」「支援制度」等のキーワードを自然に配置
- 具体的な業種名、地域名、金額を含める
- ユーザーの検索意図に合致した実用的情報
- 見出しにキーワードを効果的に配置

【コンテキスト活用】
- 既存の入力済み情報がある場合は整合性を保つ
- 他フィールドの情報と矛盾しない内容
- 全体として統一感のある情報提供

1,200文字以内で、ユーザーにとって有用でSEO効果の高い本文コンテンツを作成してください。";
    }
    
    /**
     * API リクエストの実行
     * 
     * @param array $prompt プロンプト配列
     * @return array API レスポンス
     */
    private function make_api_request($prompt) {
        $start_time = microtime(true);
        
        $request_body = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $prompt['system']
                ),
                array(
                    'role' => 'user',
                    'content' => $prompt['user']
                )
            ),
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        );
        
        $headers = array(
            'Authorization' => 'Bearer ' . $this->api_key,
            'Content-Type' => 'application/json',
            'User-Agent' => 'Grant-Insight-Perfect-AI/1.0'
        );
        
        $args = array(
            'method' => 'POST',
            'headers' => $headers,
            'body' => json_encode($request_body),
            'timeout' => $this->timeout,
            'sslverify' => true
        );
        
        // リトライ機能付きでリクエスト実行
        $last_error = '';
        
        for ($attempt = 1; $attempt <= $this->retry_count; $attempt++) {
            $response = wp_remote_request($this->api_endpoint, $args);
            
            if (is_wp_error($response)) {
                $last_error = $response->get_error_message();
                
                // 最後の試行でなければ待機
                if ($attempt < $this->retry_count) {
                    sleep($attempt); // 指数バックオフ
                }
                continue;
            }
            
            $response_code = wp_remote_retrieve_response_code($response);
            $response_body = wp_remote_retrieve_body($response);
            
            // レスポンスの検証
            $parsed_response = $this->parse_api_response($response_code, $response_body);
            
            if ($parsed_response['success']) {
                $response_time = microtime(true) - $start_time;
                $parsed_response['response_time'] = $response_time;
                return $parsed_response;
            } else {
                $last_error = $parsed_response['error'];
                
                // レート制限の場合は長めに待機
                if ($response_code === 429) {
                    sleep(min(60, $attempt * 10));
                } elseif ($attempt < $this->retry_count) {
                    sleep($attempt);
                }
            }
        }
        
        return array(
            'success' => false,
            'error' => "API呼び出しに失敗しました (試行回数: {$this->retry_count}): " . $last_error,
            'tokens_used' => 0,
            'debug_info' => array(
                'retry_count' => $this->retry_count,
                'api_key_set' => !empty($this->api_key),
                'api_endpoint' => $this->api_endpoint,
                'last_error' => $last_error
            )
        );
    }
    
    /**
     * API レスポンスの解析
     * 
     * @param int $response_code HTTP レスポンスコード
     * @param string $response_body レスポンスボディ
     * @return array 解析結果
     */
    private function parse_api_response($response_code, $response_body) {
        if ($response_code !== 200) {
            $error_message = $this->get_error_message_by_code($response_code);
            
            // レスポンスボディからエラー詳細を取得
            if (!empty($response_body)) {
                $body_data = json_decode($response_body, true);
                if (isset($body_data['error']['message'])) {
                    $error_message .= ': ' . $body_data['error']['message'];
                }
            }
            
            return array(
                'success' => false,
                'error' => $error_message,
                'tokens_used' => 0
            );
        }
        
        $data = json_decode($response_body, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return array(
                'success' => false,
                'error' => 'APIレスポンスのJSONパースに失敗しました',
                'tokens_used' => 0
            );
        }
        
        if (!isset($data['choices']) || empty($data['choices'])) {
            return array(
                'success' => false,
                'error' => 'APIレスポンスに有効なコンテンツが含まれていません',
                'tokens_used' => 0
            );
        }
        
        $tokens_used = isset($data['usage']['total_tokens']) ? $data['usage']['total_tokens'] : 0;
        
        return array(
            'success' => true,
            'data' => $data,
            'tokens_used' => $tokens_used
        );
    }
    
    /**
     * エラーコード別メッセージの取得
     */
    private function get_error_message_by_code($code) {
        switch ($code) {
            case 400:
                return 'リクエストが無効です';
            case 401:
                return 'APIキーが無効です';
            case 403:
                return 'アクセスが拒否されました';
            case 404:
                return 'APIエンドポイントが見つかりません';
            case 429:
                return 'レート制限に達しました。しばらく待ってから再試行してください';
            case 500:
                return 'OpenAIサーバーでエラーが発生しました';
            case 502:
                return 'OpenAIサーバーが一時的に利用できません';
            case 503:
                return 'OpenAIサービスが一時的に利用できません';
            default:
                return "不明なエラーが発生しました (HTTP {$code})";
        }
    }
    
    /**
     * レスポンスからコンテンツを抽出
     * 
     * @param array $api_data APIレスポンスデータ
     * @param string $field_name フィールド名
     * @return string 抽出されたコンテンツ
     */
    private function extract_content_from_response($api_data, $field_name) {
        if (!isset($api_data['choices'][0]['message']['content'])) {
            return '';
        }
        
        $content = trim($api_data['choices'][0]['message']['content']);
        
        // フィールド別の後処理
        $content = $this->post_process_content($content, $field_name);
        
        // フィルターフック
        $content = apply_filters('gi_ai_generated_content', $content, $field_name, $api_data);
        $content = apply_filters("gi_ai_generated_content_{$field_name}", $content, $api_data);
        
        return $content;
    }
    
    /**
     * コンテンツの後処理
     * 
     * @param string $content 生成コンテンツ
     * @param string $field_name フィールド名
     * @return string 処理後のコンテンツ
     */
    private function post_process_content($content, $field_name) {
        // 基本的なクリーニング処理
        $content = $this->clean_generated_content($content, $field_name);
        
        // 選択肢フィールドの値検証
        if ($field_name === 'grant_difficulty') {
            $allowed_values = array('easy', 'normal', 'hard', 'expert');
            if (!in_array(trim($content), $allowed_values)) {
                $content = 'normal'; // デフォルト値
            }
        }
        
        if ($field_name === 'application_method') {
            $allowed_values = array('online', 'mail', 'visit', 'mixed');
            if (!in_array(trim($content), $allowed_values)) {
                $content = 'mixed'; // デフォルト値
            }
        }
        
        // HTMLフィールドのサニタイズ
        $html_fields = array('grant_target', 'eligible_expenses', 'required_documents', 'post_content');
        if (in_array($field_name, $html_fields)) {
            $content = $this->sanitize_html_content($content);
        }
        
        // 文字数制限の適用
        $content = $this->apply_length_limit($content, $field_name);
        
        return $content;
    }
    
    /**
     * 生成コンテンツのクリーニング
     * 
     * @param string $content 生成されたコンテンツ
     * @param string $field_name フィールド名
     * @return string クリーニング後のコンテンツ
     */
    private function clean_generated_content($content, $field_name) {
        // 基本的なトリミング
        $content = trim($content);
        
        // 不自然な文字パターンの除去
        $problematic_patterns = array(
            '/令和\d+年度?/',           // 令和○年度
            '/平成\d+年度?/',           // 平成○年度  
            '/、{2,}/',                 // 、、、などの連続
            '/。{2,}/',                 // 。。。などの連続
            '/…{2,}/',                  // ……などの連続
            '/・{3,}/',                 // ・・・などの連続
            '/\s{3,}/',                 // 空白の連続（3個以上）
            '/\n{3,}/',                 // 改行の連続（3個以上）
        );
        
        $replacements = array(
            '',                         // 年号削除
            '',                         // 年号削除
            '、',                       // 単一の読点に
            '。',                       // 単一の句点に
            '…',                        // 単一の三点リーダーに
            '・',                       // 単一の中黒に
            ' ',                        // 単一の空白に
            "\n\n",                     // 改行は最大2個まで
        );
        
        $content = preg_replace($problematic_patterns, $replacements, $content);
        
        // タイトルフィールドの特別処理
        if ($field_name === 'post_title') {
            $content = $this->clean_title_content($content);
        }
        
        // 前後の引用符や括弧の除去
        $content = trim($content, '"\'「」『』()（）【】');
        
        return $content;
    }
    
    /**
     * タイトル専用のクリーニング
     * 
     * @param string $title タイトル
     * @return string クリーニング後のタイトル
     */
    private function clean_title_content($title) {
        // 不要な前置詞や接続詞の除去
        $unwanted_phrases = array(
            'について',
            'に関して',
            'のご案内',
            '【新着】',
            '【重要】',
            '【お知らせ】',
        );
        
        foreach ($unwanted_phrases as $phrase) {
            $title = str_replace($phrase, '', $title);
        }
        
        // 末尾の不自然な文字の除去
        $title = rtrim($title, '、。：:');
        
        // 文字数が極端に短い場合のデフォルト処理
        if (mb_strlen($title, 'UTF-8') < 8) {
            $title = '助成金制度のご案内';
        }
        
        return trim($title);
    }
    
    /**
     * HTMLコンテンツのサニタイズ
     * 
     * @param string $content HTMLコンテンツ
     * @return string サニタイズ後のコンテンツ
     */
    private function sanitize_html_content($content) {
        $allowed_tags = array(
            'p' => array(),
            'ul' => array(),
            'ol' => array(),
            'li' => array(),
            'strong' => array(),
            'em' => array(),
            'br' => array(),
            'span' => array()
        );
        
        return wp_kses($content, $allowed_tags);
    }
    
    /**
     * 文字数制限の適用
     * 
     * @param string $content コンテンツ
     * @param string $field_name フィールド名
     * @return string 制限後のコンテンツ
     */
    private function apply_length_limit($content, $field_name) {
        $limits = array(
            'ai_summary' => 200,
            'grant_target' => 1000,
            'eligible_expenses' => 800,
            'required_documents' => 600,
            'contact_info' => 400,
            'amount_note' => 500,
            'deadline_note' => 300
        );
        
        if (!isset($limits[$field_name])) {
            return $content;
        }
        
        $limit = $limits[$field_name];
        $content_length = mb_strlen(strip_tags($content), 'UTF-8');
        
        if ($content_length > $limit) {
            // HTML付きコンテンツの場合は慎重に切り詰める
            if (strpos($content, '<') !== false) {
                $content = $this->trim_html_content($content, $limit);
            } else {
                $content = mb_substr($content, 0, $limit, 'UTF-8') . '...';
            }
        }
        
        return $content;
    }
    
    /**
     * HTMLコンテンツの切り詰め
     * 
     * @param string $html_content HTMLコンテンツ
     * @param int $limit 文字数制限
     * @return string 切り詰め後のコンテンツ
     */
    private function trim_html_content($html_content, $limit) {
        $plain_text = strip_tags($html_content);
        
        if (mb_strlen($plain_text, 'UTF-8') <= $limit) {
            return $html_content;
        }
        
        // 簡単な切り詰め処理
        $truncated = mb_substr($plain_text, 0, $limit - 3, 'UTF-8') . '...';
        
        // HTMLタグを保持した切り詰めは複雑なので、
        // 必要に応じて専用ライブラリの使用を検討
        return '<p>' . esc_html($truncated) . '</p>';
    }
    
    /**
     * API接続テスト
     * 
     * @return array テスト結果
     */
    public function test_connection() {
        // 詳細な診断情報を含む
        $diagnostics = array(
            'api_key_length' => strlen($this->api_key ?? ''),
            'api_key_format' => !empty($this->api_key) && strpos($this->api_key, 'sk-') === 0,
            'retry_count' => $this->retry_count,
            'timeout' => $this->timeout,
            'endpoint' => $this->api_endpoint
        );
        
        if (empty($this->api_key)) {
            return array(
                'success' => false,
                'message' => 'APIキーが設定されていません。設定画面でOpenAI APIキーを入力してください。',
                'diagnostics' => $diagnostics
            );
        }
        
        if (strlen($this->api_key) < 20 || strpos($this->api_key, 'sk-') !== 0) {
            return array(
                'success' => false,
                'message' => 'APIキーの形式が正しくありません。OpenAI APIキーは "sk-" で始まる必要があります。',
                'diagnostics' => $diagnostics
            );
        }
        
        $test_prompt = array(
            'system' => 'あなたはテスト用のAIです。',
            'user' => 'テスト用のメッセージです。「接続成功」と回答してください。'
        );
        
        $result = $this->make_api_request($test_prompt);
        
        if ($result['success']) {
            return array(
                'success' => true,
                'message' => 'API接続テストが成功しました。使用トークン数: ' . $result['tokens_used'],
                'diagnostics' => $diagnostics
            );
        } else {
            return array(
                'success' => false,
                'message' => 'API接続テストが失敗しました: ' . $result['error'],
                'diagnostics' => array_merge($diagnostics, $result['debug_info'] ?? array())
            );
        }
    }
    
    /**
     * 使用統計の取得
     * 
     * @return array 統計情報
     */
    public function get_usage_statistics() {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        
        // 今日の統計
        $today = current_time('Y-m-d');
        $today_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(tokens_used) as total_tokens,
                AVG(processing_time) as avg_processing_time,
                SUM(success) as successful_requests
             FROM $usage_table 
             WHERE DATE(timestamp) = %s",
            $today
        ));
        
        // 今月の統計
        $this_month = current_time('Y-m');
        $monthly_stats = $wpdb->get_row($wpdb->prepare(
            "SELECT 
                COUNT(*) as total_requests,
                SUM(tokens_used) as total_tokens,
                AVG(processing_time) as avg_processing_time,
                SUM(success) as successful_requests
             FROM $usage_table 
             WHERE DATE_FORMAT(timestamp, '%%Y-%%m') = %s",
            $this_month
        ));
        
        return array(
            'today' => array(
                'requests' => intval($today_stats->total_requests ?? 0),
                'tokens' => intval($today_stats->total_tokens ?? 0),
                'success_rate' => $today_stats->total_requests > 0 
                    ? round(($today_stats->successful_requests / $today_stats->total_requests) * 100, 1) 
                    : 0,
                'avg_processing_time' => round($today_stats->avg_processing_time ?? 0, 2)
            ),
            'monthly' => array(
                'requests' => intval($monthly_stats->total_requests ?? 0),
                'tokens' => intval($monthly_stats->total_tokens ?? 0),
                'success_rate' => $monthly_stats->total_requests > 0 
                    ? round(($monthly_stats->successful_requests / $monthly_stats->total_requests) * 100, 1) 
                    : 0,
                'avg_processing_time' => round($monthly_stats->avg_processing_time ?? 0, 2)
            )
        );
    }
    
    /**
     * モデルパラメータの更新
     * 
     * @param array $params パラメータ配列
     */
    public function update_model_parameters($params) {
        if (isset($params['model'])) {
            $this->model = $params['model'];
        }
        
        if (isset($params['max_tokens'])) {
            $this->max_tokens = intval($params['max_tokens']);
        }
        
        if (isset($params['temperature'])) {
            $this->temperature = floatval($params['temperature']);
        }
        
        if (isset($params['timeout'])) {
            $this->timeout = intval($params['timeout']);
        }
        
        if (isset($params['retry_count'])) {
            $this->retry_count = intval($params['retry_count']);
        }
    }
    
    /**
     * カスタムプロンプトの登録
     * 
     * @param string $field_name フィールド名
     * @param callable $prompt_callback プロンプト生成コールバック
     */
    public function register_custom_prompt($field_name, $prompt_callback) {
        add_filter("gi_ai_prompt_{$field_name}", $prompt_callback, 10, 2);
    }

        /**
     * バッチ処理用の並列リクエスト
     * 
     * @param array $requests リクエスト配列
     * @return array レスポンス配列
     */
    public function batch_generate_content($requests) {
        if (empty($requests) || !is_array($requests)) {
            return array();
        }
        
        $results = array();
        $concurrent_limit = 3; // 同時実行制限
        $chunks = array_chunk($requests, $concurrent_limit, true);
        
        foreach ($chunks as $chunk) {
            $chunk_results = $this->process_concurrent_requests($chunk);
            $results = array_merge($results, $chunk_results);
            
            // バッチ間の待機時間
            if (count($chunks) > 1) {
                sleep(2);
            }
        }
        
        return $results;
    }
    
    /**
     * 同時リクエストの処理
     * 
     * @param array $requests リクエスト配列
     * @return array レスポンス配列
     */
    private function process_concurrent_requests($requests) {
        $multi_handle = curl_multi_init();
        $curl_handles = array();
        $results = array();
        
        // cURLハンドルの準備
        foreach ($requests as $key => $request) {
            $curl_handle = $this->prepare_curl_handle($request);
            if ($curl_handle) {
                curl_multi_add_handle($multi_handle, $curl_handle);
                $curl_handles[$key] = $curl_handle;
            }
        }
        
        // 並列実行
        $running = null;
        do {
            curl_multi_exec($multi_handle, $running);
            curl_multi_select($multi_handle);
        } while ($running > 0);
        
        // レスポンスの取得
        foreach ($curl_handles as $key => $curl_handle) {
            $response = curl_multi_getcontent($curl_handle);
            $http_code = curl_getinfo($curl_handle, CURLINFO_HTTP_CODE);
            
            $results[$key] = $this->parse_api_response($http_code, $response);
            
            curl_multi_remove_handle($multi_handle, $curl_handle);
            curl_close($curl_handle);
        }
        
        curl_multi_close($multi_handle);
        
        return $results;
    }
    
    /**
     * cURLハンドルの準備
     * 
     * @param array $request リクエストデータ
     * @return resource|false cURLハンドル
     */
    private function prepare_curl_handle($request) {
        if (!isset($request['prompt']) || !$request['prompt']) {
            return false;
        }
        
        $request_body = array(
            'model' => $this->model,
            'messages' => array(
                array(
                    'role' => 'system',
                    'content' => $request['prompt']['system']
                ),
                array(
                    'role' => 'user',
                    'content' => $request['prompt']['user']
                )
            ),
            'max_tokens' => $this->max_tokens,
            'temperature' => $this->temperature,
            'top_p' => 1.0,
            'frequency_penalty' => 0.0,
            'presence_penalty' => 0.0
        );
        
        $curl_handle = curl_init();
        
        curl_setopt_array($curl_handle, array(
            CURLOPT_URL => $this->api_endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($request_body),
            CURLOPT_HTTPHEADER => array(
                'Authorization: Bearer ' . $this->api_key,
                'Content-Type: application/json',
                'User-Agent: Grant-Insight-Perfect-AI/1.0'
            ),
            CURLOPT_TIMEOUT => $this->timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2
        ));
        
        return $curl_handle;
    }
    
    /**
     * APIキーの検証
     * 
     * @param string $api_key 検証するAPIキー
     * @return array 検証結果
     */
    public function validate_api_key($api_key) {
        $temp_api_key = $this->api_key;
        $this->api_key = $api_key;
        
        $result = $this->test_connection();
        
        $this->api_key = $temp_api_key;
        
        return $result;
    }
    
    /**
     * レート制限の監視
     * 
     * @return array レート制限情報
     */
    public function get_rate_limit_status() {
        global $wpdb;
        
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        $current_hour = current_time('Y-m-d H:00:00');
        
        // 過去1時間のリクエスト数
        $hourly_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table 
             WHERE timestamp >= %s AND success = 1",
            $current_hour
        ));
        
        // 過去1分間のリクエスト数
        $current_minute = current_time('Y-m-d H:i:00');
        $minute_requests = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $usage_table 
             WHERE timestamp >= %s AND success = 1",
            $current_minute
        ));
        
        // OpenAI の標準制限（推定値）
        $limits = array(
            'requests_per_minute' => 3500,
            'requests_per_hour' => 10000,
            'tokens_per_minute' => 200000
        );
        
        return array(
            'current_hour_requests' => intval($hourly_requests),
            'current_minute_requests' => intval($minute_requests),
            'limits' => $limits,
            'warning_threshold' => array(
                'hour' => $limits['requests_per_hour'] * 0.8,
                'minute' => $limits['requests_per_minute'] * 0.8
            )
        );
    }
    
    /**
     * エラーログの記録
     * 
     * @param string $error_message エラーメッセージ
     * @param array $context コンテキスト情報
     */
    private function log_error($error_message, $context = array()) {
        $log_entry = array(
            'timestamp' => current_time('c'),
            'error' => $error_message,
            'context' => $context,
            'user_id' => get_current_user_id(),
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? ''
        );
        
        error_log('GI AI API Error: ' . json_encode($log_entry));
        
        // 管理者通知
        if (get_option('gi_ai_error_notifications', true)) {
            $this->send_error_notification($error_message, $context);
        }
    }
    
    /**
     * エラー通知の送信
     * 
     * @param string $error_message エラーメッセージ
     * @param array $context コンテキスト情報
     */
    private function send_error_notification($error_message, $context) {
        $notification_email = get_option('gi_ai_notification_email', get_option('admin_email'));
        
        if (empty($notification_email)) {
            return;
        }
        
        $subject = '[' . get_bloginfo('name') . '] AI自動入力機能エラー通知';
        
        $message = "AI自動入力機能でエラーが発生しました。\n\n";
        $message .= "エラーメッセージ: " . $error_message . "\n\n";
        $message .= "発生時刻: " . current_time('c') . "\n";
        $message .= "ユーザーID: " . get_current_user_id() . "\n";
        
        if (!empty($context)) {
            $message .= "\n詳細情報:\n" . print_r($context, true);
        }
        
        $message .= "\n\n設定画面: " . admin_url('options-general.php?page=gi-ai-settings');
        
        wp_mail($notification_email, $subject, $message);
    }
    
    /**
     * キャッシュの管理
     * 
     * @param string $cache_key キャッシュキー
     * @param mixed $data キャッシュするデータ
     * @param int $expiration 有効期限（秒）
     */
    public function set_cache($cache_key, $data, $expiration = 3600) {
        $cache_key = 'gi_ai_' . md5($cache_key);
        set_transient($cache_key, $data, $expiration);
    }
    
    /**
     * キャッシュの取得
     * 
     * @param string $cache_key キャッシュキー
     * @return mixed|false キャッシュデータまたはfalse
     */
    public function get_cache($cache_key) {
        $cache_key = 'gi_ai_' . md5($cache_key);
        return get_transient($cache_key);
    }
    
    /**
     * キャッシュの削除
     * 
     * @param string $cache_key キャッシュキー
     */
    public function delete_cache($cache_key) {
        $cache_key = 'gi_ai_' . md5($cache_key);
        delete_transient($cache_key);
    }
    
    /**
     * プロンプトテンプレートの取得
     * 
     * @param string $template_name テンプレート名
     * @return string|false テンプレート内容
     */
    public function get_prompt_template($template_name) {
        $templates = get_option('gi_ai_prompt_templates', array());
        
        return isset($templates[$template_name]) ? $templates[$template_name] : false;
    }
    
    /**
     * プロンプトテンプレートの保存
     * 
     * @param string $template_name テンプレート名
     * @param string $template_content テンプレート内容
     */
    public function save_prompt_template($template_name, $template_content) {
        $templates = get_option('gi_ai_prompt_templates', array());
        $templates[$template_name] = $template_content;
        
        update_option('gi_ai_prompt_templates', $templates);
    }
    
    /**
     * デバッグモードでの詳細ログ
     * 
     * @param string $message ログメッセージ
     * @param array $data ログデータ
     */
    private function debug_log($message, $data = array()) {
        if (!WP_DEBUG || !get_option('gi_ai_debug_mode', false)) {
            return;
        }
        
        $log_entry = array(
            'timestamp' => microtime(true),
            'message' => $message,
            'data' => $data,
            'memory_usage' => memory_get_usage(),
            'peak_memory' => memory_get_peak_usage()
        );
        
        error_log('GI AI Debug: ' . json_encode($log_entry));
    }
    
    /**
     * APIコストの計算
     * 
     * @param int $tokens_used 使用トークン数
     * @param string $model_name モデル名
     * @return float コスト（USD）
     */
    public function calculate_api_cost($tokens_used, $model_name = 'gpt-4o-mini') {
        // 2024年12月時点の料金（実際の料金は変動する可能性があります）
        $pricing = array(
            'gpt-4o-mini' => array(
                'input' => 0.000150,  // per 1K tokens
                'output' => 0.000600  // per 1K tokens
            ),
            'gpt-4o' => array(
                'input' => 0.005,
                'output' => 0.015
            )
        );
        
        if (!isset($pricing[$model_name])) {
            $model_name = 'gpt-4o-mini';
        }
        
        // 簡略化：入力と出力を半々と仮定
        $input_tokens = $tokens_used * 0.7;  // プロンプトが多めと仮定
        $output_tokens = $tokens_used * 0.3;
        
        $input_cost = ($input_tokens / 1000) * $pricing[$model_name]['input'];
        $output_cost = ($output_tokens / 1000) * $pricing[$model_name]['output'];
        
        return $input_cost + $output_cost;
    }
    
    /**
     * 月次コストレポートの生成
     * 
     * @param string $month 対象月（Y-m形式）
     * @return array コストレポート
     */
    public function generate_cost_report($month = null) {
        if (!$month) {
            $month = current_time('Y-m');
        }
        
        global $wpdb;
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        
        $monthly_data = $wpdb->get_results($wpdb->prepare(
            "SELECT 
                DATE(timestamp) as date,
                COUNT(*) as requests,
                SUM(tokens_used) as tokens,
                SUM(success) as successful_requests
             FROM $usage_table 
             WHERE DATE_FORMAT(timestamp, '%%Y-%%m') = %s 
               AND success = 1
             GROUP BY DATE(timestamp) 
             ORDER BY date ASC",
            $month
        ));
        
        $total_tokens = 0;
        $total_cost = 0;
        $daily_breakdown = array();
        
        foreach ($monthly_data as $day_data) {
            $day_tokens = intval($day_data->tokens);
            $day_cost = $this->calculate_api_cost($day_tokens, $this->model);
            
            $total_tokens += $day_tokens;
            $total_cost += $day_cost;
            
            $daily_breakdown[] = array(
                'date' => $day_data->date,
                'requests' => intval($day_data->requests),
                'tokens' => $day_tokens,
                'cost' => $day_cost,
                'success_rate' => round((intval($day_data->successful_requests) / intval($day_data->requests)) * 100, 1)
            );
        }
        
        return array(
            'month' => $month,
            'total_requests' => array_sum(array_column($daily_breakdown, 'requests')),
            'total_tokens' => $total_tokens,
            'total_cost' => $total_cost,
            'average_daily_cost' => count($daily_breakdown) > 0 ? $total_cost / count($daily_breakdown) : 0,
            'daily_breakdown' => $daily_breakdown
        );
    }
    
    /**
     * パフォーマンス監視
     */
    public function monitor_performance() {
        $stats = $this->get_usage_statistics();
        
        // パフォーマンスアラートの条件
        $alert_conditions = array(
            'high_response_time' => $stats['today']['avg_processing_time'] > 10, // 10秒以上
            'low_success_rate' => $stats['today']['success_rate'] < 80,           // 80%未満
            'high_token_usage' => $stats['today']['tokens'] > 50000              // 5万トークン以上
        );
        
        foreach ($alert_conditions as $condition => $is_triggered) {
            if ($is_triggered) {
                $this->send_performance_alert($condition, $stats);
            }
        }
    }
    
    /**
     * パフォーマンスアラートの送信
     * 
     * @param string $condition アラート条件
     * @param array $stats 統計情報
     */
    private function send_performance_alert($condition, $stats) {
        $messages = array(
            'high_response_time' => 'API応答時間が遅延しています（' . $stats['today']['avg_processing_time'] . '秒）',
            'low_success_rate' => '成功率が低下しています（' . $stats['today']['success_rate'] . '%）',
            'high_token_usage' => 'トークン使用量が高くなっています（' . number_format($stats['today']['tokens']) . 'トークン）'
        );
        
        if (isset($messages[$condition])) {
            $this->log_error('Performance Alert: ' . $messages[$condition], $stats);
        }
    }
    
    /**
     * ヘルスチェック
     * 
     * @return array ヘルスチェック結果
     */
    public function health_check() {
        $health_status = array(
            'api_connection' => false,
            'database' => false,
            'configuration' => false,
            'performance' => false,
            'overall' => false
        );
        
        // API接続チェック
        $api_test = $this->test_connection();
        $health_status['api_connection'] = $api_test['success'];
        
        // データベースチェック
        global $wpdb;
        $usage_table = $wpdb->prefix . 'gi_ai_usage_log';
        $db_test = $wpdb->get_var("SELECT COUNT(*) FROM $usage_table LIMIT 1");
        $health_status['database'] = $db_test !== null;
        
        // 設定チェック
        $health_status['configuration'] = !empty($this->api_key) && 
                                         $this->max_tokens > 0 && 
                                         $this->timeout > 0;
        
        // パフォーマンスチェック
        $stats = $this->get_usage_statistics();
        $health_status['performance'] = $stats['today']['success_rate'] >= 80 && 
                                       $stats['today']['avg_processing_time'] <= 15;
        
        // 総合判定
        $health_status['overall'] = $health_status['api_connection'] && 
                                   $health_status['database'] && 
                                   $health_status['configuration'] && 
                                   $health_status['performance'];
        
        return $health_status;
    }
    
    /**
     * デストラクタ
     */
    public function __destruct() {
        // クリーンアップ処理
        if (defined('WP_DEBUG') && WP_DEBUG) {
            $this->debug_log('API Handler destroyed', array(
                'peak_memory' => memory_get_peak_usage(true),
                'execution_time' => microtime(true) - $_SERVER['REQUEST_TIME_FLOAT']
            ));
        }
    }
    

}

// インスタンス化
if (class_exists('GI_AI_API_Handler')) {
    // グローバル関数としてアクセス可能にする
    function gi_ai_get_api_handler() {
        static $instance = null;
        if ($instance === null) {
            $instance = new GI_AI_API_Handler();
        }
        return $instance;
    }
} // クラス定義終了
} // if (!class_exists('GI_AI_API_Handler'))
?>