<?php
// 1. TESTING MODE (Pehle sirf 2 emojis check karega)
$testing = true; 
$limit = 2; 
$count = 0;

// Tumhare paths (Forward slash use kiya hai for safety)
$emojiDataFile = 'C:/laragon/www/copyemoji.in/assets/data/emoji.json';
$emojiFolderPath = 'C:/laragon/www/copyemoji.in/emoji/';

// JSON Read karna
$jsonData = json_decode(file_get_contents($emojiDataFile), true);

if (!$jsonData) {
    die("❌ JSON kharab hai ya path galat hai. Check karo!");
}

// DOUBLE LOOP: Pehle categories, phir emojis
foreach ($jsonData as $category) {
    
    if (!isset($category['emojis'])) continue; // Agar kisi category me emoji nahi hai to skip

    foreach ($category['emojis'] as $emoji) {
        
        if ($testing && $count >= $limit) {
            echo "🛑 Testing Mode limit reached. Stopping.<br>";
            break 2; // Dono loops se bahar nikalne ke liye
        }

        $slug = $emoji['slug'];
        $name = $emoji['name'];
        $char = $emoji['emoji'];
        
        // File path check (.html or folder/index.html)
        $filePath = $emojiFolderPath . $slug . '.html';
        if (!file_exists($filePath)) {
            $filePath = $emojiFolderPath . $slug . '/index.html';
        }

        if (!file_exists($filePath)) {
            echo "⚠️ File nahi mili: $name ($slug)<br>";
            continue;
        }

        echo "🔄 Processing: $name $char...<br>";

        // 2. Ollama API Call (500+ Words Content)
        $prompt = "Write a 500+ word friendly, human-like article about the $char $name emoji. Include its exact meaning, origin, and real-life usage. Also include 6 detailed FAQs with answers. Format ONLY in raw HTML using <p> and <h3> tags. Do NOT use markdown or wrapper divs.";
        
        $data = json_encode([
            'model' => 'qwen2.5-coder:7b',
            'prompt' => $prompt,
            'stream' => false
        ]);

        $ch = curl_init('http://localhost:11434/api/generate');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        $response = curl_exec($ch);
        curl_close($ch);

        $ollamaData = json_decode($response, true);
        $aiContent = $ollamaData['response'];

        // 3. HTML Content Replace (Regex Magic)
        $html = file_get_contents($filePath);

        // Ye line tumhare pehle description section ko AI content se badal degi
        $html = preg_replace(
            '/(<section style=\'text-align: left; margin-top: 50px;\'>).*?(<\/section>)/is',
            '$1' . "\n" . $aiContent . "\n" . '$2',
            $html,
            1
        );

        // File Save karo
        file_put_contents($filePath, $html);
        echo "✅ Success: $name updated!<br><br>";

        $count++;
        sleep(3); // PC garam na ho jaye
    }
}

echo "🎉 Saim bhai, kaam ho gaya! Ek baar browser me check kar lo.";
?>