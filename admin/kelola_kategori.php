<?php
session_start();
$base_url = '/ontimeadventure/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

$flash_msg = '';

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add') {
        $nama = trim($_POST['nama_kategori']);
        
        if (!empty($nama)) {
            $stmt = $db->prepare("INSERT INTO kategori_item (nama_kategori) VALUES (?)");
            $stmt->execute([$nama]);
            $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Kategori berhasil ditambahkan!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    } elseif ($action === 'edit_kategori') {
        $id = $_POST['id_kategori'];
        $nama = trim($_POST['nama_kategori']);
        
        if (!empty($nama)) {
            $stmt = $db->prepare("UPDATE kategori_item SET nama_kategori = ? WHERE id_kategori = ?");
            $stmt->execute([$nama, $id]);
            $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Kategori berhasil diperbarui!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    } elseif ($action === 'delete') {
        $id = $_POST['id_kategori'];
        
        // Cek apakah ada item di kategori ini
        $cek = $db->prepare("SELECT COUNT(*) FROM item WHERE id_kategori = ?");
        $cek->execute([$id]);
        if ($cek->fetchColumn() > 0) {
            $flash_msg = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="ri-alert-line me-1 align-middle fs-16"></i> Gagal dihapus: Kategori masih memiliki item. Hapus atau pindahkan item terlebih dahulu.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $stmt = $db->prepare("DELETE FROM kategori_item WHERE id_kategori = ?");
            $stmt->execute([$id]);
            $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Kategori berhasil dihapus!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    }
}

$kategori = $db->query("SELECT * FROM kategori_item ORDER BY id_kategori DESC")->fetchAll();

$pageTitle = 'Kelola Kategori';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-semibold">Kelola Kategori</h4>
    </div>

    <?= $flash_msg ?>

    <div class="row">
        <!-- Tambah Kategori -->
        <div class="col-xl-4 col-lg-5">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Tambah Kategori Baru</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label text-default">Nama Kategori</label>
                            <input type="text" name="nama_kategori" class="form-control" required placeholder="Cth: Tenda">
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-wave w-100"><i class="ri-add-line me-1"></i> Tambah Kategori</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar Kategori -->
        <div class="col-xl-8 col-lg-7">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Daftar Kategori</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="50" class="text-center">No</th>
                                    <th>Nama Kategori</th>
                                    <th width="100" class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php $no = 1; foreach ($kategori as $k): ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?= $no++ ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($k['nama_kategori']) ?></td>
                                        <td class="text-center">
                                            <button class="btn btn-sm btn-warning btn-wave me-1" data-bs-toggle="modal" data-bs-target="#editKatModal" onclick="editKategori(<?= $k['id_kategori'] ?>, '<?= htmlspecialchars(addslashes($k['nama_kategori'])) ?>')"><i class="ri-edit-line"></i></button>
                                            <form method="POST" action="" class="d-inline" onsubmit="confirmDelete(event, 'Yakin hapus kategori ini?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id_kategori" value="<?= $k['id_kategori'] ?>">
                                                <button type="submit" class="btn btn-sm btn-danger btn-wave"><i class="ri-delete-bin-line"></i></button>
                                            </form>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                                <?php if(empty($kategori)): ?>
                                    <tr><td colspan="3" class="text-center text-muted py-4">Belum ada kategori</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</div>

<!-- Modal Edit Kategori -->
<div class="modal fade" id="editKatModal" tabindex="-1" aria-labelledby="editKatModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editKatModalLabel">Edit Kategori</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_kategori">
                    <input type="hidden" name="id_kategori" id="edit_id_kategori">
                    
                    <div class="mb-3">
                        <label class="form-label text-default">Nama Kategori</label>
                        <input type="text" name="nama_kategori" id="edit_nama_kategori" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>

<script>
function editKategori(id, nama) {
    document.getElementById('edit_id_kategori').value = id;
    document.getElementById('edit_nama_kategori').value = nama;
}
</script>
