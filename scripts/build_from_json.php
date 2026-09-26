<?php
/**
 * ============================================================
 * CopyEmoji.in — DB-FREE Static Site Builder (Phase 2 Clean) 🚀
 *
 * - Requires NO MySQL / PDO at all. Pure JSON -> static HTML.
 * - Source : assets/data/emoji.json (grouped by category)
 * - Output : emoji/{slug}.html + sitemap.xml
 * - SEO    : High-CTR title/meta, BreadcrumbList schema + visible
 *            breadcrumbs, clean OG/Twitter/canonical, deterministic
 *            related grid, NO boilerplate FAQ (thin-content safe).
 *
 * Run via CLI:
 *   php scripts/build_from_json.php
 *   php scripts/build_from_json.php --limit=50   (smoke test, first N only)
 *
 * PHP >= 7.4 with mbstring + json required. No composer deps.
 * ============================================================
 */

declare(strict_types=1);

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', '1');

$isCli = (PHP_SAPI === 'cli');
$EOL = $isCli ? PHP_EOL : "<br>" . PHP_EOL;

function out(string $msg): void
{
    global $EOL, $isCli;
    echo ($isCli ? strip_tags($msg) : $msg) . $EOL;
    if ($isCli) {
        @flush();
    }
}

// ─── CONFIG ────────────────────────────────────────────────
define('SITE_URL', 'https://copyemoji.in');

$basePath    = realpath(__DIR__ . '/..');
if ($basePath === false) {
    $basePath = dirname(__DIR__);
}
$jsonPath    = $basePath . '/assets/data/emoji.json';
$emojiDir    = $basePath . '/emoji';
$sitemapPath = $basePath . '/sitemap.xml';

// Optional CLI arg: --limit=N (for smoke tests)
$limit = 0;
foreach ($argv ?? [] as $arg) {
    if (preg_match('/^--limit=(\d+)$/', (string) $arg, $m)) {
        $limit = (int) $m[1];
    }
}

// ─── PRE-FLIGHT (no DB here — pure filesystem) ─────────────
if (!extension_loaded('mbstring')) {
    out('❌ mbstring extension is required.');
    exit(1);
}
if (!extension_loaded('json')) {
    out('❌ json extension is required.');
    exit(1);
}
if (!is_file($jsonPath)) {
    out('❌ Master data not found: assets/data/emoji.json');
    exit(1);
}
if (!is_dir($emojiDir) && !mkdir($emojiDir, 0777, true) && !is_dir($emojiDir)) {
    out('❌ Cannot create emoji/ output directory.');
    exit(1);
}

// ─── HELPERS ───────────────────────────────────────────────
function slugify(string $s): string
{
    $s = strtolower(trim($s));
    $s = str_replace('&', ' and ', $s);
    $s = (string) preg_replace('/[^a-z0-9]+/', '-', $s);
    return trim($s, '-');
}

function isValidSlug(string $slug): bool
{
    // Allow hyphen runs (e.g. flag-antigua--barbuda) to preserve
    // canonical slugs already present in emoji.json / existing files.
    return (bool) preg_match('/^[a-z0-9]+(?:-+[a-z0-9]+)*$/', $slug);
}

/**
 * Preserve the canonical slug from emoji.json verbatim (lowercased).
 * Unlike slugify(), this keeps intentional hyphen runs such as
 * "flag-antigua--barbuda" so existing URLs/files are not collapsed
 * into duplicates.
 */
function canonicalSlug(string $s): string
{
    $s = strtolower(trim($s));
    $s = str_replace([' ', '_'], '-', $s);
    $s = (string) preg_replace('/[^a-z0-9-]+/', '-', $s);
    return trim($s, '-');
}

function esc(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function getEmojiCodepoints(string $emojiStr): string
{
    $chars = mb_str_split($emojiStr, 1, 'UTF-8');
    $codepoints = [];
    foreach ($chars as $char) {
        $hex = strtoupper(dechex(mb_ord($char, 'UTF-8')));
        $codepoints[] = 'U+' . str_pad($hex, 4, '0', STR_PAD_LEFT);
    }
    return implode(' ', $codepoints);
}

function getHtmlEntity(string $emojiStr): string
{
    $chars = mb_str_split($emojiStr, 1, 'UTF-8');
    $entities = [];
    foreach ($chars as $char) {
        $entities[] = '&#' . mb_ord($char, 'UTF-8') . ';';
    }
    return implode('', $entities);
}

function buildCtrTitle(string $name, string $emojiChar): string
{
    $t = "$emojiChar $name Emoji — Copy & Paste in 1 Tap";
    if (mb_strlen($t, 'UTF-8') > 60) {
        $t = "$emojiChar $name Emoji — Copy & Paste";
    }
    return $t;
}

function buildCtrMeta(string $name, string $emojiChar, string $codepoints, string $entity): string
{
    $raw = "Copy $name $emojiChar in 1 tap. Meaning, WhatsApp use, Unicode $codepoints, HTML $entity + HD PNG. iPhone & Android.";
    $raw = (string) preg_replace('/\s+/', ' ', $raw);
    return mb_substr(trim($raw), 0, 150, 'UTF-8');
}

function getBreadcrumbSchema(string $name, string $slugRaw, string $categoryName, string $categorySlug): string
{
    return (string) json_encode([
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Home', 'item' => SITE_URL . '/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => $categoryName, 'item' => SITE_URL . "/category/$categorySlug"],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $name, 'item' => SITE_URL . "/emoji/$slugRaw"],
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}

/**
 * Deterministic related emojis — NO array_rand / shuffle / mt_rand.
 * Priority: same category first, then shared slug-token overlap,
 * then same first token bonus, alphabetical slug tiebreak.
 *
 * @param array  $all   Full flat emoji list
 * @param string $slug  Current emoji slug
 * @param string $cat   Current category slug
 * @param int    $limit Max results
 * @return array
 */
function getRelatedEmojis(array $all, string $slug, string $cat, int $limit = 12): array
{
    $base = array_values(array_filter(explode('-', strtolower($slug))));
    $scored = [];

    foreach ($all as $it) {
        $itSlug = $it['slug'] ?? '';
        if ($itSlug === '' || $itSlug === $slug) {
            continue;
        }
        $toks = array_values(array_filter(explode('-', strtolower($itSlug))));
        $overlap = count(array_intersect($base, $toks));
        $firstBonus = (($toks[0] ?? '') === ($base[0] ?? '') && ($base[0] ?? '') !== '') ? 2 : 0;
        $catBonus = (($it['categorySlug'] ?? '') === $cat) ? 100 : 0;
        $scored[] = [
            'it' => $it,
            's' => $catBonus + $overlap * 10 + $firstBonus,
            'k' => $itSlug,
        ];
    }

    usort($scored, static function ($a, $b) {
        if ($a['s'] !== $b['s']) {
            return $b['s'] <=> $a['s'];
        }
        return strcmp($a['k'], $b['k']);
    });

    return array_slice(array_column($scored, 'it'), 0, $limit);
}

/**
 * Short, utility-focused meaning blurb. Unique per emoji because it
 * embeds that emoji's own name/char/category/unicode/shortcode —
 * and rotates 1 of 3 deterministic variants (crc32 of slug) so that
 * neighbouring pages in the same category don't read identically.
 * This is NOT a boilerplate FAQ block.
 */
function buildMeaningHtml(string $name, string $emojiChar, string $categoryName, string $unicode, string $shortcode): string
{
    $variants = [
        "Use {$emojiChar} wherever <strong>{$name}</strong> fits the mood — chats, bios, captions and comments. It lives in the {$categoryName} set (Unicode {$unicode}), so it renders natively on iPhone, Android and desktop with no app needed.",
        "The {$emojiChar} <strong>{$name}</strong> emoji ({$categoryName}, Unicode {$unicode}) is a one-tap reaction for messages, usernames and status lines. Paste it anywhere text works — WhatsApp, Discord, Instagram — and pair it with shortcode <code>{$shortcode}</code> where supported.",
        "Copy {$emojiChar} to drop a <strong>{$name}</strong> into any conversation. Part of {$categoryName} since Unicode {$unicode}, it keeps its meaning cross-platform even though Apple, Google and Samsung draw it slightly differently.",
    ];
    $idx = abs(crc32(strtolower($name))) % count($variants);
    $p1 = $variants[$idx];
    $p2 = "Tap <strong>Copy</strong> above, then paste with Ctrl+V / long-press. Need an image? Use <strong>Download PNG</strong> for a transparent 512px file.";
    $wrap = static function (string $t): string {
        return "<p style='color: var(--muted); line-height: 1.8; font-size: 16px; margin-bottom: 15px;'>$t</p>";
    };
    return $wrap($p1) . $wrap($p2);
}

// ─── LOAD MASTER JSON ──────────────────────────────────────
out('⏳ Reading assets/data/emoji.json (DB-free mode, no PDO)…');

$rawJson = file_get_contents($jsonPath);
if ($rawJson === false) {
    out('❌ Cannot read emoji.json');
    exit(1);
}
$decoded = json_decode($rawJson, true);
if (!is_array($decoded)) {
    out('❌ emoji.json is not valid JSON: ' . json_last_error_msg());
    exit(1);
}

// Normalize: supports BOTH grouped [{name,slug,emojis:[...]}] and flat [{emoji,name,slug,...}]
$flat = [];
if (isset($decoded[0]) && is_array($decoded[0]) && isset($decoded[0]['emojis']) && is_array($decoded[0]['emojis'])) {
    // Grouped format (current master file)
    foreach ($decoded as $group) {
        $categoryName = trim((string) ($group['name'] ?? 'General')) ?: 'General';
        $categorySlug = trim((string) ($group['slug'] ?? '')) !== ''
            ? slugify((string) $group['slug'])
            : slugify($categoryName);
        foreach ($group['emojis'] as $e) {
            if (!is_array($e)) {
                continue;
            }
            $flat[] = [
                'emoji' => (string) ($e['emoji'] ?? ''),
                'name' => trim((string) ($e['name'] ?? '')) !== ''
                    ? trim((string) $e['name'])
                    : ucwords(str_replace('-', ' ', (string) ($e['slug'] ?? 'Emoji'))),
                'slug' => canonicalSlug((string) ($e['slug'] ?? '')),
                'category' => $categoryName,
                'categorySlug' => $categorySlug,
                'unicode' => (string) ($e['unicode_version'] ?? '1.0') ?: '1.0',
                'version' => (string) ($e['emoji_version'] ?? ($e['unicode_version'] ?? '1.0')) ?: '1.0',
            ];
        }
    }
} else {
    // Flat format fallback
    foreach ($decoded as $e) {
        if (!is_array($e)) {
            continue;
        }
        $char = (string) ($e['emoji'] ?? $e['emoji_char'] ?? '');
        $slugRaw = (string) ($e['slug'] ?? '');
        $catName = trim((string) ($e['category'] ?? $e['categoryName'] ?? 'General')) ?: 'General';
        $flat[] = [
            'emoji' => $char,
            'name' => trim((string) ($e['name'] ?? '')) !== ''
                ? trim((string) $e['name'])
                : ucwords(str_replace('-', ' ', $slugRaw)),
            'slug' => canonicalSlug($slugRaw),
            'category' => $catName,
            'categorySlug' => slugify((string) ($e['categorySlug'] ?? $e['category_slug'] ?? $catName)),
            'unicode' => (string) ($e['unicode_version'] ?? $e['unicode_ver'] ?? $e['unicode'] ?? '1.0') ?: '1.0',
            'version' => (string) ($e['emoji_version'] ?? $e['emoji_ver'] ?? $e['version'] ?? '1.0') ?: '1.0',
        ];
    }
}

// Validate + dedupe (first occurrence wins)
$all = [];
$seen = [];
$skipped = 0;
foreach ($flat as $item) {
    $slug = $item['slug'];
    if ($slug === '' || !isValidSlug($slug) || $item['emoji'] === '' || isset($seen[$slug])) {
        $skipped++;
        continue;
    }
    $seen[$slug] = true;
    $all[] = $item;
}

if ($limit > 0) {
    $all = array_slice($all, 0, $limit);
}

$total = count($all);
out("✅ Loaded <strong>$total</strong> valid emojis" . ($limit > 0 ? " (limit=$limit)" : "") . ($skipped > 0 ? ", skipped $skipped invalid/duplicate" : "") . ".");

if ($total === 0) {
    out('❌ Nothing to build.');
    exit(1);
}

// Canonical categories present in this build
$categories = [];
foreach ($all as $item) {
    $categories[$item['categorySlug']] = $item['category'];
}
ksort($categories);

// ─── SHARED CHROME ─────────────────────────────────────────
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

// ─── MAIN LOOP ─────────────────────────────────────────────
out('🚀 Building static emoji pages (clean Phase 2, no FAQ)…');

$today = date('Y-m-d');
$built = 0;
$emojiUrls = [];

foreach ($all as $i => $e) {
    $emojiChar    = $e['emoji'];
    $slugRaw      = $e['slug'];
    $name         = $e['name'];
    $categoryName = $e['category'];
    $categorySlug = $e['categorySlug'];
    $unicode      = $e['unicode'];
    $version      = $e['version'];
    $shortcode    = ':' . str_replace('-', '_', $slugRaw) . ':';

    $emojiCodepoints = getEmojiCodepoints($emojiChar);
    $htmlEntity      = getHtmlEntity($emojiChar);

    $ctrTitle = buildCtrTitle($name, $emojiChar);
    $metaDesc = buildCtrMeta($name, $emojiChar, $emojiCodepoints, $htmlEntity);

    $safeTitle = esc($ctrTitle);
    $safeDesc  = esc($metaDesc);
    $safeName  = esc($name);
    $safeCat   = esc($categoryName);
    $safeShort = esc($shortcode);

    // Unique-per-page keywords (derived from this emoji only)
    $keywordWords = array_unique(array_filter(array_merge(
        explode(' ', strtolower($name)),
        [strtolower($categoryName), 'emoji', 'copy', 'paste']
    )));
    $autoKeywords = esc(implode(', ', $keywordWords));

    // BreadcrumbList JSON-LD ONLY (no FAQPage schema — avoids scaled thin content flags)
    $crumbJsonLd = getBreadcrumbSchema($name, $slugRaw, $categoryName, $categorySlug);
    $schemaHtml  = "<script type=\"application/ld+json\">$crumbJsonLd</script>";

    $breadcrumbNav = "<nav aria-label='Breadcrumb' style='font-size:14px;color:var(--muted);margin-bottom:15px;'><a href='/' style='color:var(--primary);text-decoration:none;'>Home</a> › <a href='/category/$categorySlug' style='color:var(--primary);text-decoration:none;'>$safeCat</a> › $safeName</nav>";

    $meaningHtml = buildMeaningHtml($safeName, $emojiChar, $safeCat, esc($unicode), $safeShort);

    // Deterministic related grid (same category weighted, alphabetical tiebreak)
    $relatedHtml = '';
    foreach (getRelatedEmojis($all, $slugRaw, $categorySlug, 12) as $rel) {
        $relChar = $rel['emoji'];
        $relSlug = $rel['slug'];
        $relName = esc($rel['name']);
        $relatedHtml .= "
                <a href='/emoji/$relSlug' class='emoji-item' title='$relName' style='text-decoration:none;'>
                    <div class='emoji-char'>$relChar</div>
                    <div class='download-btn' style='text-align:center;'>$relName</div>
                </a>";
    }

    $fullHtml = "<!DOCTYPE html>
<html lang='en'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <title>$safeTitle</title>
    <meta name='description' content='$safeDesc'>
    <meta name='keywords' content='$autoKeywords'>
    <meta name='robots' content='index, follow, max-image-preview:large'>
    <link rel='manifest' href='/manifest.json'>
    <meta name='theme-color' content='#6366f1'>
    <link rel='canonical' href='" . SITE_URL . "/emoji/$slugRaw'>
    <meta property='og:type' content='article'>
    <meta property='og:title' content='$safeTitle'>
    <meta property='og:description' content='$safeDesc'>
    <meta property='og:url' content='" . SITE_URL . "/emoji/$slugRaw'>
    <meta property='og:image' content='" . SITE_URL . "/assets/images/preview-card.png'>
    <meta property='og:image:width' content='1200'>
    <meta property='og:image:height' content='630'>
    <meta name='twitter:card' content='summary_large_image'>
    <meta name='twitter:title' content='$safeTitle'>
    <meta name='twitter:description' content='$safeDesc'>
    <meta name='twitter:image' content='" . SITE_URL . "/assets/images/preview-card.png'>
    <link rel='stylesheet' href='/assets/css/style.css'>
    <script>(function(){var t=localStorage.getItem('theme'),s=window.matchMedia('(prefers-color-scheme: dark)').matches;if(t==='dark'||(!t&&s))document.documentElement.classList.add('dark-early');})();</script>
    <style>html.dark-early body{background:#0f172a;color:#f8fafc;}</style>
    <link rel='preconnect' href='https://fonts.googleapis.com'>
    <link rel='preconnect' href='https://fonts.gstatic.com' crossorigin>
    <link rel='preload' as='style' href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap' onload=\"this.onload=null;this.rel='stylesheet'\">
    <noscript><link rel='stylesheet' href='https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;700&display=swap'></noscript>
    $schemaHtml
    <style>
        body, .content-box, .technical-box, .emoji-item {
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
        $breadcrumbNav
        <div class='content-box'>
            <div style='text-align:center;'>
                <div style='font-size: 120px; margin-bottom: 20px;'>$emojiChar</div>
                <h1 style='font-size: 32px; margin-bottom: 20px; color: var(--text);'>$safeName Emoji Meaning</h1>
                <div style='display: flex; gap: 15px; justify-content: center; flex-wrap: wrap; margin-bottom: 10px;'>
                    <button class='submit-btn' onclick='copyEmojiMain(\"$emojiChar\")' style='width:auto; padding:15px 40px; font-size:20px; margin-top:0;'>Copy $emojiChar</button>
                    <button class='submit-btn btn-secondary' onclick='downloadEmojiPNG(\"$emojiChar\", \"$slugRaw\")' style='width:auto; padding:15px 40px; font-size:20px; margin-top:0;'>⬇️ Download PNG</button>
                </div>
            </div>

            <section style='text-align: left; margin-top: 50px;'>$meaningHtml</section>

            <section style='text-align: left; margin-top: 40px;'>
                <h2 style='color: var(--primary); font-size: 24px; margin-bottom: 20px; border-bottom: 1px solid rgba(0,0,0,0.05); padding-bottom: 10px;'>Technical Information</h2>
                <div class='technical-box' style='background: var(--bg); padding: 20px; border-radius: 12px; border: 1px solid rgba(0,0,0,0.05);'>
                    <ul style='list-style:none; padding:0; color:var(--text); line-height: 2;'>
                        <li><strong>Emoji:</strong> <span style='font-size: 24px;'>$emojiChar</span></li>
                        <li><strong>Emoji Name:</strong> $safeName</li>
                        <li><strong>Codepoints:</strong> <code>" . esc($emojiCodepoints) . "</code></li>
                        <li><strong>HTML Entity:</strong> <code>" . esc($htmlEntity) . "</code></li>
                        <li><strong>Shortcodes:</strong> <code>$safeShort</code></li>
                        <li><strong>Keywords:</strong> $autoKeywords</li>
                        <li><strong>Category:</strong> <a href='/category/$categorySlug' style='color:var(--primary); text-decoration:none;'>$safeCat</a></li>
                        <li><strong>Unicode Version:</strong> " . esc($unicode) . "</li>
                        <li><strong>Emoji Version:</strong> " . esc($version) . "</li>
                    </ul>
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
    <script src='/assets/js/main.js?v=1.5' defer></script>
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

    $target = $emojiDir . '/' . $slugRaw . '.html';
    if (file_put_contents($target, $fullHtml) === false) {
        out("⚠️ Failed to write: emoji/$slugRaw.html");
        continue;
    }

    $emojiUrls[] = SITE_URL . "/emoji/$slugRaw";
    $built++;

    if ($built % 500 === 0) {
        out("… $built / $total built");
    }
}

out("✅ Emoji pages written: <strong>$built</strong> → emoji/*.html");

// ─── CLEAN SITEMAP ─────────────────────────────────────────
out('⏳ Regenerating clean sitemap.xml…');

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

$addUrl = static function (string $loc, string $lastmod, string $freq, string $priority) use (&$xml): void {
    $xml .= '  <url>' . "\n"
        . '    <loc>' . esc($loc) . '</loc>' . "\n"
        . '    <lastmod>' . esc($lastmod) . '</lastmod>' . "\n"
        . '    <changefreq>' . esc($freq) . '</changefreq>' . "\n"
        . '    <priority>' . esc($priority) . '</priority>' . "\n"
        . '  </url>' . "\n";
};

// 1. Homepage (1.0)
$addUrl(SITE_URL . '/', $today, 'daily', '1.0');

// 2. Long-tail hubs (0.9)
foreach (['heart-emojis', 'symbols-for-discord', 'aesthetic-emojis'] as $hub) {
    $addUrl(SITE_URL . "/$hub", $today, 'weekly', '0.9');
}

// 3. Canonical categories (0.8)
foreach ($categories as $catSlug => $catName) {
    $addUrl(SITE_URL . "/category/$catSlug", $today, 'weekly', '0.8');
}

// 4. All valid emoji pages (0.6, current-date lastmod)
foreach ($emojiUrls as $loc) {
    $addUrl($loc, $today, 'weekly', '0.6');
}

$xml .= '</urlset>' . "\n";

if (file_put_contents($sitemapPath, $xml) === false) {
    out('❌ Failed to write sitemap.xml');
    exit(1);
}

$hubCount = 3;
$catCount = count($categories);
out('✅ Sitemap regenerated: homepage (1.0) + ' . $hubCount . ' hubs (0.9) + ' . $catCount . ' categories (0.8) + ' . count($emojiUrls) . ' emojis (0.6).');
out('🎉 <strong>Done! 100% DB-free build complete. No MySQL/PDO used.</strong>');
