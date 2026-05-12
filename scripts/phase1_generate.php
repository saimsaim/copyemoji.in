<?php
/**
 * ============================================================
 * CopyEmoji.in — PHASE 1: Ollama Generator (Night Rider Mode 🌙)
 * ============================================================
 */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);
date_default_timezone_set('Asia/Kolkata'); // 🇮🇳 Indian Time

// ─── CONFIG ────────────────────────────────────────────────
define('OLLAMA_URL',    'http://localhost:11434/api/generate');
define('OLLAMA_MODEL',  'llama3.2:3b');  // RTX 2050 ke liye best
define('BATCH_LIMIT',   50);             // Ek baar mein 50 uthayega 
define('SLEEP_SEC',     5);              // ❄️ Har emoji ke baad GPU cooling gap
define('RETRY_SLEEP',   30);             // ⏳ Resource kam padne pe wait time
define('BATCH_REST',    10);             // 🛌 50 pure hone ke baad lamba aaram

// DB Config
define('DB_HOST', 'localhost');
define('DB_NAME', 'copyemoji');
define('DB_USER', 'root');
define('DB_PASS', '');

// Paths
$basePath = realpath(__DIR__ . '/..');
$jsonPath = $basePath . '/assets/data/emoji.json';

// ─── OUTPUT HELPER (For Terminal) ─────────────────────────
function out($msg, $type = 'info') {
    $icons = ['info' => 'ℹ️', 'ok' => '✅', 'err' => '❌', 'wait' => '⏳', 'warn' => '⚠️', 'party' => '🥳'];
    echo ($icons[$type] ?? '') . " " . strip_tags($msg) . PHP_EOL;
}

// ─── DB CONNECT ────────────────────────────────────────────
try {
    $pdo = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    die(out("DB connect fail: " . $e->getMessage(), 'err'));
}

// ─── OLLAMA & PROCESSING FUNCTIONS ─────────────────────────
function callOllama($prompt) {
    $payload = json_encode([
        'model' => OLLAMA_MODEL,
        'prompt' => $prompt,
        'stream' => false,
        'options' => ['temperature' => 0.82, 'num_predict' => 950]
    ]);
    $ch = curl_init(OLLAMA_URL);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT => 300 // ⏳ Timeout badha diya taaki connection cut na ho
    ]);
    $res = curl_exec($ch); 
    curl_close($ch);
    $json = json_decode($res, true);
    return trim($json['response'] ?? '');
}

function cleanMarkdown($text) {
    $text = preg_replace('/^#{1,6}\s+/m', '', $text); // Headings hatao
    $text = preg_replace('/\*{1,2}([^*]+)\*{1,2}/', '$1', $text); // Bolding hatao
    $text = preg_replace('/_{1,2}([^_]+)_{1,2}/',   '$1', $text);
    $text = preg_replace('/^[\-\*•]\s+/m', '', $text);
    $text = preg_replace('/^\d+\.\s+/m', '', $text);
    $text = preg_replace('/`([^`]+)`/', '$1', $text);
    $text = preg_replace('/```[\s\S]*?```/', '', $text);
    return trim($text);
}

// ─── PROMPT & FAQ BUILDERS ─────────────────────────────────
function buildDescriptionPrompt(array $e): string {
    return "Write a description for the {$e['emoji_char']} emoji called \"{$e['name']}\".

OUTPUT FORMAT — VERY IMPORTANT:
- Write exactly 4 plain text paragraphs
- Separate each paragraph with one blank line
- Do NOT use: # headings, ## headings, * bullets, - bullets, **bold**, _italic_, backticks, or any markdown
- Plain sentences only, like a magazine article
- Each paragraph: 3 to 5 sentences
- Total length: 450 to 500 words

CONTENT GUIDE:
- Paragraph 1: Describe what the emoji looks like and its main meaning
- Paragraph 2: How people use it in texting, social media, Instagram, Twitter, WhatsApp
- Paragraph 3: Cultural facts, fun trivia, how it looks on different phones (Apple vs Android)
- Paragraph 4: Practical tips — when to use it and when NOT to use it
- Weave in these words naturally: {$e['keywords']}

Now write the actual description for {$e['emoji_char']} {$e['name']}. Plain paragraphs only — no markdown.";
}

function buildFaqFromData(array $e, string $correctEntity): string {
    $faqs = [
        [
            'q' => "What does {$e['name']} emoji mean?",
            'a' => "The {$e['emoji_char']} {$e['name']} emoji is a popular symbol in the {$e['category']} category. It is widely used across digital platforms to express emotions and add personality to conversations. People use it in texting, social media, and online chats to quickly convey their feelings."
        ],
        [
            'q' => "How is the {$e['name']} emoji used in daily conversations?",
            'a' => "The {$e['emoji_char']} emoji is commonly used in WhatsApp, Instagram, Twitter, and Facebook messages. You can simply copy and paste it into any chat or social media post. It adds a visual, expressive touch that plain text alone cannot convey."
        ],
        [
            'q' => "What is the HTML entity code for {$e['name']} emoji?",
            'a' => "The HTML entity code for the {$e['emoji_char']} emoji is {$correctEntity}. You can use this code directly in any HTML document or web page to display the emoji. It is also supported in CSS content and JavaScript strings."
        ],
        [
            'q' => "Is the {$e['name']} emoji supported on all devices and platforms?",
            'a' => "Yes, the {$e['emoji_char']} emoji is supported across iOS, Android, Windows, macOS, and most modern browsers. The visual design may look slightly different on Apple devices compared to Google or Samsung, but the meaning stays the same across all platforms."
        ],
        [
            'q' => "Which Unicode version introduced the {$e['name']} emoji?",
            'a' => "The {$e['emoji_char']} {$e['name']} emoji was introduced in Unicode version {$e['unicode_ver']}. It belongs to the {$e['category']} category in the official Unicode emoji standard. Since its introduction, it has become one of the recognizable symbols in digital communication."
        ],
    ];
    return json_encode($faqs, JSON_UNESCAPED_UNICODE);
}

// ─── QUERY PREPARATION ─────────────────────────────────────
$updateEmoji = $pdo->prepare("UPDATE emoji_content SET description = :desc, faq_json = :faq, status = 'generated', error_msg = NULL WHERE slug = :slug");
$updateStatus = $pdo->prepare("UPDATE runner_status SET current_slug = :slug WHERE id = 1");

// ─── MAHA-LOOP (Runs until DB is empty) ────────────────────
out("Night Rider Mode Started! Ab tu so ja, main dekh lunga... 🌙💻", 'ok');

while (true) {
    // Har baar naye 50 pending items fetch karo
    $pending = $pdo->query("SELECT * FROM emoji_content WHERE status = 'pending' ORDER BY id ASC LIMIT ".BATCH_LIMIT)->fetchAll();

    // Agar kuch nahi bacha, toh loop tod do
    if (empty($pending)) {
        $pdo->query("UPDATE runner_status SET current_slug = NULL WHERE id = 1");
        out("SAB DONE HAI BHAI! 🥳 Pura database complete ho gaya. Ab aaram se Phase 2 chalao.", 'party');
        break; // Loop se bahar
    }

    out("\n---------------------------------------------------", 'info');
    out("Naya Batch Start: " . count($pending) . " Emojis uthaye hain.", 'info');
    out("---------------------------------------------------", 'info');

    foreach ($pending as $e) {
        $updateStatus->execute([':slug' => $e['slug']]);
        out("Working on: [{$e['slug']}] {$e['emoji_char']}", 'wait');

        // 1. Description Generate karo (With Retry Logic)
        $descRaw = '';
        while (true) {
            $descRaw = callOllama(buildDescriptionPrompt($e));

            if (!empty($descRaw)) {
                break; // Success mil gaya, loop tod do
            }
            
            // Agar response khali hai matlab resource kam pad gaye
            out("GPU overload! ".RETRY_SLEEP." seconds wait kar rahe hain...", 'warn');
            sleep(RETRY_SLEEP); // Cool down before trying the SAME emoji again
        }

        // 2. Formatting
        $descClean = cleanMarkdown($descRaw);
        $paras = array_filter(array_map('trim', explode("\n\n", $descClean)));
        $descHtml = "";
        foreach($paras as $p) {
            if (strlen($p) > 30) {
                $descHtml .= "<p style='color: var(--muted); line-height: 1.8; font-size: 16px; margin-bottom: 15px;'>".htmlspecialchars($p, ENT_QUOTES, 'UTF-8')."</p>";
            }
        }

        // 3. HTML Entity nikaalo
        $entityParts = [];
        foreach (mb_str_split($e['emoji_char'], 1, 'UTF-8') as $char) {
            $entityParts[] = '&#' . mb_ord($char, 'UTF-8') . ';';
        }
        $correctEntity = implode('', $entityParts);

        // 4. 5-Question FAQ JSON generate karo
        $faqJson = buildFaqFromData($e, $correctEntity);

        // 5. DB mein Save karo
        $updateEmoji->execute([
            ':desc' => $descHtml,
            ':faq'  => $faqJson,
            ':slug' => $e['slug']
        ]);

        out("Saved: {$e['slug']} ✅", 'ok');
        sleep(SLEEP_SEC); // 🌬️ Normal GPU rest
    }

    // 50 pure hone ke baad lamba aaram
    out("Batch complete! Agle 50 uthane se pehle ".BATCH_REST." seconds ka rest... 🛌", 'wait');
    sleep(BATCH_REST);
}

?>