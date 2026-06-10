<?php
/**
 * favorit.php - Halaman Daftar Favorit
 */
session_start();
require_once __DIR__ . '/config/database.php';

// Cek login
if (!isset($_SESSION['id_user'])) {
    $_SESSION['flash_error'] = 'Silakan login untuk melihat daftar favorit Anda.';
    header('Location: login.php');
    exit;
}

$db = getDB();
$base_url = '/ontimeadventure/';
$id_user = $_SESSION['id_user'];

// Query Item Favorit
$sql = "
    SELECT
        i.id_item,
        i.nama_brand,
        i.nama_seri,
        i.gambar,
        i.jenis_transaksi,
        k.nama_kategori,
        MIN(v.harga_sewa_per_hari) AS harga_min,
        MAX(v.harga_sewa_per_hari) AS harga_max,
        COALESCE(SUM(v.stok_tersedia), 0) AS total_stok,
        (SELECT ROUND(AVG(rating), 1) FROM review_item WHERE id_item = i.id_item AND status_review = 'Aktif') AS avg_rating,
        (SELECT COUNT(id_review) FROM review_item WHERE id_item = i.id_item AND status_review = 'Aktif') AS count_review
    FROM favorit_item f
    JOIN item i ON f.id_item = i.id_item
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    LEFT JOIN varian_item v ON i.id_item = v.id_item
    WHERE f.id_user = :id_user AND i.status_item = 'Aktif'
    GROUP BY i.id_item
    ORDER BY f.created_at DESC
";

$stmt_items = $db->prepare($sql);
$stmt_items->execute([':id_user' => $id_user]);
$items = $stmt_items->fetchAll();

// Semua item di halaman ini pasti favorit
$user_favorites = array_column($items, 'id_item');

// ── Helper: Format Rupiah ─────────────────────────────────
function formatRupiah(int $angka): string {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

$pageTitle = 'Favorit Saya';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 mt-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="ri-heart-3-fill text-danger me-2"></i>Daftar Keinginan</h4>
            <p class="text-muted fs-14 mb-0">Barang-barang yang Anda simpan untuk disewa nanti.</p>
        </div>
        <a href="katalog.php" class="btn btn-outline-primary btn-wave"><i class="ri-arrow-left-line me-1"></i> Lanjut Cari Barang</a>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i><?= $_SESSION['flash_success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>
    <?php if (isset($_SESSION['flash_error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="ri-error-warning-line me-2"></i><?= $_SESSION['flash_error'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_error']); ?>
    <?php endif; ?>

    <style>
        @media (max-width: 576px) {
            .btn-sm-mobile { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
            .card-title-mobile { font-size: 0.9rem !important; }
            .card-price-mobile { font-size: 0.9rem !important; }
            .card-badge-mobile { font-size: 0.65rem !important; padding: 0.2rem 0.4rem !important; }
        }
    </style>

    <!-- Item Grid -->
    <div class="row">
        <?php if (empty($items)): ?>
            <div class="col-12 text-center py-5">
                <i class="ri-heart-3-line fs-50 text-muted"></i>
                <h5 class="mt-3 text-muted">Belum ada barang di daftar favorit Anda.</h5>
                <a href="katalog.php" class="btn btn-primary mt-3">Jelajahi Katalog</a>
            </div>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php
                    $has_image = false;
                    $img_path  = '';
                    if (!empty($item['gambar'])) {
                        $file_path = __DIR__ . '/assets/img/' . $item['gambar'];
                        if (file_exists($file_path)) {
                            $has_image = true;
                            $img_path  = $base_url . 'assets/img/' . htmlspecialchars($item['gambar']);
                        }
                    }
                    $total_stok = (int) $item['total_stok'];
                ?>
                <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-6 item-card-wrapper" id="fav-card-<?= $item['id_item'] ?>">
                    <div class="card custom-card product-card">
                        <div class="card-body position-relative">
                            <!-- Favorite Button (Top Right) -->
                            <button class="btn btn-icon btn-sm btn-danger position-absolute top-0 end-0 m-3 rounded-circle shadow-sm btn-favorite-page" style="z-index: 10;" data-id="<?= $item['id_item'] ?>">
                                <i class="ri-heart-3-fill"></i>
                            </button>

                            <!-- Rating Badge (Top Left) -->
                            <?php if (!empty($item['avg_rating'])): ?>
                                <span class="badge bg-warning position-absolute top-0 start-0 m-3 rounded-pill shadow-sm text-dark px-2" style="z-index: 10;">
                                    <i class="ri-star-s-fill align-middle fs-11"></i> <span class="align-middle fw-semibold fs-11"><?= htmlspecialchars($item['avg_rating']) ?></span>
                                </span>
                            <?php endif; ?>

                            <a href="<?= $base_url ?>detail_item.php?id=<?= $item['id_item'] ?>" class="product-image">
                                <?php if ($has_image): ?>
                                    <img src="<?= $img_path ?>" class="img-fluid rounded" style="width:100%; height:200px; object-fit:cover;" alt="...">
                                <?php else: ?>
                                    <div class="bg-light rounded d-flex align-items-center justify-content-center" style="height:200px; width:100%;">
                                        <i class="ri-image-line fs-50 text-muted"></i>
                                    </div>
                                <?php endif; ?>
                            </a>
                            <div class="mt-2">
                                <span class="d-block fs-12 text-muted mb-1 card-brand-mobile"><?= htmlspecialchars($item['nama_brand']) ?></span>
                                <h6 class="fw-semibold mb-1 card-title-mobile" style="white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                                    <a href="<?= $base_url ?>detail_item.php?id=<?= $item['id_item'] ?>"><?= htmlspecialchars($item['nama_seri']) ?></a>
                                </h6>
                                <p class="mb-2 fs-13 text-muted">
                                    <span class="badge bg-light text-dark card-badge-mobile"><i class="ri-price-tag-3-line me-1"></i><?= htmlspecialchars($item['nama_kategori']) ?></span>
                                    <span class="badge <?= $item['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> card-badge-mobile ms-1"><?= htmlspecialchars($item['jenis_transaksi']) ?></span>
                                </p>
                                <div class="d-flex align-items-center justify-content-between flex-wrap gap-1">
                                    <h6 class="fw-semibold text-primary mb-0 card-price-mobile">
                                        <?php if ($item['harga_min'] !== null): ?>
                                            <?php if ($item['harga_min'] === $item['harga_max']): ?>
                                                <?= formatRupiah((int)$item['harga_min']) ?>
                                            <?php else: ?>
                                                <?= formatRupiah((int)$item['harga_min']) ?>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="fs-12 text-muted">N/A</span>
                                        <?php endif; ?>
                                        <?php if ($item['jenis_transaksi'] === 'Sewa'): ?>
                                            <span class="fs-11 text-muted fw-normal d-none d-sm-inline">/hari</span>
                                        <?php endif; ?>
                                    </h6>
                                    <div>
                                        <?php if($total_stok > 0): ?>
                                            <span class="badge bg-primary-transparent card-badge-mobile"><i class="ri-checkbox-circle-line me-1"></i> <?= $total_stok ?> Tersedia</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-transparent card-badge-mobile"><i class="ri-close-circle-line me-1"></i> Habis</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handling Favorite Buttons on Favorit Page
    document.querySelectorAll('.btn-favorite-page').forEach(btn => {
        btn.addEventListener('click', function(e) {
            e.preventDefault();
            let itemId = this.getAttribute('data-id');
            
            if(confirm('Hapus barang ini dari daftar favorit?')) {
                fetch('api_toggle_favorit.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id_item=' + itemId
                })
                .then(res => res.json())
                .then(data => {
                    if(data.status === 'removed') {
                        let card = document.getElementById('fav-card-' + itemId);
                        card.style.opacity = '0';
                        setTimeout(() => {
                            card.remove();
                            // If no more cards, reload to show empty state
                            if (document.querySelectorAll('.item-card-wrapper').length === 0) {
                                location.reload();
                            }
                        }, 300);
                    } else {
                        alert(data.message || 'Terjadi kesalahan');
                    }
                })
                .catch(err => console.error(err));
            }
        });
    });
});
</script>
