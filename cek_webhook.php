<?php
/**
 * Script Pelacak Status Webhook Telegram
 * Dibuat khusus untuk membuktikan validitas koneksi dari server Telegram.
 */

require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    $stmt = $db->query("SELECT nilai FROM pengaturan WHERE kunci='telegram_bot_token'");
    $token = $stmt->fetchColumn();

    if (!$token) {
        die("Error: Token Bot Telegram tidak ditemukan di database!");
    }

    $url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
    
    // Gunakan cURL agar bisa melihat error koneksinya secara lebih jelas (jika ada)
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 15); // Waktu tunggu 15 detik
    
    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error = curl_error($ch);
    curl_close($ch);

    echo "<div style='font-family: monospace; background: #222; color: #0f0; padding: 20px; border-radius: 5px; max-width: 800px; margin: 20px auto; line-height: 1.6;'>";
    echo "<h3>🕵️‍♂️ Laporan Pelacakan Server Telegram</h3>";
    echo "========================================<br>";
    echo "<strong>Waktu Pelacakan:</strong> " . date('Y-m-d H:i:s') . "<br>";
    echo "<strong>URL Target Webhook Saat Ini:</strong> " . (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false ? "Ngrok/Lokal" : $_SERVER['HTTP_HOST']) . "<br>";
    echo "========================================<br><br>";

    if ($response === false) {
        echo "<span style='color: #ff4444;'>❌ KONEKSI GAGAL! Server Anda tidak bisa menghubungi Telegram.</span><br>";
        echo "<strong>Pesan Error Server:</strong> {$curl_error}<br>";
    } else {
        $data = json_decode($response, true);
        
        if ($data && $data['ok'] === true) {
            $info = $data['result'];
            echo "✅ <strong>Koneksi ke Mesin Telegram:</strong> BERHASIL (HTTP {$http_code})<br><br>";
            
            echo "<strong>--- BUKTI DARI SERVER PUSAT TELEGRAM ---</strong><br>";
            echo "Alamat Webhook Tersimpan : <span style='color: yellow;'>" . htmlspecialchars($info['url']) . "</span><br>";
            
            if (isset($info['last_error_message'])) {
                echo "<br><span style='color: #ff4444; font-size: 18px;'>⚠️ ERROR TERAKHIR YANG DIALAMI TELEGRAM SAAT MENCOBA MENGIRIM PESAN KE HOSTING INI:</span><br>";
                echo "<strong style='color: red; font-size: 20px; background: white; padding: 2px 5px;'>" . htmlspecialchars($info['last_error_message']) . "</strong><br>";
                
                if (isset($info['last_error_date'])) {
                    echo "<strong>Waktu Terjadi Error :</strong> " . date('Y-m-d H:i:s', $info['last_error_date']) . "<br>";
                }
            } else {
                echo "<br><span style='color: #00ff00;'>✅ Tidak ada error koneksi yang tercatat oleh Telegram!</span><br>";
            }
            
            echo "<br>Jumlah Pesan Mengantre   : " . htmlspecialchars($info['pending_update_count']) . " pesan<br>";
        } else {
            echo "❌ <strong>Gagal memparsing respons Telegram.</strong><br>";
            echo "Respons mentah: " . htmlspecialchars($response) . "<br>";
        }
    }
    echo "</div>";

} catch (Exception $e) {
    echo "Error Sistem: " . $e->getMessage();
}
?>
