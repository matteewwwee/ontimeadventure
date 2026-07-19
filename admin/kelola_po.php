<?php
session_start();
$base_url = '/ontimeadventure/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

$flash_msg = '';

// Update Status PO (Via Form POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $id_po = $_POST['id_po'];
    $status_baru = $_POST['status_po'];
    
    // Auto-update log waktu dan admin
    $waktu_query = "";
    $admin_name = $_SESSION['nama'] ?? 'Admin Web';

    if (in_array($status_baru, ['Menunggu Pengecekan', 'Ada Barang Kosong', 'Dibatalkan'])) {
        // Reset semua log jika status mundur ke tahap awal/batal
        $waktu_query = ", waktu_diambil = NULL, waktu_kembali = NULL, admin_penyetuju = NULL, admin_penyerah = NULL, admin_penerima = NULL";
    } elseif ($status_baru === 'Barang Siap') {
        $waktu_query = ", admin_penyetuju = '{$admin_name}'";
    } elseif ($status_baru === 'Barang Diambil' || $status_baru === 'Selesai (Barang Belum Kembali)') {
        // Catat waktu diambil, hapus waktu kembali jika sebelumnya sempat salah set ke selesai
        $waktu_query = ", waktu_diambil = COALESCE(waktu_diambil, CURRENT_TIMESTAMP), waktu_kembali = NULL, admin_penyerah = '{$admin_name}', admin_penerima = NULL";
    } elseif ($status_baru === 'Selesai (Barang Kembali)' || $status_baru === 'Selesai') {
        // Catat waktu kembali saat itu juga
        $waktu_query = ", waktu_diambil = COALESCE(waktu_diambil, CURRENT_TIMESTAMP), waktu_kembali = COALESCE(waktu_kembali, CURRENT_TIMESTAMP), admin_penerima = '{$admin_name}'";
        
        // Simpan denda jika ada dari AJAX form submission
        if (isset($_POST['total_denda']) && (int)$_POST['total_denda'] > 0) {
            $denda = (int)$_POST['total_denda'];
            $alasan = $db->quote($_POST['alasan_denda'] ?? 'Terlambat');
            $waktu_query .= ", total_denda = {$denda}, alasan_denda = {$alasan}";
        } else {
            $waktu_query .= ", total_denda = 0, alasan_denda = NULL";
        }
    }
    
    // Dapatkan status lama
    $stmt_old = $db->prepare("SELECT status_po FROM pengajuan_po WHERE id_po = ?");
    $stmt_old->execute([$id_po]);
    $old_status = $stmt_old->fetchColumn();

    if ($status_baru === 'Barang Diambil' && isset($_POST['jaminan'])) {
        $jaminan_val = $db->quote($_POST['jaminan']);
        $waktu_query .= ", jaminan = {$jaminan_val}";
    }

    $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ? {$waktu_query} WHERE id_po = ?");
    $stmt->execute([$status_baru, $id_po]);

    // --- TRIGGER NOTIFIKASI ---
    if ($old_status !== $status_baru) {
        $stmt_u = $db->prepare("SELECT id_user FROM pengajuan_po WHERE id_po = ?");
        $stmt_u->execute([$id_po]);
        $id_user_notif = $stmt_u->fetchColumn();

        if ($id_user_notif) {
            $judul = "Status Pesanan Diperbarui";
            $pesan = "Pesanan PO-" . str_pad($id_po, 5, '0', STR_PAD_LEFT) . " Anda kini berstatus: " . $status_baru;
            $tautan = "/ontimeadventure/riwayat_po.php";
            $stmt_notif = $db->prepare("INSERT INTO notifikasi (id_user, judul, pesan, tautan) VALUES (?, ?, ?, ?)");
            $stmt_notif->execute([$id_user_notif, $judul, $pesan, $tautan]);

            if (strpos($status_baru, 'Selesai') !== false) {
                $judul_ulasan = "Pesanan Selesai!";
                $pesan_ulasan = "Terima kasih telah bertransaksi. Yuk luangkan waktu untuk memberikan ulasan barang!";
                $stmt_notif->execute([$id_user_notif, $judul_ulasan, $pesan_ulasan, $tautan]);
            }
        }
    }
    // -------------------------

    $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Status PO berhasil diperbarui!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
}

// Update Status PO (Via URL GET dari Telegram Bot)
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']) && isset($_GET['id_po'])) {
    $id_po = (int)$_GET['id_po'];
    $action = $_GET['action'];
    $status_baru = '';
    $admin_telegram = $_GET['admin'] ?? 'Admin Telegram';
    $admin_query = "";

    if ($action === 'setujui') {
        $status_baru = 'Barang Siap';
        $admin_query = ", admin_penyetuju = '{$admin_telegram}'";
    } elseif ($action === 'batalkan') {
        $status_baru = 'Dibatalkan';
        $admin_query = ", admin_penyetuju = NULL, admin_penyerah = NULL, admin_penerima = NULL";
    }
    
    if ($status_baru !== '') {
        $stmt = $db->prepare("UPDATE pengajuan_po SET status_po = ? {$admin_query} WHERE id_po = ?");
        $stmt->execute([$status_baru, $id_po]);
        
        // --- TRIGGER NOTIFIKASI ---
        $stmt_u = $db->prepare("SELECT id_user FROM pengajuan_po WHERE id_po = ?");
        $stmt_u->execute([$id_po]);
        $id_user_notif = $stmt_u->fetchColumn();

        if ($id_user_notif) {
            $judul = "Status Pesanan Diperbarui";
            $pesan = "Pesanan PO-" . str_pad($id_po, 5, '0', STR_PAD_LEFT) . " Anda kini berstatus: " . $status_baru;
            $tautan = "/ontimeadventure/riwayat_po.php";
            $stmt_notif = $db->prepare("INSERT INTO notifikasi (id_user, judul, pesan, tautan) VALUES (?, ?, ?, ?)");
            $stmt_notif->execute([$id_user_notif, $judul, $pesan, $tautan]);
        }
        // -------------------------

        // Redirect untuk membersihkan URL agar aman jika halaman di-refresh
        header("Location: kelola_po.php?msg=success");
        exit;
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'success') {
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Status PO berhasil diperbarui dari Telegram!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    } elseif ($_GET['msg'] === 'deleted') {
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Data PO berhasil dihapus secara permanen!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    } elseif ($_GET['msg'] === 'error_delete') {
        $flash_msg = '<div class="alert alert-danger alert-dismissible fade show" role="alert"><i class="ri-error-warning-line me-1 align-middle fs-16"></i> Gagal menghapus Data PO. Mungkin ada data yang terkait.<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
    }
}

// Filter Status
$filter_status = $_GET['status'] ?? '';
$search = $_GET['search'] ?? '';
$start_date = $_GET['start_date'] ?? '';
$end_date = $_GET['end_date'] ?? '';

$where_clauses = [];
$params = [];

if (!empty($filter_status)) {
    $where_clauses[] = "p.status_po = ?";
    $params[] = $filter_status;
}
if (!empty($search)) {
    $search_terms = explode(' ', $search);
    foreach ($search_terms as $term) {
        $term = trim($term);
        if (empty($term)) continue;
        
        $where_clauses[] = "(
            u.nama LIKE ? OR 
            u.no_hp LIKE ? OR 
            p.id_po LIKE ? OR 
            EXISTS (
                SELECT 1 FROM detail_po dp 
                JOIN varian_item v ON dp.id_varian = v.id_varian 
                JOIN item i ON v.id_item = i.id_item 
                WHERE dp.id_po = p.id_po AND (i.nama_brand LIKE ? OR i.nama_seri LIKE ? OR v.keterangan_varian LIKE ?)
            )
        )";
        $search_param = "%$term%";
        for($i=0; $i<6; $i++) {
            $params[] = $search_param;
        }
    }
}
if (!empty($start_date)) {
    $where_clauses[] = "DATE(p.tgl_pengajuan) >= ?";
    $params[] = $start_date;
}
if (!empty($end_date)) {
    $where_clauses[] = "DATE(p.tgl_pengajuan) <= ?";
    $params[] = $end_date;
}

$where = "";
if (count($where_clauses) > 0) {
    $where = "WHERE " . implode(" AND ", $where_clauses);
}

// Pagination logic
$limit = 10;
$page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($page < 1) $page = 1;
$offset = ($page - 1) * $limit;

// Count total for pagination
$count_query = "
    SELECT COUNT(DISTINCT p.id_po) 
    FROM pengajuan_po p 
    JOIN users u ON p.id_user = u.id_user 
    $where
";
$stmt_count = $db->prepare($count_query);
$stmt_count->execute($params);
$total_data = $stmt_count->fetchColumn();
$total_pages = ceil($total_data / $limit);

// Ambil data PO dengan Limit
$query = "
    SELECT p.*, u.nama, u.no_hp 
    FROM pengajuan_po p 
    JOIN users u ON p.id_user = u.id_user 
    $where
    ORDER BY p.tgl_pengajuan DESC
    LIMIT $limit OFFSET $offset
";
$stmt = $db->prepare($query);
$stmt->execute($params);
$list_po = $stmt->fetchAll();

function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Menunggu Pengecekan': return 'bg-warning-transparent text-warning';
        case 'Barang Siap': return 'bg-info-transparent text-info';
        case 'Barang Diambil': return 'bg-primary-transparent text-primary';
        case 'Ada Barang Kosong': return 'bg-danger-transparent text-danger';
        case 'Selesai (Barang Belum Kembali)': return 'bg-warning-transparent text-warning';
        case 'Selesai (Barang Kembali)': return 'bg-success-transparent text-success';
        case 'Selesai': return 'bg-success-transparent text-success';
        case 'Dibatalkan': return 'bg-secondary-transparent text-secondary';
        default: return 'bg-light text-dark';
    }
}

$pageTitle = 'Kelola Penyewaan';
require_once __DIR__ . '/../includes/header.php';
?>

<?php
$export_params = http_build_query([
    'status' => $filter_status,
    'search' => $search,
    'start_date' => $start_date,
    'end_date' => $end_date
]);
?>
<div class="container-fluid mt-4 mb-5">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <h5 class="mb-0 fw-semibold">Kelola Penyewaan</h5>
        <a href="export_po.php?<?= $export_params ?>" target="_blank" class="btn btn-sm btn-info btn-wave"><i class="ri-printer-line me-1"></i> Cetak / PDF</a>
    </div>

    <!-- Filter Section -->
    <div class="card custom-card mb-3">
        <div class="card-body p-3">
            <form id="filterForm" method="GET" action="" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <label class="form-label fs-12 mb-1 text-muted">Pencarian</label>
                    <div class="input-group input-group-sm">
                        <input type="text" name="search" id="searchPO" class="form-control" placeholder="Ketik untuk mencari..." value="<?= htmlspecialchars($search) ?>">
                        <button class="btn btn-primary" type="submit" title="Cari"><i class="ri-search-line"></i></button>
                    </div>
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 mb-1 text-muted">Tanggal Mulai</label>
                    <input type="date" name="start_date" class="form-control form-control-sm" value="<?= htmlspecialchars($start_date) ?>" title="Tanggal Pengajuan Mulai" onchange="this.form.submit()">
                </div>
                <div class="col-md-2">
                    <label class="form-label fs-12 mb-1 text-muted">Tanggal Selesai</label>
                    <input type="date" name="end_date" class="form-control form-control-sm" value="<?= htmlspecialchars($end_date) ?>" title="Tanggal Pengajuan Selesai" onchange="this.form.submit()">
                </div>
                <div class="col-md-3">
                    <label class="form-label fs-12 mb-1 text-muted">Status Penyewaan</label>
                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                        <option value="">Semua Status</option>
                        <option value="Menunggu Pengecekan" <?= $filter_status == 'Menunggu Pengecekan' ? 'selected' : '' ?>>Menunggu</option>
                        <option value="Barang Siap" <?= $filter_status == 'Barang Siap' ? 'selected' : '' ?>>Barang Siap</option>
                        <option value="Barang Diambil" <?= $filter_status == 'Barang Diambil' ? 'selected' : '' ?>>Barang Diambil</option>
                        <option value="Ada Barang Kosong" <?= $filter_status == 'Ada Barang Kosong' ? 'selected' : '' ?>>Ada Kosong</option>
                        <option value="Selesai (Barang Belum Kembali)" <?= $filter_status == 'Selesai (Barang Belum Kembali)' ? 'selected' : '' ?>>Waktu Habis (Belum Kembali)</option>
                        <option value="Selesai (Barang Kembali)" <?= $filter_status == 'Selesai (Barang Kembali)' ? 'selected' : '' ?>>Selesai (Sudah Kembali)</option>
                        <option value="Selesai" <?= $filter_status == 'Selesai' ? 'selected' : '' ?>>Selesai Lama</option>
                        <option value="Dibatalkan" <?= $filter_status == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                    </select>
                </div>
                <div class="col-md-1 d-flex align-items-end mt-auto">
                    <a href="kelola_po.php" class="btn btn-sm btn-light w-100" title="Reset Semua Filter"><i class="ri-refresh-line"></i></a>
                </div>
            </form>
        </div>
    </div>
    
    <?= $flash_msg ?>

    <style>
        @media (max-width: 768px) {
            .responsive-table thead {
                display: none;
            }
            .responsive-table, .responsive-table tbody, .responsive-table tr, .responsive-table td {
                display: block;
                width: 100%;
            }
            .responsive-table tr:not(.detail-row-container) {
                margin-bottom: 1rem;
                background-color: #fff;
                border: 1px solid #e0e0e0 !important;
                border-radius: 0.5rem;
                box-shadow: 0 4px 6px rgba(0,0,0,0.05);
                overflow: hidden;
            }
            .responsive-table td {
                text-align: right;
                padding: 10px 15px 10px 45% !important;
                position: relative;
                border-bottom: 1px solid #f0f0f0;
                border-top: none !important;
                border-left: none !important;
                border-right: none !important;
            }
            .responsive-table td:last-child {
                border-bottom: 0;
            }
            .responsive-table td::before {
                content: attr(data-label);
                position: absolute;
                left: 15px;
                top: 10px;
                width: 40%;
                font-weight: 600;
                text-align: left;
                color: #6c757d;
                font-size: 13px;
                white-space: normal;
            }
            .responsive-table tr.detail-row-container {
                border: none !important;
                box-shadow: none !important;
                margin-bottom: 0;
                background: transparent;
            }
            .responsive-table tr.detail-row-container td {
                padding: 0 !important;
                text-align: left;
            }
            .responsive-table tr.detail-row-container td::before {
                display: none;
            }
            /* Reset button group width on mobile */
            .responsive-table .d-flex.gap-1 {
                justify-content: flex-end;
            }
        }
    </style>

    <div class="card custom-card">
        <div class="card-body p-0">
            <div id="kelola-po-container">
                <table class="table text-nowrap table-bordered mb-0 responsive-table">
                    <thead class="table-light">
                        <tr>
                            <th>PO ID</th>
                            <th>Pelanggan</th>
                            <th>Tanggal Sewa</th>
                            <th>Log Waktu</th>
                            <th>Total Harga</th>
                            <th class="text-center">Status</th>
                            <th>Update Status</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($list_po as $po): ?>
                            <tr>
                                <td data-label="PO ID">
                                    <span class="fw-semibold text-primary">PO-<?= str_pad($po['id_po'], 5, '0', STR_PAD_LEFT) ?></span><br>
                                    <span class="fs-12 text-muted"><?= date('d M Y', strtotime($po['tgl_pengajuan'])) ?></span>
                                </td>
                                <td data-label="Pelanggan">
                                    <span class="fw-semibold"><?= htmlspecialchars($po['nama']) ?></span><br>
                                    <span class="fs-12 text-muted"><?= htmlspecialchars($po['no_hp']) ?></span>
                                </td>
                                <td data-label="Tanggal Sewa">
                                    <span class="fs-13">
                                    <span class="text-muted">Mulai:</span> <?= date('d/m/y', strtotime($po['tgl_mulai_sewa'])) ?><br>
                                    <span class="text-muted">Selesai:</span> <?= date('d/m/y', strtotime($po['tgl_selesai_sewa'])) ?>
                                    </span>
                                </td>
                                <td data-label="Log Waktu">
                                    <span class="fs-12">
                                        <?php if (!empty($po['waktu_diambil'])): ?>
                                            <span class="text-primary" title="Waktu Diambil"><i class="ri-arrow-right-up-line"></i> <?= date('d/m/y H:i', strtotime($po['waktu_diambil'])) ?></span>
                                            <?php if (!empty($po['admin_penyerah'])): ?>
                                                <span class="text-muted d-block ms-3" style="font-size: 10px;">(Oleh: <?= htmlspecialchars($po['admin_penyerah']) ?>)</span>
                                            <?php else: ?>
                                                <br>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="ri-arrow-right-up-line"></i> Belum diambil</span><br>
                                        <?php endif; ?>
                                        
                                        <?php if (!empty($po['waktu_kembali'])): ?>
                                            <span class="text-success" title="Waktu Kembali"><i class="ri-arrow-left-down-line"></i> <?= date('d/m/y H:i', strtotime($po['waktu_kembali'])) ?></span>
                                            <?php if (!empty($po['admin_penerima'])): ?>
                                                <span class="text-muted d-block ms-3" style="font-size: 10px;">(Oleh: <?= htmlspecialchars($po['admin_penerima']) ?>)</span>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            <span class="text-muted"><i class="ri-arrow-left-down-line"></i> Belum kembali</span>
                                        <?php endif; ?>
                                    </span>
                                </td>
                                <td data-label="Total Harga" class="fw-semibold">
                                    Rp <?= number_format($po['estimasi_total_harga'], 0, ',', '.') ?>
                                    <?php if (isset($po['total_denda']) && $po['total_denda'] > 0): ?>
                                        <div class="text-danger mt-1 fs-12 fw-bold" title="<?= htmlspecialchars($po['alasan_denda']) ?>">
                                            + Denda: Rp <?= number_format($po['total_denda'], 0, ',', '.') ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td data-label="Status" class="text-center"><span class="badge <?= getStatusBadgeClass($po['status_po']) ?>"><?= htmlspecialchars($po['status_po']) ?></span></td>
                                <td data-label="Update Status">
                                    <form method="POST" action="" class="form-update-status d-flex gap-1 align-items-center justify-content-end">
                                        <input type="hidden" name="action" value="update_status">
                                        <input type="hidden" name="id_po" value="<?= $po['id_po'] ?>">
                                        <select name="status_po" class="form-select form-select-sm" style="width: 140px;">
                                            <option value="Menunggu Pengecekan" <?= $po['status_po'] == 'Menunggu Pengecekan' ? 'selected' : '' ?>>Menunggu</option>
                                            <option value="Barang Siap" <?= $po['status_po'] == 'Barang Siap' ? 'selected' : '' ?>>Barang Siap</option>
                                            <option value="Barang Diambil" <?= $po['status_po'] == 'Barang Diambil' ? 'selected' : '' ?>>Barang Diambil</option>
                                            <option value="Ada Barang Kosong" <?= $po['status_po'] == 'Ada Barang Kosong' ? 'selected' : '' ?>>Ada Kosong</option>
                                            <option value="Selesai (Barang Belum Kembali)" <?= $po['status_po'] == 'Selesai (Barang Belum Kembali)' ? 'selected' : '' ?>>Waktu Habis (Belum Kembali)</option>
                                            <option value="Selesai (Barang Kembali)" <?= $po['status_po'] == 'Selesai (Barang Kembali)' ? 'selected' : '' ?>>Selesai (Sudah Kembali)</option>
                                            <option value="Selesai" <?= $po['status_po'] == 'Selesai' ? 'selected' : '' ?>>Selesai Lama</option>
                                            <option value="Dibatalkan" <?= $po['status_po'] == 'Dibatalkan' ? 'selected' : '' ?>>Dibatalkan</option>
                                        </select>
                                        <button type="submit" class="btn btn-sm btn-success" title="Simpan Status"><i class="ri-save-line"></i></button>
                                    </form>
                                </td>
                                <td data-label="Aksi" class="text-center">
                                    <button class="btn btn-sm btn-info-transparent btn-wave" type="button" data-bs-toggle="collapse" data-bs-target="#detailCollapse-<?= $po['id_po'] ?>" aria-expanded="false" aria-controls="detailCollapse-<?= $po['id_po'] ?>">
                                        <i class="ri-eye-line me-1"></i> Detail
                                    </button>
                                    <a href="javascript:void(0);" onclick="konfirmasiHapus(<?= $po['id_po'] ?>)" class="btn btn-sm btn-danger-transparent btn-wave" title="Hapus"><i class="ri-delete-bin-line"></i></a>
                                </td>
                            </tr>
                            
                            <!-- Detail PO Collapse -->
                            <tr class="p-0 border-0 detail-row-container">
                                <td colspan="8" class="p-0 border-0">
                                    <div class="collapse" id="detailCollapse-<?= $po['id_po'] ?>">
                                        <div class="card card-body border-0 shadow-none bg-light m-3">
                                            <div class="row mb-3">
                                                <div class="col-md-6">
                                                    <h6 class="fw-semibold mb-2">Catatan Pelanggan:</h6>
                                                    <p class="fs-13 text-muted mb-0"><?= !empty($po['catatan_pelanggan']) ? nl2br(htmlspecialchars($po['catatan_pelanggan'])) : '<i>Tidak ada catatan</i>' ?></p>

                                                    <?php if (!empty($po['jaminan'])): ?>
                                                    <h6 class="fw-semibold mt-3 mb-2 text-primary">Jaminan:</h6>
                                                    <p class="fs-13 fw-bold mb-0"><?= htmlspecialchars($po['jaminan']) ?></p>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="col-md-6">
                                                    <h6 class="fw-semibold mb-2">Disetujui Oleh:</h6>
                                                    <p class="fs-13 text-muted mb-0"><?= !empty($po['admin_penyetuju']) ? htmlspecialchars($po['admin_penyetuju']) : '<i>Belum disetujui</i>' ?></p>
                                                    <?php if ($po['status_po'] === 'Selesai/Dibatalkan' || $po['status_po'] === 'Dibatalkan' || $po['status_po'] === 'Ada Barang Kosong'): ?>
                                                        <h6 class="fw-semibold mt-3 mb-2 text-danger">Dibatalkan Oleh:</h6>
                                                        <p class="fs-13 text-danger mb-0"><?= !empty($po['admin_pembatal']) ? htmlspecialchars($po['admin_pembatal']) : '<i>Sistem/Tidak tercatat</i>' ?></p>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <h6 class="mb-3 fw-semibold">Item Dipesan:</h6>
                                            <div class="table-responsive">
                                                <table class="table table-sm table-bordered bg-white mb-0 text-nowrap table-mobile-cards">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width:50px">Gambar</th>
                                                            <th>Item</th>
                                                            <th>Varian</th>
                                                            <th>Harga/Hari</th>
                                                            <th class="text-center">Jml</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $stmt_det = $db->prepare("
                                                            SELECT dp.*, vi.keterangan_varian, i.nama_brand, i.nama_seri, i.gambar, i.jenis_transaksi
                                                            FROM detail_po dp
                                                            JOIN varian_item vi ON dp.id_varian = vi.id_varian
                                                            JOIN item i ON vi.id_item = i.id_item
                                                            WHERE dp.id_po = ?
                                                        ");
                                                        $stmt_det->execute([$po['id_po']]);
                                                        $details = $stmt_det->fetchAll();
                                                        foreach ($details as $d):
                                                            $is_batal = ($d['status_detail'] === 'Dibatalkan');
                                                            $bg_class = $is_batal ? 'bg-danger-transparent text-muted text-decoration-line-through' : '';
                                                        ?>
                                                        <tr class="<?= $bg_class ?>">
                                                            <td data-label="Gambar">
                                                                <?php if (!empty($d['gambar'])): ?>
                                                                    <a href="javascript:void(0);" onclick="showImagePreview('../assets/img/<?= htmlspecialchars($d['gambar']) ?>')" title="Klik untuk perbesar">
                                                                        <img src="../assets/img/<?= htmlspecialchars($d['gambar']) ?>" alt="Item" width="40" height="40" class="rounded border <?= $is_batal ? 'opacity-50' : '' ?>" style="object-fit:cover; transition: transform 0.2s;" onmouseover="this.style.transform='scale(1.1)'" onmouseout="this.style.transform='scale(1)'">
                                                                    </a>
                                                                <?php else: ?>
                                                                    <div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px;font-size:10px;">NoImg</div>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td data-label="Item" class="fw-semibold">
                                                                <span class="badge <?= $d['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> fs-10 p-1 me-1"><?= htmlspecialchars($d['jenis_transaksi']) ?></span>
                                                                <span class="fw-semibold text-decoration-none"><?= htmlspecialchars($d['nama_brand'] . ' ' . $d['nama_seri']) ?></span>
                                                                <?php if($is_batal): ?>
                                                                    <br><span class="badge bg-danger mt-1 text-decoration-none" style="font-size:10px;">Batal: <?= htmlspecialchars($d['alasan_batal']) ?></span>
                                                                <?php endif; ?>
                                                            </td>
                                                            <td data-label="Varian"><?= htmlspecialchars($d['keterangan_varian']) ?></td>
                                                            <td data-label="Harga/Hari">Rp <?= number_format($d['harga_satuan_saat_pesan'], 0, ',', '.') ?></td>
                                                            <td data-label="Jml" class="text-center"><?= $d['jumlah_pesan'] ?></td>
                                                        </tr>
                                                        <?php endforeach; ?>
                                                        <tr>
                                                            <td colspan="4" class="text-end fw-bold">Total Belanja:</td>
                                                            <td colspan="1" class="fw-bold text-primary">Rp <?= number_format($po['estimasi_total_harga'], 0, ',', '.') ?></td>
                                                        </tr>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if(empty($list_po)): ?>
                            <tr><td colspan="8" class="text-center py-4 text-muted">Tidak ada data PO</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            
            <?php if ($total_pages > 1): ?>
            <div class="mt-4 d-flex justify-content-between align-items-center">
                <div class="text-muted fs-13">
                    Menampilkan <?= $offset + 1 ?> hingga <?= min($offset + $limit, $total_data) ?> dari <?= $total_data ?> data
                </div>
                <nav aria-label="Page navigation">
                    <ul class="pagination pagination-sm mb-0">
                        <?php 
                        // Base URL for pagination links
                        $page_url = "?search=".urlencode($search)."&start_date=".urlencode($start_date)."&end_date=".urlencode($end_date)."&status=".urlencode($filter_status);
                        ?>
                        
                        <li class="page-item <?= ($page <= 1) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page_url ?>&page=<?= $page - 1 ?>">Sebelumnya</a>
                        </li>
                        
                        <?php for($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= ($page == $i) ? 'active' : '' ?>">
                                <a class="page-link" href="<?= $page_url ?>&page=<?= $i ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <li class="page-item <?= ($page >= $total_pages) ? 'disabled' : '' ?>">
                            <a class="page-link" href="<?= $page_url ?>&page=<?= $page + 1 ?>">Selanjutnya</a>
                        </li>
                    </ul>
                </nav>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php 
require_once __DIR__ . '/../includes/footer.php'; 
?>

<!-- Modal Preview Gambar -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content">
      <div class="modal-header border-0 pb-0">
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body text-center pt-0">
        <img id="previewImage" src="" alt="Preview" class="img-fluid rounded" style="max-height: 80vh;">
      </div>
    </div>
  </div>
</div>

<!-- Modal Jaminan -->
<div class="modal fade" id="jaminanModal" tabindex="-1" aria-labelledby="jaminanModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="jaminanModalLabel">Pilih Jaminan Pelanggan</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <label class="form-label">Jenis Jaminan <span class="text-danger">*</span></label>
                    <select class="form-select" id="selectJaminan" required>
                        <option value="">Pilih Jaminan</option>
                        <option value="KTP">KTP</option>
                        <option value="SIM">SIM</option>
                        <option value="Kartu Pelajar">Kartu Pelajar</option>
                        <option value="Lainnya">Lainnya (Ketik Manual)</option>
                    </select>
                </div>
                <div class="mb-3" id="inputJaminanLainnya" style="display: none;">
                    <label class="form-label">Sebutkan Jaminan</label>
                    <input type="text" class="form-control" id="textJaminanLainnya" placeholder="Contoh: Paspor, STNK">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="btnSubmitJaminan">Simpan & Ubah Status</button>
            </div>
        </div>
    </div>
</div>

<script>
function showImagePreview(src) {
    document.getElementById('previewImage').src = src;
    var myModal = new bootstrap.Modal(document.getElementById('imagePreviewModal'));
    myModal.show();
}

let currentFormToSubmit = null;

document.querySelectorAll('.form-update-status').forEach(form => {
    form.addEventListener('submit', function(e) {
        const statusSelect = this.querySelector('select[name="status_po"]');
        const idPoInput = this.querySelector('input[name="id_po"]');
        
        if (statusSelect.value === 'Selesai (Barang Kembali)') {
            // Cek denda via AJAX jika belum dicek
            if (!this.querySelector('input[name="total_denda"]')) {
                e.preventDefault();
                currentFormToSubmit = this;
                
                fetch('ajax_hitung_denda.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                    body: 'id_po=' + idPoInput.value
                })
                .then(res => res.json())
                .then(data => {
                    if (data.denda > 0) {
                        Swal.fire({
                            title: 'Terdapat Denda Keterlambatan!',
                            html: `Batas Waktu Normal: <b>${data.deadline}</b><br>
                                   Batas Toleransi (3 Jam): <b>${data.toleransi}</b><br>
                                   Waktu Kembali: <b>${data.sekarang}</b><br><br>
                                   <div class='bg-danger-transparent text-danger p-2 border border-danger rounded'>
                                       Terlambat: <b>${data.hari_telat} Hari</b><br>
                                       Sewa Harian: <b>Rp ${data.sewa_harian.toLocaleString('id-ID')}</b><br>
                                       Total Denda: <b>Rp ${data.denda.toLocaleString('id-ID')}</b>
                                   </div>`,
                            icon: 'warning',
                            showCancelButton: true,
                            confirmButtonColor: '#3085d6',
                            cancelButtonColor: '#d33',
                            confirmButtonText: 'Terima Denda & Selesaikan',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                let hiddenInputDenda = document.createElement('input');
                                hiddenInputDenda.type = 'hidden';
                                hiddenInputDenda.name = 'total_denda';
                                hiddenInputDenda.value = data.denda;
                                
                                let hiddenInputHari = document.createElement('input');
                                hiddenInputHari.type = 'hidden';
                                hiddenInputHari.name = 'alasan_denda';
                                hiddenInputHari.value = 'Terlambat ' + data.hari_telat + ' Hari';
                                
                                currentFormToSubmit.appendChild(hiddenInputDenda);
                                currentFormToSubmit.appendChild(hiddenInputHari);
                                HTMLFormElement.prototype.submit.call(currentFormToSubmit);
                            }
                        });
                    } else {
                        // Tidak ada denda, lanjutkan submit
                        let hiddenInputDenda = document.createElement('input');
                        hiddenInputDenda.type = 'hidden';
                        hiddenInputDenda.name = 'total_denda';
                        hiddenInputDenda.value = 0;
                        currentFormToSubmit.appendChild(hiddenInputDenda);
                        HTMLFormElement.prototype.submit.call(currentFormToSubmit);
                    }
                })
                .catch(err => {
                    console.error(err);
                    alert("Gagal menghitung denda");
                });
                return;
            }
        }

        if (statusSelect.value === 'Barang Diambil' && !this.querySelector('input[name="jaminan"]')) {
            e.preventDefault();
            currentFormToSubmit = this;
            const modal = new bootstrap.Modal(document.getElementById('jaminanModal'));
            document.getElementById('selectJaminan').value = '';
            document.getElementById('textJaminanLainnya').value = '';
            document.getElementById('inputJaminanLainnya').style.display = 'none';
            modal.show();
        }
    });
});

document.getElementById('selectJaminan').addEventListener('change', function() {
    if (this.value === 'Lainnya') {
        document.getElementById('inputJaminanLainnya').style.display = 'block';
    } else {
        document.getElementById('inputJaminanLainnya').style.display = 'none';
    }
});

document.getElementById('btnSubmitJaminan').addEventListener('click', function() {
    const selectVal = document.getElementById('selectJaminan').value;
    const textVal = document.getElementById('textJaminanLainnya').value;
    
    let finalJaminan = selectVal;
    if (selectVal === 'Lainnya') {
        if (!textVal.trim()) {
            alert('Silakan sebutkan jaminan secara manual.');
            return;
        }
        finalJaminan = textVal.trim();
    } else if (!selectVal) {
        alert('Silakan pilih jaminan.');
        return;
    }

    if (currentFormToSubmit) {
        let hiddenInput = document.createElement('input');
        hiddenInput.type = 'hidden';
        hiddenInput.name = 'jaminan';
        hiddenInput.value = finalJaminan;
        currentFormToSubmit.appendChild(hiddenInput);
        
        // Allow form to submit by bypassing the submit event listener
        HTMLFormElement.prototype.submit.call(currentFormToSubmit);
    }
});

// Real-time search with debounce
document.addEventListener('DOMContentLoaded', function() {
    const searchInput = document.getElementById('searchPO');
    const filterForm = document.getElementById('filterForm');
    
    if (searchInput && filterForm) {
        // Set cursor to the end of input
        if (searchInput.value) {
            searchInput.focus();
            let val = searchInput.value;
            searchInput.value = '';
            searchInput.value = val;
        }

        let typingTimer;
        searchInput.addEventListener('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() {
                filterForm.submit();
            }, 600); // Wait 600ms after user stops typing
        });
    }
});

function konfirmasiHapus(id) {
    Swal.fire({
        title: 'Yakin ingin menghapus PO ini?',
        text: "Data yang dihapus tidak dapat dikembalikan!",
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            window.location.href = 'hapus_po.php?id=' + id;
        }
    });
}
// Skrip AJAX Polling untuk Real-time Updates
setInterval(() => {
    fetch(window.location.href)
    .then(response => response.text())
    .then(html => {
        let parser = new DOMParser();
        let doc = parser.parseFromString(html, 'text/html');
        let newContainer = doc.getElementById('kelola-po-container');
        let oldContainer = document.getElementById('kelola-po-container');
        
        if (newContainer && oldContainer) {
            // Simpan status collapse yang sedang terbuka
            let openCollapses = [];
            oldContainer.querySelectorAll('.collapse.show').forEach(col => {
                openCollapses.push(col.id);
            });
            
            // Ganti isi HTML dengan yang terbaru
            oldContainer.innerHTML = newContainer.innerHTML;
            
            // Kembalikan status collapse yang terbuka
            openCollapses.forEach(id => {
                let col = document.getElementById(id);
                if(col) {
                    col.classList.add('show');
                }
            });
        }
    })
    .catch(err => console.error('Gagal mengambil pembaruan:', err));
}, 3000);
</script>
