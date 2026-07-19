<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

// Ambil semua data barang keluar
$stmt = $db->query("
    SELECT dp.id_po, i.nama_brand, i.nama_seri, i.gambar, v.keterangan_varian, dp.jumlah_pesan, p.tgl_mulai_sewa, p.tgl_selesai_sewa, u.nama, p.status_po, p.waktu_diambil 
    FROM detail_po dp
    JOIN varian_item v ON dp.id_varian = v.id_varian
    JOIN item i ON v.id_item = i.id_item
    JOIN pengajuan_po p ON dp.id_po = p.id_po
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status_po IN ('Barang Diambil', 'Selesai (Barang Belum Kembali)')
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
                            
                            $live_telat = 0;
                            $toleransi_str = '';
                            if (!empty($bk['waktu_diambil'])) {
                                $jam_diambil = date('H:i:s', strtotime($bk['waktu_diambil']));
                                $deadline_time = strtotime($bk['tgl_selesai_sewa'] . ' ' . $jam_diambil);
                                $toleransi_time = strtotime('+3 hours', $deadline_time);
                                $toleransi_str = date('d M Y, H:i', $toleransi_time);
                                $waktu_sekarang = time();
                                
                                if ($waktu_sekarang > $toleransi_time) {
                                    $selisih_detik = $waktu_sekarang - $deadline_time;
                                    $live_telat = ceil($selisih_detik / (24 * 60 * 60));
                                }
                            }
                            
                            if ($live_telat > 0) {
                                $sisa_badge = 'bg-danger-transparent text-danger fw-bold';
                                $sisa_text = 'Telat ' . $live_telat . ' Hari';
                            } elseif ($sisa_hari == 0) {
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
                                <td>
                                    <span class="fw-semibold"><?= date('d M Y', strtotime($bk['tgl_selesai_sewa'])) ?></span>
                                    <?php if ($toleransi_str !== ''): ?>
                                        <div class="text-danger mt-1" style="font-size: 10px;" title="Batas Toleransi (+3 Jam)"><i class="ri-time-line"></i> Tenggat: <?= $toleransi_str ?></div>
                                    <?php endif; ?>
                                </td>
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
    
    /* Mobile Card View for Table */
    #barangTable, #barangTable tbody, #barangTable tr, #barangTable td {
        display: block;
    }
    #barangTable thead {
        display: none;
    }
    #barangTable tr {
        margin-bottom: 1rem;
        border: 1px solid #e0e0e0 !important;
        border-radius: 0.5rem;
        box-shadow: 0 0.125rem 0.25rem rgba(0,0,0,0.075);
        padding: 0.5rem;
        background-color: #fff;
    }
    #barangTable td {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.75rem 0.5rem !important;
        border: none !important;
        border-bottom: 1px solid #f1f1f1 !important;
        text-align: right;
    }
    #barangTable td:last-child {
        border-bottom: none !important;
    }
    
    /* Labels for mobile view */
    #barangTable td::before {
        font-weight: 600;
        margin-right: 1rem;
        text-align: left;
        color: #475569;
    }
    #barangTable td:nth-child(1)::before { content: "No"; }
    #barangTable td:nth-child(2)::before { content: "PO ID"; }
    
    /* Special handling for Nama Alat */
    #barangTable td:nth-child(3) { 
        flex-direction: column; 
        align-items: flex-start; 
        text-align: left;
        background-color: #f8fafc;
        border-radius: 0.375rem;
        margin: 0.5rem 0;
    }
    #barangTable td:nth-child(3)::before { 
        content: "Nama Alat"; 
        margin-bottom: 0.75rem;
        width: 100%;
        border-bottom: 1px dashed #cbd5e1;
        padding-bottom: 0.25rem;
    }
    
    #barangTable td:nth-child(4)::before { content: "Penyewa"; }
    #barangTable td:nth-child(5)::before { content: "Jumlah"; }
    #barangTable td:nth-child(6)::before { content: "Tgl Mulai"; }
    #barangTable td:nth-child(7)::before { content: "Tgl Kembali"; }
    #barangTable td:nth-child(8)::before { content: "Sisa Waktu"; }
    
    /* Fix empty table state */
    #barangTable td.dataTables_empty {
        justify-content: center !important;
        text-align: center !important;
        padding: 2rem !important;
        color: #64748b;
    }
    #barangTable td.dataTables_empty::before {
        display: none !important;
        content: none !important;
    }
    
    .table-responsive { border: none !important; margin: 0 !important; padding: 0 !important; }
    .card-body.p-0 > .table-responsive { padding: 0.5rem !important; }
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
