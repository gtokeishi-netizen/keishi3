<?php
/**
 * Standalone AI System Test
 * このスクリプトはWordPress環境なしでAI機能をテストします
 * しっかりチェック - Thorough API and prompt verification
 */

echo "<div style='font-family: monospace; background: #f0f0f0; padding: 20px; max-width: 1200px;'>";
echo "<h1>🔍 AI システム単体テスト - しっかりチェック</h1>";
echo "<p><strong>テスト時刻:</strong> " . date('Y-m-d H:i:s') . "</p>";

// 設定
$api_key = 'YOUR_API_KEY_HERE'; // 実際のAPIキーに置き換える必要があります
$api_endpoint = 'https://api.openai.com/v1/chat/completions';
$model = 'gpt-4o-mini';

// === 1. 基本環境チェック ===
echo "<h2>📋 1. 基本環境チェック</h2>";

$checks = [
    'PHP Version' => PHP_VERSION,
    'cURL Support' => extension_loaded('curl') ? '✅ Available' : '❌ Not Available',
    'JSON Support' => extension_loaded('json') ? '✅ Available' : '❌ Not Available',
    'OpenSSL Support' => extension_loaded('openssl') ? '✅ Available' : '❌ Not Available',
    'API Endpoint' => $api_endpoint,
    'Model' => $model
];

echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
echo "<tr><th>項目</th><th>値</th></tr>";
foreach ($checks as $item => $value) {
    echo "<tr><td>{$item}</td><td>{$value}</td></tr>";
}
echo "</table>";

// === 2. テスト用プロンプト定義 ===
echo "<h2>🎯 2. 改良されたプロンプトシステムのテスト</h2>";

// 改良されたシステムプロンプト（実際のai-api-handler.phpと同じもの）
$system_prompt = "あなたは日本の助成金・補助金制度に精通した専門家です。実在する助成金制度の一般的なパターンを参考に、具体的で実用的な情報を生成してください。

【重要】以下の例を参考に、具体的で実際的な内容を作成してください：

【良い助成金情報の例】
- 対象：中小企業（従業員数5-100名、年売上高1億円以下）
- 金額：設備投資費用の1/2（上限500万円）
- 経費：機械装置、システム開発費、研修費、外注費
- 条件：創業3年以上、法人税納税、指定地域内

【必須ルール】
1. 抽象的な表現は一切使わない
2. 必ず具体的な数値・条件・金額を含める  
3. 実際に存在しそうな制度設計にする
4. 申請者が「自分が該当するか」判断できる具体性
5. 年号（令和・平成）は絶対使用禁止
6. 記号の連続（、、、）は絶対禁止

【出力品質基準】
✅ 良い例：「製造業・サービス業の中小企業が対象」
❌ 悪い例：「様々な業種の事業者が対象」

✅ 良い例：「機械装置購入費、システム開発費、研修費」  
❌ 悪い例：「事業に必要な各種経費」

✅ 良い例：「上限300万円（対象経費の50%以内）」
❌ 悪い例：「十分な金額を支援」

【絶対禁止事項】
- 「、、、」「。。。」などの記号連続
- 令和○年度、平成○年度の記載
- 曖昧な表現（「適切な」「十分な」「各種の」等）
- 実現不可能な条件や金額
- 前置きや説明文（求められたフィールド内容のみ出力）

具体的で実用的な、実際に申請したくなる助成金情報を作成してください。";

// テスト用の投稿データ
$test_post_data = [
    'title' => 'IT導入支援助成金制度',
    'organization' => '中小企業庁',
    'max_amount' => '450万円',
    'target_business_type' => '中小企業・個人事業主',
    'existing_ai_content' => []
];

// AI概要プロンプト（実際のai-api-handler.phpと同じもの）
$summary_prompt = "以下の情報から、助成金の魅力的な概要を200文字以内で作成してください：

title: IT導入支援助成金制度
organization: 中小企業庁
max_amount: 450万円
target_business_type: 中小企業・個人事業主

【重要】以下の例を参考に、具体的で実用的な概要を作成してください：

【良い例1】
この助成金は中小企業のIT導入を支援する制度で、ソフトウェア購入費用の2/3（最大450万円）を補助します。対象は従業員5-100名の製造業・サービス業で、生産性向上が期待できる企業が申請可能です。

【良い例2】  
創業5年以内のスタートアップ向け助成金制度。新商品開発費や設備投資費用を最大1000万円まで支援（助成率50%）。IT・バイオ・環境分野の革新的事業が対象で、年2回募集しています。

【作成ルール】
1. 必ず「助成金」「補助金」「支援制度」のいずれかを含める
2. 具体的な金額・助成率を記載（不明な場合は「上限○○万円程度」）
3. 対象者を明確に記載（業種・規模・条件等）
4. 何に使える費用かを明記
5. 申請の魅力（簡単・高額・通りやすい等）を1つ以上含める
6. 200文字以内で完結させる

上記の例のような、具体的で申請したくなる概要を作成してください：";

echo "<h3>📝 送信予定のプロンプト</h3>";
echo "<h4>システムプロンプト（最初の200文字）:</h4>";
echo "<div style='background: white; padding: 10px; border: 1px solid #ccc; font-size: 12px;'>";
echo htmlspecialchars(mb_substr($system_prompt, 0, 200)) . "...";
echo "</div>";

echo "<h4>ユーザープロンプト（最初の300文字）:</h4>";
echo "<div style='background: white; padding: 10px; border: 1px solid #ccc; font-size: 12px;'>";
echo htmlspecialchars(mb_substr($summary_prompt, 0, 300)) . "...";
echo "</div>";

// === 3. APIキーチェック ===
echo "<h2>🔑 3. APIキーチェック</h2>";

if ($api_key === 'YOUR_API_KEY_HERE') {
    echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
    echo "<h3>❌ APIキーが設定されていません</h3>";
    echo "<p>このスクリプトの \$api_key 変数に実際のOpenAI APIキーを設定してください。</p>";
    echo "<p>実際のWordPress環境では、管理画面の「設定 > AI自動入力設定」でAPIキーを設定します。</p>";
    echo "</div>";
} else {
    $masked_key = str_repeat('*', max(0, strlen($api_key) - 8)) . substr($api_key, -8);
    echo "<div style='background: #ddffdd; padding: 15px; margin: 10px 0; border: 2px solid #00aa00;'>";
    echo "<h3>✅ APIキーが設定されています</h3>";
    echo "<p><strong>マスクされたキー:</strong> {$masked_key}</p>";
    echo "</div>";
    
    // === 4. 実際のAPI接続テスト ===
    echo "<h2>🌐 4. API接続テスト</h2>";
    
    if (!extension_loaded('curl')) {
        echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
        echo "<h3>❌ cURL拡張がありません</h3>";
        echo "<p>APIテストを実行するにはcURL拡張が必要です。</p>";
        echo "</div>";
    } else {
        // APIリクエストの準備
        $request_data = [
            'model' => $model,
            'messages' => [
                [
                    'role' => 'system',
                    'content' => $system_prompt
                ],
                [
                    'role' => 'user', 
                    'content' => $summary_prompt
                ]
            ],
            'max_tokens' => 1000,
            'temperature' => 0.7
        ];
        
        echo "<p>🔄 APIリクエスト実行中...</p>";
        echo "<p><strong>エンドポイント:</strong> {$api_endpoint}</p>";
        echo "<p><strong>モデル:</strong> {$model}</p>";
        echo "<p><strong>送信データサイズ:</strong> " . strlen(json_encode($request_data)) . " bytes</p>";
        
        // cURLでAPIリクエスト
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $api_endpoint);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($request_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $api_key,
            'Content-Type: application/json'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $start_time = microtime(true);
        $response = curl_exec($ch);
        $end_time = microtime(true);
        $processing_time = $end_time - $start_time;
        
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        echo "<p><strong>処理時間:</strong> " . number_format($processing_time, 2) . "秒</p>";
        echo "<p><strong>HTTPレスポンスコード:</strong> {$http_code}</p>";
        
        if ($error) {
            echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
            echo "<h3>❌ cURLエラー</h3>";
            echo "<p><strong>エラー:</strong> " . htmlspecialchars($error) . "</p>";
            echo "</div>";
        } else if ($http_code === 200) {
            $data = json_decode($response, true);
            
            if ($data && isset($data['choices'][0]['message']['content'])) {
                $generated_content = $data['choices'][0]['message']['content'];
                $tokens_used = $data['usage']['total_tokens'] ?? 'unknown';
                
                echo "<div style='background: #ddffdd; padding: 15px; margin: 10px 0; border: 2px solid #00aa00;'>";
                echo "<h3>✅ API呼び出し成功！</h3>";
                
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                echo "<tr><th>項目</th><th>値</th></tr>";
                echo "<tr><td>処理時間</td><td>" . number_format($processing_time, 2) . "秒</td></tr>";
                echo "<tr><td>使用トークン</td><td>{$tokens_used}</td></tr>";
                echo "<tr><td>生成文字数</td><td>" . mb_strlen($generated_content) . "文字</td></tr>";
                echo "</table>";
                
                echo "<h4>📄 生成されたAI概要:</h4>";
                echo "<div style='background: white; padding: 15px; border: 1px solid #ccc; font-size: 14px; line-height: 1.6;'>";
                echo "<strong>内容:</strong><br>" . nl2br(htmlspecialchars($generated_content));
                echo "</div>";
                echo "</div>";
                
                // === 5. 品質分析 ===
                echo "<h2>🎯 5. 生成内容品質分析</h2>";
                
                $issues = [];
                $good_points = [];
                
                // 問題点チェック
                if (strpos($generated_content, '、、、') !== false) $issues[] = '不正な記号連続（、、、）を検出';
                if (strpos($generated_content, '。。。') !== false) $issues[] = '不正な記号連続（。。。）を検出';
                if (strpos($generated_content, '令和') !== false) $issues[] = '年号（令和）を検出';
                if (strpos($generated_content, '平成') !== false) $issues[] = '年号（平成）を検出';
                if (mb_strlen($generated_content) < 30) $issues[] = '内容が短すぎ（30文字未満）';
                if (mb_strlen($generated_content) > 220) $issues[] = '内容が長すぎ（220文字超過）';
                if (preg_match('/(適切な|十分な|各種の|様々な)/', $generated_content)) $issues[] = '曖昧な表現を検出';
                
                // 良い点チェック
                if (preg_match('/(助成金|補助金|支援制度)/', $generated_content)) $good_points[] = '適切なキーワードを含む';
                if (preg_match('/\d+万円|\d+％/', $generated_content)) $good_points[] = '具体的な金額・率を含む';
                if (preg_match('/(中小企業|スタートアップ|製造業|IT|サービス業)/', $generated_content)) $good_points[] = '具体的な対象者を含む';
                if (preg_match('/(設備|システム|研修|開発|ソフトウェア)/', $generated_content)) $good_points[] = '具体的な用途を含む';
                if (preg_match('/(従業員|売上|創業|法人)/', $generated_content)) $good_points[] = '具体的な条件を含む';
                
                echo "<table border='1' style='border-collapse: collapse; width: 100%; margin: 10px 0;'>";
                echo "<tr><th>品質チェック項目</th><th>結果</th><th>詳細</th></tr>";
                
                // 問題点の表示
                if (empty($issues)) {
                    echo "<tr style='background: #ddffdd;'><td>問題点</td><td>✅ なし</td><td>品質に問題なし</td></tr>";
                } else {
                    echo "<tr style='background: #ffdddd;'><td>問題点</td><td>❌ " . count($issues) . "件</td><td>";
                    foreach ($issues as $issue) {
                        echo "• " . $issue . "<br>";
                    }
                    echo "</td></tr>";
                }
                
                // 良い点の表示
                if (!empty($good_points)) {
                    echo "<tr style='background: #ddffdd;'><td>良いポイント</td><td>✅ " . count($good_points) . "件</td><td>";
                    foreach ($good_points as $point) {
                        echo "• " . $point . "<br>";
                    }
                    echo "</td></tr>";
                } else {
                    echo "<tr style='background: #ffffdd;'><td>良いポイント</td><td>⚠️ なし</td><td>具体的な内容が不足</td></tr>";
                }
                
                echo "</table>";
                
                // === 6. 改善度評価 ===
                echo "<h2>📊 6. 改善度評価</h2>";
                
                $quality_score = count($good_points) - count($issues);
                $quality_percentage = max(0, min(100, ($quality_score + 5) * 10));
                
                echo "<div style='background: " . ($quality_score >= 3 ? '#ddffdd' : ($quality_score >= 1 ? '#ffffdd' : '#ffdddd')) . "; padding: 15px; margin: 10px 0; border: 2px solid " . ($quality_score >= 3 ? '#00aa00' : ($quality_score >= 1 ? '#ffaa00' : '#ff0000')) . ";'>";
                echo "<h3>品質スコア: {$quality_score}/5</h3>";
                echo "<p><strong>品質評価:</strong> {$quality_percentage}%</p>";
                
                if ($quality_score >= 3) {
                    echo "<p>✅ 優秀：改良されたプロンプトが効果的に機能しています</p>";
                } elseif ($quality_score >= 1) {
                    echo "<p>⚠️ 普通：改良は進んでいますが、さらなる改善が可能です</p>";
                } else {
                    echo "<p>❌ 要改善：プロンプトの品質向上が必要です</p>";
                }
                echo "</div>";
                
            } else {
                echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
                echo "<h3>❌ 予期しないレスポンス形式</h3>";
                echo "<pre>" . htmlspecialchars($response) . "</pre>";
                echo "</div>";
            }
        } else {
            echo "<div style='background: #ffdddd; padding: 15px; margin: 10px 0; border: 2px solid #ff0000;'>";
            echo "<h3>❌ HTTPエラー {$http_code}</h3>";
            
            if ($http_code === 401) {
                echo "<p><strong>認証エラー:</strong> APIキーが無効です</p>";
            } elseif ($http_code === 429) {
                echo "<p><strong>レート制限:</strong> リクエスト数が上限に達しています</p>";
            } elseif ($http_code === 500) {
                echo "<p><strong>サーバーエラー:</strong> OpenAI側でエラーが発生しています</p>";
            }
            
            echo "<h4>レスポンス詳細:</h4>";
            echo "<pre style='font-size: 10px;'>" . htmlspecialchars(substr($response, 0, 1000)) . "</pre>";
            echo "</div>";
        }
    }
}

// === 7. 診断まとめ ===
echo "<h2>📋 7. 診断まとめ</h2>";

echo "<h3>🔍 しっかりチェック結果</h3>";
echo "<ul>";
echo "<li><strong>プロンプト品質:</strong> 改良されたシステムプロンプトと具体的なフィールドプロンプトを確認</li>";
echo "<li><strong>API通信:</strong> " . ($api_key !== 'YOUR_API_KEY_HERE' ? (isset($generated_content) ? '✅ 正常動作' : '❌ 通信エラー') : '❌ APIキー未設定') . "</li>";
echo "<li><strong>生成品質:</strong> " . (isset($quality_score) ? ($quality_score >= 1 ? '✅ 改善確認' : '❌ 要改善') : '❌ 未テスト') . "</li>";
echo "</ul>";

if (isset($generated_content)) {
    echo "<div style='background: #e6f3ff; padding: 15px; margin: 20px 0; border: 2px solid #0066cc;'>";
    echo "<h3>💡 検証結果とアクションプラン</h3>";
    if (empty($issues) && count($good_points) >= 3) {
        echo "<p>✅ <strong>成功：</strong>改良されたプロンプトシステムが効果的に機能しています。品質向上が確認できました。</p>";
    } elseif (!empty($issues)) {
        echo "<p>⚠️ <strong>部分的成功：</strong>改良は進んでいますが、以下の問題があります：</p>";
        echo "<ul>";
        foreach ($issues as $issue) {
            echo "<li>{$issue}</li>";
        }
        echo "</ul>";
        echo "<p><strong>推奨アクション：</strong>プロンプトをさらに具体化し、禁止事項のチェックを強化してください。</p>";
    }
    echo "</div>";
} else {
    echo "<div style='background: #ffeeee; padding: 15px; margin: 20px 0; border: 2px solid #cc0000;'>";
    echo "<h3>❌ テスト未完了</h3>";
    echo "<p>APIキーの設定またはネットワーク接続に問題があるため、実際の生成品質を確認できませんでした。</p>";
    echo "<p><strong>推奨アクション：</strong>APIキーを設定して再テストしてください。</p>";
    echo "</div>";
}

echo "</div>";
?>

<style>
table { font-size: 12px; }
pre { font-size: 10px; overflow-x: auto; }
h1, h2, h3 { color: #333; }
</style>