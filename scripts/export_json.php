<?php
// C:\xampp\htdocs\copyemoji.in\scripts\export_json.php

// 1. Connection fix (Folder same hai toh path simple rakho)
include 'db_connect.php';

// Slug sanitizer (must match scripts/fix_slugs.php + phase2_render.php)
if (!function_exists('sanitize_slug')) {
    function sanitize_slug(string $raw): string {
        $s = mb_strtolower(trim($raw), 'UTF-8');
        if (class_exists('Transliterator')) {
            $t = Transliterator::create('Any-Latin; Latin-ASCII;');
            if ($t) $s = $t->transliterate($s);
        } else {
            $iconv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $s);
            if ($iconv !== false) $s = $iconv;
        }
        $s = str_replace(['&', '+', '@', '#'], ['-and-', '-plus-', '-at-', '-hash-'], $s);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        $s = preg_replace('/-+/', '-', $s);
        $s = trim($s, '-');
        return $s !== '' ? $s : 'emoji';
    }
}

// 2. Data fetch
$stmt = $pdo->query("SELECT * FROM emoji_content ORDER BY category ASC, id ASC");
$allEmojis = $stmt->fetchAll();

$data = [];
foreach ($allEmojis as $row) {
    $cat = $row['category'];
    // Canonical slug: dashes + 'and', must match phase2_render.php + _redirects
    $catSlug = sanitize_slug(str_replace(' & ', ' and ', $cat));

    if (!isset($data[$cat])) {
        $data[$cat] = [
            "name" => $cat,
            "slug" => $catSlug,
            "emojis" => []
        ];
    }

    $emojiSlug = sanitize_slug($row['slug']);
    $keywords = $row['keywords'] ?? '';
    $searchText = mb_strtolower(
        $row['name'] . ' ' . str_replace('-', ' ', $emojiSlug) . ' ' . $keywords . ' ' . $cat,
        'UTF-8'
    );

    // 🔥 YE HAI WOH STRUCTURE JO main.js KO CHAHIYE 🔥 (v1.5: + search_text)
    $data[$cat]["emojis"][] = [
        "emoji" => $row['emoji_char'],
        "skin_tone_support" => (bool)($row['skin_tone_support'] ?? false), // Boolean convert kiya
        "skin_tone_support_unicode_version" => $row['skin_tone_support_unicode_version'] ?? "",
        "name" => $row['name'],
        "slug" => $emojiSlug,
        "unicode_version" => $row['unicode_ver'],
        "emoji_version" => $row['emoji_ver'],
        "search_text" => $searchText
    ];
}

// 3. Folder check aur JSON Save
$targetDir = '../assets/data';
if (!file_exists($targetDir)) {
    mkdir($targetDir, 0777, true);
    echo "📂 Folder nahi tha, naya bana diya!<br>";
}

file_put_contents($targetDir . '/emoji.json', json_encode(array_values($data), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
echo "✅ emoji.json successfully generate ho gaya!";
?>