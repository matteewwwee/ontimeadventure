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
        $nama = trim($_POST['nama']);
        $no_hp = trim($_POST['no_hp']);
        $pin = $_POST['pin'];
        $role = $_POST['role'];
        
        // Cek duplicate no_hp
        $cek = $db->prepare("SELECT id_user FROM users WHERE no_hp = ?");
        $cek->execute([$no_hp]);
        
        if ($cek->fetch()) {
            $flash_msg = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="ri-alert-line me-1 align-middle fs-16"></i> Gagal: Nomor HP sudah terdaftar.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $hashed_pin = password_hash($pin, PASSWORD_DEFAULT);
            $stmt = $db->prepare("INSERT INTO users (no_hp, pin, nama, role) VALUES (?, ?, ?, ?)");
            $stmt->execute([$no_hp, $hashed_pin, $nama, $role]);
            $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> User berhasil ditambahkan!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
        
    } elseif ($action === 'delete') {
        $id = $_POST['id_user'];
        if ($id == $_SESSION['id_user']) {
            $flash_msg = '<div class="alert alert-warning alert-dismissible fade show" role="alert"><i class="ri-alert-line me-1 align-middle fs-16"></i> Gagal: Anda tidak dapat menghapus akun Anda sendiri.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        } else {
            $stmt = $db->prepare("DELETE FROM users WHERE id_user = ?");
            $stmt->execute([$id]);
            $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> User berhasil dihapus!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    }
}

// Get All Users
$users = $db->query("SELECT * FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = 'Kelola User';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-semibold">Kelola User</h4>
    </div>

    <?= $flash_msg ?>

    <div class="row">
        <!-- Tambah User -->
        <div class="col-xl-4 col-lg-5">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Tambah User Baru</div>
                </div>
                <div class="card-body">
                    <form method="POST" action="">
                        <input type="hidden" name="action" value="add">
                        
                        <div class="mb-3">
                            <label class="form-label text-default">Nama Lengkap</label>
                            <input type="text" name="nama" class="form-control" required>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-default">No HP (Untuk Login)</label>
                            <input type="text" name="no_hp" class="form-control" required placeholder="08xxxxxxxxxx">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label text-default">PIN (4 Digit)</label>
                            <input type="password" name="pin" class="form-control" maxlength="4" pattern="[0-9]{4}" required>
                        </div>
                        
                        <div class="mb-4">
                            <label class="form-label text-default">Role</label>
                            <select name="role" class="form-select" required>
                                <option value="pelanggan">Pelanggan</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        
                        <button type="submit" class="btn btn-primary btn-wave w-100"><i class="ri-user-add-line me-1"></i> Tambah User</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Daftar User -->
        <div class="col-xl-8 col-lg-7">
            <div class="card custom-card">
                <div class="card-header">
                    <div class="card-title">Daftar User</div>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table id="userTable" class="table text-nowrap table-bordered table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th class="text-center">No</th>
                                    <th>Nama</th>
                                    <th>No HP</th>
                                    <th class="text-center">Role</th>
                                    <th>Tgl Daftar</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $u): ?>
                                    <tr>
                                        <td class="text-center fw-semibold"><?= $u['id_user'] ?></td>
                                        <td class="fw-semibold"><?= htmlspecialchars($u['nama']) ?></td>
                                        <td><?= htmlspecialchars($u['no_hp']) ?></td>
                                        <td class="text-center">
                                            <span class="badge <?= $u['role'] == 'admin' ? 'bg-danger-transparent text-danger' : 'bg-primary-transparent text-primary' ?>">
                                                <?= ucfirst($u['role']) ?>
                                            </span>
                                        </td>
                                        <td><?= date('d M Y', strtotime($u['created_at'])) ?></td>
                                        <td class="text-center">
                                            <?php if ($u['id_user'] != $_SESSION['id_user']): ?>
                                                <form method="POST" action="" class="d-inline" onsubmit="confirmDelete(event, 'Yakin hapus user ini?');">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger btn-wave"><i class="ri-delete-bin-line"></i></button>
                                                </form>
                                            <?php else: ?>
                                                <span class="badge bg-light text-dark">Anda</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS & JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<script>
$(document).ready(function() {
    if ($.fn.DataTable.isDataTable('#userTable')) {
        $('#userTable').DataTable().destroy();
    }
    
    let t = $('#userTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
        },
        "order": [[ 4, "desc" ]], // Urutkan berdasarkan tanggal daftar secara default
        "columnDefs": [
            { "searchable": false, "orderable": false, "targets": 0 },
            { "orderable": false, "targets": 5 } // Kolom Aksi tidak bisa diurutkan
        ]
    });

    // Add auto numbering
    t.on('order.dt search.dt', function () {
        let i = 1;
        t.cells(null, 0, {search:'applied', order:'applied'}).every(function (cell) {
            this.data(i++);
        });
    }).draw();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
