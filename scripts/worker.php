<?php
/**
 * ============================================================
 * CopyEmoji.in — BACKEND WORKER (Live Output Version)
 * Run in Terminal: php worker.php
 * ============================================================
 */

set_time_limit(0);
date_default_timezone_set('Asia/Kolkata'); // 🇮🇳 Indian Timezone Fix

echo "🚀 CopyEmoji Background Worker Started...\n";
echo "Dhyan rahe: Is terminal ko band mat karna jab tak sab generate na ho jaye.\n\n";

while (true) {
    echo "\n[" . date('H:i:s') . "] 🟢 Naya Batch start ho raha hai...\n";
    echo str_repeat("-", 50) . "\n";
    
    // popen() use kar rahe hain taaki LIVE output dikhe aur data buffer na ho
    $handle = popen("php phase1_generate.php", "r");
    $output = "";
    
    while (!feof($handle)) {
        $line = fgets($handle);
        if ($line) {
            echo $line;          // Terminal pe turant (live) print karega
            $output .= $line;    // Loop todne ki condition check karne ke liye save karega
            @ob_flush(); flush();
        }
    }
    pclose($handle);
    
    echo str_repeat("-", 50) . "\n";
    
    // Agar output mein likha hai ki sab done, toh worker ruk jayega
    if (strpos($output, 'Sab done hai') !== false || strpos($output, 'Koi pending emoji nahi') !== false) {
        echo "\n✅ SAB KUCH GENERATE HO GAYA! Worker ruk raha hai...\n";
        echo "👉 Ab browser mein jaakar 'Phase 2' wala button daba do!\n";
        break;
    }

    echo "[" . date('H:i:s') . "] 10 seconds ka normal rest MySQL ke liye... 😴\n";
    sleep(10); // Agle batch se pehle thoda aaram
}