<?php
/**
 * ============================================================
 * CopyEmoji.in — FIX SLUGS (Phase 3.1, run once before rebuild)
 * - Sanitizes emoji + category slugs (double dashes, accents,
 *   piata/pinata, keycap-, flag-- variants)
 * - Normalizes category slugs to canonical long form
 * - Updates DB (emoji_content) + assets/data/emoji.json
 * - Dry-run by default: php fix_slugs.php --apply to write
 * ============================================================
 */

set_time_limit(0);
error_reporting(E_ALL);
ini_set('display_errors', 1);

$apply = in_array('--apply', $argv ?? [], true);
$basePath = realpath(__DIR__ . '/..');
$jsonPath = $basePath . '/assets/data/emoji.json';

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

// Canonical category map (short -> long, must match _redirects + phase2_render)
$categoryCanonical = [
    'smileys-emotion'   => 'smileys-and-emotion',
    'people-body'       => 'people-and-body',
    'animals-nature'    => 'animals-and-nature',
    'food-drink'        => 'food-and-drink',
    'travel-places'     => 'travel-and-places',
];

// Known one-off bad slugs (old -> new, must match _redirects)
$knownFixes = [
    'piata'                     => 'pinata',
    'keycap-'                   => 'keycap-hash',
    'flag-antigua--barbuda'     => 'flag-antigua-barbuda',
    'flag-bosnia--herzegovina'  => 'flag-bosnia-herzegovina',
    'flag-congo---brazzaville'  => 'flag-congo-brazzaville',
    'flag-congo---kinshasa'     => 'flag-congo-kinshasa',
];

function resolve_slug(string $old, array $knownFixes): string {
    if (isset($knownFixes[$old])) return $knownFixes[$old];
    $clean = sanitize_slug($old);
    if (isset($knownFixes[$clean])) return $knownFixes[$clean];
    return $clean;
}

echo ($apply ? "APPLY MODE: writing changes\n" : "DRY-RUN: no writes (use --apply to write)\n");

// ─── 1. DB pass (if reachable) ───
$pdo = null;
try {
    require __DIR__ . '/db_connect.php'; // provides $pdo
} catch (Throwable $e) {
    echo "DB skip: " . $e->getMessage() . "\n";
}

$dbChanges = [];
if (isset($pdo)) {
    $rows = $pdo->query("SELECT id, slug FROM emoji_content ORDER BY id ASC")->fetchAll();
    $seen = [];
    $upd = $pdo->prepare("UPDATE emoji_content SET slug = :new WHERE id = :id");
    foreach ($rows as $r) {
        $old = $r['slug'];
        $new = resolve_slug($old, $knownFixes);
        // de-dupe: pinata collision (piata + pinata both exist)
        if (isset($seen[$new])) {
            $i = 2;
            while (isset($seen["$new-$i"])) $i++;
            $new = "$new-$i";
        }
        $seen[$new] = true;
        if ($new !== $old) {
            $dbChanges[] = [$old, $new];
            if ($apply) $upd->execute([':new' => $new, ':id' => $r['id']]);
        }
    }
    echo "DB slug changes: " . count($dbChanges) . "\n";
    foreach (array_slice($dbChanges, 0, 20) as [$o, $n]) echo "  $o -> $n\n";
}

// ─── 2. JSON pass ───
if (!file_exists($jsonPath)) {
    echo "JSON not found: $jsonPath\n";
    exit(1);
}
$json = json_decode(file_get_contents($jsonPath), true);
if (!$json) {
    echo "JSON decode failed\n";
    exit(1);
}

$jsonChanges = [];
$seenJson = [];
foreach ($json as &$cat) {
    $oldCatSlug = $cat['slug'] ?? '';
    $normCat = sanitize_slug(str_replace(' & ', ' and ', $cat['name'] ?? $oldCatSlug));
    if (isset($categoryCanonical[$oldCatSlug])) $normCat = $categoryCanonical[$oldCatSlug];
    if (isset($categoryCanonical[$normCat])) $normCat = $categoryCanonical[$normCat];
    if ($normCat !== $oldCatSlug) {
        $jsonChanges[] = ["cat:$oldCatSlug", "cat:$normCat"];
        $cat['slug'] = $normCat;
    }
    foreach ($cat['emojis'] as &$e) {
        $old = $e['slug'] ?? '';
        $new = resolve_slug($old, $knownFixes);
        if (isset($seenJson[$new])) {
            $i = 2;
            while (isset($seenJson["$new-$i"])) $i++;
            $new = "$new-$i";
        }
        $seenJson[$new] = true;
        if ($new !== $old) {
            $jsonChanges[] = [$old, $new];
            $e['slug'] = $new;
        }
    }
    unset($e);
}
unset($cat);

echo "JSON slug changes: " . count($jsonChanges) . "\n";
foreach (array_slice($jsonChanges, 0, 20) as [$o, $n]) echo "  $o -> $n\n";

if ($apply) {
    file_put_contents($jsonPath, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    echo "Wrote $jsonPath\n";
    echo "Next: php scripts/export_json.php (re-export from DB) then php scripts/phase2_render.php\n";
} else {
    echo "Dry-run done. Re-run with --apply to write JSON" . (isset($pdo) ? " + DB" : "") . ".\n";
}
