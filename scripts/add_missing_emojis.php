<?php
/**
 * ============================================================
 * CopyEmoji.in — Add missing Unicode 15.1 & 16.0 emojis 🆕
 *
 * - Curated dataset of all latest Unicode 15.1 & 16.0 base emojis
 * - Source : assets/data/emoji.json (grouped by category)
 * - STRICT DEDUPLICATION: skips any entry whose 'slug' OR 'emoji'
 *   character already exists in the database.
 * - Appends only truly missing emojis into the matching category
 *   group and saves the JSON back with Unicode unescaped.
 *
 * Run via CLI:
 *   php scripts/add_missing_emojis.php
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

$basePath = realpath(__DIR__ . '/..');
if ($basePath === false) {
    $basePath = dirname(__DIR__);
}
$jsonPath = $basePath . '/assets/data/emoji.json';

if (!is_file($jsonPath)) {
    out('❌ Master data not found: assets/data/emoji.json');
    exit(1);
}

// ─── CURATED DATASET: Unicode 15.1 (Sept 2023) + Unicode 16.0 (Sept 2024) ───
// 28 distinct Emoji 15.1 base sequences + 8 Emoji 16.0 entries = 36 total.
// Slugs/names follow the existing emoji.json conventions (lowercase slugs,
// Title Case names) so strict dedup matches verbatim. Categories match the
// existing group names in emoji.json exactly.
// ──────────────────────────────────────────────────────────────────────────
$newEmojis = [
    // ---- Unicode 15.1 — Smileys & Emotion ----
    ['emoji' => "🙂‍↔️", 'name' => 'Head Shaking Horizontally', 'slug' => 'head-shaking-horizontally', 'category' => 'Smileys & Emotion', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🙂‍↕️", 'name' => 'Head Shaking Vertically', 'slug' => 'head-shaking-vertically', 'category' => 'Smileys & Emotion', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],

    // ---- Unicode 15.1 — People & Body (facing-right base forms) ----
    ['emoji' => "🚶‍➡️", 'name' => 'Person Walking Facing Right', 'slug' => 'person-walking-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🚶‍♀️‍➡️", 'name' => 'Woman Walking Facing Right', 'slug' => 'woman-walking-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🚶‍♂️‍➡️", 'name' => 'Man Walking Facing Right', 'slug' => 'man-walking-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧎‍➡️", 'name' => 'Person Kneeling Facing Right', 'slug' => 'person-kneeling-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧎‍♀️‍➡️", 'name' => 'Woman Kneeling Facing Right', 'slug' => 'woman-kneeling-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧎‍♂️‍➡️", 'name' => 'Man Kneeling Facing Right', 'slug' => 'man-kneeling-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧑‍🦯‍➡️", 'name' => 'Person With White Cane Facing Right', 'slug' => 'person-with-white-cane-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "👨‍🦯‍➡️", 'name' => 'Man With White Cane Facing Right', 'slug' => 'man-with-white-cane-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "👩‍🦯‍➡️", 'name' => 'Woman With White Cane Facing Right', 'slug' => 'woman-with-white-cane-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧑‍🦼‍➡️", 'name' => 'Person In Motorized Wheelchair Facing Right', 'slug' => 'person-in-motorized-wheelchair-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "👨‍🦼‍➡️", 'name' => 'Man In Motorized Wheelchair Facing Right', 'slug' => 'man-in-motorized-wheelchair-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "👩‍🦼‍➡️", 'name' => 'Woman In Motorized Wheelchair Facing Right', 'slug' => 'woman-in-motorized-wheelchair-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧑‍🦽‍➡️", 'name' => 'Person In Manual Wheelchair Facing Right', 'slug' => 'person-in-manual-wheelchair-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "👨‍🦽‍➡️", 'name' => 'Man In Manual Wheelchair Facing Right', 'slug' => 'man-in-manual-wheelchair-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "👩‍🦽‍➡️", 'name' => 'Woman In Manual Wheelchair Facing Right', 'slug' => 'woman-in-manual-wheelchair-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🏃‍➡️", 'name' => 'Person Running Facing Right', 'slug' => 'person-running-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🏃‍♀️‍➡️", 'name' => 'Woman Running Facing Right', 'slug' => 'woman-running-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🏃‍♂️‍➡️", 'name' => 'Man Running Facing Right', 'slug' => 'man-running-facing-right', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],

    // ---- Unicode 15.1 — People & Body (gender-neutral families) ----
    ['emoji' => "🧑‍🧑‍🧒", 'name' => 'Family Adult Adult Child', 'slug' => 'family-adult-adult-child', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧑‍🧑‍🧒‍🧒", 'name' => 'Family Adult Adult Child Child', 'slug' => 'family-adult-adult-child-child', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧑‍🧒", 'name' => 'Family Adult Child', 'slug' => 'family-adult-child', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🧑‍🧒‍🧒", 'name' => 'Family Adult Child Child', 'slug' => 'family-adult-child-child', 'category' => 'People & Body', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],

    // ---- Unicode 15.1 — Animals & Nature / Food & Drink / Objects ----
    ['emoji' => "🐦‍🔥", 'name' => 'Phoenix', 'slug' => 'phoenix', 'category' => 'Animals & Nature', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🍋‍🟩", 'name' => 'Lime', 'slug' => 'lime', 'category' => 'Food & Drink', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "🍄‍🟫", 'name' => 'Brown Mushroom', 'slug' => 'brown-mushroom', 'category' => 'Food & Drink', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],
    ['emoji' => "⛓️‍💥", 'name' => 'Broken Chain', 'slug' => 'broken-chain', 'category' => 'Objects', 'unicode_version' => '15.1', 'emoji_version' => '15.1'],

    // ---- Unicode 16.0 (Sept 2024) ----
    ['emoji' => "🫩", 'name' => 'Face With Bags Under Eyes', 'slug' => 'face-with-bags-under-eyes', 'category' => 'Smileys & Emotion', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🫆", 'name' => 'Fingerprint', 'slug' => 'fingerprint', 'category' => 'People & Body', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🪾", 'name' => 'Leafless Tree', 'slug' => 'leafless-tree', 'category' => 'Animals & Nature', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🫜", 'name' => 'Root Vegetable', 'slug' => 'root-vegetable', 'category' => 'Food & Drink', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🪉", 'name' => 'Harp', 'slug' => 'harp', 'category' => 'Objects', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🪏", 'name' => 'Shovel', 'slug' => 'shovel', 'category' => 'Objects', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🫟", 'name' => 'Splatter', 'slug' => 'splatter', 'category' => 'Symbols', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
    ['emoji' => "🇨🇶", 'name' => 'Flag Sark', 'slug' => 'flag-sark', 'category' => 'Flags', 'unicode_version' => '16.0', 'emoji_version' => '16.0'],
];

// ─── LOAD MASTER JSON ──────────────────────────────────────
out('⏳ Reading assets/data/emoji.json…');

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

// Build lookup sets for STRICT DEDUPLICATION (slug + emoji char).
$existingSlugs = [];
$existingChars = [];
foreach ($decoded as $group) {
    if (!isset($group['emojis']) || !is_array($group['emojis'])) {
        continue;
    }
    foreach ($group['emojis'] as $e) {
        if (!is_array($e)) {
            continue;
        }
        if (isset($e['slug'])) {
            $existingSlugs[strtolower(trim((string) $e['slug']))] = true;
        }
        if (isset($e['emoji'])) {
            $existingChars[(string) $e['emoji']] = true;
        }
    }
}

// Map category name (lowercased) -> group index for appending.
$groupIndexByCategory = [];
foreach ($decoded as $idx => $group) {
    $catName = strtolower(trim((string) ($group['name'] ?? '')));
    if ($catName !== '' && !isset($groupIndexByCategory[$catName])) {
        $groupIndexByCategory[$catName] = $idx;
    }
}

$added = [];
$skipped = 0;

foreach ($newEmojis as $item) {
    $slug = strtolower(trim((string) ($item['slug'] ?? '')));
    $char = (string) ($item['emoji'] ?? '');

    // STRICT DEDUPLICATION: skip if slug OR emoji char already exists.
    if ($slug === '' || $char === '' || isset($existingSlugs[$slug]) || isset($existingChars[$char])) {
        $skipped++;
        continue;
    }

    $category = trim((string) ($item['category'] ?? 'General')) ?: 'General';
    $catKey = strtolower($category);
    if (!isset($groupIndexByCategory[$catKey])) {
        out("⚠️ Unknown category '$category' for slug '$slug' — skipping.");
        $skipped++;
        continue;
    }

    $entry = [
        'emoji' => $char,
        'skin_tone_support' => false,
        'skin_tone_support_unicode_version' => '',
        'name' => trim((string) ($item['name'] ?? '')),
        'slug' => $slug,
        'unicode_version' => (string) ($item['unicode_version'] ?? '15.1'),
        'emoji_version' => (string) ($item['emoji_version'] ?? '15.1'),
    ];

    $decoded[$groupIndexByCategory[$catKey]]['emojis'][] = $entry;
    $existingSlugs[$slug] = true;
    $existingChars[$char] = true;
    $added[] = $char . ' ' . $entry['name'] . ' (' . $slug . ') [' . $category . ']';
}

if (count($added) > 0) {
    $encoded = json_encode($decoded, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    if ($encoded === false) {
        out('❌ Failed to encode updated emoji.json: ' . json_last_error_msg());
        exit(1);
    }
    if (file_put_contents($jsonPath, $encoded . PHP_EOL) === false) {
        out('❌ Failed to write assets/data/emoji.json');
        exit(1);
    }
}

out('✅ Done. New emojis added: ' . count($added) . ' (skipped already-existing: ' . $skipped . ').');
if (count($added) > 0) {
    out('🆕 Added:');
    foreach ($added as $line) {
        out('  - ' . $line);
    }
} else {
    out('ℹ️ No missing emojis — database already contains all Unicode 15.1 & 16.0 entries.');
}
