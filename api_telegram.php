<?php
// Endpoint Webhook untuk menerima Callback Query dari tombol Inline Telegram
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/telegram_helper.php';

// Telegram mengirimkan data berupa JSON POST
$update_raw = file_get_contents('php://input');
$update = json_decode($update_raw, true);

// Pastikan request berisi callback_query (klik tombol inline)
if (isset($update['callback_query'])) {
    $cb = $update['callback_query'];
    $cb_id = $cb['id'];
    $chat_id = $cb['message']['chat']['id'];
    $message_id = $cb['message']['message_id'];
    $data = $cb['data']; // e.g., action=setujui&id_po=123
    $admin_name = $cb['from']['first_name'] ?? 'Admin Telegram';

    // Parse callback data
    parse_str($data, $params);
    $action = $params['action'] ?? '';
    $id_po = $params['id_po'] ?? 0;

    $config = get_telegram_config();
    $token = $config['bot_token'];

    if ($id_po && in_array($action, ['setujui', 'batalkan'])) {
        $db = getDB();
        $status_baru = '';
        
        if ($action === 'setujui') {
            $status_baru = 'Barang Siap';
            $msg_reply = "âœ… PO-$id_po telah disetujui!";
            
            $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, admin_penyetuju = ? WHERE id_po = ?");
            $stmt->execute([$status_baru, $admin_name, $id_po]);

        } elseif ($action === 'batalkan') {
            $status_baru = 'Dibatalkan';
            $msg_reply = "â Œ PO-$id_po telah dibatalkan.";
            
            $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, admin_penyetuju = NULL, admin_penyerah = NULL, admin_penerima = NULL WHERE id_po = ?");
            $stmt->execute([$status_baru, $id_po]);
        }

        // 1. Kirim Answer Callback Query agar ikon loading pada tombol berhenti
        file_get_contents("https://api.telegram.org/bot{$token}/answerCallbackQuery?callback_query_id={$cb_id}&text=" . urlencode($msg_reply));

        // 2. Ubah pesan asli (hilangkan tombol, tambahkan status)
        $old_text = $cb['message']['text'] ?? '';
        $new_text = $old_text . "\n\n---------------------------\n" . $msg_reply . " (Oleh: " . $admin_name . ")";
        
        $post_data = [
            'chat_id' => $chat_id,
            'message_id' => $message_id,
            'text' => $new_text
        ];
        
        $ch = curl_init("https://api.telegram.org/bot{$token}/editMessageText");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($post_data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_exec($ch);
        curl_close($ch);
    } else {
        // Jika bukan aksi yang dikenali
        file_get_contents("https://api.telegram.org/bot{$token}/answerCallbackQuery?callback_query_id={$cb_id}&text=" . urlencode("Aksi tidak valid atau buka web admin"));
    }
}

// Telegram harus selalu mendapatkan respon HTTP 200 OK
http_response_code(200);
echo "OK";
