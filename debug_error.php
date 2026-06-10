<?php
$log_file = __DIR__ . '/webhook_debug_log.txt';
if (file_exists($log_file)) {
    $lines = file($log_file);
    $last = array_slice($lines, -50);
    echo "<h3>Webhook Log Terakhir:</h3><pre style='background:#f4f4f4; padding:10px;'>";
    foreach ($last as $l) {
        echo htmlspecialchars($l);
    }
    echo "</pre>";
} else {
    echo "<h3>Belum ada log.</h3>";
    echo "<p>Coba tekan tombol 'Setujui' di Telegram sekarang, lalu <b>Refresh</b> halaman ini!</p>";
}
