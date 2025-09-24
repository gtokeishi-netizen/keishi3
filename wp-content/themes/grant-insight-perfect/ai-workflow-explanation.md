# AI自動入力機能の完全ワークフロー

## ✅ はい、AIに指示を送って結果を受け取る機能は完全に備わっています！

### 📋 完全な処理フロー

```
1. [ユーザー操作] → 2. [フィールド選択] → 3. [AI指示送信] → 4. [AI処理] → 5. [結果受信] → 6. [入力項目反映]
```

### 🔄 詳細なステップバイステップ

#### 1️⃣ **ユーザーが操作開始**
```javascript
// 管理画面でボタンをクリック
$('#gi-ai-execute-btn').on('click', function() {
    // 選択されたフィールドを収集
    var selectedFields = [];
    $('.gi-ai-field-checkbox:checked').each(function() {
        selectedFields.push($(this).val());
    });
```

#### 2️⃣ **選択フィールドの検証**
```javascript
if (selectedFields.length === 0) {
    alert('処理対象のフィールドを選択してください');
    return;
}
```

#### 3️⃣ **AJAX通信でAI処理を開始**
```javascript
var data = {
    action: 'gi_ai_auto_fill',           // WordPress AJAX アクション
    nonce: gi_ai_ajax.nonce,             // セキュリティ検証
    post_id: post_id,                    // 対象投稿ID
    target_fields: selectedFields        // 処理対象フィールド
};

$.post(gi_ai_ajax.ajax_url, data, function(response) {
    // AI処理完了後の処理
});
```

#### 4️⃣ **サーバーサイドでAI処理実行**
```php
// ai-auto-fill.php の process_auto_fill() メソッド
public function process_auto_fill() {
    // セキュリティ検証
    if (!wp_verify_nonce($_POST['nonce'], 'gi_ai_auto_fill_nonce')) {
        wp_die('セキュリティチェックに失敗しました');
    }
    
    // 各フィールドを順次処理
    foreach ($target_fields as $field_name) {
        // AI生成実行
        $api_result = $this->api_handler->generate_field_content($post_data, $field_name);
    }
}
```

#### 5️⃣ **OpenAI APIに指示送信**
```php
// ai-api-handler.php の generate_field_content() メソッド
public function generate_field_content($post_data, $target_field) {
    // プロンプト構築
    $prompt = $this->build_prompt($post_data, $target_field);
    
    // API リクエスト送信
    $api_response = $this->make_api_request($prompt);
    
    // レスポンス処理
    $content = $this->extract_content_from_response($api_response['data'], $target_field);
}
```

#### 6️⃣ **具体的なAPI通信**
```php
private function make_api_request($prompt) {
    $request_body = array(
        'model' => 'gpt-4o-mini',
        'messages' => array(
            array('role' => 'system', 'content' => $prompt['system']),
            array('role' => 'user', 'content' => $prompt['user'])
        ),
        'max_tokens' => $this->max_tokens,
        'temperature' => $this->temperature
    );
    
    // WordPress wp_remote_request でOpenAI APIに送信
    $response = wp_remote_request($this->api_endpoint, $args);
}
```

#### 7️⃣ **AI応答の解析と処理**
```php
private function extract_content_from_response($api_data, $field_name) {
    $content = trim($api_data['choices'][0]['message']['content']);
    
    // コンテンツの後処理（不自然な文字除去など）
    $content = $this->post_process_content($content, $field_name);
    
    return $content;
}
```

#### 8️⃣ **入力項目への反映**
```php
if ($api_result['success']) {
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
}
```

#### 9️⃣ **結果のユーザー通知**
```javascript
if (response.success) {
    showSuccessMessage('処理が完了しました。' + response.data.updated_fields.length + '件のフィールドを更新しました。');
    
    // ページリロードで更新を反映
    location.reload();
} else {
    showErrorMessage('エラー: ' + response.data);
}
```

### 🎯 **対応フィールド一覧**

| フィールド | AI指示内容 | 反映先 |
|------------|------------|--------|
| **post_title** | 魅力的なタイトル生成 | WordPress投稿タイトル |
| **post_content** | 詳細な本文生成 | WordPress投稿本文 |
| **ai_summary** | 200文字概要生成 | ACFフィールド |
| **grant_target** | 対象者・事業のHTML生成 | ACFフィールド |
| **eligible_expenses** | 対象経費のリスト生成 | ACFフィールド |
| **grant_difficulty** | 難易度判定（4段階） | ACFフィールド |
| **required_documents** | 必要書類リスト生成 | ACFフィールド |
| **application_method** | 申請方法判定 | ACFフィールド |
| **contact_info** | 問い合わせ先生成 | ACFフィールド |
| **amount_note** | 金額補足説明 | ACFフィールド |
| **deadline_note** | 締切補足説明 | ACFフィールド |

### 🛡️ **品質保証機能**

1. **コンテンツクリーニング**: 不自然な文字の自動除去
2. **文字数制限**: フィールド別の制限を厳格に適用
3. **HTMLサニタイズ**: 安全なHTMLのみ許可
4. **エラーハンドリング**: 失敗時の適切な処理
5. **リトライ機能**: API通信失敗時の自動再試行
6. **使用量監視**: 日次制限のチェック

### 📊 **実行結果の追跡**

- ✅ **成功ログ**: 更新されたフィールド数、使用トークン数
- ✅ **エラーログ**: 失敗原因の詳細記録
- ✅ **統計情報**: 日次・月次の使用状況
- ✅ **バックアップ**: 変更前の内容を自動保存

## 🎉 **結論**

**完全に機能します！** AIに明確な指示を送信し、その結果を各入力項目に自動反映する機能が備わっています。ユーザーはボタンをクリックするだけで、高品質なコンテンツが自動生成されます。