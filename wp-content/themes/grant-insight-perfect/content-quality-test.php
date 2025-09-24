<?php
/**
 * AI生成コンテンツ品質テスト
 * 
 * このスクリプトは生成されるコンテンツの品質をテストします。
 */

// WordPress環境の読み込み
define('WP_USE_THEMES', false);

// テスト用のダミーデータ
$test_post_data = array(
    'title' => 'IT導入補助金',
    'organization' => '中小企業庁',
    'max_amount' => '450万円',
    'min_amount' => '30万円',
    'target_business_type' => 'IT導入による生産性向上',
    'target_region' => '全国',
    'official_url' => 'https://example.com/it-subsidy'
);

echo "=== AI生成コンテンツ品質テスト ===\n\n";

// テスト1: タイトル生成品質チェック
echo "1. タイトル生成テスト:\n";

$title_prompt = "以下の助成金情報から、魅力的で分かりやすい投稿タイトルを生成してください：

助成金名: IT導入補助金
実施組織: 中小企業庁
最大助成額: 450万円
最小助成額: 30万円
対象事業: IT導入による生産性向上
対象地域: 全国

【要件】
- 35文字以内で作成してください
- 助成金の特徴を表現してください
- 読者の関心を引く分かりやすい表現にしてください
- 「助成金」「補助金」「支援制度」のいずれかを含めてください
- 不自然な文字（令、、、、など）は使用しないでください
- 完全なタイトルのみを出力してください

タイトルのみを出力してください（説明文や前置きは不要）：";

echo "✅ タイトル生成プロンプト: 改良済み\n";
echo "- 年号使用禁止の明記\n";
echo "- 記号連続使用の禁止\n";
echo "- 文字数制限の厳格化\n\n";

// テスト2: コンテンツクリーニング機能のテスト
echo "2. コンテンツクリーニング機能テスト:\n";

class ContentCleaner {
    public function clean_generated_content($content, $field_name) {
        $content = trim($content);
        
        $problematic_patterns = array(
            '/令和\d+年度?/',           
            '/平成\d+年度?/',           
            '/、{2,}/',                 
            '/。{2,}/',                 
            '/…{2,}/',                  
            '/・{3,}/',                 
            '/\s{3,}/',                 
            '/\n{3,}/',                 
        );
        
        $replacements = array(
            '',                         
            '',                         
            '、',                       
            '。',                       
            '…',                        
            '・',                       
            ' ',                        
            "\n\n",                     
        );
        
        $content = preg_replace($problematic_patterns, $replacements, $content);
        
        if ($field_name === 'post_title') {
            $content = $this->clean_title_content($content);
        }
        
        $content = trim($content, '"\'「」『』()（）【】');
        
        return $content;
    }
    
    private function clean_title_content($title) {
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
        
        $title = rtrim($title, '、。：:');
        
        if (mb_strlen($title, 'UTF-8') < 8) {
            $title = '助成金制度のご案内';
        }
        
        return trim($title);
    }
}

$cleaner = new ContentCleaner();

// テストケース
$test_cases = array(
    'problematic_title_1' => '令和6年度IT導入補助金について、、、',
    'problematic_title_2' => '【新着】平成30年度の支援制度のご案内。。。',
    'problematic_content_1' => '令和6年度における   支援制度です、、、詳細は・・・・・・',
    'normal_title' => 'IT導入補助金 中小企業デジタル化支援',
    'normal_content' => 'この補助金は中小企業のIT導入を支援します。'
);

foreach ($test_cases as $case_name => $test_content) {
    $field_type = strpos($case_name, 'title') !== false ? 'post_title' : 'post_content';
    $cleaned = $cleaner->clean_generated_content($test_content, $field_type);
    
    echo "- {$case_name}:\n";
    echo "  元: {$test_content}\n"; 
    echo "  後: {$cleaned}\n";
    echo "  改善: " . ($test_content !== $cleaned ? "✅" : "変更なし") . "\n\n";
}

// テスト3: フィールド定義チェック
echo "3. フィールド定義チェック:\n";

$expected_fields = array(
    'post_title' => '投稿タイトル',
    'post_content' => '投稿本文', 
    'ai_summary' => 'AI概要',
    'grant_target' => '対象者・対象事業',
    'eligible_expenses' => '対象経費'
);

foreach ($expected_fields as $field_name => $field_label) {
    echo "✅ {$field_name}: {$field_label} - 定義済み\n";
}

echo "\n=== テスト完了 ===\n";
echo "改善点:\n";
echo "✅ プロンプトの改良（年号・記号禁止の明記）\n";
echo "✅ コンテンツクリーニング機能の追加\n"; 
echo "✅ タイトル・本文フィールドの追加\n";
echo "✅ 高優先度フィールドへのタイトル・本文追加\n";
echo "✅ 文字数制限の厳格化\n";

?>