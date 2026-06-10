<?php
// Script untuk Mendaftarkan Webhook Telegram
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/telegram_helper.php';

// Pastikan diakses menggunakan HTTPS (Telegram mewajibkan HTTPS)
$protocol = 'https';
$host = $_SERVER['HTTP_HOST'];
$base_path = (strpos($host, 'localhost') !== false || strpos($host, '127.0.0.1') !== false || strpos($host, 'ngrok') !== false) ? '/ontimeadventure' : '';

$db = getDB();
$stmt = $db->query("SELECT nilai FROM pengaturan WHERE kunci='cf_worker_url'");
$cf_url = $stmt->fetchColumn();

if (!empty($cf_url)) {
    $webhook_url = $cf_url;
} else {
    $webhook_url = $protocol . '://' . $host . $base_path . '/webhook.php';
}

$result = set_telegram_webhook($webhook_url);

?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Set Telegram Webhook</title>
    <style>
        body { font-family: Arial, sans-serif; padding: 40px; text-align: center; }
        .success { color: #2ecc71; }
        .error { color: #e74c3c; }
    </style>
</head>
<body>
    <?php if ($result): ?>
        <h1 class="success">âœ… Webhook Berhasil Terpasang!</h1>
        <p>Bot Telegram Anda sekarang terhubung ke:</p>
        <code><?= htmlspecialchars($webhook_url) ?></code>
        <p style="margin-top:20px; color:#555;">Tombol "Setujui" dan "Batalkan" di Telegram kini sudah aktif secara background.</p>
    <?php else: ?>
        <h1 class="error">â Œ Gagal Memasang Webhook</h1>
        <p>Pastikan Token Bot Anda sudah diisi dengan benar di halaman Pengaturan Admin.</p>
        <p>Atau URL hosting Anda mungkin tidak memiliki HTTPS yang valid (SSL).</p>
    <?php endif; ?>
</body>
</html>
