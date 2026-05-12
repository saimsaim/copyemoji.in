<?php
// auto_runner_stats.php — Auto runner ke liye JSON stats endpoint
header('Content-Type: application/json');
header('Cache-Control: no-cache');

define('DB_HOST', 'localhost');
define('DB_NAME', 'copyemoji');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    $pdo  = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME.";charset=utf8mb4", DB_USER, DB_PASS);
    
    // View se total stats uthao
    $stats = $pdo->query("SELECT * FROM emoji_stats")->fetch(PDO::FETCH_ASSOC);
    
    // Nayi table se live status uthao
    $state = $pdo->query("SELECT current_slug FROM runner_status WHERE id = 1")->fetch(PDO::FETCH_ASSOC);
    
    // Dono ko merge kardo
    $stats['current_slug'] = $state ? $state['current_slug'] : null;

    echo json_encode($stats);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}