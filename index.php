<?php
/**
 * Landing Page — On Time Adventure (Vyzor UI)
 */
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? '/ontimeadventure/' : '/';

require_once __DIR__ . '/config/database.php';
$db = getDB();

$stmt_reviews = $db->query("
    SELECT r.rating, r.komentar, r.tanggal as created_at, u.nama, i.id_item, i.nama_brand, i.nama_seri, i.gambar 
    FROM review_item r
    JOIN users u ON r.id_user = u.id_user
    JOIN detail_po dp ON r.id_detail = dp.id_detail
    JOIN varian_item v ON dp.id_varian = v.id_varian
    JOIN item i ON v.id_item = i.id_item
    WHERE r.status_review = 'Aktif'
    ORDER BY r.tanggal DESC
    LIMIT 10
");
$latest_reviews = $stmt_reviews->fetchAll();

// Remove redirect so logged in users can see the landing page

$pageTitle = 'Beranda';
require __DIR__ . '/includes/header.php';
?>

<!-- Start:: Landing Banner -->
<div class="landing-banner" id="home">
    <div class="banner-image-container">
        <!-- Optional: background shape or illustration -->
        <img src="<?= $base_url ?>assets/vyzor/images/media/backgrounds/5.png" alt="" style="opacity: 0.1">
    </div>
    <div class="container">
        <div class="row align-items-center justify-content-center">
            <div class="col-xl-6 px-4 px-xl-3">
                <div class="mb-4">
                    <div class="d-inline-flex align-items-center gap-2 text-default badge bg-white border fs-13 rounded-pill">
                        <span class="avatar avatar-xs avatar-rounded bg-primary"><i class="ri-flashlight-fill fs-14"></i></span>Platform Rental Outdoor #1
                    </div>
                </div>
                <h1 class="fw-semibold mt-3 landing-banner-heading">Siapkan Petualangan <br> Anda bersama <span class="text-primary">On Time</span> Adventure</h1>
                <span class="d-block fs-18">Rental Alat Camping & Climbing Terpercaya. Cek ketersediaan, booking online, dan pantau status pesanan Anda.</span>
                <div class="btn-list banner-buttons mt-4">
                    <a href="<?= $base_url ?>register.php" class="btn btn-primary btn-lg rounded-pill btn-w-lg">Mulai Sekarang</a>
                    <a class="btn btn-lg btn-light border rounded-pill btn-w-lg" href="<?= $base_url ?>katalog.php">Lihat Katalog</a>
                </div>
            </div>
            <div class="col-xl-6 mt-5 mt-xl-0 px-4 px-xl-3" style="margin-bottom: 2rem;">
                <div class="banner-main-img text-center text-xl-end d-block">
                    <?php 
                    $banners = isset($app_settings['banner_slider']) ? (json_decode($app_settings['banner_slider'], true) ?: []) : [];
                    if (empty($banners)): 
                    ?>
                        <img src="<?= $base_url ?>assets/vyzor/images/media/backgrounds/7.png" alt="Banner Placeholder" class="img-fluid rounded">
                    <?php else: ?>
                        <div id="heroBannerSlider" class="carousel slide carousel-fade shadow-lg rounded-3 overflow-hidden" data-bs-ride="carousel">
                            <div class="carousel-inner">
                                <?php foreach ($banners as $index => $b): ?>
                                    <div class="carousel-item <?= $index === 0 ? 'active' : '' ?>" data-bs-interval="3000">
                                        <img src="<?= $base_url ?>assets/img/banner/<?= htmlspecialchars($b) ?>" class="d-block w-100" alt="Banner" style="height: 450px; object-fit: cover; object-position: center;">
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <?php if (count($banners) > 1): ?>
                            <button class="carousel-control-prev" type="button" data-bs-target="#heroBannerSlider" data-bs-slide="prev">
                                <span class="carousel-control-prev-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Previous</span>
                            </button>
                            <button class="carousel-control-next" type="button" data-bs-target="#heroBannerSlider" data-bs-slide="next">
                                <span class="carousel-control-next-icon" aria-hidden="true"></span>
                                <span class="visually-hidden">Next</span>
                            </button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>
<!-- End:: Landing Banner -->

<!-- Start:: Section Features -->
<section class="section" id="feature">
    <div class="container px-4 px-lg-3">
        <div class="heading-section text-center px-3">
            <div class="heading-subtitle">Fitur Utama</div>
            <h2 class="heading-title fw-bold mb-3">Kenapa Memilih Kami?</h2>
            <div class="heading-description fs-16 text-muted px-lg-5 mx-lg-5">
                Sewa Alat Outdoor Jadi Lebih Mudah, Cepat, dan Terjamin.
            </div>
        </div>
        <div class="row mt-5">
            <div class="col-xl-4 col-md-6">
                <div class="card custom-card">
                    <div class="card-body text-center">
                        <div class="lh-1 mb-3 d-flex justify-content-center">
                            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent text-primary">   
                                <i class="ri-list-check-2 fs-30"></i>
                            </span>
                        </div>
                        <h5 class="fw-semibold">Katalog Lengkap</h5>
                        <span class="fs-15 text-muted">
                            Koleksi alat dari berbagai brand dengan detail stok & kondisi yang transparan.
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card custom-card">
                    <div class="card-body text-center">
                        <div class="lh-1 mb-3 d-flex justify-content-center">
                            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent text-primary">   
                                <i class="ri-smartphone-line fs-30"></i>
                            </span>
                        </div>
                        <h5 class="fw-semibold">Booking Online</h5>
                        <span class="fs-15 text-muted">
                            Sewa alat dari mana saja tanpa harus antri ke toko.
                        </span>
                    </div>
                </div>
            </div>
            <div class="col-xl-4 col-md-6">
                <div class="card custom-card">
                    <div class="card-body text-center">
                        <div class="lh-1 mb-3 d-flex justify-content-center">
                            <span class="avatar avatar-xl avatar-rounded bg-primary-transparent text-primary">   
                                <i class="ri-truck-line fs-30"></i>
                            </span>
                        </div>
                        <h5 class="fw-semibold">Status Real-Time</h5>
                        <span class="fs-15 text-muted">
                            Pantau status barang dari diproses hingga siap diambil secara instan.
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>
<!-- End:: Section Features -->

<!-- Start:: Section Reviews -->
<?php if (!empty($latest_reviews)): ?>
<section class="section section-bg">
    <div class="container px-4 px-lg-3">
        <div class="heading-section text-center px-3">
            <div class="heading-subtitle">Ulasan Pelanggan</div>
            <h2 class="heading-title fw-bold mb-3">Apa Kata Mereka?</h2>
            <div class="heading-description fs-16 text-muted px-lg-5 mx-lg-5">
                Pengalaman nyata dari pendaki yang telah menyewa di On Time Adventure.
            </div>
        </div>
        <div class="row mt-5">
            <?php foreach ($latest_reviews as $r): ?>
                <div class="col-xl-4 col-md-6 mb-4">
                    <a href="detail_item.php?id=<?= $r['id_item'] ?>" class="text-decoration-none">
                        <div class="card custom-card card-bg-light border-0 shadow-sm h-100 review-card-hover" style="transition: transform 0.3s ease, box-shadow 0.3s ease;">
                            <div class="card-body p-4">
                                <div class="d-flex align-items-center mb-3">
                                    <div class="me-3">
                                        <img src="https://ui-avatars.com/api/?name=<?= urlencode($r['nama']) ?>&background=random" alt="img" width="45" height="45" class="rounded-circle">
                                    </div>
                                    <div>
                                        <h6 class="fw-semibold mb-0 text-dark"><?= htmlspecialchars($r['nama']) ?></h6>
                                        <span class="text-muted fs-12"><?= date('d M Y', strtotime($r['created_at'])) ?></span>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="ri-star-s-fill <?= $i <= $r['rating'] ? 'text-warning' : 'text-muted op-2' ?> fs-18"></i>
                                    <?php endfor; ?>
                                </div>
                                <?php if (!empty(trim($r['komentar']))): ?>
                                    <p class="mb-3 text-muted fs-14">"<?= nl2br(htmlspecialchars(trim($r['komentar']))) ?>"</p>
                                <?php endif; ?>
                                <div class="d-flex align-items-center bg-white p-2 rounded border mt-auto">
                                    <?php 
                                    $has_img = false;
                                    if (!empty($r['gambar'])) {
                                        $path = __DIR__ . '/assets/img/' . $r['gambar'];
                                        if (file_exists($path)) {
                                            $has_img = true;
                                        }
                                    }
                                    ?>
                                    <?php if($has_img): ?>
                                        <img src="<?= $base_url ?>assets/img/<?= htmlspecialchars($r['gambar']) ?>" alt="" width="40" height="40" class="rounded me-2 object-fit-cover">
                                    <?php else: ?>
                                        <div class="bg-light rounded me-2 d-flex align-items-center justify-content-center text-muted" style="width: 40px; height: 40px;"><i class="ri-image-line"></i></div>
                                    <?php endif; ?>
                                    <div>
                                        <span class="d-block fw-semibold fs-12 text-dark"><?= htmlspecialchars($r['nama_brand']) ?></span>
                                        <span class="d-block fs-11 text-muted"><?= htmlspecialchars($r['nama_seri']) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
<style>
.review-card-hover:hover {
    transform: translateY(-5px);
    box-shadow: 0 10px 20px rgba(0,0,0,0.1) !important;
}
</style>
<?php endif; ?>
<!-- End:: Section Reviews -->

<?php require __DIR__ . '/includes/footer.php'; ?>
