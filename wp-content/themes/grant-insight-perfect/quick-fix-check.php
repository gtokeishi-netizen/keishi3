<?php
/**
 * Quick Fix Checker - APIキー設定の確認と修正
 */

echo "<h1>🔧 APIキー設定クイックフィックス</h1>";

// WordPress関数をシミュレート（実際のWordPress環境では不要）
if (!function_exists('get_option')) {
    function get_option($key, $default = '') {
        // ここで実際のAPIキーを直接設定してテスト可能
        if ($key === 'gi_openai_api_key') {
            return 'sk-test123...'; // 実際のキーに置き換え
        }
        return $default;
    }
}

// 1. APIキー確認
$api_key = get_option('gi_openai_api_key');
echo "<h2>現在のAPIキー状況</h2>";

if (empty($api_key)) {
    echo "<div style='background: #ffdddd; padding: 15px; border: 2px solid #ff0000;'>";
    echo "<h3>❌ APIキーが設定されていません</h3>";
    echo "<p><strong>解決方法:</strong></p>";
    echo "<ol>";
    echo "<li>WordPress管理画面にログイン</li>";
    echo "<li>「設定」→「AI自動入力設定」に移動</li>";
    echo "<li>OpenAI APIキーを入力</li>";
    echo "<li>「テスト接続」ボタンで動作確認</li>";
    echo "<li>設定を保存</li>";
    echo "</ol>";
    echo "</div>";
} else {
    $masked_key = str_repeat('*', max(0, strlen($api_key) - 8)) . substr($api_key, -8);
    echo "<div style='background: #ddffdd; padding: 15px; border: 2px solid #00aa00;'>";
    echo "<h3>✅ APIキーが設定されています</h3>";
    echo "<p><strong>マスクされたキー:</strong> {$masked_key}</p>";
    echo "</div>";
}

// 2. 管理画面設定の直接リンク
echo "<h2>🎯 直接アクション</h2>";
echo "<div style='background: #e6f3ff; padding: 15px; border: 2px solid #0066cc;'>";
echo "<h3>すぐに確認すべき場所</h3>";
echo "<ol>";
echo "<li><strong>WordPress管理画面:</strong> /wp-admin/ にアクセス</li>";
echo "<li><strong>AI設定画面:</strong> 設定 → AI自動入力設定</li>";
echo "<li><strong>投稿編集画面:</strong> 任意のgrant投稿を開いて「AI自動入力」メタボックスを確認</li>";
echo "</ol>";
echo "</div>";

// 3. JavaScript実行テスト
echo "<h2>🖥️ フロントエンド動作確認</h2>";
echo "<div style='background: #ffffdd; padding: 15px; border: 2px solid #ffaa00;'>";
echo "<h3>投稿編集画面で以下を確認してください</h3>";
echo "<ol>";
echo "<li><strong>ブラウザの開発者ツールを開く</strong> (F12キー)</li>";
echo "<li><strong>コンソールタブを選択</strong></li>";
echo "<li><strong>AI自動入力ボタンをクリック</strong></li>";
echo "<li><strong>以下のようなログが表示されるか確認:</strong>";
echo "<pre style='background: #f0f0f0; padding: 10px;'>";
echo "[AI Debug] 送信データ: {...}\n";
echo "[AI Debug] AJAX URL: .../wp-admin/admin-ajax.php\n";
echo "[AI Debug] 選択フィールド: [...]\n";
echo "[AI Debug] レスポンス受信: {...}";
echo "</pre>";
echo "</li>";
echo "</ol>";
echo "</div>";

// 4. 期待される改善内容の確認
echo "<h2>📊 改善内容の確認方法</h2>";
echo "<div style='background: #f0fff0; padding: 15px; border: 2px solid #00cc00;'>";
echo "<h3>生成品質の改善を確認するポイント</h3>";
echo "<ul>";
echo "<li>✅ <strong>具体的な金額:</strong> 「上限○○万円」「助成率○○%」等の数値</li>";
echo "<li>✅ <strong>具体的な対象者:</strong> 「従業員○名以下」「年売上○億円以下」等</li>";
echo "<li>✅ <strong>具体的な業種:</strong> 「製造業」「IT関連業」等の明確な業種名</li>";
echo "<li>✅ <strong>具体的な用途:</strong> 「設備投資」「システム開発」等の具体的用途</li>";
echo "<li>❌ <strong>禁止事項なし:</strong> 「令和」「平成」「、、、」「適切な」等の表現なし</li>";
echo "</ul>";
echo "</div>";

echo "<h2>🎯 次のアクション</h2>";
echo "<div style='background: #fff0e6; padding: 15px; border: 2px solid #ff6600;'>";
echo "<h3>もし生成品質が改善しない場合</h3>";
echo "<ol>";
echo "<li><strong>APIキーを再設定:</strong> 正しいOpenAI APIキーを入力し直す</li>";
echo "<li><strong>WordPress設定を確認:</strong> プラグインとテーマが正しく読み込まれているか</li>";
echo "<li><strong>プロンプトログを確認:</strong> コンソールログで実際に改良されたプロンプトが送信されているか</li>";
echo "<li><strong>キャッシュをクリア:</strong> ブラウザキャッシュとWordPressキャッシュをクリア</li>";
echo "</ol>";
echo "</div>";

?>

<style>
body { font-family: monospace; background: #f0f0f0; padding: 20px; max-width: 1000px; }
h1, h2, h3 { color: #333; }
pre { font-size: 11px; }
ol, ul { margin-left: 20px; }
</style>