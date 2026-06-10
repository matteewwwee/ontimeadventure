<?php
$log_file = __DIR__ . '/webhook_debug_log.txt';

echo "<h2>1. Status Webhook di Server Telegram</h2>";
require_once __DIR__ . '/includes/telegram_helper.php';
$config = get_telegram_config();
$token = $config['bot_token'];
if ($token) {
    $info_url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
    $info = @file_get_contents($info_url);
    if ($info) {
        $json = json_decode($info, true);
        echo "<b>URL Webhook Saat Ini:</b> " . htmlspecialchars($json['result']['url'] ?? 'TIDAK ADA') . "<br>";
        echo "<b>Pesan Error Terakhir:</b> " . htmlspecialchars($json['result']['last_error_message'] ?? 'Tidak ada error') . "<br>";
        echo "<b>Update Tertunda:</b> " . htmlspecialchars($json['result']['pending_update_count'] ?? '0') . "<br>";
        echo "<pre style='background:#eef; padding:10px; font-size:12px;'>" . htmlspecialchars(print_r($json['result'], true)) . "</pre>";
    } else {
        echo "<span style='color:red;'>Gagal menghubungi API Telegram. Cek Token atau Koneksi Server!</span><br>";
    }
} else {
    echo "<span style='color:red;'>Token Bot Telegram Kosong di Database!</span><br>";
}

echo "<hr><h2>2. Log Kedatangan Data di Hosting</h2>";
if (file_exists($log_file)) {
    $lines = file($log_file);
    $last = array_slice($lines, -50);
    echo "<pre style='background:#f4f4f4; padding:10px;'>";
    foreach ($last as $l) {
        echo htmlspecialchars($l);
    }
    echo "</pre>";
} else {
    echo "<h3>Belum ada log kedatangan.</h3>";
    echo "<p>Artinya Telegram belum/gagal mengirim data ke hosting ini.</p>";
}
