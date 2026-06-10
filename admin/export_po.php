<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

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

$query = "
    SELECT p.id_po, u.nama, u.no_hp, p.tgl_pengajuan, p.tgl_mulai_sewa, p.tgl_selesai_sewa, p.estimasi_total_harga, p.status_po, p.waktu_diambil, p.waktu_kembali
    FROM pengajuan_po p 
    JOIN users u ON p.id_user = u.id_user 
    $where
    ORDER BY p.tgl_pengajuan DESC
";

$stmt = $db->prepare($query);
$stmt->execute($params);
$list_po = $stmt->fetchAll();

$protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://';
$host = $_SERVER['HTTP_HOST'];
$base_img_url = $protocol . $host . '/ontimeadventure/assets/img/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Cetak Laporan Penyewaan</title>
    <style>
        @page {
            size: A4 landscape;
            margin: 10mm;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: center;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header img {
            height: 60px;
            width: auto;
            margin-right: 20px;
        }
        .header-text {
            text-align: center;
        }
        .header-text h2 {
            margin: 0;
            font-size: 22px;
            color: #2c3e50;
            letter-spacing: 1px;
        }
        .header-text p {
            margin: 5px 0 0 0;
            font-size: 12px;
            color: #555;
        }
        .report-title {
            text-align: center;
            font-size: 16px;
            font-weight: bold;
            margin-bottom: 5px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .print-date {
            text-align: center;
            font-size: 11px;
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        th, td {
            border-bottom: 1px solid #e0e0e0;
            padding: 10px 8px;
            vertical-align: top;
        }
        th {
            background-color: #f8f9fa;
            color: #2c3e50;
            text-align: center;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 10px;
            letter-spacing: 0.5px;
            border-top: 2px solid #2c3e50;
            border-bottom: 2px solid #e0e0e0 !important;
        }
        tbody tr:nth-child(even) {
            background-color: #fafafa;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .status { font-weight: 600; color: #e67e22; }
        
        @media print {
            .no-print { display: none; }
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align: right; margin-bottom: 20px;">
        <button onclick="window.print()" style="padding: 10px 20px; background-color: #2c3e50; color: white; border: none; border-radius: 5px; cursor: pointer; font-weight:bold;">🖨️ Cetak / Simpan PDF</button>
    </div>

    <div class="header">
        <img src="<?= $protocol . $host . '/ontimeadventure/' ?>logo-ontimeadventure.png" alt="Logo On Time Adventure">
        <div class="header-text">
            <h2>ON TIME ADVENTURE</h2>
            <p>JL Bangkala, Blok D No. 68A, Bumi Tamalanrea Permai ( BTP, Tamalanrea, Kec. Tamalanrea, Kota Makassar, Sulawesi Selatan 90245</p>
        </div>
    </div>

    <div class="report-title">LAPORAN PENYEWAAN ALAT</div>
    <div class="print-date">Tanggal Cetak: <?= date('d F Y H:i') ?></div>

    <table class="table">
        <thead>
            <tr>
                <th>PO ID</th>
                <th>Nama Pelanggan</th>
                <th>No HP</th>
                <th>Tgl Pengajuan</th>
                <th>Tgl Mulai Sewa</th>
                <th>Tgl Selesai Sewa</th>
                <th>Detail Pesanan</th>
                <th>Total Harga (Rp)</th>
                <th>Status</th>
                <th>Waktu Diambil</th>
                <th>Waktu Kembali</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($list_po as $po): 
                $po_id = "PO-" . str_pad($po['id_po'], 5, '0', STR_PAD_LEFT);
                
                $stmtItems = $db->prepare("
                    SELECT d.jumlah_pesan, v.keterangan_varian, i.nama_brand, i.nama_seri
                    FROM detail_po d
                    JOIN varian_item v ON d.id_varian = v.id_varian
                    JOIN item i ON v.id_item = i.id_item
                    WHERE d.id_po = ?
                ");
                $stmtItems->execute([$po['id_po']]);
                $items = $stmtItems->fetchAll();
                
                $item_texts = [];
                foreach ($items as $itm) {
                    $item_texts[] = "- " . $itm['nama_brand'] . ' ' . $itm['nama_seri'] . ' (' . $itm['keterangan_varian'] . ') x' . $itm['jumlah_pesan'];
                }
                
                $waktu_ambil = !empty($po['waktu_diambil']) ? date('d/m/Y H:i', strtotime($po['waktu_diambil'])) : '-';
                $waktu_kembali = !empty($po['waktu_kembali']) ? date('d/m/Y H:i', strtotime($po['waktu_kembali'])) : '-';
            ?>
            <tr>
                <td class="text-center"><b><?= $po_id ?></b></td>
                <td><?= htmlspecialchars($po['nama']) ?></td>
                <td><?= htmlspecialchars($po['no_hp']) ?></td>
                <td class="text-center"><?= date('d/m/Y H:i', strtotime($po['tgl_pengajuan'])) ?></td>
                <td class="text-center"><?= date('d/m/Y', strtotime($po['tgl_mulai_sewa'])) ?></td>
                <td class="text-center"><?= date('d/m/Y', strtotime($po['tgl_selesai_sewa'])) ?></td>
                <td><?= implode("<br>", $item_texts) ?></td>
                <td class="text-right">Rp <?= number_format($po['estimasi_total_harga'], 0, ',', '.') ?></td>
                <td class="text-center status"><?= htmlspecialchars($po['status_po']) ?></td>
                <td class="text-center"><?= $waktu_ambil ?></td>
                <td class="text-center"><?= $waktu_kembali ?></td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
