<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
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
            $stmt = $db->prepare("DELETE FROM users WHERE id_user = ? AND role != 'admin'");
            $stmt->execute([$id]);
            $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> User berhasil dihapus!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        }
    } elseif ($action === 'edit_user') {
        $id = $_POST['id_user'];
        $new_role = $_POST['role'];
        $new_pin = trim($_POST['pin']);
        
        $update_sql = "UPDATE users SET role = ?";
        $params = [$new_role];
        
        if (!empty($new_pin)) {
            $hashed_pin = password_hash($new_pin, PASSWORD_DEFAULT);
            $update_sql .= ", pin = ?";
            $params[] = $hashed_pin;
        }
        
        $update_sql .= " WHERE id_user = ?";
        $params[] = $id;
        
        $stmt = $db->prepare($update_sql);
        $stmt->execute($params);
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Data user berhasil diperbarui!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
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
                                                <?php if ($u['role'] === 'pelanggan'): ?>
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="btn btn-sm btn-info btn-wave" title="Edit User & PIN" onclick="editUser(<?= $u['id_user'] ?>, '<?= $u['role'] ?>')"><i class="ri-edit-line"></i></button>
                                                        <form method="POST" action="" class="d-inline" onsubmit="confirmDelete(event, 'Yakin hapus user ini?');">
                                                            <input type="hidden" name="action" value="delete">
                                                            <input type="hidden" name="id_user" value="<?= $u['id_user'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-danger btn-wave" title="Hapus User"><i class="ri-delete-bin-line"></i></button>
                                                        </form>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="d-flex justify-content-center gap-1">
                                                        <button type="button" class="btn btn-sm btn-info btn-wave" title="Edit User & PIN" onclick="editUser(<?= $u['id_user'] ?>, '<?= $u['role'] ?>')"><i class="ri-edit-line"></i></button>
                                                        <span class="badge bg-light text-muted align-self-center">Admin Tetap</span>
                                                    </div>
                                                <?php endif; ?>
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

<!-- Modal Edit User -->
<div class="modal fade" id="modalEditUser" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <form method="POST" action="">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User & Reset PIN</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_user">
                    <input type="hidden" name="id_user" id="edit_id_user">
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Role User</label>
                        <select name="role" id="edit_role" class="form-select" required>
                            <option value="pelanggan">Pelanggan</option>
                            <option value="admin">Admin</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Reset PIN Baru</label>
                        <input type="password" name="pin" class="form-control" maxlength="4" pattern="[0-9]{4}" placeholder="Kosongkan jika tidak ingin mengubah PIN">
                        <div class="form-text text-muted">Masukkan 4 digit angka jika ingin mengganti PIN tanpa perlu mengetahui PIN lama. Kosongkan jika tidak ingin diganti.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </form>
        </div>
    </div>
</div>

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

function editUser(id, currentRole) {
    document.getElementById('edit_id_user').value = id;
    document.getElementById('edit_role').value = currentRole;
    $('#modalEditUser').modal('show');
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
