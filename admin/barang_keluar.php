<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

// Ambil semua data barang keluar
$stmt = $db->query("
    SELECT dp.id_po, i.nama_brand, i.nama_seri, i.gambar, v.keterangan_varian, dp.jumlah_pesan, p.tgl_mulai_sewa, p.tgl_selesai_sewa, u.nama 
    FROM detail_po dp
    JOIN varian_item v ON dp.id_varian = v.id_varian
    JOIN item i ON v.id_item = i.id_item
    JOIN pengajuan_po p ON dp.id_po = p.id_po
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status_po = 'Barang Diambil'
    ORDER BY p.tgl_selesai_sewa ASC
");
$barang_keluar = $stmt->fetchAll();

$pageTitle = 'Barang Keluar (Sedang Disewa)';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-semibold">Barang Keluar (Sedang Disewa)</h4>
    </div>

    <div class="card custom-card">
        <div class="card-body p-0">
            <div class="table-responsive p-3">
                <table id="barangTable" class="table text-nowrap table-bordered table-hover mb-0 w-100">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center">No</th>
                            <th>PO ID</th>
                            <th>Nama Alat</th>
                            <th>Penyewa</th>
                            <th class="text-center">Jumlah</th>
                            <th>Tgl Mulai</th>
                            <th>Tgl Selesai (Kembali)</th>
                            <th class="text-center">Sisa Waktu</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $today = new DateTime();
                        foreach ($barang_keluar as $bk): 
                            $tgl_kembali = new DateTime($bk['tgl_selesai_sewa']);
                            $interval = $today->diff($tgl_kembali);
                            $sisa_hari = (int)$interval->format('%R%a');
                            
                            $sisa_badge = 'bg-success-transparent text-success';
                            $sisa_text = $sisa_hari . ' hari lagi';
                            
                            if ($sisa_hari == 0) {
                                $sisa_badge = 'bg-warning-transparent text-warning';
                                $sisa_text = 'Hari ini';
                            } elseif ($sisa_hari < 0) {
                                $sisa_badge = 'bg-danger-transparent text-danger';
                                $sisa_text = 'Terlambat ' . abs($sisa_hari) . ' hari';
                            }
                        ?>
                            <tr>
                                <td class="text-center fw-semibold"></td>
                                <td class="fw-semibold"><a href="kelola_po.php?search=PO-<?= str_pad($bk['id_po'], 4, '0', STR_PAD_LEFT) ?>">PO-<?= str_pad($bk['id_po'], 4, '0', STR_PAD_LEFT) ?></a></td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-md me-3 bg-light text-muted border rounded">
                                            <?php if(!empty($bk['gambar']) && file_exists(__DIR__.'/../assets/img/'.$bk['gambar'])): ?>
                                                <img src="<?= $base_url ?>assets/img/<?= htmlspecialchars($bk['gambar']) ?>" alt="img" style="object-fit: cover; width:100%; height:100%; border-radius:inherit; cursor: pointer;" onclick="showGlobalPreview(this.src)">
                                            <?php else: ?>
                                                <i class="ri-image-line fs-18"></i>
                                            <?php endif; ?>
                                        </span>
                                        <div>
                                            <div class="fw-semibold text-dark">
                                                <?= htmlspecialchars($bk['nama_brand'] . ' ' . $bk['nama_seri']) ?>
                                            </div>
                                            <span class="d-block fs-11 text-muted"><?= htmlspecialchars($bk['keterangan_varian']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td><?= htmlspecialchars($bk['nama']) ?></td>
                                <td class="text-center fw-semibold"><?= $bk['jumlah_pesan'] ?></td>
                                <td><?= date('d M Y', strtotime($bk['tgl_mulai_sewa'])) ?></td>
                                <td><span class="fw-semibold"><?= date('d M Y', strtotime($bk['tgl_selesai_sewa'])) ?></span></td>
                                <td class="text-center"><span class="badge <?= $sisa_badge ?>"><?= $sisa_text ?></span></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- DataTables CSS & JS -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
<link href="https://cdn.datatables.net/1.13.6/css/dataTables.bootstrap5.min.css" rel="stylesheet">
<style>
/* Responsive DataTables Controls */
@media (max-width: 767.98px) {
    div.dataTables_wrapper div.dataTables_length,
    div.dataTables_wrapper div.dataTables_filter,
    div.dataTables_wrapper div.dataTables_info,
    div.dataTables_wrapper div.dataTables_paginate {
        text-align: center !important;
        margin-bottom: 15px;
    }
    div.dataTables_wrapper div.dataTables_filter input {
        width: 100%;
        margin-left: 0;
        margin-top: 5px;
        display: block;
    }
    div.dataTables_wrapper div.dataTables_length select {
        width: auto;
        display: inline-block;
    }
    .dataTables_wrapper .row {
        margin-left: 0;
        margin-right: 0;
    }
}
</style>

<script>
$(document).ready(function() {
    let t = $('#barangTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.13.6/i18n/id.json"
        },
        "order": [[ 6, "asc" ]], // Urutkan berdasarkan tanggal kembali secara default
        "columnDefs": [
            { "searchable": false, "orderable": false, "targets": 0 }
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
