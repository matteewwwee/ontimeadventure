<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

// Handle Actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] === 'toggle_status') {
        $id_review = (int)$_POST['id_review'];
        $new_status = $_POST['new_status'] === 'Aktif' ? 'Aktif' : 'Nonaktif';
        
        $stmt = $db->prepare("UPDATE review_item SET status_review = ? WHERE id_review = ?");
        $stmt->execute([$new_status, $id_review]);
        $_SESSION['flash_success'] = "Status ulasan berhasil diubah.";
    } elseif ($_POST['action'] === 'add_kata') {
        $kata = trim(strtolower($_POST['kata']));
        if (!empty($kata)) {
            $stmt = $db->prepare("INSERT IGNORE INTO filter_kata (kata) VALUES (?)");
            $stmt->execute([$kata]);
            $_SESSION['flash_success'] = "Kata '$kata' berhasil ditambahkan ke daftar filter.";
        }
    } elseif ($_POST['action'] === 'delete_kata') {
        $id_kata = (int)$_POST['id_kata'];
        $stmt = $db->prepare("DELETE FROM filter_kata WHERE id_kata = ?");
        $stmt->execute([$id_kata]);
        $_SESSION['flash_success'] = "Kata berhasil dihapus dari daftar filter.";
    }
    
    header("Location: kelola_ulasan.php");
    exit;
}

$pageTitle = 'Kelola Ulasan';
require_once __DIR__ . '/../includes/header.php';

// Fetch Reviews
$stmt = $db->prepare("
    SELECT r.*, u.no_hp, i.nama_brand, i.nama_seri, v.keterangan_varian
    FROM review_item r
    JOIN users u ON r.id_user = u.id_user
    JOIN item i ON r.id_item = i.id_item
    JOIN detail_po dp ON r.id_detail = dp.id_detail
    JOIN varian_item v ON dp.id_varian = v.id_varian
    ORDER BY r.tanggal DESC
");
$stmt->execute();
$reviews = $stmt->fetchAll();

// Fetch Filter Words
$stmt_kata = $db->query("SELECT * FROM filter_kata ORDER BY kata ASC");
$filter_kata = $stmt_kata->fetchAll();
?>

<div class="container-fluid mt-4 mb-5">
    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h4 class="mb-1 fw-semibold">Kelola Ulasan</h4>
            <p class="text-muted mb-0">Pantau dan moderasi ulasan pelanggan.</p>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['flash_success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="ri-checkbox-circle-line me-2"></i><?= $_SESSION['flash_success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['flash_success']); ?>
    <?php endif; ?>

    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-4 border-bottom-0" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold fs-15 px-4" id="ulasan-tab" data-bs-toggle="tab" data-bs-target="#tab-ulasan" type="button" role="tab" aria-controls="tab-ulasan" aria-selected="true"><i class="ri-list-check me-1"></i> Daftar Ulasan</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold fs-15 px-4" id="filter-tab" data-bs-toggle="tab" data-bs-target="#tab-filter" type="button" role="tab" aria-controls="tab-filter" aria-selected="false"><i class="ri-shield-keyhole-line me-1"></i> Filter Kata Kotor</button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <!-- Tab Daftar Ulasan -->
        <div class="tab-pane fade show active" id="tab-ulasan" role="tabpanel" aria-labelledby="ulasan-tab">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover align-middle mb-0 table-mobile-cards" id="ulasanTable" style="width: 100%;">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 40px;" class="text-center">No</th>
                                    <th>Tanggal</th>
                            <th>Pengulas</th>
                            <th>Barang</th>
                            <th class="text-center">Rating</th>
                            <th style="min-width: 200px;">Komentar</th>
                            <th>Foto</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center">Aksi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($reviews as $rev): 
                                    $masked_hp = substr($rev['no_hp'], 0, 4) . '****' . substr($rev['no_hp'], -3);
                                    $is_aktif = $rev['status_review'] === 'Aktif';
                                    $row_class = !$is_aktif ? 'table-secondary text-muted' : '';
                                ?>
                                    <tr class="<?= $row_class ?>">
                                        <td data-label="No" class="text-center"></td>
                                <td data-label="Tanggal" class="td-block-mobile">
                                    <div class="fs-13"><?= date('d M Y', strtotime($rev['tanggal'])) ?></div>
                                    <div class="fs-11 text-muted"><?= date('H:i', strtotime($rev['tanggal'])) ?></div>
                                </td>
                                <td data-label="Pengulas"><?= htmlspecialchars($masked_hp) ?></td>
                                <td data-label="Barang" class="td-block-mobile">
                                    <div class="fw-semibold"><?= htmlspecialchars($rev['nama_brand'] . ' ' . $rev['nama_seri']) ?></div>
                                    <div class="fs-12 text-muted"><?= htmlspecialchars($rev['keterangan_varian']) ?></div>
                                </td>
                                <td data-label="Rating" class="text-center text-warning fs-14">
                                    <?php for($i=1; $i<=5; $i++): ?>
                                        <i class="ri-star-<?= $i <= $rev['rating'] ? 'fill' : 'line' ?>"></i>
                                    <?php endfor; ?>
                                </td>
                                <td data-label="Komentar" class="td-block-mobile">
                                    <?php if(!empty($rev['komentar'])): ?>
                                        <span class="fs-13 <?= !$is_aktif ? 'text-decoration-line-through' : '' ?>"><?= nl2br(htmlspecialchars($rev['komentar'])) ?></span>
                                    <?php else: ?>
                                        <i class="text-muted fs-12">Tidak ada komentar</i>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Foto" class="text-center">
                                    <?php if(!empty($rev['foto'])): ?>
                                        <img src="<?= $base_url ?>assets/img/reviews/<?= htmlspecialchars($rev['foto']) ?>" class="rounded border" style="width:40px; height:40px; object-fit:cover; cursor:pointer;" onclick="if(window.previewModalImage){window.previewModalImage.src=this.src; var m = new bootstrap.Modal(document.getElementById('imagePreviewModal')); m.show();}">
                                    <?php else: ?>
                                        <span class="text-muted fs-12">-</span>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status" class="text-center">
                                    <span class="badge <?= $is_aktif ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger' ?> fs-12">
                                        <?= htmlspecialchars($rev['status_review']) ?>
                                    </span>
                                </td>
                                <td data-label="Aksi" class="text-center">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id_review" value="<?= $rev['id_review'] ?>">
                                        <input type="hidden" name="new_status" value="<?= $is_aktif ? 'Nonaktif' : 'Aktif' ?>">
                                        <button type="submit" class="btn btn-sm <?= $is_aktif ? 'btn-outline-danger' : 'btn-outline-success' ?> btn-wave" title="<?= $is_aktif ? 'Matikan Ulasan' : 'Aktifkan Ulasan' ?>">
                                            <i class="<?= $is_aktif ? 'ri-eye-off-line' : 'ri-eye-line' ?>"></i> <?= $is_aktif ? 'Sembunyikan' : 'Tampilkan' ?>
                                        </button>
                                    </form>
                                                            </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tab Filter Kata -->
        <div class="tab-pane fade" id="tab-filter" role="tabpanel" aria-labelledby="filter-tab">
            <div class="row">
                <div class="col-md-5">
                    <div class="card custom-card">
                        <div class="card-header bg-light">
                            <h6 class="card-title mb-0">Tambah Kata Terlarang</h6>
                        </div>
                        <div class="card-body">
                            <form method="POST">
                                <input type="hidden" name="action" value="add_kata">
                                <div class="mb-3">
                                    <label class="form-label">Kata / Frasa</label>
                                    <input type="text" class="form-control" name="kata" required placeholder="Contoh: penipu">
                                    <div class="form-text">Kata ini akan otomatis memblokir ulasan baru yang mengandungnya.</div>
                                </div>
                                <button type="submit" class="btn btn-primary w-100">Simpan Kata</button>
                            </form>
                        </div>
                    </div>
                </div>
                <div class="col-md-7">
                    <div class="card custom-card">
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover align-middle mb-0" id="filterTable" style="width: 100%;">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 50px;" class="text-center">No</th>
                                            <th>Kata Terlarang</th>
                                            <th class="text-center" style="width: 100px;">Aksi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php $no = 1; foreach ($filter_kata as $fk): ?>
                                            <tr>
                                                <td class="text-center"><?= $no++ ?></td>
                                                <td class="fw-semibold text-danger"><?= htmlspecialchars($fk['kata']) ?></td>
                                                <td class="text-center">
                                                    <form method="POST" onsubmit="return confirm('Apakah Anda yakin ingin menghapus kata ini?');">
                                                        <input type="hidden" name="action" value="delete_kata">
                                                        <input type="hidden" name="id_kata" value="<?= $fk['id_kata'] ?>">
                                                        <button type="submit" class="btn btn-sm btn-outline-danger btn-icon" title="Hapus"><i class="ri-delete-bin-line"></i></button>
                                                    </form>
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

<!-- Scripts -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">

<script>
document.addEventListener("DOMContentLoaded", function() {
    window.previewModalImage = document.getElementById('previewModalImage');
    
    // Initialize DataTables for Ulasan
    if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
        var t = $('#ulasanTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
            },
            "order": [[ 1, "desc" ]],
            "columnDefs": [
                { "searchable": false, "orderable": false, "targets": 0 },
                { "orderable": false, "targets": [6, 8] }
            ]
        });

        // Add auto numbering to Ulasan Table
        t.on('order.dt search.dt', function () {
            let i = 1;
            t.cells(null, 0, {search:'applied', order:'applied'}).every(function (cell) {
                this.data(i++);
            });
        }).draw();
        
        // Initialize DataTables for Filter Kata
        $('#filterTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
            },
            "pageLength": 10,
            "lengthChange": false
        });
    }
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
