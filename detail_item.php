<?php
/**
 * detail_item.php - Halaman Detail Item
 */
session_start();
require_once __DIR__ . '/config/database.php';

$db = getDB();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? '/ontimeadventure/' : '/';

// ── Validasi Parameter ID ───────────────────────────────────
if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    header('Location: ' . $base_url . 'katalog.php');
    exit;
}
$item_id = (int) $_GET['id'];

// ── Handle POST: Tambah ke Keranjang ────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tambah_keranjang'])) {
    if (!isset($_SESSION['id_user'])) {
        $_SESSION['flash_error'] = 'Anda harus login terlebih dahulu untuk menambah item ke keranjang.';
        header('Location: ' . $base_url . 'login.php');
        exit;
    }
    
    $id_varian = (int) ($_POST['id_varian'] ?? 0);
    $jumlah    = max(1, (int) ($_POST['jumlah'] ?? 1));

    $stmt_cek = $db->prepare("
        SELECT id_varian, stok_tersedia
        FROM varian_item
        WHERE id_varian = :id_varian AND id_item = :id_item
    ");
    $stmt_cek->execute([':id_varian' => $id_varian, ':id_item' => $item_id]);
    $varian_cek = $stmt_cek->fetch();

    if ($varian_cek && $varian_cek['stok_tersedia'] > 0) {
        if (!isset($_SESSION['keranjang'])) {
            $_SESSION['keranjang'] = [];
        }

        $jumlah = min($jumlah, (int)$varian_cek['stok_tersedia']);

        if (isset($_SESSION['keranjang'][$id_varian])) {
            $new_qty = $_SESSION['keranjang'][$id_varian] + $jumlah;
            $_SESSION['keranjang'][$id_varian] = min($new_qty, (int)$varian_cek['stok_tersedia']);
        } else {
            $_SESSION['keranjang'][$id_varian] = $jumlah;
        }

        $_SESSION['flash_success'] = 'Item berhasil ditambahkan ke keranjang!';
    } else {
        $_SESSION['flash_error'] = 'Gagal menambahkan item. Ketersediaan habis.';
    }

    header('Location: ' . $base_url . 'detail_item.php?id=' . $item_id);
    exit;
}

// ── Fetch Item Data + Kategori ──────────────────────────────
$stmt_item = $db->prepare("
    SELECT
        i.id_item,
        i.nama_brand,
        i.nama_seri,
        i.deskripsi_umum,
        i.gambar,
        i.jenis_transaksi,
        k.nama_kategori,
        k.id_kategori
    FROM item i
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    WHERE i.id_item = :id AND i.status_item = 'Aktif'
");
$stmt_item->execute([':id' => $item_id]);
$item = $stmt_item->fetch();

if (!$item) {
    header('Location: ' . $base_url . 'katalog.php');
    exit;
}

// ── Fetch Semua Varian Item ─────────────────────────────────
$stmt_varian = $db->prepare("
    SELECT id_varian, keterangan_varian, harga_sewa_per_hari, stok_tersedia, catatan_kondisi, gambar
    FROM varian_item
    WHERE id_item = :id
    ORDER BY harga_sewa_per_hari ASC
");
$stmt_varian->execute([':id' => $item_id]);
$varian_list = $stmt_varian->fetchAll();

// ── Cek Favorit ──────────────────────────────────────────
$is_favorite = false;
if (isset($_SESSION['id_user'])) {
    $stmt_fav = $db->prepare("SELECT id_favorit FROM favorit_item WHERE id_user = ? AND id_item = ?");
    $stmt_fav->execute([$_SESSION['id_user'], $item_id]);
    if ($stmt_fav->fetch()) {
        $is_favorite = true;
    }
}

// ── Fetch Jadwal Booking Aktif ──────────────────────────────
$stmt_booked = $db->prepare("
    SELECT d.id_varian, p.tgl_mulai_sewa, p.tgl_selesai_sewa, d.jumlah_pesan, p.status_po 
    FROM detail_po d
    JOIN pengajuan_po p ON d.id_po = p.id_po
    WHERE p.status_po NOT IN ('Dibatalkan', 'Selesai (Barang Kembali)', 'Selesai')
      AND p.tgl_selesai_sewa >= CURDATE()
      AND d.id_varian IN (SELECT id_varian FROM varian_item WHERE id_item = :id)
    ORDER BY p.tgl_mulai_sewa ASC
");
$stmt_booked->execute([':id' => $item_id]);
$booked_data = $stmt_booked->fetchAll();

$booked_dates = [];
foreach ($booked_data as $b) {
    $booked_dates[$b['id_varian']][] = $b;
}

// ── Fetch Reviews ────────────────────────────────────────────────
$stmt_reviews = $db->prepare("
    SELECT r.*, u.no_hp, u.nama 
    FROM review_item r 
    JOIN users u ON r.id_user = u.id_user 
    WHERE r.id_item = :id AND r.status_review = 'Aktif'
    ORDER BY r.tanggal DESC
");
$stmt_reviews->execute([':id' => $item_id]);
$reviews = $stmt_reviews->fetchAll();

$total_reviews = count($reviews);
$avg_rating = 0;
if ($total_reviews > 0) {
    $sum = array_sum(array_column($reviews, 'rating'));
    $avg_rating = round($sum / $total_reviews, 1);
}

if (!function_exists('formatRupiah')) {
    function formatRupiah(int $angka): string {
        return 'Rp ' . number_format($angka, 0, ',', '.');
    }
}

// Cek gambar
$has_image = false;
$img_path  = '';
if (!empty($item['gambar'])) {
    $file_path = __DIR__ . '/assets/img/' . $item['gambar'];
    if (file_exists($file_path)) {
        $has_image = true;
        $img_path  = $base_url . 'assets/img/' . htmlspecialchars($item['gambar']);
    }
}

$pageTitle = $item['nama_brand'] . ' ' . $item['nama_seri'];
require_once __DIR__ . '/includes/header.php';
?>

<style>
    @media (max-width: 576px) {
        .detail-container { margin-top: 15px !important; padding-top: 0 !important; }
        .image-col-mobile { padding-left: 0 !important; padding-right: 0 !important; }
        .image-card-mobile { border-radius: 0 !important; margin-bottom: 0.5rem !important; box-shadow: none !important; border-bottom: 1px solid #eee; border-top: none !important; }
        .image-body-mobile { padding: 0 !important; }
        .info-card-mobile { border-radius: 0 !important; box-shadow: none !important; margin-bottom: 0.5rem !important; border-bottom: 1px solid #eee; }
        .variant-card-mobile { border: 1px solid #e0e0e0 !important; padding: 0.75rem !important; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
    }
    @media (min-width: 577px) {
        .image-card-mobile, .info-card-mobile { border: none !important; box-shadow: none !important; background-color: transparent !important; }
        .info-card-mobile .card-body, .image-card-mobile .card-body { padding: 0 !important; }
    }
</style>
<div class="container mt-0 mt-md-4 pt-0 pt-md-2 detail-container">
    <!-- Detail Layout -->
    <div class="row mt-0 mt-md-4">
        <!-- Image Column -->
        <div class="col-xl-5 col-lg-5 col-md-12 image-col-mobile">
            <div class="card custom-card border-0 image-card-mobile position-relative">
                <!-- Floating Back Button -->
                <a href="<?= $base_url ?>katalog.php" class="btn btn-icon btn-white rounded-circle shadow-sm position-absolute bg-white text-dark d-flex align-items-center justify-content-center" style="top: 15px; left: 15px; z-index: 10; width: 40px; height: 40px; opacity: 0.85;">
                    <i class="ri-arrow-left-line fs-20"></i>
                </a>
                <div class="card-body p-3 image-body-mobile text-center">
                    <?php if ($has_image): ?>
                        <img id="mainProductImage" src="<?= $img_path ?>" class="img-fluid rounded-md" style="width: 100%; object-fit: cover; max-height: 500px;" alt="<?= htmlspecialchars($item['nama_brand'] . ' ' . $item['nama_seri']) ?>">
                    <?php else: ?>
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height:400px; width:100%;">
                            <i class="ri-image-line fs-100 text-muted"></i>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Info Column -->
        <div class="col-xl-7 col-lg-7 col-md-12">
            <div class="card custom-card info-card-mobile">
                <div class="card-body p-3 p-md-4">
                    <div class="d-flex justify-content-between align-items-start mb-1">
                        <h4 class="fw-bold fs-22 fs-md-28 text-dark mb-0"><?= htmlspecialchars($item['nama_seri']) ?></h4>
                        <?php if (isset($_SESSION['id_user'])): ?>
                            <button class="btn btn-icon btn-sm btn-<?= $is_favorite ? 'danger' : 'light' ?> rounded-circle shadow-sm ms-2 flex-shrink-0" id="btnFavorite" data-id="<?= $item['id_item'] ?>" title="Tambahkan ke Favorit">
                                <i class="<?= $is_favorite ? 'ri-heart-3-fill' : 'ri-heart-3-line' ?>"></i>
                            </button>
                        <?php else: ?>
                            <a href="<?= $base_url ?>login.php" class="btn btn-icon btn-sm btn-light rounded-circle shadow-sm ms-2 flex-shrink-0" title="Login untuk menyimpan">
                                <i class="ri-heart-3-line text-muted"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                    <?php if($total_reviews > 0): ?>
                        <div class="mb-2 d-flex align-items-center gap-1">
                            <i class="ri-star-fill text-warning fs-15"></i>
                            <span class="fw-semibold fs-14 text-dark"><?= $avg_rating ?></span>
                            <span class="text-muted fs-13">(<?= $total_reviews ?> Ulasan)</span>
                        </div>
                    <?php endif; ?>
                    <h6 class="text-uppercase text-muted fw-semibold mb-3 fs-13">
                        <span class="badge bg-primary-transparent me-2"><?= htmlspecialchars($item['nama_kategori']) ?></span>
                        <span class="badge <?= $item['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> me-2"><?= htmlspecialchars($item['jenis_transaksi']) ?></span>
                        <?= htmlspecialchars($item['nama_brand']) ?>
                    </h6>
                    
                    <?php 
                        $min_price = !empty($varian_list) ? min(array_column($varian_list, 'harga_sewa_per_hari')) : 0;
                        $max_price = !empty($varian_list) ? max(array_column($varian_list, 'harga_sewa_per_hari')) : 0;
                        $price_display = $min_price === $max_price ? formatRupiah((int)$min_price) : formatRupiah((int)$min_price) . ' - ' . formatRupiah((int)$max_price);
                    ?>
                    <h5 class="fw-semibold text-primary mb-3"><?= $price_display ?> 
                        <?php if ($item['jenis_transaksi'] === 'Sewa'): ?>
                            <span class="fs-13 text-muted fw-normal">/hari</span>
                        <?php endif; ?>
                    </h5>
                    
                    <?php if (!empty($item['deskripsi_umum'])): ?>
                        <p class="text-muted fs-14 mb-4"><?= nl2br(htmlspecialchars($item['deskripsi_umum'])) ?></p>
                    <?php endif; ?>

                    <h5 class="fw-semibold mb-3">Pilihan Varian:</h5>
                    
                    <?php if (empty($varian_list)): ?>
                        <div class="alert alert-warning">Belum ada varian tersedia untuk item ini.</div>
                    <?php else: ?>
                        <div class="row gy-2 gy-md-3">
                            <?php foreach ($varian_list as $v): ?>
                                <div class="col-12">
                                    <div class="rounded p-3 bg-white variant-card-mobile">
                                        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                                            <div class="d-flex align-items-center gap-3">
                                                <?php if (!empty($v['gambar']) && file_exists(__DIR__ . '/assets/img/' . $v['gambar'])): ?>
                                                    <img src="<?= $base_url ?>assets/img/<?= htmlspecialchars($v['gambar']) ?>" class="img-thumbnail border-primary" style="width: 45px; height: 45px; object-fit: cover;" alt="Varian">
                                                <?php endif; ?>
                                                <div>
                                                    <h6 class="fw-semibold mb-1 fs-15"><?= htmlspecialchars($v['keterangan_varian']) ?></h6>
                                                    <span class="fw-bold text-primary fs-15"><?= formatRupiah((int)$v['harga_sewa_per_hari']) ?> 
                                                        <?php if ($item['jenis_transaksi'] === 'Sewa'): ?>
                                                            <span class="fs-11 text-muted fw-normal">/hari</span>
                                                        <?php endif; ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="d-flex align-items-center gap-2 mb-3 flex-wrap">
                                            <?php if((int)$v['stok_tersedia'] > 0): ?>
                                                <span class="badge bg-primary-transparent border border-primary"><i class="ri-checkbox-circle-line me-1"></i>Tersedia: <?= (int)$v['stok_tersedia'] ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-danger-transparent border border-danger"><i class="ri-close-circle-line me-1"></i>Habis</span>
                                            <?php endif; ?>

                                            <?php if (!empty($v['catatan_kondisi'])): ?>
                                                <span class="badge bg-info-transparent"><i class="ri-information-line me-1"></i>Kondisi: <?= htmlspecialchars($v['catatan_kondisi']) ?></span>
                                            <?php endif; ?>
                                        </div>

                                        <?php if ($item['jenis_transaksi'] === 'Sewa'): ?>
                                            <div id="jadwal_po_wrapper_<?= $v['id_varian'] ?>" class="mb-3 p-2 bg-light rounded border" style="<?= isset($booked_dates[$v['id_varian']]) ? '' : 'display: none;' ?>">
                                                <span class="fs-12 text-muted d-block mb-2 fw-semibold"><i class="ri-calendar-event-line text-primary"></i> Jadwal Terpesan (PO):</span>
                                                <div class="d-flex flex-column gap-1 jadwal-list" data-varian="<?= $v['id_varian'] ?>">
                                                    <?php if (isset($booked_dates[$v['id_varian']])): ?>
                                                    <?php foreach ($booked_dates[$v['id_varian']] as $bd): ?>
                                                        <?php 
                                                            $is_pending = ($bd['status_po'] === 'Menunggu Pengecekan'); 
                                                            $icon = $is_pending ? 'ri-time-line text-warning' : 'ri-check-double-line text-success';
                                                            $status_text = $is_pending ? 'Menunggu Konfirmasi' : 'Terkonfirmasi';
                                                            $badge_class = $is_pending ? 'bg-warning-transparent text-warning' : 'bg-success-transparent text-success';
                                                        ?>
                                                        <div class="fs-12 text-dark mb-1 d-flex align-items-center flex-wrap gap-1">
                                                            <i class="<?= $icon ?>"></i> 
                                                            <span><?= date('d M Y', strtotime($bd['tgl_mulai_sewa'])) ?> - <?= date('d M Y', strtotime($bd['tgl_selesai_sewa'])) ?></span>
                                                            <span class="fw-bold text-primary">(<?= $bd['jumlah_pesan'] ?> unit)</span>
                                                            <span class="badge <?= $badge_class ?> p-1" style="font-size: 10px;"><?= $status_text ?></span>
                                                        </div>
                                                    <?php endforeach; ?>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="mt-2 text-muted border-top pt-2" style="font-size: 11px; line-height: 1.4;">
                                                    <i class="ri-information-line text-info fs-13 align-middle"></i> <strong>Info:</strong> Jadwal <span class="badge bg-warning-transparent text-warning px-1" style="font-size: 9px;">Menunggu Konfirmasi</span> berarti masih berpeluang untuk Anda pesan (Sistem Siapa Cepat). Jika <span class="badge bg-success-transparent text-success px-1" style="font-size: 9px;">Terkonfirmasi</span>, maka ketersediaan pada tanggal tersebut sudah pasti habis.
                                                </div>
                                            </div>
                                        <?php endif; ?>

                                        <?php if ((int)$v['stok_tersedia'] > 0): ?>
                                            <form method="POST" action="<?= $base_url ?>detail_item.php?id=<?= $item_id ?>" class="d-flex gap-2 align-items-center w-100">
                                                <input type="hidden" name="tambah_keranjang" value="1">
                                                <input type="hidden" name="id_varian" value="<?= $v['id_varian'] ?>">
                                                <input type="number" name="jumlah" value="1" min="1" max="<?= (int)$v['stok_tersedia'] ?>" class="form-control form-control-sm text-center" style="width: 60px; height: 35px;" aria-label="Jumlah">
                                                <button type="submit" class="btn btn-primary btn-sm btn-wave flex-fill" style="height: 35px;">
                                                    <i class="ri-shopping-cart-line me-1"></i> Keranjang
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <button class="btn btn-danger btn-sm disabled btn-wave w-100" style="height: 35px;" disabled>
                                                <i class="ri-close-circle-line me-1"></i> Kosong
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <!-- Rekomendasi Section -->
    <?php
    $current_item_id = $item_id;
    require_once __DIR__ . '/rekomendasi.php';
    ?>

    <div class="row mt-5 mb-4">
        <div class="col-12 d-flex justify-content-between align-items-center mb-3">
            <h4 class="fw-semibold mb-0">Item Serupa</h4>
            <a href="<?= $base_url ?>katalog.php" class="btn btn-sm btn-outline-primary btn-wave">Lihat Semua</a>
        </div>

        <?php if (empty($rekomendasi)): ?>
            <div class="col-12 text-center text-muted py-3">Belum ada rekomendasi yang tersedia.</div>
        <?php else: ?>
            <div class="col-12">
                <div class="d-flex gap-3 overflow-auto pb-3" style="scrollbar-width: thin;">
                    <?php foreach ($rekomendasi as $rec): ?>
                        <?php
                            $rec_has_image = false;
                            $rec_img_path  = '';
                            if (!empty($rec['gambar'])) {
                                $rec_file = __DIR__ . '/assets/img/' . $rec['gambar'];
                                if (file_exists($rec_file)) {
                                    $rec_has_image = true;
                                    $rec_img_path  = $base_url . 'assets/img/' . htmlspecialchars($rec['gambar']);
                                }
                            }
                        ?>
                        <div class="card custom-card flex-shrink-0" style="width: 220px;">
                            <div class="card-body p-2">
                                <a href="<?= $base_url ?>detail_item.php?id=<?= $rec['id_item'] ?>">
                                    <?php if ($rec_has_image): ?>
                                        <img src="<?= $rec_img_path ?>" class="img-fluid rounded mb-2" style="width:100%; height:150px; object-fit:cover;">
                                    <?php else: ?>
                                        <div class="bg-light rounded d-flex align-items-center justify-content-center mb-2" style="height:150px; width:100%;">
                                            <i class="ri-image-line fs-30 text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                </a>
                                <span class="d-block fs-11 text-muted mb-1 text-uppercase"><?= htmlspecialchars($rec['nama_brand']) ?></span>
                                <h6 class="fw-semibold mb-1 text-truncate" title="<?= htmlspecialchars($rec['nama_seri']) ?>">
                                    <a href="<?= $base_url ?>detail_item.php?id=<?= $rec['id_item'] ?>" class="text-dark"><?= htmlspecialchars($rec['nama_seri']) ?></a>
                                </h6>
                                <p class="fs-12 text-muted mb-1"><?= htmlspecialchars($rec['nama_kategori']) ?></p>
                                <?php if (($app_settings['tampilkan_kemiripan'] ?? '1') == '1'): ?>
                                    <div class="text-primary fs-11">Kemiripan: <?= number_format($rec['similarity_score'] * 100, 1) ?>%</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Reviews Section -->
    <div class="row mt-5 mb-4">
        <div class="col-12 mb-3">
            <h4 class="fw-semibold mb-0">Ulasan Pelanggan</h4>
        </div>
        <div class="col-12">
            <?php if ($total_reviews > 0): ?>
                <div class="card custom-card">
                    <div class="card-body p-4">
                        <div class="row">
                            <div class="col-md-3 text-center mb-4 mb-md-0 border-end">
                                <h1 class="display-4 fw-bold mb-0 text-dark"><?= $avg_rating ?></h1>
                                <div class="text-warning fs-20 mb-1">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="ri-star-<?= $i <= round($avg_rating) ? 'fill' : 'line' ?>"></i>
                                    <?php endfor; ?>
                                </div>
                                <span class="text-muted fs-13"><?= $total_reviews ?> ulasan</span>
                            </div>
                            <div class="col-md-9 px-md-4">
                                <?php foreach($reviews as $rev): 
                                    $masked_hp = substr($rev['no_hp'], 0, 4) . '****' . substr($rev['no_hp'], -3);
                                ?>
                                    <div class="mb-4 pb-4 border-bottom last-no-border">
                                        <div class="d-flex align-items-center justify-content-between mb-2">
                                            <div class="d-flex align-items-center">
                                                <div class="fw-semibold text-dark me-3">
                                                    <?= htmlspecialchars(!empty($rev['nama']) ? $rev['nama'] : $masked_hp) ?>
                                                </div>
                                                <div class="text-warning fs-14">
                                                    <?php for($i=1; $i<=5; $i++): ?>
                                                        <i class="ri-star-<?= $i <= $rev['rating'] ? 'fill' : 'line' ?>"></i>
                                                    <?php endfor; ?>
                                                </div>
                                            </div>
                                            <span class="text-muted fs-12"><i class="ri-time-line me-1"></i><?= date('d M Y', strtotime($rev['tanggal'])) ?></span>
                                        </div>
                                        <?php if(!empty($rev['komentar'])): ?>
                                            <p class="text-muted fs-14 mb-2"><?= nl2br(htmlspecialchars($rev['komentar'])) ?></p>
                                        <?php endif; ?>
                                        <?php if(!empty($rev['foto'])): ?>
                                            <img src="assets/img/reviews/<?= htmlspecialchars($rev['foto']) ?>" class="rounded border mt-2" style="max-height: 100px; max-width:100%; object-fit:cover; cursor:pointer;" onclick="if(window.previewModalImage && window.previewModal){window.previewModalImage.src=this.src; window.previewModal.show();}">
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; ?>
                                <style>.last-no-border:last-child{border-bottom:none !important; margin-bottom:0 !important; padding-bottom:0 !important;}</style>
                            </div>
                        </div>
                    </div>
                </div>
            <?php else: ?>
                <div class="text-center text-muted py-4 bg-light rounded border">
                    <i class="ri-message-3-line fs-24 d-block mb-2 text-primary"></i>
                    Belum ada ulasan untuk barang ini.
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal Full Preview Gambar -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-md-down modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0 pt-3 pe-3 justify-content-end position-absolute w-100 z-3" style="top:0; right:0;">
                <button type="button" class="btn btn-icon btn-dark rounded-circle shadow" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.8; width: 40px; height: 40px;">
                    <i class="ri-close-line fs-20 text-white"></i>
                </button>
            </div>
            <div class="modal-body text-center p-0 d-flex justify-content-center align-items-center" style="min-height: 80vh;" data-bs-dismiss="modal">
                <img id="previewModalImage" src="" class="img-fluid rounded shadow-lg" style="max-height: 85vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const mainImage = document.getElementById('mainProductImage');
    const thumbnails = document.querySelectorAll('.varian-thumbnail');
    window.previewModalImage = document.getElementById('previewModalImage');
    window.previewModal = null;
    
    if (document.getElementById('imagePreviewModal')) {
        window.previewModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    }
    
    if (mainImage) {
        // Full Preview on click
        mainImage.style.cursor = 'zoom-in';
        mainImage.addEventListener('click', function() {
            if (window.previewModalImage && window.previewModal) {
                window.previewModalImage.src = this.src;
                window.previewModal.show();
            }
        });
        
        // Thumbnail click handler
        if (thumbnails.length > 0) {
            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    const newSrc = this.getAttribute('data-full-image');
                    // Simple fade effect
                    mainImage.style.opacity = '0.5';
                    setTimeout(() => {
                        mainImage.src = newSrc;
                        mainImage.style.opacity = '1';
                    }, 150);
                });
            });
        }
    }

    // Handle Favorite Toggle
    const btnFav = document.getElementById('btnFavorite');
    if (btnFav) {
        btnFav.addEventListener('click', function(e) {
            e.preventDefault();
            let itemId = this.getAttribute('data-id');
            let icon = this.querySelector('i');
            
            fetch('api_toggle_favorit.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                body: 'id_item=' + itemId
            })
            .then(res => res.json())
            .then(data => {
                if(data.status === 'added') {
                    this.classList.replace('btn-light', 'btn-danger');
                    icon.classList.replace('ri-heart-3-line', 'ri-heart-3-fill');
                } else if(data.status === 'removed') {
                    this.classList.replace('btn-danger', 'btn-light');
                    icon.classList.replace('ri-heart-3-fill', 'ri-heart-3-line');
                } else {
                    alert(data.message || 'Terjadi kesalahan');
                }
            })
            .catch(err => console.error(err));
        });
    }
});

// Real-time PO fetching script
<?php if ($item['jenis_transaksi'] === 'Sewa'): ?>
document.addEventListener('DOMContentLoaded', function() {
    function fetchJadwalPO() {
        fetch('<?= $base_url ?>api_jadwal_po.php?id=<?= $item_id ?>')
            .then(response => response.json())
            .then(res => {
                if(res.error) return;
                const data = res.data || {};
                
                document.querySelectorAll('.jadwal-list').forEach(list => {
                    const id_varian = list.getAttribute('data-varian');
                    const wrapper = document.getElementById('jadwal_po_wrapper_' + id_varian);
                    
                    if(data[id_varian] && data[id_varian].length > 0) {
                        wrapper.style.display = 'block';
                        let html = '';
                        data[id_varian].forEach(bd => {
                            let icon = bd.is_pending ? 'ri-time-line text-warning' : 'ri-check-double-line text-success';
                            let statusText = bd.is_pending ? 'Menunggu Konfirmasi' : 'Terkonfirmasi';
                            let badgeClass = bd.is_pending ? 'bg-warning-transparent text-warning' : 'bg-success-transparent text-success';
                            
                            html += `<div class="fs-12 text-dark mb-1 d-flex align-items-center flex-wrap gap-1">
                                <i class="${icon}"></i> 
                                <span>${bd.tgl_mulai} - ${bd.tgl_selesai}</span>
                                <span class="fw-bold text-primary">(${bd.jumlah} unit)</span>
                                <span class="badge ${badgeClass} p-1" style="font-size: 10px;">${statusText}</span>
                            </div>`;
                        });
                        list.innerHTML = html;
                    } else {
                        wrapper.style.display = 'none';
                        list.innerHTML = '';
                    }
                });
            })
            .catch(e => console.error("Error fetching jadwal:", e));
    }

    setInterval(fetchJadwalPO, 5000);
});
<?php endif; ?>
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
