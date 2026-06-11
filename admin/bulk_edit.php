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

    try {
        $db->beginTransaction();
        $stmt = $db->prepare("UPDATE varian_item SET harga_sewa_per_hari = ?, stok_tersedia = ?, keterangan_varian = ?, catatan_kondisi = ? WHERE id_varian = ?");
        
        $count = 0;
        foreach ($hargas as $id_varian => $harga) {
            $stok = $stoks[$id_varian] ?? 0;
            $keterangan = trim($keterangans[$id_varian] ?? '');
            $catatan = trim($catatans[$id_varian] ?? '');
            $stmt->execute([(int)$harga, (int)$stok, $keterangan, $catatan, (int)$id_varian]);
            $count++;
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
        v.id_varian, v.keterangan_varian, v.harga_sewa_per_hari, v.stok_tersedia, v.catatan_kondisi,
        i.nama_brand, i.nama_seri,
        k.nama_kategori
    FROM varian_item v
    JOIN item i ON v.id_item = i.id_item
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    ORDER BY k.nama_kategori ASC, i.nama_brand ASC, i.nama_seri ASC
");
$variants = $stmt->fetchAll();

$pageTitle = "Bulk Edit Harga & Stok";
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="fw-bold mb-1"><i class="ri-table-2 text-success me-2"></i>Bulk Edit Harga & Stok</h4>
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
                
                <div class="table-responsive" style="max-height: 65vh; overflow-y: auto;">
                    <table class="table table-hover table-striped table-bordered align-middle mb-0" style="min-width: 800px;">
                        <thead class="table-light position-sticky top-0 shadow-sm" style="z-index: 10;">
                            <tr>
                                <th class="text-center" width="5%">No</th>
                                <th width="15%">Kategori</th>
                                <th width="20%">Nama Barang</th>
                                <th width="15%" class="text-center bg-primary-transparent text-primary">Varian / Ukuran</th>
                                <th width="15%" class="text-center bg-success-transparent text-success">Harga Sewa / Hari</th>
                                <th width="10%" class="text-center bg-info-transparent text-info">Stok Tersedia</th>
                                <th width="15%" class="text-center bg-warning-transparent text-warning">Catatan Kondisi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (count($variants) > 0): ?>
                                <?php foreach ($variants as $index => $v): ?>
                                    <tr>
                                        <td class="text-center text-muted"><?= $index + 1 ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($v['nama_kategori']) ?></span></td>
                                        <td class="fw-bold"><?= htmlspecialchars($v['nama_brand'] . ' ' . $v['nama_seri']) ?></td>
                                        <td class="p-2">
                                            <input type="text" 
                                                   name="keterangan[<?= $v['id_varian'] ?>]" 
                                                   class="form-control form-control-sm focus-ring focus-ring-primary" 
                                                   value="<?= htmlspecialchars($v['keterangan_varian']) ?>" 
                                                   required>
                                        </td>
                                        <td class="p-2">
                                            <div class="input-group input-group-sm">
                                                <span class="input-group-text bg-light border-end-0 text-muted">Rp</span>
                                                <input type="number" 
                                                       name="harga[<?= $v['id_varian'] ?>]" 
                                                       class="form-control fw-bold text-end border-start-0 focus-ring focus-ring-success" 
                                                       value="<?= $v['harga_sewa_per_hari'] ?>" 
                                                       min="0" required>
                                            </div>
                                        </td>
                                        <td class="p-2">
                                            <input type="number" 
                                                   name="stok[<?= $v['id_varian'] ?>]" 
                                                   class="form-control form-control-sm text-center fw-bold focus-ring focus-ring-info" 
                                                   value="<?= $v['stok_tersedia'] ?>" 
                                                   min="0" required>
                                        </td>
                                        <td class="p-2">
                                            <input type="text" 
                                                   name="catatan[<?= $v['id_varian'] ?>]" 
                                                   class="form-control form-control-sm focus-ring focus-ring-warning" 
                                                   value="<?= htmlspecialchars($v['catatan_kondisi'] ?? '') ?>" 
                                                   placeholder="Misal: Aman, Lecet">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="ri-inbox-2-line fs-24 d-block mb-2"></i> Belum ada varian item yang ditambahkan.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="p-3 bg-light border-top d-flex justify-content-end align-items-center rounded-bottom position-sticky bottom-0">
                    <span class="text-muted me-3 fs-13"><i class="ri-information-line"></i> Total <?= count($variants) ?> varian siap diperbarui.</span>
                    <button type="submit" class="btn btn-success px-5 fw-bold" onclick="return confirm('Apakah Anda yakin ingin menyimpan seluruh perubahan harga dan stok ini?');">
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

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
