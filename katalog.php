<?php
/**
 * katalog.php - Halaman Katalog Item
 */
session_start();
require_once __DIR__ . '/config/database.php';

$db = getDB();
$base_url = '/ontimeadventure/';

// ── Filter Kategori (optional GET parameter) ───────────────
$filter_kategori = isset($_GET['kategori']) ? (int) $_GET['kategori'] : null;

// ── Ambil Semua Kategori untuk Filter Bar ───────────────────
$stmt_kat = $db->query("SELECT id_kategori, nama_kategori FROM kategori_item ORDER BY nama_kategori ASC");
$kategori_list = $stmt_kat->fetchAll();

// ── Query Item dengan JOIN + Aggregasi Varian ───────────────
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
    FROM item i
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    LEFT JOIN varian_item v ON i.id_item = v.id_item
";

$params = [];
$sql .= " WHERE i.status_item = 'Aktif'";
if ($filter_kategori) {
    $sql .= " AND i.id_kategori = :id_kategori";
    $params[':id_kategori'] = $filter_kategori;
}

$sql .= " GROUP BY i.id_item ORDER BY i.nama_brand ASC, i.nama_seri ASC";

$stmt_items = $db->prepare($sql);
$stmt_items->execute($params);
$items = $stmt_items->fetchAll();

// Ambil daftar favorit pengguna jika sudah login
$user_favorites = [];
if (isset($_SESSION['id_user'])) {
    $stmt_fav = $db->prepare("SELECT id_item FROM favorit_item WHERE id_user = ?");
    $stmt_fav->execute([$_SESSION['id_user']]);
    $user_favorites = $stmt_fav->fetchAll(PDO::FETCH_COLUMN);
}

// ── Buat JSON untuk Rekomendasi Pencarian ───────────────────
$items_json = array_map(function($i) use ($base_url) {
    return [
        'id' => $i['id_item'],
        'name' => $i['nama_brand'] . ' ' . $i['nama_seri'],
        'category' => $i['nama_kategori'],
        'image' => !empty($i['gambar']) ? $base_url . 'assets/img/' . $i['gambar'] : '',
        'search_str' => strtolower($i['nama_brand'] . ' ' . $i['nama_seri'] . ' ' . $i['nama_kategori'])
    ];
}, $items);
$items_json_str = json_encode($items_json);

// ── Helper: Format Rupiah ───────────────────────────────────
function formatRupiah(int $angka): string {
    return 'Rp ' . number_format($angka, 0, ',', '.');
}

// ── Header ──────────────────────────────────────────────────
$pageTitle = 'Katalog';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container mt-1 mt-md-4 pt-0 pt-md-2 katalog-container">
    <div class="d-flex align-items-center justify-content-between mb-3 mb-md-4 flex-wrap gap-2">
        <h4 class="mb-0 fw-semibold fs-18 fs-md-24">Katalog Perlengkapan <span class="badge bg-primary ms-2" id="katalog-count"><?= count($items) ?></span></h4>
        <div class="search-box-container position-relative w-100 flex-md-grow-0 mt-2 mt-md-0">
            <input type="text" id="searchItem" class="form-control rounded-pill shadow-sm" style="padding-left: 45px !important;" placeholder="Cari nama, merk, atau kategori..." autocomplete="off">
            <i class="ri-search-line position-absolute top-50 translate-middle-y text-muted fs-16" style="left: 18px;"></i>
            
            <!-- Kotak Rekomendasi (Autocomplete) -->
            <ul id="searchSuggestions" class="list-group position-absolute w-100 shadow-lg d-none border-0" style="top: 110%; left: 0; z-index: 1000; max-height: 300px; overflow-y: auto; border-radius: 12px;"></ul>
        </div>
    </div>

    <!-- Filter Bar -->
    <div class="card custom-card mb-4 border-0 shadow-sm">
        <div class="card-body p-2 p-md-3">
            <div class="d-flex flex-nowrap overflow-auto gap-2 pb-1 filter-scroll" style="scrollbar-width: none;">
                <a href="<?= $base_url ?>katalog.php" class="kategori-link btn <?= $filter_kategori === null ? 'btn-primary' : 'btn-outline-primary' ?> btn-wave rounded-pill flex-shrink-0 btn-sm-mobile">
                    <i class="ri-list-check me-1"></i> Semua
                </a>
                <?php foreach ($kategori_list as $kat): ?>
                    <a href="<?= $base_url ?>katalog.php?kategori=<?= $kat['id_kategori'] ?>"
                       class="kategori-link btn <?= $filter_kategori === (int)$kat['id_kategori'] ? 'btn-primary' : 'btn-outline-primary' ?> btn-wave rounded-pill flex-shrink-0 btn-sm-mobile">
                        <?= htmlspecialchars($kat['nama_kategori']) ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <style>
        .filter-scroll::-webkit-scrollbar { display: none; }
        @media (max-width: 576px) {
            .katalog-container { margin-top: 15px !important; }
            .btn-sm-mobile { padding: 0.25rem 0.75rem; font-size: 0.8rem; }
            .card-title-mobile { font-size: 0.9rem !important; }
            .card-price-mobile { font-size: 0.9rem !important; }
            .card-badge-mobile { font-size: 0.65rem !important; padding: 0.2rem 0.4rem !important; }
        }
        @media (min-width: 768px) {
            .search-box-container { max-width: 300px; }
        }
        #katalog-grid { transition: opacity 0.3s ease; }
    </style>

    <!-- Item Grid -->
    <div class="row" id="katalog-grid">
        <?php if (empty($items)): ?>
            <div class="col-12 text-center py-5">
                <i class="ri-search-line fs-50 text-muted"></i>
                <h5 class="mt-3 text-muted">Belum ada item tersedia untuk kategori ini.</h5>
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
                <div class="col-xxl-3 col-xl-4 col-lg-4 col-md-6 col-sm-6 col-6 item-card-wrapper" data-name="<?= strtolower(htmlspecialchars($item['nama_brand'] . ' ' . $item['nama_seri'] . ' ' . $item['nama_kategori'])) ?>">
                    <div class="card custom-card product-card">
                        <div class="card-body position-relative">
                            <!-- Favorite Button (Top Right) -->
                            <?php if (isset($_SESSION['id_user'])): 
                                $is_fav = in_array($item['id_item'], $user_favorites);
                            ?>
                                <button class="btn btn-icon btn-sm btn-<?= $is_fav ? 'danger' : 'light' ?> position-absolute top-0 end-0 m-3 rounded-circle shadow-sm btn-favorite" style="z-index: 10;" data-id="<?= $item['id_item'] ?>">
                                    <i class="<?= $is_fav ? 'ri-heart-3-fill' : 'ri-heart-3-line' ?>"></i>
                                </button>
                            <?php else: ?>
                                <a href="<?= $base_url ?>login.php" class="btn btn-icon btn-sm btn-light position-absolute top-0 end-0 m-3 rounded-circle shadow-sm" style="z-index: 10;">
                                    <i class="ri-heart-3-line text-muted"></i>
                                </a>
                            <?php endif; ?>

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

<script>
document.addEventListener('DOMContentLoaded', function() {
    const filterScroll = document.querySelector('.filter-scroll');
    if (filterScroll) {
        filterScroll.addEventListener('click', function(e) {
            const link = e.target.closest('a.kategori-link');
            if (link) {
                e.preventDefault();
                const url = link.href;
                
                // Animasi aktif pada tombol (biar terasa instan)
                document.querySelectorAll('.kategori-link').forEach(btn => {
                    btn.classList.remove('btn-primary');
                    btn.classList.add('btn-outline-primary');
                });
                link.classList.remove('btn-outline-primary');
                link.classList.add('btn-primary');
                
                const grid = document.getElementById('katalog-grid');
                grid.style.opacity = '0.3'; // Efek loading transisi
                
                fetch(url)
                    .then(response => response.text())
                    .then(html => {
                        const parser = new DOMParser();
                        const doc = parser.parseFromString(html, 'text/html');
                        
                        // Timpa konten Grid dan Jumlah (Count)
                        const newGrid = doc.getElementById('katalog-grid');
                        const newCount = doc.getElementById('katalog-count');
                        
                        if (newGrid) {
                            grid.innerHTML = newGrid.innerHTML;
                        }
                        if (newCount) {
                            document.getElementById('katalog-count').innerText = newCount.innerText;
                        }
                        
                        grid.style.opacity = '1';
                        // Update URL tanpa reload
                        window.history.pushState({path: url}, '', url);
                    })
                    .catch(() => {
                        grid.style.opacity = '1';
                    });
            }
        });
    }
});
</script>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById('searchItem');
    const items = document.querySelectorAll('.item-card-wrapper');
    const countBadge = document.getElementById('katalog-count');
    const suggestionsBox = document.getElementById('searchSuggestions');
    
    // Data produk dari PHP untuk rekomendasi
    const itemsData = <?= $items_json_str ?>;
    const baseUrl = '<?= $base_url ?>';

    if (searchInput) {
        searchInput.addEventListener('input', function(e) {
            const term = e.target.value.toLowerCase().trim();
            let visibleCount = 0;

            // 1. Filter Grid Utama
            items.forEach(item => {
                const searchStr = item.getAttribute('data-name');
                if (searchStr.includes(term)) {
                    item.style.display = '';
                    visibleCount++;
                } else {
                    item.style.display = 'none';
                }
            });
            if (countBadge) countBadge.textContent = visibleCount;

            // 2. Tampilkan Rekomendasi (Autocomplete)
            if (term.length > 0) {
                // Cari 5 produk paling relevan
                const matches = itemsData.filter(i => i.search_str.includes(term)).slice(0, 5);
                
                if (matches.length > 0) {
                    suggestionsBox.innerHTML = matches.map(m => {
                        const imgHtml = m.image 
                            ? `<img src="${m.image}" style="width:40px; height:40px; object-fit:cover; border-radius:6px; margin-right:12px;">` 
                            : `<div style="width:40px; height:40px; border-radius:6px; margin-right:12px;" class="bg-light d-flex align-items-center justify-content-center"><i class="ri-image-line text-muted"></i></div>`;
                        
                        return `
                        <a href="${baseUrl}detail_item.php?id=${m.id}" class="list-group-item list-group-item-action d-flex align-items-center p-2 border-bottom">
                            ${imgHtml}
                            <div>
                                <div class="fw-semibold text-dark fs-13 mb-0 lh-1">${m.name}</div>
                                <div class="fs-11 text-muted mt-1"><i class="ri-price-tag-3-line"></i> ${m.category}</div>
                            </div>
                        </a>`;
                    }).join('');
                    suggestionsBox.classList.remove('d-none');
                } else {
                    suggestionsBox.innerHTML = `<div class="list-group-item text-center text-muted fs-13 py-3">Pencarian tidak ditemukan.</div>`;
                    suggestionsBox.classList.remove('d-none');
                }
            } else {
                suggestionsBox.classList.add('d-none');
            }
        });

        // Sembunyikan rekomendasi jika klik di luar
        document.addEventListener('click', function(e) {
            if (!searchInput.contains(e.target) && !suggestionsBox.contains(e.target)) {
                suggestionsBox.classList.add('d-none');
            }
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Handling Favorite Buttons
    document.querySelectorAll('.btn-favorite').forEach(btn => {
        btn.addEventListener('click', function(e) {
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
    });
});
</script>
