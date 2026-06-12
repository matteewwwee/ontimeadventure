<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

$db = getDB();
$flash_msg = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'bulk_update') {
    $hargas = $_POST['harga'] ?? [];
    $stoks = $_POST['stok'] ?? [];
    $keterangans = $_POST['keterangan'] ?? [];
    $catatans = $_POST['catatan'] ?? [];
    $deskripsis = $_POST['deskripsi'] ?? [];

    try {
        $db->beginTransaction();
        $stmt_varian = $db->prepare("UPDATE varian_item SET harga_sewa_per_hari = ?, stok_tersedia = ?, keterangan_varian = ?, catatan_kondisi = ? WHERE id_varian = ?");
        $stmt_item = $db->prepare("UPDATE item SET deskripsi_umum = ? WHERE id_item = ?");
        
        $count = 0;
        foreach ($hargas as $id_varian => $harga) {
            $stok = $stoks[$id_varian] ?? 0;
            $keterangan = trim($keterangans[$id_varian] ?? '');
            $catatan = trim($catatans[$id_varian] ?? '');
            $stmt_varian->execute([(int)$harga, (int)$stok, $keterangan, $catatan, (int)$id_varian]);
            $count++;
        }
        foreach ($deskripsis as $id_item => $desk) {
            $stmt_item->execute([trim($desk), (int)$id_item]);
        }
        $db->commit();
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show"><i class="ri-check-line me-1 align-middle fs-16"></i> Berhasil memperbarui ' . $count . ' data varian sekaligus!<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    } catch (Exception $e) {
        $db->rollBack();
        $flash_msg = '<div class="alert alert-danger alert-dismissible fade show"><i class="ri-error-warning-line me-1 align-middle fs-16"></i> Gagal menyimpan: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
    }
}

// Fetch all variants with their parent items
$stmt = $db->query("
    SELECT 
        v.id_varian, v.id_item, v.keterangan_varian, v.harga_sewa_per_hari, v.stok_tersedia, v.catatan_kondisi, v.gambar as v_gambar,
        i.nama_brand, i.nama_seri, i.deskripsi_umum, i.gambar as i_gambar,
        k.nama_kategori
    FROM varian_item v
    JOIN item i ON v.id_item = i.id_item
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    ORDER BY k.nama_kategori ASC, i.nama_brand ASC, i.nama_seri ASC
");
$variants = $stmt->fetchAll();

$item_rowspans = [];
foreach ($variants as $v) {
    $item_rowspans[$v['id_item']] = ($item_rowspans[$v['id_item']] ?? 0) + 1;
}

$pageTitle = "Edit Cepat Harga & Stok";
require_once __DIR__ . '/../includes/header.php';
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
?>

<div class="container-fluid py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h4 class="fw-bold mb-1 fs-5 fs-md-4"><i class="ri-table-2 text-success me-2"></i>Edit Cepat Harga & Stok</h4>
            <p class="text-muted mb-0 fs-13">Ubah harga sewa dan jumlah stok seluruh barang layaknya Excel.</p>
        </div>
        <div>
            <a href="kelola_item.php" class="btn btn-outline-secondary w-100 w-md-auto text-nowrap">
                <i class="ri-arrow-left-line me-1"></i> Kembali ke Item
            </a>
        </div>
    </div>

    <?= $flash_msg ?>

    <style>
        @media (min-width: 992px) {
            .sidemenu-toggle { display: none !important; }
        }
    </style>

    <form action="" method="POST" id="bulkForm" class="mb-4">
        <input type="hidden" name="action" value="bulk_update">
        
        <!-- DESKTOP VIEW (Model Excel dengan Rowspan) -->
        <div class="table-responsive d-none d-lg-block mb-3 bg-white border rounded shadow-sm" style="max-height: 70vh; overflow-y: auto;">
            <table class="table table-hover table-striped table-bordered align-middle mb-0" style="min-width: 800px;">
                <thead class="table-light position-sticky top-0 shadow-sm" style="z-index: 10;">
                    <tr>
                        <th class="text-center" width="5%">No</th>
                        <th width="10%">Kategori</th>
                        <th width="15%">Nama Barang</th>
                        <th width="20%">Deskripsi Umum</th>
                        <th width="15%" class="text-center bg-primary-transparent text-primary">Varian / Ukuran</th>
                        <th width="10%" class="text-center bg-success-transparent text-success">Harga / Hari</th>
                        <th width="10%" class="text-center bg-info-transparent text-info">Stok Tersedia</th>
                        <th width="15%" class="text-center bg-warning-transparent text-warning">Catatan Kondisi</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($variants) > 0): 
                        $last_item_id = null;
                    ?>
                        <?php foreach ($variants as $index => $v): 
                            $is_first = ($v['id_item'] !== $last_item_id);
                            $last_item_id = $v['id_item'];
                        ?>
                            <tr>
                                <td class="text-center text-muted"><?= $index + 1 ?></td>
                                <?php if ($is_first): 
                                    $gambar = !empty($v['v_gambar']) ? $v['v_gambar'] : $v['i_gambar'];
                                    $gambarPath = empty($gambar) ? $base_url . 'assets/images/placeholder.jpg' : $base_url . 'assets/img/' . $gambar;
                                ?>
                                    <td rowspan="<?= $item_rowspans[$v['id_item']] ?>"><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['nama_kategori']) ?></span></td>
                                    <td rowspan="<?= $item_rowspans[$v['id_item']] ?>" class="align-top">
                                        <div class="d-flex align-items-center gap-2">
                                            <img src="<?= $gambarPath ?>" class="rounded border" style="width: 45px; height: 45px; object-fit: cover; cursor: pointer;" alt="" onclick="previewImage(this.src)">
                                            <span class="fw-bold"><?= htmlspecialchars($v['nama_brand'] . ' ' . $v['nama_seri']) ?></span>
                                        </div>
                                    </td>
                                    <td rowspan="<?= $item_rowspans[$v['id_item']] ?>" class="p-2 align-top">
                                        <textarea name="deskripsi_desktop_dummy[<?= $v['id_item'] ?>]" class="form-control form-control-sm focus-ring focus-ring-secondary border-secondary-subtle sync-textarea" data-itemid="<?= $v['id_item'] ?>" placeholder="Tulis deskripsi umum..." style="resize: none;"><?= htmlspecialchars($v['deskripsi_umum'] ?? '') ?></textarea>
                                    </td>
                                <?php endif; ?>
                                <td class="p-2">
                                    <input type="text" name="keterangan_desktop_dummy[<?= $v['id_varian'] ?>]" class="form-control form-control-sm focus-ring focus-ring-primary sync-input" data-name="keterangan[<?= $v['id_varian'] ?>]" value="<?= htmlspecialchars($v['keterangan_varian'] ?? '') ?>" placeholder="-">
                                </td>
                                <td class="p-2">
                                    <div class="input-group input-group-sm">
                                        <span class="input-group-text bg-light border-end-0 text-muted">Rp</span>
                                        <input type="number" name="harga_desktop_dummy[<?= $v['id_varian'] ?>]" class="form-control fw-bold text-end border-start-0 focus-ring focus-ring-success sync-input" data-name="harga[<?= $v['id_varian'] ?>]" value="<?= $v['harga_sewa_per_hari'] ?>" min="0" required>
                                    </div>
                                </td>
                                <td class="p-2 text-center">
                                    <input type="number" name="stok_desktop_dummy[<?= $v['id_varian'] ?>]" class="form-control form-control-sm text-center fw-bold focus-ring focus-ring-info sync-input" data-name="stok[<?= $v['id_varian'] ?>]" value="<?= $v['stok_tersedia'] ?>" min="0" required>
                                </td>
                                <td class="p-2">
                                    <input type="text" name="catatan_desktop_dummy[<?= $v['id_varian'] ?>]" class="form-control form-control-sm focus-ring focus-ring-warning sync-input" data-name="catatan[<?= $v['id_varian'] ?>]" value="<?= htmlspecialchars($v['catatan_kondisi'] ?? '') ?>" placeholder="-">
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center text-muted py-5">
                                <i class="ri-inbox-2-line fs-24 d-block mb-2"></i> Belum ada varian item yang ditambahkan.
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <!-- MOBILE VIEW (Card Layout) -->
        <div class="row d-lg-none g-4 mb-3" style="max-height: 70vh; overflow-y: auto; overflow-x: hidden; padding: 0.5rem;">
                    <?php if (count($variants) > 0): 
                        // Group variants by item
                        $grouped_variants = [];
                        foreach ($variants as $v) {
                            if (!isset($grouped_variants[$v['id_item']])) {
                                $grouped_variants[$v['id_item']] = [
                                    'kategori' => $v['nama_kategori'],
                                    'brand' => $v['nama_brand'],
                                    'seri' => $v['nama_seri'],
                                    'deskripsi' => $v['deskripsi_umum'],
                                    'gambar' => !empty($v['v_gambar']) ? $v['v_gambar'] : $v['i_gambar'],
                                    'variants' => []
                                ];
                            }
                            $grouped_variants[$v['id_item']]['variants'][] = $v;
                        }
                    ?>
                        <?php foreach ($grouped_variants as $id_item => $item): 
                            $gambarPath = empty($item['gambar']) ? $base_url . 'assets/images/placeholder.jpg' : $base_url . 'assets/img/' . $item['gambar'];
                        ?>
                            <div class="col-12 col-xl-6">
                                <div class="card shadow-sm border-0 h-100">
                                    <div class="card-header bg-light d-flex flex-column flex-sm-row gap-3 align-items-sm-center border-bottom-0">
                                        <div class="d-flex align-items-center gap-3" style="min-width: 200px;">
                                            <img src="<?= $gambarPath ?>" class="rounded shadow-sm" style="width: 55px; height: 55px; object-fit: cover; cursor: pointer;" alt="" onclick="previewImage(this.src)">
                                            <div>
                                                <span class="badge bg-primary-transparent text-primary mb-1"><?= htmlspecialchars($item['kategori']) ?></span>
                                                <h6 class="fw-bold mb-0 text-dark"><?= htmlspecialchars($item['brand'] . ' ' . $item['seri']) ?></h6>
                                            </div>
                                        </div>
                                        <div class="flex-grow-1 w-100">
                                            <textarea name="deskripsi[<?= $id_item ?>]" class="form-control form-control-sm focus-ring focus-ring-secondary border-secondary-subtle" placeholder="Tulis deskripsi umum..." style="resize: none;"><?= htmlspecialchars($item['deskripsi'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="d-none d-md-flex row g-0 px-3 py-2 bg-light border-bottom border-top text-muted fs-12 fw-bold">
                                            <div class="col-md-3">Varian/Ukuran</div>
                                            <div class="col-md-3">Harga / Hari</div>
                                            <div class="col-md-2 text-center">Stok</div>
                                            <div class="col-md-4">Kondisi</div>
                                        </div>
                                        <?php foreach ($item['variants'] as $v): ?>
                                        <div class="row g-2 px-3 py-2 border-bottom align-items-end">
                                            <div class="col-12 col-md-3">
                                                <label class="form-label fs-12 mb-1 text-muted d-md-none">Varian/Ukuran</label>
                                                <input type="text" name="keterangan[<?= $v['id_varian'] ?>]" class="form-control form-control-sm focus-ring focus-ring-primary" value="<?= htmlspecialchars($v['keterangan_varian'] ?? '') ?>" placeholder="-">
                                            </div>
                                            <div class="col-6 col-md-3">
                                                <label class="form-label fs-12 mb-1 text-muted d-md-none">Harga / Hari</label>
                                                <div class="input-group input-group-sm">
                                                    <span class="input-group-text bg-light border-end-0 text-muted">Rp</span>
                                                    <input type="number" name="harga[<?= $v['id_varian'] ?>]" class="form-control fw-bold text-end border-start-0 focus-ring focus-ring-success" value="<?= $v['harga_sewa_per_hari'] ?>" min="0" required>
                                                </div>
                                            </div>
                                            <div class="col-6 col-md-2">
                                                <label class="form-label fs-12 mb-1 text-muted d-md-none text-center d-block">Stok</label>
                                                <input type="number" name="stok[<?= $v['id_varian'] ?>]" class="form-control form-control-sm text-center fw-bold focus-ring focus-ring-info" value="<?= $v['stok_tersedia'] ?>" min="0" required>
                                            </div>
                                            <div class="col-12 col-md-4">
                                                <label class="form-label fs-12 mb-1 text-muted d-md-none">Kondisi</label>
                                                <input type="text" name="catatan[<?= $v['id_varian'] ?>]" class="form-control form-control-sm focus-ring focus-ring-warning" value="<?= htmlspecialchars($v['catatan_kondisi'] ?? '') ?>" placeholder="-">
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="col-12 text-center text-muted py-5">
                            <i class="ri-inbox-2-line fs-24 d-block mb-2"></i> Belum ada varian item yang ditambahkan.
                        </div>
                    <?php endif; ?>
                </div>

        <div class="p-3 bg-white shadow-sm border rounded d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3 position-sticky bottom-0" style="z-index: 10;">
            <span class="text-muted fs-13"><i class="ri-information-line"></i> Total <?= count($variants) ?> varian siap diperbarui.</span>
            <button type="submit" class="btn btn-success px-4 fw-bold w-100 w-sm-auto">
                <i class="ri-save-3-line me-1"></i> Simpan Perubahan
            </button>
        </div>
    </form>
</div>

<script>
// Sinkronisasi antara input Desktop dan Mobile agar selalu bernilai sama saat disubmit
document.addEventListener('DOMContentLoaded', function() {
    const syncTextareas = document.querySelectorAll('.sync-textarea');
    syncTextareas.forEach(ta => {
        ta.addEventListener('input', function() {
            const itemId = this.getAttribute('data-itemid');
            const target = document.querySelector(`textarea[name="deskripsi[${itemId}]"]`);
            if(target) target.value = this.value;
        });
    });

    const syncInputs = document.querySelectorAll('.sync-input');
    syncInputs.forEach(inp => {
        inp.addEventListener('input', function() {
            const name = this.getAttribute('data-name');
            const target = document.querySelector(`input[name="${name}"]`);
            if(target) target.value = this.value;
        });
    });
});
</script>

<style>
    /* Make input fields look cleaner in table */
    .table td.p-2 input {
        border-color: #e9ecef;
        background-color: #f8f9fa;
        transition: all 0.2s;
    }
    .table td.p-2 input:focus {
        background-color: #fff;
        border-color: var(--bs-success);
        box-shadow: 0 0 0 0.25rem rgba(25, 135, 84, 0.25);
    }
    .table td.p-2 .focus-ring-info:focus {
        border-color: var(--bs-info);
        box-shadow: 0 0 0 0.25rem rgba(13, 202, 240, 0.25);
    }
</style>

<!-- Modal Image Preview -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0">
                <button type="button" class="btn-close btn-close-white ms-auto" data-bs-dismiss="modal" aria-label="Close" style="filter: invert(1); opacity: 1;"></button>
            </div>
            <div class="modal-body text-center p-0 mt-2">
                <img id="previewImageSrc" src="" class="img-fluid rounded shadow-lg" alt="Preview">
            </div>
        </div>
    </div>
</div>

<script>
function previewImage(src) {
    document.getElementById('previewImageSrc').src = src;
    var myModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    myModal.show();
}
</script>

<script>
document.getElementById('bulkForm').addEventListener('submit', function(e) {
    e.preventDefault();
    Swal.fire({
        title: 'Simpan Perubahan?',
        text: 'Apakah Anda yakin ingin menyimpan seluruh perubahan harga dan stok ini?',
        icon: 'question',
        showCancelButton: true,
        confirmButtonColor: '#28a745',
        cancelButtonColor: '#6c757d',
        confirmButtonText: 'Ya, Simpan!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            this.submit();
        }
    });
});

document.addEventListener('DOMContentLoaded', function() {
    const textareas = document.querySelectorAll('textarea');
    function adjustHeight(el) {
        el.style.height = 'auto';
        el.style.height = (el.scrollHeight) + 'px';
    }
    textareas.forEach(ta => {
        ta.style.overflowY = 'hidden';
        adjustHeight(ta);
        ta.addEventListener('input', function() {
            adjustHeight(this);
        });
    });
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
