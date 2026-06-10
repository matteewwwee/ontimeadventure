<?php
/**
 * Submit PO — On Time Adventure
 * Processes the Pre-Order submission from the cart page.
 * Uses database transactions for atomicity.
 */

require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';
require_once __DIR__ . '/includes/telegram_helper.php';

$base_url = '/ontimeadventure/';

// ── Only accept POST ──
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// ── CSRF validation ──
if (!validateCsrfToken()) {
    $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan coba lagi.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// ── Validate cart is not empty ──
if (empty($_SESSION['keranjang'])) {
    $_SESSION['flash_error'] = 'Keranjang kosong. Silakan tambahkan item terlebih dahulu.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// ── Validate dates & input ──
$tgl_mulai   = $_POST['tgl_mulai_sewa']   ?? '';
$tgl_selesai = $_POST['tgl_selesai_sewa'] ?? '';
$catatan_pelanggan = trim($_POST['catatan_pelanggan'] ?? '');

if (empty($tgl_mulai) || empty($tgl_selesai)) {
    $_SESSION['flash_error'] = 'Tanggal mulai dan selesai sewa wajib diisi.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// Validate date format
$dtMulai   = DateTime::createFromFormat('Y-m-d', $tgl_mulai);
$dtSelesai = DateTime::createFromFormat('Y-m-d', $tgl_selesai);

if (!$dtMulai || !$dtSelesai) {
    $_SESSION['flash_error'] = 'Format tanggal tidak valid.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// End date must be after start date
if ($dtSelesai <= $dtMulai) {
    $_SESSION['flash_error'] = 'Tanggal selesai harus lebih besar dari tanggal mulai sewa.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// Start date must not be in the past
$today = new DateTime('today');
if ($dtMulai < $today) {
    $_SESSION['flash_error'] = 'Tanggal mulai sewa tidak boleh di masa lalu.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// ── Calculate duration ──
$selisihHari = (int) $dtMulai->diff($dtSelesai)->days;
if ($selisihHari <= 0) {
    $_SESSION['flash_error'] = 'Durasi sewa tidak valid.';
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

$db = getDB();

// ── Verify stock availability for all items ──
$keranjang    = $_SESSION['keranjang'];
$ids          = array_keys($keranjang);
$placeholders = implode(',', array_fill(0, count($ids), '?'));

$stmt = $db->prepare("SELECT v.id_varian, v.harga_sewa_per_hari, v.stok_tersedia, i.jenis_transaksi 
                      FROM varian_item v 
                      JOIN item i ON v.id_item = i.id_item 
                      WHERE v.id_varian IN ($placeholders)");
$stmt->execute($ids);
$varianData = $stmt->fetchAll();

// Index by id_varian for quick lookup
$varianMap = [];
foreach ($varianData as $v) {
    $varianMap[$v['id_varian']] = $v;
}

// Check each cart item has sufficient stock
$stockErrors = [];
foreach ($keranjang as $idVarian => $jumlah) {
    if (!isset($varianMap[$idVarian])) {
        $stockErrors[] = "Varian ID $idVarian tidak ditemukan.";
        continue;
    }
    if ($jumlah > $varianMap[$idVarian]['stok_tersedia']) {
        $stockErrors[] = "Stok varian ID $idVarian tidak mencukupi (tersedia: {$varianMap[$idVarian]['stok_tersedia']}, diminta: $jumlah).";
    }
    if ($jumlah <= 0) {
        $stockErrors[] = "Jumlah pesanan tidak valid untuk varian ID $idVarian.";
    }
}

if (!empty($stockErrors)) {
    $_SESSION['flash_error'] = 'Gagal mengajukan PO: ' . implode(' ', $stockErrors);
    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}

// ── Calculate estimated total price ──
$estimasiTotal = 0;
foreach ($keranjang as $idVarian => $jumlah) {
    $harga = $varianMap[$idVarian]['harga_sewa_per_hari'];
    if ($varianMap[$idVarian]['jenis_transaksi'] === 'Beli') {
        $estimasiTotal += $harga * $jumlah; // Harga beli tidak dikali durasi
    } else {
        $estimasiTotal += $harga * $jumlah * $selisihHari;
    }
}

// ── Begin transaction ──
try {
    $db->beginTransaction();

    // Insert into pengajuan_po
    $stmtPo = $db->prepare(
        "INSERT INTO pengajuan_po (id_user, tgl_mulai_sewa, tgl_selesai_sewa, estimasi_total_harga, catatan_pelanggan, status_po)
         VALUES (?, ?, ?, ?, ?, 'Menunggu Pengecekan')"
    );
    $stmtPo->execute([
        $_SESSION['id_user'],
        $tgl_mulai,
        $tgl_selesai,
        $estimasiTotal,
        $catatan_pelanggan
    ]);

    $idPo = (int) $db->lastInsertId();

    // Insert detail_po for each cart item
    $stmtDetail = $db->prepare(
        "INSERT INTO detail_po (id_po, id_varian, jumlah_pesan, harga_satuan_saat_pesan)
         VALUES (?, ?, ?, ?)"
    );

    foreach ($keranjang as $idVarian => $jumlah) {
        $hargaSatuan = $varianMap[$idVarian]['harga_sewa_per_hari'];
        $stmtDetail->execute([
            $idPo,
            $idVarian,
            $jumlah,
            $hargaSatuan
        ]);
    }

    $db->commit();

    // Clear cart after successful submission
    $_SESSION['keranjang'] = [];

    // ── Check for Overlapping Dates ──
    $hasOverlap = false;
    $overlapStmt = $db->prepare("
        SELECT p.id_po 
        FROM detail_po d
        JOIN pengajuan_po p ON d.id_po = p.id_po
        WHERE d.id_varian = ?
          AND p.status_po NOT IN ('Dibatalkan', 'Selesai (Barang Kembali)', 'Selesai')
          AND p.tgl_selesai_sewa >= ?
          AND p.tgl_mulai_sewa <= ?
        LIMIT 1
    ");

    foreach ($keranjang as $idVarian => $jumlah) {
        $overlapStmt->execute([$idVarian, $tgl_mulai, $tgl_selesai]);
        if ($overlapStmt->fetch()) {
            $hasOverlap = true;
            break;
        }
    }

    // Format PO number for display
    $poNumber = 'PO-' . str_pad($idPo, 5, '0', STR_PAD_LEFT);
    
    if ($hasOverlap) {
        $_SESSION['flash_warning'] = "Pre-Order berhasil diajukan dengan nomor <strong>$poNumber</strong>!<br><br><strong>Peringatan:</strong> Beberapa alat yang Anda pesan memiliki jadwal yang mungkin bertabrakan dengan antrean penyewa lain. Admin kami akan melakukan pengecekan ketersediaan secara manual dan menghubungi Anda.";
    } else {
        $_SESSION['flash_success'] = "Pre-Order berhasil diajukan! Nomor PO Anda: <strong>$poNumber</strong>. Silakan pantau statusnya di halaman Riwayat PO.";
    }

    // Send Telegram Notification to Admin
    send_po_telegram_notification($idPo);

    header('Location: ' . $base_url . 'riwayat_po.php');
    exit;

} catch (Exception $e) {
    $db->rollBack();

    $_SESSION['flash_error'] = 'Terjadi kesalahan saat memproses PO. Silakan coba lagi.';
    // Optionally log error: error_log($e->getMessage());

    header('Location: ' . $base_url . 'keranjang_po.php');
    exit;
}
