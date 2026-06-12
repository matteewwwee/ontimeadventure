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
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="ri-table-2 text-success me-2"></i>Edit Cepat Harga & Stok</h4>
            <p class="text-muted mb-0 fs-14">Ubah harga sewa dan jumlah stok seluruh barang dalam satu halaman, layaknya Microsoft Excel.</p>
        </div>
        <div>
            <a href="kelola_item.php" class="btn btn-outline-secondary">
                <i class="ri-arrow-left-line me-1"></i> Kembali ke Kelola Item
            </a>
        </div>
    </div>

    <?= $flash_msg ?>

    <div class="card border-0 shadow-sm mb-4">
        <div class="card-body p-0">
            <form action="" method="POST" id="bulkForm">
                <input type="hidden" name="action" value="bulk_update">
                
                <div class="row g-4 mb-3" style="max-height: 65vh; overflow-y: auto; overflow-x: hidden; padding-right: 5px;">
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
                                            <textarea name="deskripsi[<?= $id_item ?>]" class="form-control form-control-sm focus-ring focus-ring-secondary border-secondary-subtle" rows="2" placeholder="Tulis deskripsi umum..."><?= htmlspecialchars($item['deskripsi'] ?? '') ?></textarea>
                                        </div>
                                    </div>
                                    <div class="card-body p-0">
                                        <div class="table-responsive">
                                            <table class="table table-sm table-hover align-middle mb-0" style="min-width: 500px;">
                                                <thead class="table-light text-muted" style="font-size: 0.8rem;">
                                                    <tr>
                                                        <th width="30%" class="ps-3 border-bottom-0">Varian/Ukuran</th>
                                                        <th width="30%" class="border-bottom-0">Harga / Hari</th>
                                                        <th width="15%" class="text-center border-bottom-0">Stok</th>
                                                        <th width="25%" class="pe-3 border-bottom-0">Kondisi</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php foreach ($item['variants'] as $v): ?>
                                                    <tr>
                                                        <td class="ps-3 p-2">
                                                            <input type="text" name="keterangan[<?= $v['id_varian'] ?>]" class="form-control form-control-sm focus-ring focus-ring-primary" value="<?= htmlspecialchars($v['keterangan_varian'] ?? '') ?>" placeholder="-">
                                                        </td>
                                                        <td class="p-2">
                                                            <div class="input-group input-group-sm">
                                                                <span class="input-group-text bg-light border-end-0 text-muted">Rp</span>
                                                                <input type="number" name="harga[<?= $v['id_varian'] ?>]" class="form-control fw-bold text-end border-start-0 focus-ring focus-ring-success" value="<?= $v['harga_sewa_per_hari'] ?>" min="0" required>
                                                            </div>
                                                        </td>
                                                        <td class="p-2 text-center">
                                                            <input type="number" name="stok[<?= $v['id_varian'] ?>]" class="form-control form-control-sm text-center fw-bold focus-ring focus-ring-info" value="<?= $v['stok_tersedia'] ?>" min="0" required>
                                                        </td>
                                                        <td class="pe-3 p-2">
                                                            <input type="text" name="catatan[<?= $v['id_varian'] ?>]" class="form-control form-control-sm focus-ring focus-ring-warning" value="<?= htmlspecialchars($v['catatan_kondisi'] ?? '') ?>" placeholder="-">
                                                        </td>
                                                    </tr>
                                                    <?php endforeach; ?>
                                                </tbody>
                                            </table>
                                        </div>
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

                <div class="p-3 bg-light border-top d-flex justify-content-end align-items-center rounded-bottom position-sticky bottom-0">
                    <span class="text-muted me-3 fs-13"><i class="ri-information-line"></i> Total <?= count($variants) ?> varian siap diperbarui.</span>
                    <button type="submit" class="btn btn-success px-5 fw-bold">
                        <i class="ri-save-3-line me-2"></i> Simpan Semua Perubahan
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

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
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
