<?php
set_time_limit(0);
error_reporting(E_ALL);

$basePath = realpath(__DIR__ . "/..");
$jsonPath = $basePath . "/assets/data/emoji.json";
$emojiDir = $basePath . "/emoji";
$categoryDir = $basePath . "/category";
$sitemapPath = $basePath . "/sitemap.xml";

// 1. Data Load
if (!file_exists($jsonPath)) die("Bhai, emoji.json nahi mila!");
$data = json_decode(file_get_contents($jsonPath), true);
if (!is_array($data)) die("Invalid JSON Format!");

// Folders create karna
if (!file_exists($emojiDir)) mkdir($emojiDir, 0777, true);
if (!file_exists($categoryDir)) mkdir($categoryDir, 0777, true);

// 2. Exact Header & Footer
$headerHtml = '
<header class="navbar">
    <div class="logo-area">
        <a href="/" style="text-decoration: none; color: inherit;">
            <h1>😊 CopyEmoji<span class="highlight">.in</span></h1>
        </a>
    </div>
    <div class="controls">
        <a href="/kaomoji" class="kaomoji-nav-btn" aria-label="Go to Kaomoji">🎌 Kaomoji</a>
        <div class="skin-tone-selector">
            <button class="tone-btn active" data-tone="default" aria-label="Default Skin Tone" title="Default">✋</button>
            <button class="tone-btn" data-tone="light" aria-label="Light Skin Tone" title="Light">✋🏻</button>
            <button class="tone-btn" data-tone="medium-light" aria-label="Medium Light Skin Tone" title="Medium-Light">✋🏼</button>
            <button class="tone-btn" data-tone="medium" aria-label="Medium Skin Tone" title="Medium">✋🏽</button>
            <button class="tone-btn" data-tone="medium-dark" aria-label="Medium Dark Skin Tone" title="Medium-Dark">✋🏾</button>
            <button class="tone-btn" data-tone="dark" aria-label="Dark Skin Tone" title="Dark">✋🏿</button>
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

// 3. ✨ JADUI FUNCTIONS ✨
function getEmojiCodepoints($emojiStr) {
    $chars = mb_str_split($emojiStr, 1, 'UTF-8');
    $codepoints = [];
    foreach ($chars as $char) {
        $hex = strtoupper(dechex(mb_ord($char, 'UTF-8')));
        $codepoints[] = "U+" . str_pad($hex, 4, '0', STR_PAD_LEFT);
    }
    return implode(" ", $codepoints);
}

function getHtmlEntity($emojiStr) {
    $chars = mb_str_split($emojiStr, 1, 'UTF-8');
    $entities = [];
    foreach ($chars as $char) {
        $dec = mb_ord($char, 'UTF-8');
        $entities[] = "&amp;#" . $dec . ";";
    }
    return implode(" ", $entities);
}

function getLongDescription($name, $emoji, $category, $unicode, $version) {
    $p1 = ["The $emoji $name emoji is a universally recognized symbol that belongs to the $category category.", "A classic $emoji $name emoji. It is widely used to express sentiments and ideas related to the $category group."];
    $p2 = ["Traditionally, this ideograph is used across various cultures to convey strong emotions and context without the need for long text.", "It is frequently used to express positive or specific sentiments, making it a staple on social messaging platforms."];
    $p3 = ["This is historically one of the most popular emojis in its category, frequently seen in daily chats and public posts.", "Users across the globe rely on this specific emoji to add tone, personality, and visual flair to their daily texts."];
    $p4 = ["The $name was officially approved as part of Unicode $unicode and later added to Emoji $version.", "From a technical standpoint, this character was accepted into Unicode $unicode and rolled out to mobile devices in Emoji $version."];

    return "<p style='color: var(--muted); line-height: 1.8; font-size: 16px; margin-bottom: 15px;'>" . $p1[array_rand($p1)] . " " . $p2[array_rand($p2)] . "</p><p style='color: var(--muted); line-height: 1.8; font-size: 16px; margin-bottom: 15px;'>" . $p3[array_rand($p3)] . "</p><p style='color: var(--muted); line-height: 1.8; font-size: 16px; margin-bottom: 15px;'>" . $p4[array_rand($p4)] . "</p>";
}

// 4. ORIGINAL SITEMAP DATA
$sitemapData = [
    ["url" => "https://copyemoji.in/", "lastmod" => "2026-02-19", "freq" => "daily", "priority" => "1.0"],
    ["url" => "https://copyemoji.in/kaomoji", "lastmod" => "2026-02-19", "freq" => "weekly", "priority" => "0.9"],
    ["url" => "https://copyemoji.in/about", "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.8"],
    ["url" => "https://copyemoji.in/contact", "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.8"],
    ["url" => "https://copyemoji.in/privacy", "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.6"],
    ["url" => "https://copyemoji.in/terms", "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.6"],
    ["url" => "https://copyemoji.in/disclaimer", "lastmod" => "2025-02-13", "freq" => "monthly", "priority" => "0.6"],
    ["url" => "https://copyemoji.in/features", "lastmod" => "2026-02-15", "freq" => "monthly", "priority" => "0.7"]
];

echo "Bhai, Smooth UI Engine Start! 🚀<br>";

// 5. GENERATION LOOP (Emoji + Category)
foreach ($data as $catObj) {
    $categoryName = $catObj['name'] ?? 'General';
    $categorySlug = str_replace('_', '-', $catObj['slug'] ?? 'general');
    $emojisList = $catObj['emojis'] ?? [];
    
    $categoryGridHtml = "";

    foreach ($emojisList as $e) {
        $emojiChar = $e['emoji'] ?? '';
        $slugRaw = str_replace('_', '-', $e['slug'] ?? '');
        $name = ucwords(str_replace('-', ' ', $slugRaw));
        $unicode = $e['unicode_version'] ?? '1.0';
        $version = $e['emoji_version'] ?? '1.0';
        $skinSupport = (isset($e['skin_tone_support']) && $e['skin_tone_support']) ? 'Yes' : 'No';
        $shortcode = ":" . ($e['slug'] ?? '') . ":";
        
      // ✨ NAYA LOGIC: Category page cards now copy on click, and have 'i' button for details ✨
        $categoryGridHtml .= "
        <div class='emoji-item' onclick='copyEmojiMain(\"$emojiChar\")' title='Copy $name' style='cursor:pointer; position:relative;'>
            <a href='/emoji/$slugRaw' class='info-btn' title='View Details' target='_blank' onclick='event.stopPropagation()' style='position:absolute; top:5px; right:5px; text-decoration:none; font-size:14px; opacity:0.6; transition:opacity 0.2s;'>ℹ️</a>
            <div class='emoji-char'>$emojiChar</div>
            <button class='download-btn' style='pointer-events:none; background:rgba(99,102,241,0.1); color:#4338ca; border:1px solid rgba(99,102,241,0.2); border-radius:8px; padding:6px 0; width:100%; font-size:12px; font-weight:700;'>Copy</button>
        </div>";

        $emojiCodepoints = getEmojiCodepoints($emojiChar);
        $htmlEntity = getHtmlEntity($emojiChar);
        
        $keywordWords = explode(" ", strtolower($name));
        $keywordWords[] = strtolower(str_replace(" & ", ", ", $categoryName));
        $keywordWords[] = "emoji";
        $autoKeywords = implode(", ", $keywordWords);

        $richDescription = getLongDescription($name, $emojiChar, $categoryName, $unicode, $version);
        $metaDesc = "Copy the $name emoji ($emojiChar). Find its Meaning, HTML Entity ($htmlEntity), Codepoints ($emojiCodepoints), and Keywords ($autoKeywords).";

        $relatedHtml = "";
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

        $faqJson = json_encode([
            "@context" => "https://schema.org",
            "@type" => "FAQPage",
            "mainEntity" => [
                ["@type" => "Question", "name" => "What does $name emoji mean?", "acceptedAnswer" => ["@type" => "Answer", "text" => "The $name emoji is a widely used symbol in the $categoryName category, often used to express related sentiments."]],
                ["@type" => "Question", "name" => "What is the HTML entity for $name emoji?", "acceptedAnswer" => ["@type" => "Answer", "text" => "The HTML entity code for the $emojiChar emoji is $htmlEntity."]],
                ["@type" => "Question", "name" => "How to download $name emoji PNG?", "acceptedAnswer" => ["@type" => "Answer", "text" => "You can click the 'Download PNG' button to get a transparent HD image of the $emojiChar emoji."]]
            ]
        ]);

        // Full HTML for Single Emoji
        $fullHtml = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$name Emoji $emojiChar - HTML Entity, Meaning & Copy</title>
    <meta name='description' content='$metaDesc'>
    <meta name='keywords' content='$autoKeywords'>
    <link rel='canonical' href='https://copyemoji.in/emoji/$slugRaw'>
    <link rel='stylesheet' href='../assets/css/style.css'>
    <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap' rel='stylesheet'>
    <script type='application/ld+json'>$faqJson</script>
    <style>
        /* 🔥 THE MAGIC UI FIX: Slowed down transition (0.5s ease-in-out) to mimic the heavy DOM of index.html 🔥 */
        /* Removed specific text tags to prevent text color glitching before background changes */
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
                    <h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>What does $name emoji mean?</h3>
                    <p style='color: var(--muted); margin-bottom: 20px; line-height: 1.6;'>The $name emoji is a widely used symbol in the $categoryName category.</p>
                    <h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>What is the HTML entity for $name emoji?</h3>
                    <p style='color: var(--muted); margin-bottom: 20px; line-height: 1.6;'>The HTML entity code for the $emojiChar emoji is $htmlEntity.</p>
                    <h3 style='font-size: 18px; margin-bottom: 8px; color: var(--text);'>How to download $name emoji PNG?</h3>
                    <p style='color: var(--muted); margin-bottom: 0; line-height: 1.6;'>You can click the 'Download PNG' button to get a transparent HD image of the $emojiChar emoji.</p>
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
    <script src='../assets/js/main.js'></script>
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
        $sitemapData[] = ["url" => "https://copyemoji.in/emoji/$slugRaw", "freq" => "weekly", "priority" => "0.7"];
    }

    // ✨ CATEGORY PAGE HTML ✨
    $catMetaDesc = "Browse and copy all $categoryName emojis instantly. Discover meanings, Unicode info, and download PNGs for emojis in the $categoryName category.";
    
    $catPageHtml = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$categoryName Emojis - Copy & Paste Instantly 🚀</title>
    <meta name='description' content='$catMetaDesc'>
    <link rel='canonical' href='https://copyemoji.in/category/$categorySlug'>
    <link rel='stylesheet' href='../assets/css/style.css'>
    <link href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap' rel='stylesheet'>
    <style>
        /* 🔥 THE MAGIC UI FIX FOR CATEGORY PAGES 🔥 */
        body, .content-box, .emoji-item { 
            transition: background 0.5s ease-in-out, color 0.5s ease-in-out, border-color 0.5s ease-in-out, box-shadow 0.5s ease-in-out !important; 
        }
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
    $footerHtml
    <script src='../assets/js/main.js'></script>
    <script>document.getElementById('year').textContent = new Date().getFullYear();</script>
</body>
</html>";

    file_put_contents("$categoryDir/$categorySlug.html", $catPageHtml);
    $sitemapData[] = ["url" => "https://copyemoji.in/category/$categorySlug", "freq" => "weekly", "priority" => "0.8"];
}

// 6. SITEMAP GENERATOR
$xml = '<?xml version="1.0" encoding="UTF-8"?>' . PHP_EOL;
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . PHP_EOL;
foreach ($sitemapData as $item) {
    $lastmodDate = isset($item['lastmod']) ? $item['lastmod'] : date('Y-m-d');
    
    $xml .= "  <url>" . PHP_EOL;
    $xml .= "    <loc>{$item['url']}</loc>" . PHP_EOL;
    $xml .= "    <lastmod>{$lastmodDate}</lastmod>" . PHP_EOL;
    $xml .= "    <changefreq>{$item['freq']}</changefreq>" . PHP_EOL;
    $xml .= "    <priority>{$item['priority']}</priority>" . PHP_EOL;
    $xml .= "  </url>" . PHP_EOL;
}
$xml .= '</urlset>';
file_put_contents($sitemapPath, $xml);

echo "<h2>✅ Transition Glitch Fixed!</h2><p>Ab index page jaisa ekdum premium '0.5s ease-in-out' fade aayega!</p>";
?>