<?php
// C:\xampp\htdocs\copyemoji.in\scripts\export_json.php

// 1. Connection fix (Folder same hai toh path simple rakho)
include 'db_connect.php'; 

// 2. Data fetch
$stmt = $pdo->query("SELECT * FROM emoji_content ORDER BY category ASC, id ASC");
$allEmojis = $stmt->fetchAll();

$data = [];
foreach ($allEmojis as $row) {
    $cat = $row['category'];
    // Slug generate karne ka logic match karo
    $catSlug = str_replace(' ', '_', strtolower($cat));
    
    if (!isset($data[$cat])) {
        $data[$cat] = [
            "name" => $cat, 
            "slug" => $catSlug, 
            "emojis" => []
        ];
    }
    
    // 🔥 YE HAI WOH STRUCTURE JO main.js KO CHAHIYE 🔥
    $data[$cat]["emojis"][] = [
        "emoji" => $row['emoji_char'],
        "skin_tone_support" => (bool)($row['skin_tone_support'] ?? false), // Boolean convert kiya
        "skin_tone_support_unicode_version" => $row['skin_tone_support_unicode_version'] ?? "",
        "name" => $row['name'],
        "slug" => $row['slug'],
        "unicode_version" => $row['unicode_ver'],
        "emoji_version" => $row['emoji_ver']
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