<?php
/**
 * pengaturan.php - Halaman Pengaturan Tema
 */
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Pastikan hanya admin yang bisa mengakses
if ($_SESSION['role'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
// Baca setting dari database
$app_settings = [
    'primary_color' => '#198754',
    'tampilkan_kemiripan' => '1',
    'telegram_bot_token' => '',
    'telegram_chat_id' => '',
    'telegram_bot_username' => '',
    'telegram_message_template' => "Halo Admin!\nAda pesanan PO baru:\n[DETAIL_PESANAN]\n\nMohon segera di cek!",
    'wa_template_siap' => "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure mengucapkan terima kasih banyak karena sudah mempercayakan kebutuhan camping Kakak pada kami. 😊\n\nYeay, kabar gembira nih! Perlengkapan alat camping untuk pesanan Kakak ([NOMOR_PO]) sudah siap dan bisa langsung diambil di basecamp On Time Adventure ya. ⛺✨\n\nOiya, untuk pembayarannya nanti mohon siapkan uang pas (cash) ya, Kak! 💵 Tapi tenang aja, kalau nggak bawa cash, kami juga menyediakan pembayaran via QRIS dan Transfer Bank kok! 💳📱\n\nBiar nggak nyasar, Kakak bisa cek lokasi kami di sini ya:\n📍 https://maps.app.goo.gl/nwTFhVah2qhet7so8\n\nDitunggu kedatangannya, Kak! Semoga petualangannya nanti seru dan lancar jaya! ⛰️🎒",
    'wa_template_batal' => "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure sebelumnya meminta maaf yang sebesar-besarnya ya, Kak. 🙏\n\nUntuk pesanan Kakak dengan nomor [NOMOR_PO] terpaksa tidak bisa kami proses dulu karena: [ALASAN_BATAL].\n\nWah sayang banget padahal pengen banget support petualangan Kakak kali ini. 🥺\nTapi tenang, Kakak masih bisa cek persediaan alat ganti yang lain di katalog website ya! Siapa tahu ada yang cocok. 🏕️✨\n\nMakasih banyak Kak atas pengertiannya, sehat dan sukses selalu untuk rencana campingnya! ⛰️🎒",
    'wa_template_pengembalian' => "Halo Kak [NAMA_PELANGGAN]! 👋\nKami dari pihak On Time Adventure ingin menginformasikan bahwa masa sewa alat camping Kakak untuk PO [NOMOR_PO] telah habis per tanggal [TGL_SELESAI].\n\nMohon untuk segera mengembalikan alat tersebut ke tempat kami ya Kak. Jika ada kendala, silakan hubungi kami.\nTerima kasih! 🏕️✨"
];
$db = getDB();
$settings_query = $db->query("SELECT kunci, nilai FROM pengaturan");
while ($row = $settings_query->fetch()) {
    // Jangan timpa dengan string kosong jika ini adalah template teks
    if (in_array($row['kunci'], ['wa_template_siap', 'wa_template_batal', 'wa_template_pengembalian', 'telegram_message_template']) && trim($row['nilai']) === '') {
        continue;
    }
    $app_settings[$row['kunci']] = $row['nilai'];
}

// ── Handle AJAX Requests for Telegram ──
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'fetch_chat_id') {
        require_once __DIR__ . '/../includes/telegram_helper.php';
        header('Content-Type: application/json');
        $token = trim($_POST['telegram_bot_token'] ?? '');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token Bot tidak boleh kosong.']);
        } else {
            echo json_encode(get_latest_chat_id($token));
        }
        exit;
    }
    if ($_POST['action'] === 'test_telegram') {
        require_once __DIR__ . '/../includes/telegram_helper.php';
        header('Content-Type: application/json');
        $token = trim($_POST['telegram_bot_token'] ?? '');
        $chat_id = trim($_POST['telegram_chat_id'] ?? '');
        
        if (empty($token) || empty($chat_id)) {
            echo json_encode(['success' => false, 'message' => 'Token Bot dan Chat ID harus diisi!']);
            exit;
        }
        
        $url = "https://api.telegram.org/bot{$token}/sendMessage";
        $data = ['chat_id' => $chat_id, 'text' => "✅ Test Notifikasi Berhasil!\n\nBot Anda sudah terhubung dengan aplikasi On Time Adventure.", 'parse_mode' => 'HTML'];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $resData = json_decode($res, true);
        if ($resData && $resData['ok']) {
            echo json_encode(['success' => true, 'message' => 'Pesan test berhasil dikirim ke Telegram Anda!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal mengirim pesan. Pastikan Token dan Chat ID benar.']);
        }
        exit;
    }
    if ($_POST['action'] === 'check_webhook_status') {
        header('Content-Type: application/json');
        $token = trim($_POST['telegram_bot_token'] ?? '');
        if (empty($token)) {
            echo json_encode(['success' => false, 'message' => 'Token Bot tidak boleh kosong.']);
            exit;
        }
        $url = "https://api.telegram.org/bot{$token}/getWebhookInfo";
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        $res = curl_exec($ch);
        curl_close($ch);
        
        $resData = json_decode($res, true);
        if ($resData && $resData['ok']) {
            $info = $resData['result'];
            $status_html = "URL Target: <b>" . htmlspecialchars($info['url'] ?: 'Belum di-set') . "</b><br>";
            $status_html .= "Pesan Nyangkut: <b>" . ($info['pending_update_count'] ?? 0) . "</b><br>";
            if (!empty($info['last_error_message'])) {
                $status_html .= "<br><span class='text-danger'>Error Terakhir:<br>" . htmlspecialchars($info['last_error_message']) . "</span>";
            } else {
                $status_html .= "<br><span class='text-success'>Status: <b>Lancar (Tidak ada Error)</b></span>";
            }
            echo json_encode(['success' => true, 'html' => $status_html]);
        } else {
            echo json_encode(['success' => false, 'message' => 'Gagal terhubung ke Telegram.']);
        }
        exit;
    }
}

// Proses Upload Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'upload_banner') {
    $upload_dir = __DIR__ . '/../assets/img/banner/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    if (isset($_FILES['banner_image']) && $_FILES['banner_image']['error'] === UPLOAD_ERR_OK) {
        $file_tmp = $_FILES['banner_image']['tmp_name'];
        $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9.\-_]/', '', basename($_FILES['banner_image']['name']));
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
        
        $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($file_ext, $allowed_ext)) {
            if (move_uploaded_file($file_tmp, $upload_dir . $file_name)) {
                $banners = isset($app_settings['banner_slider']) ? (json_decode($app_settings['banner_slider'], true) ?: []) : [];
                $banners[] = $file_name;
                $stmt = $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES ('banner_slider', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
                $stmt->execute([json_encode($banners)]);
                $_SESSION['flash_success'] = 'Banner berhasil diunggah!';
            } else {
                $_SESSION['flash_error'] = 'Gagal mengunggah file banner.';
            }
        } else {
            $_SESSION['flash_error'] = 'Format file tidak diizinkan. Gunakan JPG, PNG, atau WEBP.';
        }
    } else {
        $_SESSION['flash_error'] = 'Gagal upload atau tidak ada file dipilih.';
    }
    header('Location: pengaturan.php');
    exit;
}

// Proses Hapus Banner
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete_banner') {
    $filename = $_POST['filename'] ?? '';
    if (!empty($filename)) {
        $banners = isset($app_settings['banner_slider']) ? (json_decode($app_settings['banner_slider'], true) ?: []) : [];
        $new_banners = array_values(array_filter($banners, function($b) use ($filename) { return $b !== $filename; }));
        
        $stmt = $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES ('banner_slider', ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        $stmt->execute([json_encode($new_banners)]);
        
        $filepath = __DIR__ . '/../assets/img/banner/' . $filename;
        if (file_exists($filepath)) { unlink($filepath); }
        $_SESSION['flash_success'] = 'Banner berhasil dihapus!';
    }
    header('Location: pengaturan.php');
    exit;
}

// Proses form disubmit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    
    if ($_POST['action'] === 'simpan_tema') {
        $new_color = trim($_POST['primary_color'] ?? '#198754');
        if (preg_match('/^#[a-f0-9]{6}$/i', $new_color)) {
            $db = getDB();
            $stmt = $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
            $stmt->execute(['primary_color', $new_color]);
            list($r, $g, $b) = sscanf($new_color, "#%02x%02x%02x");
            $stmt->execute(['tema_warna', "$r, $g, $b"]);
            
            $tampilkan = isset($_POST['tampilkan_kemiripan']) ? '1' : '0';
            $stmt->execute(['tampilkan_kemiripan', $tampilkan]);
            
            $_SESSION['flash_success'] = 'Tema berhasil disimpan ke Database!';
        } else {
            $_SESSION['flash_error'] = 'Format warna tidak valid.';
        }
        header('Location: pengaturan.php?tab=tema');
        exit;
    }

    if ($_POST['action'] === 'simpan_telegram') {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        $stmt->execute(['telegram_bot_token', trim($_POST['telegram_bot_token'] ?? '')]);
        $stmt->execute(['telegram_chat_id', trim($_POST['telegram_chat_id'] ?? '')]);
        $stmt->execute(['telegram_bot_username', trim($_POST['telegram_bot_username'] ?? '')]);
        $stmt->execute(['cf_worker_url', trim($_POST['cf_worker_url'] ?? '')]);
        $stmt->execute(['webhook_secret_token', trim($_POST['webhook_secret_token'] ?? '')]);
        $_SESSION['flash_success'] = 'Koneksi Telegram berhasil disimpan!';
        header('Location: pengaturan.php?tab=telegram');
        exit;
    }

    if ($_POST['action'] === 'simpan_pesan') {
        $db = getDB();
        $stmt = $db->prepare("INSERT INTO pengaturan (kunci, nilai) VALUES (?, ?) ON DUPLICATE KEY UPDATE nilai = VALUES(nilai)");
        
        // Restore default jika user menyimpan form kosong secara sengaja, 
        // tapi kita lebih baik simpan saja walau kosong, fallback PHP yg akan load defaultnya nanti.
        $stmt->execute(['telegram_message_template', trim($_POST['telegram_message_template'] ?? '')]);
        $stmt->execute(['wa_template_siap', trim($_POST['wa_template_siap'] ?? '')]);
        $stmt->execute(['wa_template_batal', trim($_POST['wa_template_batal'] ?? '')]);
        $stmt->execute(['wa_template_pengembalian', trim($_POST['wa_template_pengembalian'] ?? '')]);
        
        $_SESSION['flash_success'] = 'Format Pesan berhasil disimpan!';
        header('Location: pengaturan.php?tab=pesan');
        exit;
    }
}

$pageTitle = 'Pengaturan Tampilan';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Start::row-1 -->
<div class="row mt-4 mb-4">
    <div class="col-xl-10 col-lg-12 mx-auto">
        <div class="card custom-card">
            <div class="card-header border-bottom p-0">
                <ul class="nav nav-tabs card-header-tabs nav-style-1 nav-justified w-100 m-0" role="tablist">
                    <?php $active_tab = $_GET['tab'] ?? 'tema'; ?>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $active_tab === 'tema' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-tema" aria-selected="<?= $active_tab === 'tema' ? 'true' : 'false' ?>" role="tab">
                            <i class="ri-palette-line me-1"></i> Tema & Tampilan
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $active_tab === 'telegram' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-telegram" aria-selected="<?= $active_tab === 'telegram' ? 'true' : 'false' ?>" tabindex="-1" role="tab">
                            <i class="ri-telegram-line me-1"></i> Koneksi Telegram
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $active_tab === 'pesan' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-pesan" aria-selected="<?= $active_tab === 'pesan' ? 'true' : 'false' ?>" tabindex="-1" role="tab">
                            <i class="ri-message-3-line me-1"></i> Format Pesan
                        </a>
                    </li>
                    <li class="nav-item" role="presentation">
                        <a class="nav-link <?= $active_tab === 'banner' ? 'active' : '' ?>" data-bs-toggle="tab" href="#tab-banner" aria-selected="<?= $active_tab === 'banner' ? 'true' : 'false' ?>" tabindex="-1" role="tab">
                            <i class="ri-image-line me-1"></i> Banner
                        </a>
                    </li>
                </ul>
            </div>
            
            <div class="card-body p-4">
                <div class="tab-content">
                    
                    <!-- TAB TEMA -->
                    <div class="tab-pane <?= $active_tab === 'tema' ? 'active show' : '' ?>" id="tab-tema" role="tabpanel">
                        <form method="POST" action="pengaturan.php">
                            <input type="hidden" name="action" value="simpan_tema">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Warna Utama (Primary Color)</label>
                                <div class="d-flex align-items-center gap-3">
                                    <input type="color" class="form-control form-control-color" id="primary_color" name="primary_color" value="<?= htmlspecialchars($app_settings['primary_color']) ?>" title="Pilih warna utama">
                                    <div class="text-muted fs-13">Warna ini akan digunakan pada tombol, badge, link, dan elemen utama lainnya.</div>
                                </div>
                            </div>
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Rekomendasi Warna Alam (Adventure)</label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <button type="button" class="btn btn-sm text-white" style="background-color: #198754;" onclick="document.getElementById('primary_color').value='#198754'">Hijau Hutan</button>
                                    <button type="button" class="btn btn-sm text-white" style="background-color: #0d6efd;" onclick="document.getElementById('primary_color').value='#0d6efd'">Biru Langit</button>
                                    <button type="button" class="btn btn-sm text-white" style="background-color: #d97706;" onclick="document.getElementById('primary_color').value='#d97706'">Cokelat Tanah</button>
                                    <button type="button" class="btn btn-sm text-white" style="background-color: #dc3545;" onclick="document.getElementById('primary_color').value='#dc3545'">Merah Api</button>
                                    <button type="button" class="btn btn-sm text-white" style="background-color: #0f172a;" onclick="document.getElementById('primary_color').value='#0f172a'">Batu Gelap</button>
                                    <button type="button" class="btn btn-sm text-white" style="background-color: #8b5cf6;" onclick="document.getElementById('primary_color').value='#8b5cf6'">Vyzor Ungu (Asli)</button>
                                </div>
                            </div>
                            
                            <hr class="my-4 border-dashed border-light">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Tampilan Item & Katalog</label>
                                <div class="form-check form-switch mt-2">
                                    <input class="form-check-input" type="checkbox" role="switch" id="tampilkan_kemiripan" name="tampilkan_kemiripan" value="1" <?= $app_settings['tampilkan_kemiripan'] == '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="tampilkan_kemiripan">Tampilkan Persentase Kemiripan pada "Item Serupa"</label>
                                </div>
                                <div class="text-muted fs-13 mt-1">Jika dinonaktifkan, angka rekomendasi algoritma CBF (seperti "Kemiripan: 50.1%") tidak akan muncul bagi pelanggan.</div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary btn-wave"><i class="ri-save-line me-1"></i> Simpan Tema</button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB TELEGRAM -->
                    <div class="tab-pane <?= $active_tab === 'telegram' ? 'active show' : '' ?>" id="tab-telegram" role="tabpanel">
                        <form method="POST" action="pengaturan.php">
                            <input type="hidden" name="action" value="simpan_telegram">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold">Telegram Bot Token</label>
                                <input type="text" class="form-control" id="telegram_bot_token" name="telegram_bot_token" value="<?= htmlspecialchars($app_settings['telegram_bot_token'] ?? '') ?>" placeholder="Contoh: 123456789:ABCdefGHIjklMNOpqrSTUvwxYZ">
                                <div class="text-muted fs-13 mt-1">Token ini didapat dari <b>@BotFather</b> saat Anda membuat bot.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Telegram Bot Username (Opsional)</label>
                                <div class="input-group">
                                    <span class="input-group-text">@</span>
                                    <input type="text" class="form-control" id="telegram_bot_username" name="telegram_bot_username" value="<?= htmlspecialchars($app_settings['telegram_bot_username'] ?? '') ?>" placeholder="Booking101102Bot">
                                </div>
                                <div class="text-muted fs-13 mt-1">Digunakan untuk dokumentasi di halaman web. Bot ini sudah disetel untuk mengabaikan tag nama ini dari Telegram sehingga Anda tidak wajib mengisi ini jika tak ingin.</div>
                            </div>

                            <div class="mb-4">
                                <label class="form-label fw-semibold">Telegram Chat ID (Penerima Notifikasi)</label>
                                <div class="input-group">
                                    <input type="text" class="form-control" id="telegram_chat_id" name="telegram_chat_id" value="<?= htmlspecialchars($app_settings['telegram_chat_id'] ?? '') ?>" placeholder="Contoh: 123456789, 987654321">
                                    <button type="button" class="btn btn-secondary btn-wave" id="btnFetchChatId">
                                        <i class="ri-refresh-line"></i> Ambil Chat ID
                                    </button>
                                </div>
                                <div class="text-muted fs-13 mt-1" id="chatIdHelperText">
                                    Pisahkan dengan koma (,) jika Anda ingin notifikasi dikirim ke lebih dari 1 admin sekaligus.
                                </div>
                            </div>

                            <hr class="border-dashed my-4">
                            
                            <div class="alert alert-warning bg-warning-transparent border-warning mt-3">
                                <h6 class="fw-bold mb-2 text-warning"><i class="ri-shield-keyhole-line align-middle"></i> Pengaturan Cloudflare Webhook Proxy (Opsional)</h6>
                                <p class="mb-2 fs-13 text-dark">Gunakan pengaturan ini hanya jika server hosting Anda memblokir koneksi dari Telegram (biasanya ditandai dengan error <i>Connection reset by peer</i>).</p>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">URL Cloudflare Worker Anda</label>
                                    <input type="text" class="form-control" name="cf_worker_url" value="<?= htmlspecialchars($app_settings['cf_worker_url'] ?? '') ?>" placeholder="https://telegram-proxy.username.workers.dev/">
                                    <div class="text-muted fs-13 mt-1">Jika diisi, bot akan dikaitkan ke URL ini alih-alih URL hosting Anda.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label fw-semibold text-dark">Kata Sandi Rahasia (Secret Token)</label>
                                    <input type="text" class="form-control" name="webhook_secret_token" value="<?= htmlspecialchars($app_settings['webhook_secret_token'] ?? '') ?>" placeholder="Contoh: BukaPintu123">
                                    <div class="text-muted fs-13 mt-1">Kata sandi untuk melindungi webhook.php Anda. Sesuaikan dengan yang ada di kode Worker di bawah.</div>
                                </div>
                                
                                <?php
                                $worker_url = $base_url . 'webhook.php';
                                $full_webhook_url = "https://" . $_SERVER['HTTP_HOST'] . $worker_url;
                                $secret = htmlspecialchars($app_settings['webhook_secret_token'] ?? 'RAHASIA123');
                                ?>
                                
                                <label class="form-label fw-semibold mt-2 text-dark">Kode Javascript untuk Cloudflare Worker:</label>
                                <div class="bg-dark text-white p-3 rounded-2" style="position: relative;">
                                    <button type="button" class="btn btn-sm btn-light position-absolute top-0 end-0 m-2" onclick="navigator.clipboard.writeText(document.getElementById('cfCode').innerText); Swal.fire('Tersalin!', 'Kode berhasil disalin', 'success');"><i class="ri-clipboard-line"></i> Copy</button>
                                    <pre id="cfCode" class="mb-0 text-white fs-12" style="max-height: 200px; overflow-y: auto;">export default {
  async fetch(request, env, ctx) {
    const TARGET_URL = "<?= $full_webhook_url ?>";
    
    if (request.method !== "POST") {
      return new Response("Jembatan aktif. Harap gunakan POST.", { status: 200 });
    }

    const body = await request.text();

    const modifiedRequest = new Request(TARGET_URL, {
      method: "POST",
      headers: {
        "Content-Type": "application/json",
        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36",
        "X-Sandi-Rahasia": "<?= $secret ?>"
      },
      body: body
    });

    try {
      const response = await fetch(modifiedRequest);
      const responseText = await response.text();
      return new Response(responseText, { status: response.status, headers: { "Content-Type": "text/plain" } });
    } catch (e) {
      return new Response("Gagal menembus gerbang: " + e.message, { status: 500 });
    }
  },
};</pre>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between mt-4">
                                <div>
                                    <button type="button" class="btn btn-outline-info btn-wave me-2" id="btnTestTelegram">
                                        <i class="ri-send-plane-line me-1"></i> Test Kirim Notifikasi
                                    </button>
                                    <button type="button" class="btn btn-outline-warning btn-wave" id="btnCheckWebhook">
                                        <i class="ri-radar-line me-1"></i> Cek Status Telegram
                                    </button>
                                </div>
                                <button type="submit" class="btn btn-primary btn-wave"><i class="ri-save-line me-1"></i> Simpan Telegram</button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB PESAN -->
                    <div class="tab-pane <?= $active_tab === 'pesan' ? 'active show' : '' ?>" id="tab-pesan" role="tabpanel">
                        <form method="POST" action="pengaturan.php">
                            <input type="hidden" name="action" value="simpan_pesan">
                            
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-info"><i class="ri-telegram-line"></i> Format Pesan Telegram (Untuk Admin)</label>
                                <textarea class="form-control" name="telegram_message_template" rows="5"><?= htmlspecialchars($app_settings['telegram_message_template'] ?? '') ?></textarea>
                                <div class="text-muted fs-13 mt-2">
                                    Pesan ini akan dikirim otomatis oleh Bot Telegram kepada Admin ketika pelanggan melakukan Check Out.
                                </div>
                            </div>
                            
                            <div class="alert alert-primary bg-primary-transparent border-primary mt-3">
                                <h6 class="fw-bold mb-2 text-primary"><i class="ri-robot-line align-middle"></i> Panduan Perintah (Command) Bot Telegram</h6>
                                <ul class="mb-0 fs-13 ps-3 text-dark">
                                    <li class="mb-1"><code>/booking</code> — Melihat daftar pesanan yang "Barang Siap" diambil. Terlampir tombol <b>"📦 Serahkan Barang"</b>.</li>
                                    <li class="mb-1"><code>/sewa</code> — Melihat daftar pelanggan yang alatnya saat ini sedang "Barang Diambil" (sedang disewa & belum jatuh tempo).</li>
                                    <li><code>/barang</code> — Melihat daftar penyewa yang menunggak "Selesai (Belum Kembali)". Terlampir tombol untuk mengirim WA peringatan.</li>
                                    <li class="mt-2 text-muted"><i>Catatan: Bot sudah mendukung command dengan tag username, misal: <code>/barang@<?= htmlspecialchars($app_settings['telegram_bot_username'] ?: 'namabot') ?></code></i></li>
                                </ul>
                            </div>
                            
                            <hr class="border-dashed my-4">
                            <div class="mb-4">
                                <label class="form-label fw-semibold text-success"><i class="ri-whatsapp-line"></i> Format Pesan WhatsApp (Saat Penyewaan Disetujui/Siap)</label>
                                <textarea class="form-control" name="wa_template_siap" rows="4"><?= htmlspecialchars($app_settings['wa_template_siap'] ?? '') ?></textarea>
                                <div class="text-muted fs-13 mt-2">
                                    Gunakan variabel: <code>[NAMA_PELANGGAN]</code>, <code>[NOMOR_PO]</code>, <code>[DETAIL_ITEM]</code>, <code>[PERIODE_SEWA]</code>, <code>[TOTAL_HARGA]</code>
                                </div>
                            </div>
                            
                            <hr class="border-dashed my-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-danger"><i class="ri-whatsapp-line"></i> Format Pesan WhatsApp (Saat Penyewaan Dibatalkan)</label>
                                <textarea class="form-control" name="wa_template_batal" rows="4"><?= htmlspecialchars($app_settings['wa_template_batal'] ?? '') ?></textarea>
                                <div class="text-muted fs-13 mt-2">
                                    Gunakan variabel: <code>[NAMA_PELANGGAN]</code>, <code>[NOMOR_PO]</code>, <code>[ALASAN_BATAL]</code>, <code>[DETAIL_ITEM]</code>, <code>[PERIODE_SEWA]</code>, <code>[TOTAL_HARGA]</code>
                                </div>
                            </div>
                            
                            <hr class="border-dashed my-4">
                            <div class="mb-3">
                                <label class="form-label fw-semibold text-warning"><i class="ri-whatsapp-line"></i> Format Pesan WhatsApp (Pengingat Pengembalian)</label>
                                <textarea class="form-control" name="wa_template_pengembalian" rows="4"><?= htmlspecialchars($app_settings['wa_template_pengembalian'] ?? '') ?></textarea>
                                <div class="text-muted fs-13 mt-2">
                                    Pesan ini dipakai untuk mengingatkan batas waktu pengembalian alat (bisa dikirim melalui Telegram <code>/barang</code>).<br> Gunakan variabel: <code>[NAMA_PELANGGAN]</code>, <code>[NOMOR_PO]</code>, <code>[DETAIL_ITEM]</code>, <code>[TGL_SELESAI]</code>
                                </div>
                            </div>
                            
                            <div class="d-flex justify-content-end mt-4">
                                <button type="submit" class="btn btn-primary btn-wave"><i class="ri-save-line me-1"></i> Simpan Pesan</button>
                            </div>
                        </form>
                    </div>

                    <!-- TAB BANNER -->
                    <div class="tab-pane <?= $active_tab === 'banner' ? 'active show' : '' ?>" id="tab-banner" role="tabpanel">
                        <div class="mb-4">
                            <label class="form-label fw-semibold">Daftar Banner Saat Ini</label>
                            <?php 
                            $banners = isset($app_settings['banner_slider']) ? (json_decode($app_settings['banner_slider'], true) ?: []) : [];
                            if (empty($banners)): ?>
                                <div class="alert alert-light text-center">Belum ada gambar banner. Placeholder tenda akan ditampilkan.</div>
                            <?php else: ?>
                                <div class="row gy-3">
                                    <?php foreach ($banners as $b): ?>
                                        <div class="col-sm-6 col-md-4">
                                            <div class="card border border-light shadow-sm mb-0">
                                                <img src="<?= $base_url ?>assets/img/banner/<?= htmlspecialchars($b) ?>" class="card-img-top" style="height: 120px; object-fit: cover;" alt="Banner">
                                                <div class="card-body p-2 text-center">
                                                    <button type="button" class="btn btn-sm btn-danger btn-wave" onclick="if(confirm('Hapus banner ini?')) document.getElementById('formDeleteBanner_<?= md5($b) ?>').submit();">
                                                        <i class="ri-delete-bin-line"></i> Hapus
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                        <form id="formDeleteBanner_<?= md5($b) ?>" method="POST" action="pengaturan.php" style="display:none;">
                                            <input type="hidden" name="action" value="delete_banner">
                                            <input type="hidden" name="filename" value="<?= htmlspecialchars($b) ?>">
                                        </form>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <hr class="border-dashed my-4">
                        
                        <div class="mb-0">
                            <label class="form-label fw-semibold">Tambah Banner Baru</label>
                            <form method="POST" action="pengaturan.php" enctype="multipart/form-data" class="d-flex gap-2">
                                <input type="hidden" name="action" value="upload_banner">
                                <input type="file" class="form-control" name="banner_image" accept="image/png, image/jpeg, image/jpg, image/webp" required>
                                <button type="submit" class="btn btn-primary btn-wave text-nowrap"><i class="ri-upload-2-line"></i> Upload</button>
                            </form>
                            <div class="text-muted fs-13 mt-2">Rekomendasi rasio gambar lanskap (16:9). Gambar akan ter-crop otomatis menyesuaikan tinggi area slider.</div>
                        </div>
                    </div>

                </div>
            </div>
        </div>
    </div>
</div>
<!--End::row-1 -->

<script>
document.addEventListener("DOMContentLoaded", function() {
    const btnFetch = document.getElementById('btnFetchChatId');
    const btnTest = document.getElementById('btnTestTelegram');
    const btnCheck = document.getElementById('btnCheckWebhook');
    const inputToken = document.getElementById('telegram_bot_token');
    const inputChatId = document.getElementById('telegram_chat_id');
    const helperText = document.getElementById('chatIdHelperText');

    btnFetch.addEventListener('click', function() {
        const token = inputToken.value.trim();
        if (!token) {
            Swal.fire('Oops', 'Token Bot tidak boleh kosong!', 'warning');
            return;
        }

        btnFetch.disabled = true;
        btnFetch.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Loading...';

        const formData = new FormData();
        formData.append('action', 'fetch_chat_id');
        formData.append('telegram_bot_token', token);

        fetch('pengaturan.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btnFetch.disabled = false;
            btnFetch.innerHTML = '<i class="ri-refresh-line"></i> Ambil Chat ID Terakhir';
            
            if (data.success) {
                inputChatId.value = data.chat_id;
                Swal.fire('Berhasil!', `Chat ID ditemukan: ${data.chat_id} (atas nama: ${data.name})`, 'success');
                helperText.innerHTML = `<span class="text-success fw-bold"><i class="ri-check-line"></i> Chat ID atas nama: ${data.name} berhasil dimuat!</span>`;
            } else {
                Swal.fire('Gagal', data.message || 'Tidak ada pesan terbaru. Kirim pesan ke bot Anda lalu coba lagi.', 'error');
            }
        })
        .catch(err => {
            btnFetch.disabled = false;
            btnFetch.innerHTML = '<i class="ri-refresh-line"></i> Ambil Chat ID Terakhir';
            Swal.fire('Error', 'Terjadi kesalahan sistem.', 'error');
        });
    });

    btnTest.addEventListener('click', function() {
        const token = inputToken.value.trim();
        const chatId = inputChatId.value.trim();

        if (!token || !chatId) {
            Swal.fire('Oops', 'Token Bot dan Chat ID harus diisi sebelum ditest!', 'warning');
            return;
        }

        btnTest.disabled = true;
        btnTest.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengirim...';

        const formData = new FormData();
        formData.append('action', 'test_telegram');
        formData.append('telegram_bot_token', token);
        formData.append('telegram_chat_id', chatId);

        fetch('pengaturan.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="ri-send-plane-line me-1"></i> Test Kirim Notifikasi';
            
            if (data.success) {
                Swal.fire('Berhasil!', data.message, 'success');
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        })
        .catch(err => {
            btnTest.disabled = false;
            btnTest.innerHTML = '<i class="ri-send-plane-line me-1"></i> Test Kirim Notifikasi';
            Swal.fire('Error', 'Terjadi kesalahan saat mengirim pesan test.', 'error');
        });
    });

    btnCheck.addEventListener('click', function() {
        const token = inputToken.value.trim();
        if (!token) {
            Swal.fire('Oops', 'Token Bot harus diisi terlebih dahulu!', 'warning');
            return;
        }

        btnCheck.disabled = true;
        btnCheck.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Mengecek...';

        const formData = new FormData();
        formData.append('action', 'check_webhook_status');
        formData.append('telegram_bot_token', token);

        fetch('pengaturan.php', { method: 'POST', body: formData })
        .then(res => res.json())
        .then(data => {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="ri-radar-line me-1"></i> Cek Status Telegram';
            
            if (data.success) {
                Swal.fire({
                    title: 'Status Telegram Bot',
                    html: `<div class="text-start fs-14 mt-3">${data.html}</div>`,
                    icon: 'info'
                });
            } else {
                Swal.fire('Gagal', data.message, 'error');
            }
        })
        .catch(err => {
            btnCheck.disabled = false;
            btnCheck.innerHTML = '<i class="ri-radar-line me-1"></i> Cek Status Telegram';
            Swal.fire('Error', 'Terjadi kesalahan saat mengecek status.', 'error');
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
