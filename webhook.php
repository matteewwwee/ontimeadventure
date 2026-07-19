<?php
/**
 * Telegram Webhook Handler - On Time Adventure
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/telegram_helper.php';

// SISTEM KEAMANAN PINTU BELAKANG (SECRET TOKEN)
// Memastikan hanya Cloudflare Worker yang tahu sandi ini yang boleh mengeksekusi file ini.
$db = getDB();
$stmt = $db->query("SELECT nilai FROM pengaturan WHERE kunci='webhook_secret_token'");
$token_db = $stmt->fetchColumn() ?: '';

$sandi_rahasia = $_SERVER['HTTP_X_SANDI_RAHASIA'] ?? '';
if (!empty($token_db) && $sandi_rahasia !== $token_db) {
    http_response_code(403);
    die("Akses Ditolak: Anda bukan kurir resmi!");
}

function trigger_notifikasi_po($db, $id_po, $status_baru) {
    $stmt_u = $db->prepare("SELECT id_user FROM pengajuan_po WHERE id_po = ?");
    $stmt_u->execute([$id_po]);
    $id_user_notif = $stmt_u->fetchColumn();

    if ($id_user_notif) {
        $judul = "Status Pesanan Diperbarui";
        $pesan = "Pesanan PO-" . str_pad($id_po, 5, '0', STR_PAD_LEFT) . " Anda kini berstatus: " . $status_baru;
        $tautan = "/ontimeadventure/riwayat_po.php";
        $stmt_notif = $db->prepare("INSERT INTO notifikasi (id_user, judul, pesan, tautan) VALUES (?, ?, ?, ?)");
        $stmt_notif->execute([$id_user_notif, $judul, $pesan, $tautan]);

        if (strpos($status_baru, 'Selesai') !== false) {
            $judul_ulasan = "Pesanan Selesai!";
            $pesan_ulasan = "Terima kasih telah bertransaksi. Yuk luangkan waktu untuk memberikan ulasan barang!";
            $stmt_notif->execute([$id_user_notif, $judul_ulasan, $pesan_ulasan, $tautan]);
        }
    }
}

// Baca data JSON dari Telegram
$content = file_get_contents("php://input");
$update = json_decode($content, true);

if (!$update) {
    http_response_code(400);
    exit;
}

// Simpan Chat ID dari pesan masuk agar tombol "Ambil Chat ID" tetap berfungsi
// meskipun Webhook aktif (karena getUpdates API tidak bisa dipakai bersamaan Webhook)
if (isset($update['message'])) {
    $chat_id = $update['message']['chat']['id'];
    $chat_type = $update['message']['chat']['type'];
    $chat_name = $update['message']['chat']['title'] ?? $update['message']['chat']['first_name'] ?? 'Unknown';
    $data = [
        'id' => $chat_id,
        'name' => $chat_name,
        'type' => $chat_type,
        'time' => date('Y-m-d H:i:s')
    ];
    file_put_contents(__DIR__ . '/config/latest_chat_id.json', json_encode($data));
}

$config = get_telegram_config();
$token = $config['bot_token'];
if (empty($token)) {
    http_response_code(500);
    exit;
}

$db = getDB();

// Handle Force Reply for Jaminan
if (isset($update['message']['reply_to_message'])) {
    $text = $update['message']['text'] ?? '';
    $replied_text = $update['message']['reply_to_message']['text'] ?? '';
    if (preg_match('/jaminan untuk PO-(\d+)/', $replied_text, $matches)) {
        $id_po = (int)$matches[1];
        $jaminan = trim($text);
        $clicked_by = $update['message']['from']['first_name'] ?? 'Admin';
        $chat_id = $update['message']['chat']['id'];
        
        $status_baru = 'Barang Diambil';
        $icon = '📦';
        
        $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, waktu_diambil = COALESCE(waktu_diambil, NOW()), admin_penyerah = COALESCE(admin_penyerah, ?), jaminan = ? WHERE id_po = ?");
        $stmt->execute([$status_baru, $clicked_by, $jaminan, $id_po]);
        if ($stmt->rowCount() > 0) { trigger_notifikasi_po($db, $id_po, $status_baru); }
        
        if ($stmt->rowCount() > 0) {
            $poFormat = "PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT);
            $new_text = "🎒 <b>Penyerahan Barang Berhasil!</b>\n\n";
            $new_text .= "Nomor PO: <b>{$poFormat}</b>\n";
            $new_text .= "Status Saat Ini: <b>{$icon} {$status_baru}</b>\n";
            $new_text .= "Jaminan: <b>{$jaminan}</b>\n";
            $new_text .= "Diserahkan oleh: <b>{$clicked_by}</b> pada " . date('d/m/Y H:i');
            send_message($token, $chat_id, $new_text, 'HTML');
        } else {
            send_message($token, $chat_id, "Gagal memproses PO-{$id_po}. Mungkin status sudah diubah.");
        }
        http_response_code(200);
        exit;
    }
}
$app_settings = [];
$settings_query = $db->query("SELECT kunci, nilai FROM pengaturan");
while ($row = $settings_query->fetch()) {
    $app_settings[$row['kunci']] = trim($row['nilai'] ?? '') !== '' ? $row['nilai'] : null;
}

// Helper untuk merespon Callback Query (munculkan notifikasi pop-up kecil di Telegram)
function answer_callback_query($token, $callback_query_id, $text = "", $show_alert = false) {
    $url = "https://api.telegram.org/bot{$token}/answerCallbackQuery";
    $data = [
        'callback_query_id' => $callback_query_id,
        'text' => $text,
        'show_alert' => $show_alert
    ];
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    curl_close($ch);
}

// Helper untuk mengubah teks pesan setelah diklik
function edit_message_text($token, $chat_id, $message_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$token}/editMessageText";
    $data = [
        'chat_id' => $chat_id,
        'message_id' => $message_id,
        'text' => $text,
        'parse_mode' => $parse_mode
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    $response = curl_exec($ch);
    if ($response === false) {
        error_log("Telegram API Error: " . curl_error($ch));
    } else {
        $result = json_decode($response, true);
        if (!$result['ok']) {
            error_log("Telegram API Failed: " . $response);
        }
    }
    curl_close($ch);
}

// Helper untuk mengirim pesan baru
function send_message($token, $chat_id, $text, $parse_mode = 'HTML', $reply_markup = null) {
    $url = "https://api.telegram.org/bot{$token}/sendMessage";
    $data = [
        'chat_id' => $chat_id,
        'text' => $text,
        'parse_mode' => $parse_mode,
        'disable_web_page_preview' => true
    ];
    if ($reply_markup) {
        $data['reply_markup'] = json_encode($reply_markup);
    }
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_exec($ch);
    curl_close($ch);
}

// Proses Callback Query
if (isset($update['callback_query'])) {
    $callback_query_id = $update['callback_query']['id'];
    $chat_id = $update['callback_query']['message']['chat']['id'];
    $message_id = $update['callback_query']['message']['message_id'];
    $data = $update['callback_query']['data'];
    $original_text = $update['callback_query']['message']['text']; 
    // original_text ini raw text tanpa HTML. Untuk mempertahankan HTML, 
    // kita harus query ulang ke database atau membuat string baru.

    parse_str($data, $params);
    $action = $params['action'] ?? '';
    $id_po = $params['id_po'] ?? 0;
    
    // Siapa yang menekan tombol
    $clicked_by = trim(($update['callback_query']['from']['first_name'] ?? '') . ' ' . ($update['callback_query']['from']['last_name'] ?? ''));
    if (empty($clicked_by)) {
        $clicked_by = $update['callback_query']['from']['username'] ?? 'Admin';
    }
    $clicked_by = htmlspecialchars($clicked_by);
    
    // Ambil data pelanggan untuk keperluan WhatsApp
      $stmtUser = $db->prepare("SELECT u.nama, u.no_hp, p.tgl_mulai_sewa, p.tgl_selesai_sewa, p.estimasi_total_harga FROM pengajuan_po p JOIN users u ON p.id_user = u.id_user WHERE p.id_po = ?");
      $stmtUser->execute([$id_po]);
      $user = $stmtUser->fetch();
      $no_hp_wa = $user ? preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $user['no_hp'])) : '';
      $namaPelanggan = $user ? $user['nama'] : '';
    $tgl_mulai_sewa = $user ? date('d/m/Y', strtotime($user['tgl_mulai_sewa'])) : '';
    $tgl_selesai_sewa = $user ? date('d/m/Y', strtotime($user['tgl_selesai_sewa'])) : '';
    $periode_sewa = "{$tgl_mulai_sewa} s.d. {$tgl_selesai_sewa}";
    $total_harga = $user ? "Rp " . number_format($user['estimasi_total_harga'], 0, ',', '.') : 'Rp 0';

    // Ambil detail item
    $stmtDetail = $db->prepare("
        SELECT d.jumlah_pesan, d.status_detail, d.alasan_batal, v.keterangan_varian, i.nama_brand, i.nama_seri
        FROM detail_po d
        JOIN varian_item v ON d.id_varian = v.id_varian
        JOIN item i ON v.id_item = i.id_item
        WHERE d.id_po = ?
    ");
    $stmtDetail->execute([$id_po]);
    $details = $stmtDetail->fetchAll();
    $detail_pesanan_wa = "";
    $detail_batal_wa = "";
    $ada_dibatalkan = false;
    foreach ($details as $d) {
        $brand = htmlspecialchars($d['nama_brand']);
        $seri = htmlspecialchars($d['nama_seri']);
        $varian = htmlspecialchars($d['keterangan_varian']);
        if ($d['status_detail'] === 'Dibatalkan') {
            $alasan = htmlspecialchars($d['alasan_batal'] ?? 'Barang kosong');
            $detail_batal_wa .= "❌ ~{$brand} {$seri} ({$varian}) x{$d['jumlah_pesan']}~ (Batal: {$alasan})\n";
            $ada_dibatalkan = true;
        } else {
            $detail_pesanan_wa .= "✅ {$brand} {$seri} ({$varian}) x{$d['jumlah_pesan']}\n";
        }
    }
    if ($ada_dibatalkan) {
        $detail_pesanan_wa .= "\n" . $detail_batal_wa;
    }
    $detail_pesanan_wa = trim($detail_pesanan_wa);

    $poFormat = "PO-" . str_pad($id_po, 5, '0', STR_PAD_LEFT);

    // Cek status saat ini
    $stmtStatus = $db->prepare("SELECT status_po FROM pengajuan_po WHERE id_po = ?");
    $stmtStatus->execute([$id_po]);
    $currentStatus = $stmtStatus->fetchColumn();

    $allowed_anytime = ['detail', 'serahkan_barang', 'pilih_jaminan', 'force_reply_jaminan', 'batalkan_po_booking', 'kembalikan_barang'];
    if ($currentStatus === 'Barang Siap') {
        $allowed_anytime = array_merge($allowed_anytime, ['setujui', 'batalkan', 'batal_alasan']);
    }
    if ($currentStatus !== 'Menunggu Pengecekan' && !in_array($action, $allowed_anytime)) {
        answer_callback_query($token, $callback_query_id, "PO ini sudah diproses! Status saat ini: " . $currentStatus, true);
        
        // Hapus tombol agar tidak bisa diklik lagi
        $new_text = "<b>🚨 PRE-ORDER SUDAH DIPROSES! 🚨</b>\n\n";
        $new_text .= "Nomor PO: {$poFormat}\n";
        $new_text .= "Status Saat Ini: <b>{$currentStatus}</b>";
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML');
        http_response_code(200);
        exit;
    }

    if ($action === 'setujui') {
        $status_baru = 'Barang Siap';
        $icon = '✅';
        
        $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, admin_penyetuju = COALESCE(admin_penyetuju, ?) WHERE id_po = ?");
        $stmt->execute([$status_baru, $clicked_by, $id_po]);
        if ($stmt->rowCount() > 0) { trigger_notifikasi_po($db, $id_po, $status_baru); }
        
        answer_callback_query($token, $callback_query_id, "Status PO-$id_po diubah menjadi: $status_baru");
        
        // Pesan WA
        $pesanWaTemplate = $app_settings['wa_template_siap'] ?? "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure mengucapkan terima kasih banyak karena sudah mempercayakan kebutuhan camping Kakak pada kami. 😊\n\nYeay, kabar gembira nih! Perlengkapan alat camping untuk pesanan Kakak ([NOMOR_PO]) sudah siap dan bisa langsung diambil di basecamp On Time Adventure ya. ⛺✨\n\n🛒 Detail Pesanan:\n[DETAIL_ITEM]\n\n📅 Periode Sewa: [PERIODE_SEWA]\n💰 Total Biaya Sewa: [TOTAL_HARGA]\n\nOiya, untuk pembayarannya nanti mohon siapkan uang pas (cash) ya, Kak! 💵 Tapi tenang aja, kalau nggak bawa cash, kami juga menyediakan pembayaran via QRIS dan Transfer Bank kok! 💳📱\n\nBiar nggak nyasar, Kakak bisa cek lokasi kami di sini ya:\n📍 https://maps.app.goo.gl/nwTFhVah2qhet7so8\n\nDitunggu kedatangannya, Kak! Semoga petualangannya nanti seru dan lancar jaya! ⛰️🎒";
        $pesanWa = str_replace(['[NAMA_PELANGGAN]', '[NOMOR_PO]', '[PERIODE_SEWA]', '[TOTAL_HARGA]', '[DETAIL_ITEM]'], [$namaPelanggan, $poFormat, $periode_sewa, $total_harga, $detail_pesanan_wa], $pesanWaTemplate);
        $urlWa = "https://wa.me/{$no_hp_wa}?text=" . urlencode($pesanWa);
        
        $new_text = "<b>🚨 PRE-ORDER DIPROSES! 🚨</b>\n\n";
        $new_text .= "Nomor PO: {$poFormat}\n";
        $new_text .= "Status Saat Ini: <b>$icon $status_baru</b>\n";
        $new_text .= "Periode Sewa: <b>{$periode_sewa}</b>\n\n";
        $new_text .= "<b>🛒 Detail Pesanan:</b>\n{$detail_pesanan_wa}\n";
        $new_text .= "<b>💰 Total Harga: {$total_harga}</b>\n\n";
        $new_text .= "Diproses oleh: <b>{$clicked_by}</b>\n\n";
        $new_text .= "<i>Klik tombol di bawah untuk mengabari pelanggan via WhatsApp.</i>";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 Kirim Pesan WA (Barang Siap)', 'url' => $urlWa]],
                [['text' => '❌ Ralat & Batalkan PO', 'callback_data' => "action=batalkan&id_po={$id_po}"]],
                [['text' => '🔍 Cek Detail (Lihat Gambar)', 'callback_data' => "action=detail&id_po={$id_po}"]]
            ]
        ];
        
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
    }
    
    elseif ($action === 'batalkan') {
        // Tampilkan opsi alasan
        answer_callback_query($token, $callback_query_id, "Silakan pilih alasan pembatalan");
        
        $new_text = "<b>Pilih Alasan Pembatalan untuk {$poFormat}:</b>";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '🚫 Barang Tidak Tersedia', 'callback_data' => "action=batal_alasan&id_po={$id_po}&alasan=kosong"]],
                [['text' => '⚠️ Barang Sedang Disewa', 'callback_data' => "action=batal_alasan&id_po={$id_po}&alasan=disewa"]],
                [['text' => '❌ Kesalahan Admin/Pengecekan', 'callback_data' => "action=batal_alasan&id_po={$id_po}&alasan=kesalahan"]]
            ]
        ];
        
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
    }
    
    elseif ($action === 'batal_alasan') {
        $alasan_code = $params['alasan'] ?? 'kosong';
        if ($alasan_code === 'kesalahan') {
            $teks_alasan = 'terdapat kesalahan pengecekan/sistem dari pihak admin';
        } else {
            $teks_alasan = ($alasan_code === 'kosong') ? 'barang tidak tersedia saat ini' : 'barang sedang dalam masa penyewaan orang lain';
        }
        
        $status_baru = 'Dibatalkan';
        $icon = '❌';
        
        $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, admin_penyetuju = NULL, admin_penyerah = NULL, admin_penerima = NULL, admin_pembatal = ? WHERE id_po = ?");
        $stmt->execute([$status_baru, $clicked_by, $id_po]);
        if ($stmt->rowCount() > 0) { trigger_notifikasi_po($db, $id_po, $status_baru); }
        
        answer_callback_query($token, $callback_query_id, "PO dibatalkan. Alasan: $teks_alasan");
        
        // Pesan WA
        $pesanWaTemplate = $app_settings['wa_template_batal'] ?? "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure sebelumnya meminta maaf yang sebesar-besarnya ya, Kak. 🙏\n\nUntuk pesanan Kakak dengan nomor [NOMOR_PO] terpaksa tidak bisa kami proses dulu karena: [ALASAN_BATAL].\n\n🛒 Detail Pesanan:\n[DETAIL_ITEM]\n\n📅 Periode Sewa: [PERIODE_SEWA]\n💰 Total Biaya Sewa: [TOTAL_HARGA]\n\nWah sayang banget padahal pengen banget support petualangan Kakak kali ini. 🥺\nTapi tenang, Kakak masih bisa cek persediaan alat ganti yang lain di katalog website ya! Siapa tahu ada yang cocok. 🏕️✨\n\nMakasih banyak Kak atas pengertiannya, sehat dan sukses selalu untuk rencana campingnya! ⛰️🎒";
        $pesanWa = str_replace(['[NAMA_PELANGGAN]', '[NOMOR_PO]', '[ALASAN_BATAL]', '[PERIODE_SEWA]', '[TOTAL_HARGA]', '[DETAIL_ITEM]'], [$namaPelanggan, $poFormat, $teks_alasan, $periode_sewa, $total_harga, $detail_pesanan_wa], $pesanWaTemplate);
        $urlWa = "https://wa.me/{$no_hp_wa}?text=" . urlencode($pesanWa);
        
        $new_text = "<b>🚨 PRE-ORDER DIBATALKAN! 🚨</b>\n\n";
        $new_text .= "Nomor PO: {$poFormat}\n";
        $new_text .= "Status Saat Ini: <b>$icon $status_baru</b>\n";
        $new_text .= "Alasan: <i>{$teks_alasan}</i>\n";
        $new_text .= "Periode Sewa: <b>{$periode_sewa}</b>\n\n";
        $new_text .= "<b>🛒 Detail Pesanan:</b>\n{$detail_pesanan_wa}\n";
        $new_text .= "<b>💰 Total Harga: {$total_harga}</b>\n\n";
        $new_text .= "Dibatalkan oleh: <b>{$clicked_by}</b>\n\n";
        $new_text .= "<i>Klik tombol di bawah untuk mengabari pelanggan via WhatsApp.</i>";
        
        $keyboard = [
            'inline_keyboard' => [
                [['text' => '💬 Kirim Pesan WA (Penolakan)', 'url' => $urlWa]],
                [['text' => '🔍 Cek Detail (Lihat Gambar)', 'callback_data' => "action=detail&id_po={$id_po}"]]
            ]
        ];
        
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
    }
    
    elseif ($action === 'edit_po') {
        answer_callback_query($token, $callback_query_id, "Memuat mode edit pesanan...");
        
        $new_text = "<b>Pilih Barang yang Kosong / Ingin Dicoret dari {$poFormat}:</b>\n\n";
        $new_text .= "<i>Perhatian: Mengklik tombol barang akan langsung menghapus barang tersebut dari PO ini dan menghitung ulang total harga!</i>\n\n";
        $new_text .= "<b>Daftar Item Saat Ini:</b>\n{$detail_pesanan_wa}\n";
        $new_text .= "<b>Total Harga Saat Ini: {$total_harga}</b>";
        
        $stmtDet = $db->prepare("SELECT d.id_detail, d.status_detail, i.nama_brand, i.nama_seri FROM detail_po d JOIN varian_item v ON d.id_varian = v.id_varian JOIN item i ON v.id_item = i.id_item WHERE d.id_po = ?");
        $stmtDet->execute([$id_po]);
        $items = $stmtDet->fetchAll();
        
        $keyboard = ['inline_keyboard' => []];
        foreach ($items as $item) {
            if ($item['status_detail'] === 'Disetujui') {
                $nama = htmlspecialchars($item['nama_brand'] . ' ' . $item['nama_seri']);
                $keyboard['inline_keyboard'][] = [
                    ['text' => "❌ (Kosong) {$nama}", 'callback_data' => "action=hapus_item&id_po={$id_po}&id_detail={$item['id_detail']}&alasan=kosong"]
                ];
                $keyboard['inline_keyboard'][] = [
                    ['text' => "⚠️ (Disewa) {$nama}", 'callback_data' => "action=hapus_item&id_po={$id_po}&id_detail={$item['id_detail']}&alasan=disewa"]
                ];
            }
        }
        
        $keyboard['inline_keyboard'][] = [
            ['text' => '✅ Selesai Edit & Setujui', 'callback_data' => "action=setujui&id_po={$id_po}"]
        ];
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Kembali', 'callback_data' => "action=refresh_po&id_po={$id_po}"]
        ];
        
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
    }
    
    elseif ($action === 'hapus_item') {
        $id_detail = $params['id_detail'] ?? 0;
        $alasan_code = $params['alasan'] ?? 'kosong';
        $teks_alasan = ($alasan_code === 'kosong') ? 'Barang kosong/rusak' : 'Masih disewa orang lain';
        
        $stmtDel = $db->prepare("UPDATE detail_po SET status_detail = 'Dibatalkan', alasan_batal = ? WHERE id_detail = ? AND id_po = ?");
        $stmtDel->execute([$teks_alasan, $id_detail, $id_po]);
        
        if ($stmtDel->rowCount() > 0) {
            $stmtUser2 = $db->prepare("SELECT tgl_mulai_sewa, tgl_selesai_sewa FROM pengajuan_po WHERE id_po = ?");
            $stmtUser2->execute([$id_po]);
            $poData = $stmtUser2->fetch();
            $selisihHari = (new DateTime($poData['tgl_mulai_sewa']))->diff(new DateTime($poData['tgl_selesai_sewa']))->days;
            if ($selisihHari <= 0) $selisihHari = 1;
            
            $stmtSum = $db->prepare("SELECT SUM(harga_satuan_saat_pesan * jumlah_pesan) FROM detail_po WHERE id_po = ? AND status_detail = 'Disetujui'");
            $stmtSum->execute([$id_po]);
            $sumHarga = (int)$stmtSum->fetchColumn();
            $estimasi_baru = $sumHarga * $selisihHari;
            
            $stmtUpd = $db->prepare("UPDATE pengajuan_po SET estimasi_total_harga = ? WHERE id_po = ?");
            $stmtUpd->execute([$estimasi_baru, $id_po]);
            
            answer_callback_query($token, $callback_query_id, "Barang berhasil dicoret!");
        } else {
            answer_callback_query($token, $callback_query_id, "Gagal/sudah dicoret sebelumnya.");
        }
        
        $stmtDetail = $db->prepare("SELECT d.id_detail, d.jumlah_pesan, d.status_detail, d.alasan_batal, v.keterangan_varian, i.nama_brand, i.nama_seri FROM detail_po d JOIN varian_item v ON d.id_varian = v.id_varian JOIN item i ON v.id_item = i.id_item WHERE d.id_po = ?");
        $stmtDetail->execute([$id_po]);
        $details = $stmtDetail->fetchAll();
        $detail_pesanan_wa = "";
        $keyboard = ['inline_keyboard' => []];
        $semua_batal = true;
        
        foreach ($details as $d) {
            $brand = htmlspecialchars($d['nama_brand']);
            $seri = htmlspecialchars($d['nama_seri']);
            $varian = htmlspecialchars($d['keterangan_varian']);
            if ($d['status_detail'] === 'Disetujui') {
                $semua_batal = false;
                $detail_pesanan_wa .= "✅ {$brand} {$seri} ({$varian}) x{$d['jumlah_pesan']}\n";
                $keyboard['inline_keyboard'][] = [
                    ['text' => "❌ (Kosong) {$brand} {$seri}", 'callback_data' => "action=hapus_item&id_po={$id_po}&id_detail={$d['id_detail']}&alasan=kosong"]
                ];
                $keyboard['inline_keyboard'][] = [
                    ['text' => "⚠️ (Disewa) {$brand} {$seri}", 'callback_data' => "action=hapus_item&id_po={$id_po}&id_detail={$d['id_detail']}&alasan=disewa"]
                ];
            } else {
                $alasan = htmlspecialchars($d['alasan_batal']);
                $detail_pesanan_wa .= "❌ ~{$brand} {$seri} ({$varian}) x{$d['jumlah_pesan']}~ (Batal: {$alasan})\n";
            }
        }
        $detail_pesanan_wa = trim($detail_pesanan_wa);
        
        $total_harga = "Rp " . number_format($estimasi_baru ?? $user['estimasi_total_harga'], 0, ',', '.');
        
        $new_text = "<b>Pilih Barang yang Kosong / Ingin Dicoret dari {$poFormat}:</b>\n\n";
        $new_text .= "<b>Daftar Item Saat Ini:</b>\n{$detail_pesanan_wa}\n";
        $new_text .= "<b>Total Harga Saat Ini: {$total_harga}</b>";
        
        if(!empty($details)) {
            $keyboard['inline_keyboard'][] = [
                ['text' => '✅ Selesai Edit & Setujui', 'callback_data' => "action=setujui&id_po={$id_po}"]
            ];
        }
        $keyboard['inline_keyboard'][] = [
            ['text' => '🔙 Kembali', 'callback_data' => "action=refresh_po&id_po={$id_po}"]
        ];
        
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
    }
    
    elseif ($action === 'refresh_po') {
        answer_callback_query($token, $callback_query_id, "Kembali ke menu awal...");
        $new_text = "<b>🚨 PRE-ORDER BARU MASUK! 🚨</b>\n\n";
        $new_text .= "<b>Nomor PO:</b> {$poFormat}\n";
        $new_text .= "<b>Pelanggan:</b> {$namaPelanggan}\n";
        $new_text .= "<b>No. HP:</b> <a href=\"https://wa.me/{$no_hp_wa}\">{$no_hp_wa}</a>\n\n";
        $new_text .= "<b>📅 Periode Sewa:</b>\n{$periode_sewa}\n\n";
        $new_text .= "<b>🛒 Detail Pesanan:</b>\n" . (!empty($detail_pesanan_wa) ? $detail_pesanan_wa : "<i>(Kosong)</i>") . "\n";
        $new_text .= "\n<b>💰 Estimasi Total: {$total_harga}</b>\n\n";
        
        $keyboard = [
            'inline_keyboard' => [
                [
                    ['text' => '✅ Setujui', 'callback_data' => "action=setujui&id_po={$id_po}"],
                    ['text' => '❌ Batalkan', 'callback_data' => "action=batalkan&id_po={$id_po}"]
                ],
                [
                    ['text' => '✏️ Edit Pesanan (Barang Kosong)', 'callback_data' => "action=edit_po&id_po={$id_po}"]
                ],
                [
                    ['text' => '🔍 Cek Detail (Lihat Gambar)', 'callback_data' => "action=detail&id_po={$id_po}"]
                ]
            ]
        ];
        edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
    }
    
    elseif ($action === 'detail') {
        answer_callback_query($token, $callback_query_id, "Mengirim gambar detail pesanan...");
        
        $stmtDetail = $db->prepare("
            SELECT v.keterangan_varian, i.nama_brand, i.nama_seri, i.gambar
            FROM detail_po d
            JOIN varian_item v ON d.id_varian = v.id_varian
            JOIN item i ON v.id_item = i.id_item
            WHERE d.id_po = ?
        ");
        $stmtDetail->execute([$id_po]);
        $items = $stmtDetail->fetchAll();
        
        foreach ($items as $item) {
            if (!empty($item['gambar'])) {
                $file_path = __DIR__ . '/assets/img/' . $item['gambar'];
                if (file_exists($file_path)) {
                    $caption = "📷 <b>{$item['nama_brand']} {$item['nama_seri']}</b>\nVarian: {$item['keterangan_varian']}";
                    send_telegram_photo($file_path, $caption);
                }
            }
        }
    }
    
    elseif ($action === 'pilih_jaminan') {
          $poFormat = "PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT);
          $new_text = "<b>Pilih Jaminan untuk {$poFormat}</b>\nSilakan pilih dokumen jaminan yang diberikan oleh pelanggan:";
          $keyboard = [
              'inline_keyboard' => [
                  [['text' => 'KTP', 'callback_data' => "action=serahkan_barang&id_po={$id_po}&jaminan=KTP"]],
                  [['text' => 'SIM', 'callback_data' => "action=serahkan_barang&id_po={$id_po}&jaminan=SIM"]],
                  [['text' => 'Kartu Pelajar', 'callback_data' => "action=serahkan_barang&id_po={$id_po}&jaminan=Kartu Pelajar"]],
                  [['text' => 'Lainnya (Ketik Manual)', 'callback_data' => "action=force_reply_jaminan&id_po={$id_po}"]],
                  [['text' => '🔙 Batal', 'callback_data' => "action=refresh_po&id_po={$id_po}"]]
              ]
          ];
          edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
      }
      
      elseif ($action === 'force_reply_jaminan') {
          $poFormat = "PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT);
          $new_text = "Meminta input jaminan manual untuk {$poFormat}...";
          edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML');

          $reply_markup = json_encode([
              'force_reply' => true,
              'input_field_placeholder' => 'Ketik jenis jaminan...'
          ]);
          $msg = "Balas pesan ini dengan nama jaminan untuk PO-{$id_po} (misal: Paspor, STNK)";
          send_message($token, $chat_id, $msg, 'HTML', json_decode($reply_markup, true));
      }

      elseif ($action === 'serahkan_barang') {
          $jaminan = $params['jaminan'] ?? 'Tidak diketahui';
          $status_baru = 'Barang Diambil';
          $icon = '📦';
          
          $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, waktu_diambil = COALESCE(waktu_diambil, NOW()), admin_penyerah = COALESCE(admin_penyerah, ?), jaminan = ? WHERE id_po = ?");
          $stmt->execute([$status_baru, $clicked_by, $jaminan, $id_po]);
        if ($stmt->rowCount() > 0) { trigger_notifikasi_po($db, $id_po, $status_baru); }
          
          if ($stmt->rowCount() > 0) {
              answer_callback_query($token, $callback_query_id, "Sip! Barang PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT) . " resmi diambil pelanggan!", true);
              
              $poFormat = "PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT);
              $new_text = "🎒 <b>Penyerahan Barang Berhasil!</b>\n\n";
              $new_text .= "Nomor PO: <b>{$poFormat}</b>\n";
              $new_text .= "Status Saat Ini: <b>{$icon} {$status_baru}</b>\n";
              $new_text .= "Jaminan: <b>{$jaminan}</b>\n";
              $new_text .= "Diserahkan oleh: <b>{$clicked_by}</b> pada " . date('d/m/Y H:i');
              
              edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML');
          } else {
              answer_callback_query($token, $callback_query_id, "Gagal! Mungkin status sudah diubah sebelumnya.", true);
              edit_message_text($token, $chat_id, $message_id, "⚠️ <i>Tombol Kedaluwarsa. Status barang telah berubah.</i>", 'HTML');
          }
      }
    
    elseif ($action === 'batalkan_po_booking') {
        $status_baru = 'Selesai/Dibatalkan';
        $icon = '❌';
        
        $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ? WHERE id_po = ?");
        $stmt->execute([$status_baru, $id_po]);
        if ($stmt->rowCount() > 0) { trigger_notifikasi_po($db, $id_po, $status_baru); }
        
        if ($stmt->rowCount() > 0) {
            
            // Get user phone for WA Batal
            $stmtUser = $db->prepare("SELECT u.nama, u.no_hp FROM pengajuan_po p JOIN users u ON p.id_user = u.id_user WHERE p.id_po = ?");
            $stmtUser->execute([$id_po]);
            $usr = $stmtUser->fetch();
            $no_hp_wa = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $usr['no_hp']));
            $poFormat = "PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT);
            $template_batal = $app_settings['wa_template_batal'] ?? "Mohon maaf Kak [NAMA_PELANGGAN], pesanan PO-[NOMOR_PO] terpaksa kami batalkan.";
            $pesanWaBatal = str_replace(['[NAMA_PELANGGAN]', '[NOMOR_PO]', '[ALASAN_BATAL]'], [$usr['nama'], $poFormat, '-'], $template_batal);
            $urlWaBatal = "https://wa.me/{$no_hp_wa}?text=" . urlencode($pesanWaBatal);
            
            $keyboard = [
                'inline_keyboard' => [
                    [['text' => '💬 Kirim WA (Batal)', 'url' => $urlWaBatal]]
                ]
            ];
            
            answer_callback_query($token, $callback_query_id, "Pesanan PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT) . " berhasil dibatalkan!", true);
            
            $new_text = "❌ <b>Pesanan Dibatalkan!</b>\n\n";
            $new_text .= "Nomor PO: <b>{$poFormat}</b>\n";
            $new_text .= "Status Saat Ini: <b>{$icon} {$status_baru}</b>\n";
            $new_text .= "Dibatalkan oleh: <b>{$clicked_by}</b> pada " . date('d/m/Y H:i');
            
            edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML', $keyboard);
        } else {
            answer_callback_query($token, $callback_query_id, "Gagal! Mungkin status sudah diubah sebelumnya.", true);
            edit_message_text($token, $chat_id, $message_id, "⚠️ <i>Tombol Kedaluwarsa. Status barang telah berubah.</i>", 'HTML');
        }
    }
    
    elseif ($action === 'kembalikan_barang') {
        $status_baru = 'Selesai (Barang Kembali)';
        $icon = '🏁';
        
        $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ?, waktu_kembali = COALESCE(waktu_kembali, NOW()), admin_penerima = COALESCE(admin_penerima, ?) WHERE id_po = ?");
        $stmt->execute([$status_baru, $clicked_by, $id_po]);
        if ($stmt->rowCount() > 0) { trigger_notifikasi_po($db, $id_po, $status_baru); }
        
        if ($stmt->rowCount() > 0) {
            // Restore stock only for 'Sewa'
            
            answer_callback_query($token, $callback_query_id, "Barang PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT) . " berhasil dikembalikan!", true);
            
            $poFormat = "PO-" . str_pad($id_po, 4, '0', STR_PAD_LEFT);
            $new_text = "🏁 <b>Barang Berhasil Dikembalikan!</b>\n\n";
            $new_text .= "Nomor PO: <b>{$poFormat}</b>\n";
            $new_text .= "Status Saat Ini: <b>{$icon} {$status_baru}</b>\n";
            $new_text .= "Diterima oleh: <b>{$clicked_by}</b> pada " . date('d/m/Y H:i');
            
            edit_message_text($token, $chat_id, $message_id, $new_text, 'HTML');
        } else {
            answer_callback_query($token, $callback_query_id, "Gagal! Mungkin status sudah diubah sebelumnya.", true);
            edit_message_text($token, $chat_id, $message_id, "⚠️ <i>Tombol Kedaluwarsa. Status barang telah berubah.</i>", 'HTML');
        }
    }
}

// Proses Pesan Teks (Commands)
if (isset($update['message']['text'])) {
    $text = trim($update['message']['text']);
    $chat_id = $update['message']['chat']['id'];
    
    // Pisahkan command dari tag bot username (misal: /barang@Namabot -> /barang)
    $cmd_parts = explode('@', strtolower($text));
    $cmd = trim($cmd_parts[0]);
    
    if ($cmd === '/barang' || $cmd === '//barang' || $cmd === '/sewa' || $cmd === '//sewa' || $cmd === '/booking' || $cmd === '//booking') {
        
        $is_sewa = ($cmd === '/sewa' || $cmd === '//sewa');
        $is_booking = ($cmd === '/booking' || $cmd === '//booking');
        
        if ($is_booking) {
            $status_filter = "'Barang Siap'";
            $header_msg = "🎒 <b>Barang Siap Diambil:</b>\nPelanggan yang belum mengambil pesanannya:";
            $empty_msg = "✅ Tidak ada pesanan yang berstatus Barang Siap saat ini.";
        } else {
            $status_filter = $is_sewa ? "'Barang Diambil'" : "'Selesai (Barang Belum Kembali)'";
            $header_msg = $is_sewa ? "🏕️ <b>Daftar Barang Sedang Disewa:</b>\nPelanggan yang saat ini memegang alat (belum jatuh tempo):" : "📋 <b>Daftar Penunggak (Belum Kembali):</b>\nSilakan tagih pelanggan di bawah ini:";
            $empty_msg = $is_sewa ? "✅ Saat ini tidak ada barang yang sedang disewa pelanggan." : "✅ Tidak ada barang telat/penunggak saat ini.";
        }
        
        // Query users
          $stmt = $db->query("
              SELECT p.id_po, u.nama, u.no_hp, p.tgl_selesai_sewa, p.status_po, p.waktu_diambil, p.waktu_kembali, p.jaminan, p.estimasi_total_harga
              FROM pengajuan_po p
              JOIN users u ON p.id_user = u.id_user
              WHERE p.status_po IN ($status_filter)
              ORDER BY p.tgl_selesai_sewa ASC
          ");
        $results = $stmt->fetchAll();
        
        if (empty($results)) {
            send_message($token, $chat_id, $empty_msg);
        } else {
            send_message($token, $chat_id, $header_msg);
            
            foreach ($results as $row) {
                $poFormat = "PO-" . str_pad($row['id_po'], 4, '0', STR_PAD_LEFT);
                $nama = $row['nama'];
                $no_hp = $row['no_hp'];
                $tgl_selesai = date('d/m/Y', strtotime($row['tgl_selesai_sewa']));
                $status = $row['status_po'];
                
                // Fetch details for WA
                $stmtDetail = $db->prepare("
                    SELECT d.jumlah_pesan, v.keterangan_varian, i.nama_brand, i.nama_seri
                    FROM detail_po d
                    JOIN varian_item v ON d.id_varian = v.id_varian
                    JOIN item i ON v.id_item = i.id_item
                    WHERE d.id_po = ?
                ");
                $stmtDetail->execute([$row['id_po']]);
                $details = $stmtDetail->fetchAll();
                
                $detail_pesanan_wa = "";
                foreach ($details as $d) {
                    $detail_pesanan_wa .= "- {$d['nama_brand']} {$d['nama_seri']} ({$d['keterangan_varian']}) x{$d['jumlah_pesan']}\n";
                }
                
                // Hitung Denda Keterlambatan
                $hari_telat = 0;
                $total_denda = 0;
                if (!empty($row['waktu_diambil'])) {
                    $stmt_harga = $db->prepare("SELECT SUM(harga_satuan_saat_pesan * jumlah_pesan) as sewa_harian FROM detail_po WHERE id_po = ? AND (status_detail IS NULL OR status_detail != 'Dibatalkan')");
                    $stmt_harga->execute([$row['id_po']]);
                    $sewa_harian = (int)$stmt_harga->fetchColumn();
                    
                    $jam_diambil = date('H:i:s', strtotime($row['waktu_diambil']));
                    $deadline_time = strtotime($row['tgl_selesai_sewa'] . ' ' . $jam_diambil);
                    $toleransi_time = strtotime('+3 hours', $deadline_time);
                    $waktu_sekarang = time();
                    
                    if ($waktu_sekarang > $toleransi_time) {
                        $selisih_detik = $waktu_sekarang - $deadline_time;
                        $hari_telat = ceil($selisih_detik / (24 * 60 * 60));
                        $total_denda = $hari_telat * $sewa_harian;
                    }
                }
                $total_biaya = $row['estimasi_total_harga'] + $total_denda;
                
                // WA Template
                $template = $app_settings['wa_template_pengembalian'] ?? "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure ingin menginformasikan bahwa masa sewa alat camping Kakak untuk PO [NOMOR_PO] telah habis per tanggal [TGL_SELESAI].\n\n🛒 Detail Alat:\n[DETAIL_ITEM]\n\nMohon untuk segera mengembalikan alat tersebut ke tempat kami ya Kak. Jika ada kendala, silakan hubungi kami.\nTerima kasih! 🏕️✨";
                $pesanWa = str_replace(
                    ['[NAMA_PELANGGAN]', '[NOMOR_PO]', '[TGL_SELESAI]', '[DETAIL_ITEM]'], 
                    [$nama, $poFormat, $tgl_selesai, $detail_pesanan_wa], 
                    $template
                );
                
                // Append denda info to WA
                if ($hari_telat > 0) {
                    $pesanWa .= "\n\n⚠️ *INFO KETERLAMBATAN*\nKakak tercatat terlambat {$hari_telat} Hari.\nEstimasi Denda: Rp " . number_format($total_denda, 0, ',', '.') . "\nTotal Tagihan (Sewa + Denda): Rp " . number_format($total_biaya, 0, ',', '.');
                }
                
                // Remove leading '0' or '+' from no_hp
                $no_hp_wa = preg_replace('/^0/', '62', preg_replace('/[^0-9]/', '', $no_hp));
                $urlWa = "https://wa.me/{$no_hp_wa}?text=" . urlencode($pesanWa);
                
                // Telegram message
                  $msg = "👤 <b>Penyewa:</b> {$nama}\n";
                  $msg .= "🏷 <b>No. PO:</b> {$poFormat}\n";
                  $msg .= "📊 <b>Status:</b> {$status}\n";
                  if ($is_sewa && !empty($row['jaminan'])) {
                      $msg .= "💳 <b>Jaminan:</b> {$row['jaminan']}\n";
                  }
                  if (!empty($row['waktu_diambil'])) {
                      $msg .= "🕒 <b>Waktu Diambil:</b> " . date('d/m/Y H:i', strtotime($row['waktu_diambil'])) . "\n";
                  }
                  if (!empty($row['waktu_kembali'])) {
                      $msg .= "🏠 <b>Waktu Kembali:</b> " . date('d/m/Y H:i', strtotime($row['waktu_kembali'])) . "\n";
                  }
                  $msg .= "⏳ <b>Batas Waktu:</b> {$tgl_selesai}\n\n";
                  $msg .= "🛒 <b>Item Pesanan:</b>\n{$detail_pesanan_wa}\n";
                  
                  $msg .= "💰 <b>Estimasi Harga:</b> Rp " . number_format($row['estimasi_total_harga'], 0, ',', '.') . "\n";
                  if ($hari_telat > 0) {
                      $msg .= "⚠️ <b>Keterlambatan:</b> {$hari_telat} Hari\n";
                      $msg .= "🔥 <b>Denda Keterlambatan:</b> Rp " . number_format($total_denda, 0, ',', '.') . "\n";
                      $msg .= "🧾 <b>Total Tagihan Berjalan:</b> Rp " . number_format($total_biaya, 0, ',', '.') . "\n";
                  }
                
                $keyboard = [];
                if ($is_booking) {
                    $template_siap = $app_settings['wa_template_siap'] ?? "Halo Kak [NAMA_PELANGGAN], pesanan PO-[NOMOR_PO] sudah siap diambil di toko On Time Adventure ya!";
                    $pesanWaSiap = str_replace(['[NAMA_PELANGGAN]', '[NOMOR_PO]'], [$nama, $poFormat], $template_siap);
                    $urlWaSiap = "https://wa.me/{$no_hp_wa}?text=" . urlencode($pesanWaSiap);
                    
                    $keyboard = [
                        'inline_keyboard' => [
                            [['text' => '📦 Serahkan Barang', 'callback_data' => "action=pilih_jaminan&id_po={$row['id_po']}"]],
                            [['text' => '❌ Batalkan Pesanan', 'callback_data' => "action=batalkan_po_booking&id_po={$row['id_po']}"]],
                            [['text' => '💬 WA (Barang Siap)', 'url' => $urlWaSiap]]
                        ]
                    ];
                } else if ($is_sewa) {
                    $keyboard = [
                        'inline_keyboard' => [
                            [['text' => '🏁 Barang Dikembalikan', 'callback_data' => "action=kembalikan_barang&id_po={$row['id_po']}"]]
                        ]
                    ];
                } else {
                    $keyboard = [
                        'inline_keyboard' => [
                            [['text' => '💬 Kirim Pengingat WA', 'url' => $urlWa]]
                        ]
                    ];
                }
                
                send_message($token, $chat_id, $msg, 'HTML', $keyboard);
            }
        }
    }
}

// Respon OK untuk Telegram agar tidak re-try
http_response_code(200);
echo "OK";
