<?php
/**
 * Helper Notifikasi Telegram - On Time Adventure
 */

function get_telegram_config() {
    $config = [
        'bot_token' => '',
        'chat_id' => ''
    ];
    
    try {
        global $db;
        $localDb = $db;
        if (!$localDb) {
            require_once __DIR__ . '/../config/database.php';
            $localDb = getDB();
        }
        $settings_query = $localDb->query("SELECT kunci, nilai FROM pengaturan WHERE kunci IN ('telegram_bot_token', 'telegram_chat_id')");
        while ($row = $settings_query->fetch()) {
            if ($row['kunci'] === 'telegram_bot_token') $config['bot_token'] = $row['nilai'];
            if ($row['kunci'] === 'telegram_chat_id') $config['chat_id'] = $row['nilai'];
        }
    } catch (Exception $e) {}
    
    return $config;
}

function send_telegram_message($text, $parse_mode = 'HTML', $reply_markup = null) {
    $config = get_telegram_config();
    $token = $config['bot_token'];
    $chat_ids = explode(',', $config['chat_id']);
    
    if (empty($token) || empty($config['chat_id'])) {
        return false;
    }
    
    $overall_success = true;
    foreach ($chat_ids as $cid) {
        $cid = trim($cid);
        if (empty($cid)) continue;
        
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = [
            'chat_id' => $cid,
            'text' => $text,
            'parse_mode' => $parse_mode,
            'disable_web_page_preview' => true
        ];
        if ($reply_markup) {
            $data['reply_markup'] = json_encode($reply_markup);
        }
        
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            $result = curl_exec($ch);
            curl_close($ch);
            if ($result === false) $overall_success = false;
        } else {
            $options = [
                'http' => [
                    'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                    'method'  => 'POST',
                    'content' => http_build_query($data),
                    'timeout' => 10
                ],
                'ssl' => [
                    'verify_peer' => false,
                    'verify_peer_name' => false
                ]
            ];
            $context  = stream_context_create($options);
            $result = @file_get_contents($url, false, $context);
            if ($result === false) $overall_success = false;
        }
    }
    
    return $overall_success;
}

function send_po_telegram_notification($id_po) {
    try {
        $db = getDB();
        
        // Ambil data PO dan User
        $stmt = $db->prepare("
            SELECT p.*, u.nama, u.no_hp
            FROM pengajuan_po p
            JOIN users u ON p.id_user = u.id_user
            WHERE p.id_po = ?
        ");
        $stmt->execute([$id_po]);
        $po = $stmt->fetch();
        
        if (!$po) return false;
        
        // Ambil detail PO
        $stmtDetail = $db->prepare("
            SELECT d.jumlah_pesan, d.harga_satuan_saat_pesan, v.keterangan_varian, i.nama_brand, i.nama_seri
            FROM detail_po d
            JOIN varian_item v ON d.id_varian = v.id_varian
            JOIN item i ON v.id_item = i.id_item
            WHERE d.id_po = ?
        ");
        $stmtDetail->execute([$id_po]);
        $details = $stmtDetail->fetchAll();
        
        $poNumber = 'PO-' . str_pad($po['id_po'], 5, '0', STR_PAD_LEFT);
        $tglMulai = date('d/m/Y', strtotime($po['tgl_mulai_sewa']));
        $tglSelesai = date('d/m/Y', strtotime($po['tgl_selesai_sewa']));
        $selisihHari = (new DateTime($po['tgl_mulai_sewa']))->diff(new DateTime($po['tgl_selesai_sewa']))->days;
        
        // Format Nomor WA
        $no_hp_wa = $po['no_hp'];
        if (substr($no_hp_wa, 0, 1) === '0') {
            $no_hp_wa = '62' . substr($no_hp_wa, 1);
        } elseif (substr($no_hp_wa, 0, 3) === '+62') {
            $no_hp_wa = substr($no_hp_wa, 1);
        }
        
        $namaPelanggan = htmlspecialchars($po['nama']);
        
        // Susun Pesan
        $msg = "<b>🚨 PRE-ORDER BARU MASUK! 🚨</b>\n\n";
        $msg .= "<b>Nomor PO:</b> {$poNumber}\n";
        $msg .= "<b>Pelanggan:</b> {$namaPelanggan}\n";
        $msg .= "<b>No. HP:</b> <a href=\"https://wa.me/{$no_hp_wa}\">{$po['no_hp']}</a>\n\n";
        $msg .= "<b>📅 Periode Sewa:</b>\n";
        $msg .= "{$tglMulai} s.d. {$tglSelesai} ({$selisihHari} Hari)\n\n";
        
        $msg .= "<b>🛒 Detail Pesanan:</b>\n";
        foreach ($details as $d) {
            $brand = htmlspecialchars($d['nama_brand']);
            $seri = htmlspecialchars($d['nama_seri']);
            $varian = htmlspecialchars($d['keterangan_varian']);
            $msg .= "• {$brand} {$seri} - {$varian}\n";
            $msg .= "  <i>{$d['jumlah_pesan']} pcs x Rp " . number_format($d['harga_satuan_saat_pesan'], 0, ',', '.') . "</i>\n";
        }
        
        $msg .= "\n<b>💰 Estimasi Total: Rp " . number_format($po['estimasi_total_harga'], 0, ',', '.') . "</b>\n\n";
        
        // Gunakan URL Langsung agar bisa diakses di Hosting tanpa Webhook
        $app_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) 
            ? "http://{$_SERVER['HTTP_HOST']}/ontimeadventure/admin/kelola_po.php"
            : "https://{$_SERVER['HTTP_HOST']}/admin/kelola_po.php";

        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Setujui', 'url' => "{$app_url}?action=setujui&id_po={$id_po}"],
                    ['text' => '❌ Batalkan', 'url' => "{$app_url}?action=batalkan&id_po={$id_po}"]
                ],
                [
                    ['text' => '🔍 Buka di Web Admin', 'url' => "{$app_url}?search=" . urlencode($poNumber)]
                ]
            ]
        ];
        
        return send_telegram_message($msg, 'HTML', $keyboard);
        
    } catch (Exception $e) {
        // Jangan biarkan error menghentikan aplikasi
        error_log("Gagal mengirim notifikasi Telegram: " . $e->getMessage());
        return false;
    }
}

function get_latest_chat_id($token) {
    $log_file = __DIR__ . '/../config/latest_chat_id.json';
    
    if (file_exists($log_file)) {
        $data = json_decode(file_get_contents($log_file), true);
        if ($data && isset($data['id'])) {
            return [
                'success' => true,
                'chat_id' => $data['id'],
                'name' => $data['name'] . ' (' . $data['type'] . ')'
            ];
        }
    }
    
    return ['success' => false, 'message' => 'Belum ada pesan yang terekam. Pastikan Anda sudah mengirim pesan ke Bot atau menambahkan Bot ke dalam Grup, kirim chat apapun di sana, lalu klik tombol ini lagi.'];
}

function send_telegram_photo($photo_path, $caption = '', $reply_markup = null) {
    $config = get_telegram_config();
    $token = $config['bot_token'];
    $chat_ids = explode(',', $config['chat_id']);
    
    if (empty($token) || empty($config['chat_id']) || !file_exists($photo_path)) {
        return false;
    }
    
    $overall_success = true;
    foreach ($chat_ids as $cid) {
        $cid = trim($cid);
        if (empty($cid)) continue;
        
        $url = "https://api.telegram.org/bot{$token}/sendPhoto";
        $post_fields = [
            'chat_id' => $cid,
            'photo' => new CURLFile(realpath($photo_path)),
            'caption' => $caption,
            'parse_mode' => 'HTML'
        ];
        
        if ($reply_markup) {
            $post_fields['reply_markup'] = json_encode($reply_markup);
        }
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: multipart/form-data'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post_fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $result = curl_exec($ch);
        curl_close($ch);
        if ($result === false) $overall_success = false;
    }
    return $overall_success;
}

function set_telegram_webhook($webhook_url) {
    $config = get_telegram_config();
    $token = $config['bot_token'];
    if (empty($token)) return false;
    
    $url = "https://api.telegram.org/bot{$token}/setWebhook";
    $data = ['url' => $webhook_url];
    
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $result = curl_exec($ch);
    curl_close($ch);
    return $result;
}
