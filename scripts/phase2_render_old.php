<?php
/**
 * ============================================================
 * CopyEmoji.in — PHASE 2: FINAL RENDER (Safe Sitemap + Original UI) 🚀
 * ============================================================
 */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ─── DB CONFIG ─────────────────────────────────────────────
define('DB_HOST', 'localhost');
define('DB_NAME', 'copyemoji');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER, DB_PASS,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
    );
} catch (PDOException $e) {
    die("DB fail: " . $e->getMessage());
}

// ─── PATHS ─────────────────────────────────────────────────
$basePath    = realpath(__DIR__ . '/..');
$jsonPath    = $basePath . '/assets/data/emoji.json';
$emojiDir    = $basePath . '/emoji';
$categoryDir = $basePath . '/category';
$sitemapPath = $basePath . '/sitemap.xml';

if (!file_exists($jsonPath)) die("emoji.json nahi mila!");
$data = json_decode(file_get_contents($jsonPath), true);
if (!is_array($data)) die("Invalid JSON!");

if (!file_exists($emojiDir))    mkdir($emojiDir,    0777, true);
if (!file_exists($categoryDir)) mkdir($categoryDir, 0777, true);

// ─── DB CONTENT LOADER ────────────────────────────────────
$dbContent = [];
$rows = $pdo->query("
    SELECT slug, description, faq_json
    FROM emoji_content
    WHERE status = 'generated' AND description IS NOT NULL
")->fetchAll();
foreach ($rows as $row) {
    $dbContent[$row['slug']] = $row;
}

$totalInDb = count($dbContent);
echo "DB mein generated descriptions: <strong>$totalInDb</strong><br>";

// ─── SMART SITEMAP EXTRACTOR (Kaomoji Saver) 🛡️ ───────────
$sitemapData = [];
if (file_exists($sitemapPath)) {
    $existingXml = simplexml_load_file($sitemapPath);
    if ($existingXml !== false) {
        foreach ($existingXml->url as $node) {
            $loc = (string)$node->loc;
            
            // Check if URL is strictly an emoji or emoji-category page
            $isEmojiPage = preg_match('#^https?://[^/]+/emoji/#', $loc);
            $isCategoryPage = preg_match('#^https?://[^/]+/category/#', $loc);
            
            // Agar emoji ya category page NAHI hai, toh usko preserve karo (Kaomoji, About, etc. safe rahenge)
            if (!$isEmojiPage && !$isCategoryPage) {
                $sitemapData[] = [
                    "url"      => $loc,
                    "lastmod"  => (string)$node->lastmod,
                    "freq"     => (string)$node->changefreq,
                    "priority" => (string)$node->priority
                ];
            }
        }
    }
}

// Agar in case sitemap khali ho, toh default static pages daal do
if (empty($sitemapData)) {
    $sitemapData = [
        ["url" => "https://copyemoji.in/",          "lastmod" => date('Y-m-d'), "freq" => "daily",   "priority" => "1.0"],
        ["url" => "https://copyemoji.in/kaomoji",   "lastmod" => date('Y-m-d'), "freq" => "weekly",  "priority" => "0.9"],
        ["url" => "https://copyemoji.in/about",     "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.8"],
        ["url" => "https://copyemoji.in/contact",   "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.8"],
        ["url" => "https://copyemoji.in/privacy",   "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.6"],
        ["url" => "https://copyemoji.in/terms",     "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.6"],
        ["url" => "https://copyemoji.in/disclaimer","lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.6"],
        ["url" => "https://copyemoji.in/features",  "lastmod" => "2026-02-15", "freq" => "monthly", "priority" => "0.7"],
    ];
}

// ─── HELPER FUNCTIONS ─────────────────────────────────────
function getEmojiCodepoints(string $emojiStr): string {
    $chars = mb_str_split($emojiStr, 1, 'UTF-8');
    $codepoints = [];
    foreach ($chars as $char) {
        $hex = strtoupper(dechex(mb_ord($char, 'UTF-8')));
        $codepoints[] = "U+" . str_pad($hex, 4, '0', STR_PAD_LEFT);
    }
    return implode(" ", $codepoints);
}

function getHtmlEntity(string $emojiStr): string {
    $chars = mb_str_split($emojiStr, 1, 'UTF-8');
    $entities = [];
    foreach ($chars as $char) {
        $entities[] = "&amp;#" . mb_ord($char, 'UTF-8') . ";";
    }
    return implode(" ", $entities);
}

function getDescription(string $slug, array $dbContent, string $name, string $emojiChar, string $category, string $unicode, string $version): string {
    if (isset($dbContent[$slug]) && !empty($dbContent[$slug]['description'])) {
        return $dbContent[$slug]['description']; 
    }
    $p1 = ["The $emojiChar $name emoji is a universally recognized symbol that belongs to the $category category.", "A classic $emojiChar $name emoji, widely used to express sentiments related to the $category group."];
    $p2 = ["Traditionally, this ideograph is used across cultures to convey strong emotions without long text.", "It is frequently used to express positive sentiments, making it a staple on social messaging platforms."];
    $p3 = ["This is historically one of the most popular emojis in its category, seen in daily chats.", "Users across the globe rely on this emoji to add tone, personality, and visual flair to their texts."];
    $p4 = ["The $name was officially approved as part of Unicode $unicode and later added to Emoji $version.", "This character was accepted into Unicode $unicode and rolled out to mobile devices in Emoji $version."];
    $wrap = fn($t) => "<p style='color: var(--muted); line-height: 1.8; font-size: 16px; margin-bottom: 15px;'>$t</p>";
    return $wrap($p1[array_rand($p1)] . " " . $p2[array_rand($p2)]) . $wrap($p3[array_rand($p3)]) . $wrap($p4[array_rand($p4)]);
}

function getFaqHtml(string $slug, array $dbContent, string $name, string $emojiChar, string $htmlEntity): string {
    if (isset($dbContent[$slug]) && !empty($dbContent[$slug]['faq_json'])) {
        $faqs = json_decode($dbContent[$slug]['faq_json'], true);
        if (is_array($faqs) && count($faqs) >= 3) {
            $html = '';
            foreach ($faqs as $faq) {
                $q = htmlspecialchars($faq['q'] ?? '', ENT_QUOTES, 'UTF-8');
                $a = htmlspecialchars($faq['a'] ?? '', ENT_QUOTES, 'UTF-8');
                $html .= "<h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>$q</h3>
                    <p style='color: var(--muted); margin-bottom: 20px; line-height: 1.6;'>$a</p>";
            }
            return $html;
        }
    }
    return "
    <h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>What does $name emoji mean?</h3>
    <p style='color: var(--muted); margin-bottom: 20px; line-height: 1.6;'>The $name emoji is a widely used symbol.</p>
    <h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>What is the HTML entity for $name emoji?</h3>
    <p style='color: var(--muted); margin-bottom: 20px; line-height: 1.6;'>The HTML entity code is $htmlEntity.</p>
    <h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>How to download $name emoji PNG?</h3>
    <p style='color: var(--muted); margin-bottom: 0; line-height: 1.6;'>Click the 'Download PNG' button to get a transparent HD image.</p>";
}

function getFaqSchema(string $slug, array $dbContent, string $name, string $emojiChar, string $htmlEntity): string {
    if (isset($dbContent[$slug]) && !empty($dbContent[$slug]['faq_json'])) {
        $faqs = json_decode($dbContent[$slug]['faq_json'], true);
        if (is_array($faqs) && count($faqs) >= 3) {
            $entities = [];
            foreach ($faqs as $faq) {
                $entities[] = [
                    "@type"          => "Question",
                    "name"           => $faq['q'] ?? '',
                    "acceptedAnswer" => ["@type" => "Answer", "text" => $faq['a'] ?? '']
                ];
            }
            return json_encode(["@context" => "https://schema.org", "@type" => "FAQPage", "mainEntity" => $entities]);
        }
    }
    return json_encode(["@context" => "https://schema.org", "@type" => "FAQPage", "mainEntity" => [
        ["@type" => "Question", "name" => "What does $name emoji mean?", "acceptedAnswer" => ["@type" => "Answer", "text" => "The $name emoji ($emojiChar) is a widely used symbol."]],
        ["@type" => "Question", "name" => "What is the HTML entity for $name emoji?", "acceptedAnswer" => ["@type" => "Answer", "text" => "The HTML entity code for $emojiChar is $htmlEntity."]],
    ]]);
}

// ─── HEADER & FOOTER ───────────────────────────────────────
$headerHtml = '
<header class="navbar">
    <div class="logo-area">
        <a href="/" style="text-decoration: none; color: inherit;">
            <h1>😊 CopyEmoji<span class="highlight">.in</span></h1>
        </a>
    </div>
    <div class="controls">
        <a href="/" class="kaomoji-nav-btn" aria-label="Go to Emojis">😀 Emojis</a>
        <a href="/kaomoji" class="kaomoji-nav-btn" aria-label="Go to Kaomoji">🎌 Kaomoji</a>
        <div class="skin-tone-selector">
            <button class="tone-btn active" data-tone="default" title="Default">✋</button>
            <button class="tone-btn" data-tone="light" title="Light">✋🏻</button>
            <button class="tone-btn" data-tone="medium-light" title="Medium-Light">✋🏼</button>
            <button class="tone-btn" data-tone="medium" title="Medium">✋🏽</button>
            <button class="tone-btn" data-tone="medium-dark" title="Medium-Dark">✋🏾</button>
            <button class="tone-btn" data-tone="dark" title="Dark">✋🏿</button>
        </div>
        <button id="theme-toggle" aria-label="Toggle Dark Mode">🌙</button>
    </div>
</header>';

$footerHtml = '
<footer class="footer">
    <div class="footer-links" style="margin-bottom: 20px;">
        <a href="/features" style="margin: 0 10px; color: var(--muted); text-decoration: none; font-weight: 500;">Features</a>
        <a href="/about" style="margin: 0 10px; color: var(--muted); text-decoration: none; font-weight: 500;">About Us</a>
        <a href="/contact" style="margin: 0 10px; color: var(--muted); text-decoration: none; font-weight: 500;">Contact Us</a>
        <a href="/privacy" style="margin: 0 10px; color: var(--muted); text-decoration: none; font-weight: 500;">Privacy Policy</a>
        <a href="/terms" style="margin: 0 10px; color: var(--muted); text-decoration: none; font-weight: 500;">Terms</a>
        <a href="/disclaimer" style="margin: 0 10px; color: var(--muted); text-decoration: none; font-weight: 500;">Disclaimer</a>
    </div>
    <p>&copy; <span id="year"></span> <strong>CopyEmoji.in</strong> • Crafted with ❤️ by <span class="author-name">Saim Khalifa</span></p>
</footer>';

// ─── DB UPDATE STATEMENT ───────────────────────────────────
$markDoneStmt = $pdo->prepare("UPDATE emoji_content SET html_updated = 1 WHERE slug = :slug");

echo "<h2>🚀 Phase 2: FULL SITE RENDER Start!</h2>";

$htmlCount = 0;
$catCount  = 0;

// ─── MAIN LOOP ─────────────────────────────────────────────
foreach ($data as $catObj) {
    $categoryName = $catObj['name']  ?? 'General';
    $categorySlug = str_replace('_', '-', $catObj['slug'] ?? 'general');
    $emojisList   = $catObj['emojis'] ?? [];
    $categoryGridHtml = '';

    foreach ($emojisList as $e) {
        $emojiChar  = $e['emoji'] ?? '';
        $slugRaw    = str_replace('_', '-', $e['slug'] ?? '');
        $name       = ucwords(str_replace('-', ' ', $slugRaw));
        $unicode    = $e['unicode_version'] ?? '1.0';
        $version    = $e['emoji_version']   ?? '1.0';
        $shortcode  = ":" . ($e['slug'] ?? '') . ":";

        $emojiCodepoints = getEmojiCodepoints($emojiChar);
        $htmlEntity      = getHtmlEntity($emojiChar);
        $keywordWords    = array_merge(
            explode(" ", strtolower($name)),
            [strtolower(str_replace(" & ", ", ", $categoryName)), "emoji"]
        );
        $autoKeywords = implode(", ", $keywordWords);

        // ── DB se ya fallback ──
        $richDescription = getDescription($slugRaw, $dbContent, $name, $emojiChar, $categoryName, $unicode, $version);
        $faqHtml         = getFaqHtml($slugRaw, $dbContent, $name, $emojiChar, $htmlEntity);
        $faqJsonLd       = getFaqSchema($slugRaw, $dbContent, $name, $emojiChar, $htmlEntity);
        $metaDesc        = "Copy the $name emoji ($emojiChar). Meaning, HTML Entity ($htmlEntity), Codepoints ($emojiCodepoints), Keywords: $autoKeywords.";

        // 🔥 YAHAN LAGA HAI ORIGINAL COPY BUTTON WALA LOGIC 🔥
        $categoryGridHtml .= "
        <div class='emoji-item' onclick='copyEmojiMain(\"$emojiChar\")' title='Copy $name' style='cursor:pointer; position:relative;'>
            <a href='/emoji/$slugRaw' class='info-btn' title='View Details' target='_blank' onclick='event.stopPropagation()' style='position:absolute; top:5px; right:5px; text-decoration:none; font-size:14px; opacity:0.6; transition:opacity 0.2s;'>ℹ️</a>
            <div class='emoji-char'>$emojiChar</div>
            <button class='download-btn' style='pointer-events:none; background:rgba(99,102,241,0.1); color:#4338ca; border:1px solid rgba(99,102,241,0.2); border-radius:8px; padding:6px 0; width:100%; font-size:12px; font-weight:700;'>Copy</button>
        </div>";

        // Related emojis for single pages
        $relatedHtml = '';
        if (count($emojisList) > 1) {
            $relatedKeys = array_rand($emojisList, min(12, count($emojisList)));
            foreach ((array)$relatedKeys as $rk) {
                if ($emojisList[$rk]['slug'] === $e['slug']) continue;
                $relChar = $emojisList[$rk]['emoji'];
                $relSlug = str_replace('_', '-', $emojisList[$rk]['slug']);
                $relatedHtml .= "
                <a href='/emoji/$relSlug' class='emoji-item' style='text-decoration:none;'>
                    <div class='emoji-char'>$relChar</div>
                    <div class='download-btn' style='text-align:center;'>View</div>
                </a>";
            }
        }

        // ── FULL HTML FOR SINGLE EMOJI PAGE ──
        $fullHtml = "<!DOCTYPE html>
<html lang='en'>
<head>
<script src='https://quge5.com/88/tag.min.js' data-zone='227917' async data-cfasync='false'></script>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$name Emoji $emojiChar - HTML Entity, Meaning & Copy</title>
    <meta name='description' content='$metaDesc'>
    <meta name='keywords' content='$autoKeywords'>
    <link rel='manifest' href='/manifest.json'>
    <meta name='theme-color' content='#6366f1'>
    <link rel='canonical' href='https://copyemoji.in/emoji/$slugRaw'>
    <link rel='stylesheet' href='/assets/css/style.css'>
    <script>(function(){var t=localStorage.getItem('theme'),s=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&s))document.documentElement.classList.add('dark-early');})();</script>
    <style>html.dark-early body{background:#0f172a;color:#f8fafc;}</style>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link rel='preload' as='style' href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap' onload=\"this.onload=null;this.rel='stylesheet'\">
    <noscript><link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap'></noscript>
    <script type='application/ld+json'>$faqJsonLd</script>
    <style>
        body, .content-box, .technical-box, .faq-box, .emoji-item {
            transition: background 0.5s ease-in-out, color 0.5s ease-in-out, border-color 0.5s ease-in-out, box-shadow 0.5s ease-in-out !important;
        }
        .related-emoji-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 16px; margin-top: 20px; }
        .btn-secondary { background: transparent; color: var(--text); border: 2px solid var(--primary); box-shadow: none; }
        .btn-secondary:hover { background: var(--primary); color: white; box-shadow: 0 6px 20px rgba(99, 102, 241, 0.4); }
    </style>
</head>
<body>
    $headerHtml
    <main class='main-wrapper'>
        <div class='content-box'>
            <div style='text-align:center;'>
                <div style='font-size: 120px; margin-bottom: 20px;'>$emojiChar</div>
                <h1 style='font-size: 32px; margin-bottom: 20px; color: var(--text);'>$name Emoji Meaning</h1>
                <div style='display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-bottom: 10px;'>
                    <button class='submit-btn' onclick='copyEmojiMain(\"$emojiChar\")' style='width:auto; padding:15px 40px; font-size:20px; margin-top:0;'>Copy $emojiChar</button>
                    <button class='submit-btn btn-secondary' onclick='downloadEmojiPNG(\"$emojiChar\", \"$slugRaw\")' style='width:auto; padding:15px 40px; font-size:20px; margin-top:0;'>⬇️ Download PNG</button>
                </div>
            </div>

            <section style='text-align: left; margin-top: 50px;'>$richDescription</section>

            <section style='text-align: left; margin-top: 40px;'>
                <h2 style='color: var(--primary); font-size: 24px; margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px;'>Technical Information</h2>
                <div class='technical-box' style='background: var(--bg); padding: 20px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);'>
                    <ul style='list-style:none; padding:0; color:var(--text); line-height: 2;'>
                        <li><strong>Emoji:</strong> <span style='font-size: 24px;'>$emojiChar</span></li>
                        <li><strong>Emoji Name:</strong> $name</li>
                        <li><strong>Codepoints:</strong> <code>$emojiCodepoints</code></li>
                        <li><strong>HTML Entity:</strong> <code>$htmlEntity</code></li>
                        <li><strong>Shortcodes:</strong> <code>$shortcode</code></li>
                        <li><strong>Keywords:</strong> $autoKeywords</li>
                        <li><strong>Category:</strong> <a href='/category/$categorySlug' style='color:var(--primary); text-decoration:none;'>$categoryName</a></li>
                        <li><strong>Unicode Version:</strong> $unicode</li>
                    </ul>
                </div>
            </section>

            <section style='text-align: left; margin-top: 50px;'>
                <h2 style='color: var(--primary); font-size: 24px; margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px;'>Frequently Asked Questions</h2>
                <div class='faq-box' style='background: var(--bg); padding: 20px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);'>
                    $faqHtml
                </div>
            </section>

            <section style='text-align: left; margin-top: 50px;'>
                <h2 style='color: var(--primary); font-size: 24px; margin-bottom: 20px;'>Related Emojis</h2>
                <div class='related-emoji-grid'>$relatedHtml</div>
            </section>
        </div>
    </main>
    <div id='toast' class='toast'>Copied!</div>
    $footerHtml
    <script src='/assets/js/main.js?v=1.3' defer></script>
    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
        function copyEmojiMain(char) {
            navigator.clipboard.writeText(char);
            const toast = document.getElementById('toast');
            toast.innerText = char + ' Copied!';
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 2000);
        }
        function downloadEmojiPNG(char, slug) {
            const canvas = document.createElement('canvas'); canvas.width = 512; canvas.height = 512;
            const ctx = canvas.getContext('2d'); ctx.clearRect(0, 0, 512, 512);
            ctx.font = '400px \"Outfit\", \"Segoe UI Emoji\", \"Apple Color Emoji\", sans-serif';
            ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(char, 256, 290);
            const url = canvas.toDataURL('image/png');
            const a = document.createElement('a'); a.href = url; a.download = slug + '-emoji.png';
            document.body.appendChild(a); a.click(); document.body.removeChild(a);
            const toast = document.getElementById('toast'); toast.innerText = 'Downloading PNG... ⬇️';
            toast.classList.add('show'); setTimeout(() => { toast.classList.remove('show'); }, 2000);
        }
    </script>
</body>
</html>";

        file_put_contents("$emojiDir/$slugRaw.html", $fullHtml);
        $markDoneStmt->execute([':slug' => $slugRaw]); 
        
        $sitemapData[] = ["url" => "https://copyemoji.in/emoji/$slugRaw", "freq" => "weekly", "priority" => "0.7", "lastmod" => date('Y-m-d')];
        $htmlCount++;
    }

    // 🔥 CATEGORY PAGE MEIN BHI SAARI SCRIPTS AUR TOAST WAPAS LA DIYA 🔥
    $catMetaDesc = "Browse and copy all $categoryName emojis instantly. Discover meanings, Unicode info, and download PNGs for emojis in the $categoryName category.";
    $catPageHtml = "<!DOCTYPE html>
<html lang='en'>
<head>
<script src='https://quge5.com/88/tag.min.js' data-zone='227917' async data-cfasync='false'></script>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$categoryName Emojis - Copy & Paste Instantly 🚀</title>
    <meta name='description' content='$catMetaDesc'>
    <link rel='canonical' href='https://copyemoji.in/category/$categorySlug'>
    <link rel='manifest' href='/manifest.json'>
    <meta name='theme-color' content='#6366f1'>
    <link rel='stylesheet' href='/assets/css/style.css'>
    <script>(function(){var t=localStorage.getItem('theme'),s=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&s))document.documentElement.classList.add('dark-early');})();</script>
    <style>html.dark-early body{background:#0f172a;color:#f8fafc;}</style>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link rel='preload' as='style' href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap' onload=\"this.onload=null;this.rel='stylesheet'\">
    <noscript><link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap'></noscript>
    <style>
        body, .content-box, .emoji-item { transition: background 0.5s ease-in-out, color 0.5s ease-in-out, border-color 0.5s ease-in-out, box-shadow 0.5s ease-in-out !important; }
        .category-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(100px, 1fr)); gap: 16px; margin-top: 30px; }
    </style>
</head>
<body>
    $headerHtml
    <main class='main-wrapper'>
        <div class='content-box' style='max-width: 1000px; padding: 40px;'>
            <h1 style='color: var(--text); font-size: 36px; text-align: center;'>$categoryName Emojis</h1>
            <p style='color: var(--muted); text-align: center; font-size: 18px; margin-top: 10px;'>Click any emoji to view its details, meaning, and copy it.</p>
            <div class='category-grid'>
                $categoryGridHtml
            </div>
        </div>
    </main>
    <div id='toast' class='toast'>Copied!</div>
    $footerHtml
    <script src='/assets/js/main.js?v=1.3' defer></script>
    <script>
        document.getElementById('year').textContent = new Date().getFullYear();
        function copyEmojiMain(char) {
            navigator.clipboard.writeText(char);
            const toast = document.getElementById('toast');
            toast.innerText = char + ' Copied!';
            toast.classList.add('show');
            setTimeout(() => { toast.classList.remove('show'); }, 2000);
        }
    </script>
</body>
</html>";

    file_put_contents("$categoryDir/$categorySlug.html", $catPageHtml);
    
    $sitemapData[] = ["url" => "https://copyemoji.in/category/$categorySlug", "freq" => "weekly", "priority" => "0.8", "lastmod" => date('Y-m-d')];
    $catCount++;
}

// ─── FINAL SITEMAP GENERATION ──────────────────────────────
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach ($sitemapData as $item) {
    $lastmod = $item['lastmod'] ?? date('Y-m-d');
    $xml .= "  <url>\n    <loc>{$item['url']}</loc>\n    <lastmod>{$lastmod}</lastmod>\n    <changefreq>{$item['freq']}</changefreq>\n    <priority>{$item['priority']}</priority>\n  </url>\n";
}
$xml .= '</urlset>';
file_put_contents($sitemapPath, $xml);

// ─── REPORTING ───────────────────────────────────────────
echo "<h2>✅ Poori Site Render Ho Gayi Bhai (With Original UI)!</h2>";
echo "<p>HTML files updated: <strong>$htmlCount</strong></p>";
echo "<p>Category pages updated: <strong>$catCount</strong></p>";
echo "<p style='color:green;'><strong>Category UI & Sitemap restored successfully!</strong></p>";
echo "<p>👉 Ab tu bindaas Cloudflare pe push kar sakta hai!</p>";
?>