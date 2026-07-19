<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Pastikan hanya admin yang bisa akses
requireAdmin();

$db = getDB();

// Queries for statistics
$total_user = $db->query("SELECT COUNT(*) FROM users WHERE role = 'pelanggan'")->fetchColumn();
$total_item = $db->query("SELECT COUNT(*) FROM item")->fetchColumn();
$total_kategori = $db->query("SELECT COUNT(*) FROM kategori_item")->fetchColumn();
$total_po = $db->query("SELECT COUNT(*) FROM pengajuan_po")->fetchColumn();

$menunggu = $db->query("SELECT COUNT(*) FROM pengajuan_po WHERE status_po = 'Menunggu Pengecekan'")->fetchColumn();
$siap = $db->query("SELECT COUNT(*) FROM pengajuan_po WHERE status_po = 'Barang Siap'")->fetchColumn();
$kosong = $db->query("SELECT COUNT(*) FROM pengajuan_po WHERE status_po = 'Ada Barang Kosong'")->fetchColumn();

// Recent PO
$stmt = $db->query("
    SELECT p.*, u.nama, u.no_hp 
    FROM pengajuan_po p 
    JOIN users u ON p.id_user = u.id_user 
    ORDER BY p.tgl_pengajuan DESC LIMIT 5
");
$recent_po = $stmt->fetchAll();

// Barang Keluar (Sedang Disewa)
$stmt = $db->query("
    SELECT dp.id_po, i.nama_brand, i.nama_seri, i.gambar, v.keterangan_varian, dp.jumlah_pesan, p.tgl_mulai_sewa, p.tgl_selesai_sewa, u.nama, p.status_po, p.waktu_diambil 
    FROM detail_po dp
    JOIN varian_item v ON dp.id_varian = v.id_varian
    JOIN item i ON v.id_item = i.id_item
    JOIN pengajuan_po p ON dp.id_po = p.id_po
    JOIN users u ON p.id_user = u.id_user
    WHERE p.status_po IN ('Barang Diambil', 'Selesai (Barang Belum Kembali)')
    ORDER BY p.tgl_selesai_sewa ASC
    LIMIT 5
");
$barang_keluar = $stmt->fetchAll();

// Fungsi untuk menentukan class badge status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Menunggu Pengecekan': return 'bg-warning-transparent text-warning';
        case 'Barang Siap': return 'bg-info-transparent text-info';
        case 'Ada Barang Kosong': return 'bg-danger-transparent text-danger';
        case 'Selesai': return 'bg-success-transparent text-success';
        case 'Dibatalkan': return 'bg-secondary-transparent text-secondary';
        default: return 'bg-light text-dark';
    }
}

$pageTitle = 'Dashboard Admin';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-semibold">Dashboard Admin</h4>
    </div>

    <!-- Stats Grid -->
    <div class="row">
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block text-muted fs-13 mb-1">Total Pelanggan</span>
                            <h4 class="fw-semibold mb-0"><?= $total_user ?></h4>
                        </div>
                        <div class="avatar avatar-lg avatar-rounded bg-primary-transparent">
                            <i class="ri-user-line fs-24"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block text-muted fs-13 mb-1">Total Item</span>
                            <h4 class="fw-semibold mb-0"><?= $total_item ?></h4>
                        </div>
                        <div class="avatar avatar-lg avatar-rounded bg-success-transparent">
                            <i class="ri-box-3-line fs-24"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="card custom-card">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block text-muted fs-13 mb-1">Total PO</span>
                            <h4 class="fw-semibold mb-0"><?= $total_po ?></h4>
                        </div>
                        <div class="avatar avatar-lg avatar-rounded bg-warning-transparent">
                            <i class="ri-file-list-3-line fs-24"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-lg-6 col-md-6 col-sm-6">
            <div class="card custom-card border-top border-3 border-warning">
                <div class="card-body">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="d-block text-muted fs-13 mb-1">Menunggu Cek</span>
                            <h4 class="fw-semibold text-warning mb-0"><?= $menunggu ?></h4>
                        </div>
                        <div class="avatar avatar-lg avatar-rounded bg-danger-transparent">
                            <i class="ri-error-warning-line fs-24 text-warning"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent PO Table -->
    <div class="row mt-3">
        <div class="col-xl-12">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">Daftar Barang Keluar (Sedang Disewa)</div>
                    <a href="barang_keluar.php" class="btn btn-sm btn-primary-light btn-wave">Lihat Semua</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-bordered mb-0 table-mobile-cards">
                            <thead class="table-light">
                                <tr>
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
                                <?php if (empty($barang_keluar)): ?>
                                    <tr><td colspan="7" class="text-center text-muted py-4">Tidak ada barang yang sedang disewa (keluar).</td></tr>
                                <?php else: ?>
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
                                            <td data-label="PO ID" class="fw-semibold"><a href="kelola_po.php?search=PO-<?= str_pad($bk['id_po'], 4, '0', STR_PAD_LEFT) ?>">PO-<?= str_pad($bk['id_po'], 4, '0', STR_PAD_LEFT) ?></a></td>
                                            <td data-label="Nama Alat">
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
                                            <td data-label="Penyewa"><?= htmlspecialchars($bk['nama']) ?></td>
                                            <td data-label="Jumlah" class="text-center fw-semibold"><?= $bk['jumlah_pesan'] ?></td>
                                            <td data-label="Tgl Mulai"><?= date('d M Y', strtotime($bk['tgl_mulai_sewa'])) ?></td>
                                            <td data-label="Tgl Selesai (Kembali)">
                                                <span class="fw-semibold"><?= date('d M Y', strtotime($bk['tgl_selesai_sewa'])) ?></span>
                                                <?php if ($toleransi_str !== ''): ?>
                                                    <div class="text-danger mt-1" style="font-size: 10px;" title="Batas Toleransi (+3 Jam)"><i class="ri-time-line"></i> Tenggat: <?= $toleransi_str ?></div>
                                                <?php endif; ?>
                                            </td>
                                            <td data-label="Sisa Waktu" class="text-center"><span class="badge <?= $sisa_badge ?>"><?= $sisa_text ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-12 mt-3">
            <div class="card custom-card">
                <div class="card-header justify-content-between">
                    <div class="card-title">Pre-Order Terbaru</div>
                    <a href="kelola_po.php" class="btn btn-sm btn-primary-light btn-wave">Lihat Semua PO</a>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table text-nowrap table-bordered mb-0 table-mobile-cards">
                            <thead class="table-light">
                                <tr>
                                    <th>PO ID</th>
                                    <th>Pelanggan</th>
                                    <th>Tanggal Pengajuan</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($recent_po)): ?>
                                    <tr><td colspan="5" class="text-center text-muted py-4">Belum ada data PO</td></tr>
                                <?php else: ?>
                                    <?php foreach ($recent_po as $po): ?>
                                        <tr>
                                            <td data-label="PO ID" class="fw-semibold">PO-<?= str_pad($po['id_po'], 4, '0', STR_PAD_LEFT) ?></td>
                                            <td data-label="Pelanggan">
                                                <div class="d-flex align-items-center fw-semibold">
                                                    <?= htmlspecialchars($po['nama']) ?>
                                                </div>
                                                <span class="d-block fs-11 text-muted"><?= htmlspecialchars($po['no_hp']) ?></span>
                                            </td>
                                            <td data-label="Tanggal Pengajuan"><?= date('d M Y H:i', strtotime($po['tgl_pengajuan'])) ?></td>
                                            <td data-label="Total" class="text-primary fw-semibold">Rp <?= number_format($po['estimasi_total_harga'], 0, ',', '.') ?></td>
                                            <td data-label="Status"><span class="badge <?= getStatusBadgeClass($po['status_po']) ?>"><?= htmlspecialchars($po['status_po']) ?></span></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
