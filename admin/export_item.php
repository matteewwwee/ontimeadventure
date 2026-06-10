<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

// Query all items and their variants
$query = "
    SELECT 
        i.id_item, i.nama_brand, i.nama_seri, i.jenis_transaksi, i.status_item,
        k.nama_kategori,
        v.keterangan_varian, v.harga_sewa_per_hari, v.stok_tersedia, v.catatan_kondisi
    FROM item i
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    LEFT JOIN varian_item v ON i.id_item = v.id_item
    ORDER BY k.nama_kategori ASC, i.nama_brand ASC, i.nama_seri ASC, v.harga_sewa_per_hari ASC
";

$stmt = $db->query($query);
$raw_data = $stmt->fetchAll();

// Group data by item
$items = [];
foreach ($raw_data as $row) {
    $id = $row['id_item'];
    if (!isset($items[$id])) {
        $items[$id] = [
            'brand' => $row['nama_brand'],
            'seri' => $row['nama_seri'],
            'kategori' => $row['nama_kategori'],
            'transaksi' => $row['jenis_transaksi'],
            'status' => $row['status_item'],
            'varians' => []
        ];
    }
    if (!empty($row['keterangan_varian'])) {
        $items[$id]['varians'][] = [
            'keterangan' => $row['keterangan_varian'],
            'harga' => $row['harga_sewa_per_hari'],
            'stok' => $row['stok_tersedia'],
            'kondisi' => $row['catatan_kondisi']
        ];
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <title>Daftar Inventaris Barang - On Time Adventure</title>
    <style>
        @page {
            size: A4 portrait;
            margin: 15mm;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            font-size: 11px;
            color: #333;
        }
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            border-bottom: 2px solid #2c3e50;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .header-title {
            text-align: right;
        }
        .header h1 {
            margin: 0 0 5px 0;
            color: #2c3e50;
            font-size: 20px;
            text-transform: uppercase;
        }
        .header p {
            margin: 0;
            color: #7f8c8d;
            font-size: 12px;
        }
        .logo {
            font-size: 24px;
            font-weight: 900;
            color: #e74c3c;
            letter-spacing: -1px;
        }
        .logo span {
            color: #2c3e50;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        th, td {
            border: 1px solid #bdc3c7;
            padding: 8px;
            vertical-align: top;
        }
        th {
            background-color: #ecf0f1;
            color: #2c3e50;
            font-weight: 600;
            text-align: left;
        }
        .text-center { text-align: center; }
        .text-right { text-align: right; }
        .badge {
            padding: 3px 6px;
            border-radius: 3px;
            font-size: 9px;
            font-weight: bold;
            display: inline-block;
        }
        .bg-success { background-color: #d4edda; color: #155724; }
        .bg-danger { background-color: #f8d7da; color: #721c24; }
        .bg-primary { background-color: #cce5ff; color: #004085; }
        .bg-secondary { background-color: #e2e3e5; color: #383d41; }
        
        .varian-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 5px;
            font-size: 10px;
        }
        .varian-table th, .varian-table td {
            border: 1px solid #eee;
            padding: 4px;
        }
        .varian-table th {
            background-color: #fafafa;
            font-weight: normal;
            color: #666;
        }
        
        @media print {
            body { -webkit-print-color-adjust: exact; print-color-adjust: exact; }
            .no-print { display: none !important; }
        }
        
        .footer {
            margin-top: 30px;
            text-align: right;
            font-size: 10px;
            color: #7f8c8d;
        }
    </style>
</head>
<body onload="window.print()">
    <div class="no-print" style="text-align: center; margin-bottom: 20px; padding: 15px; background: #fff3cd; border: 1px solid #ffeeba; border-radius: 5px;">
        <p style="margin: 0; color: #856404; font-size: 13px;">Gunakan fitur <b>Simpan sebagai PDF (Save as PDF)</b> pada dialog Print untuk mengekspor data ini.</p>
        <button onclick="window.print()" style="margin-top: 12px; padding: 8px 20px; cursor: pointer; background: #e74c3c; color: white; border: none; border-radius: 4px; font-weight: bold; font-size: 13px;">Cetak Ulang PDF</button>
    </div>

    <div class="header">
        <div class="logo">ON TIME <span>ADVENTURE</span></div>
        <div class="header-title">
            <h1>Daftar Inventaris Barang</h1>
            <p>Dicetak pada: <?= date('d/m/Y H:i:s') ?></p>
        </div>
    </div>

    <table>
        <thead>
            <tr>
                <th class="text-center" style="width: 30px;">No</th>
                <th style="width: 120px;">Kategori</th>
                <th>Item & Detail Varian</th>
                <th class="text-center" style="width: 90px;">Status</th>
            </tr>
        </thead>
        <tbody>
            <?php 
            $no = 1;
            $total_stok_keseluruhan = 0;
            foreach ($items as $item): 
                $status_class = $item['status'] === 'Aktif' ? 'bg-success' : 'bg-secondary';
                $transaksi_class = $item['transaksi'] === 'Sewa' ? 'bg-primary' : 'bg-danger';
            ?>
            <tr>
                <td class="text-center"><?= $no++ ?></td>
                <td><?= htmlspecialchars($item['kategori']) ?></td>
                <td>
                    <div style="margin-bottom: 8px; font-size: 12px;">
                        <strong><?= htmlspecialchars($item['brand']) ?></strong> - <?= htmlspecialchars($item['seri']) ?>
                    </div>
                    <?php if (empty($item['varians'])): ?>
                        <div style="padding: 4px 8px; color: #e74c3c; font-style: italic; background: #fdf2f2; border-radius: 3px; display: inline-block;">Belum ada varian</div>
                    <?php else: ?>
                        <table class="varian-table" style="margin: 0; width: 90%;">
                            <tr>
                                <th>Keterangan</th>
                                <th class="text-right">Harga/Hari</th>
                                <th class="text-center" style="width: 40px;">Stok</th>
                                <th>Kondisi</th>
                            </tr>
                            <?php foreach ($item['varians'] as $v): 
                                $total_stok_keseluruhan += $v['stok'];
                            ?>
                            <tr>
                                <td><?= htmlspecialchars($v['keterangan']) ?></td>
                                <td class="text-right">Rp <?= number_format($v['harga'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <?php if($v['stok'] > 0): ?>
                                        <?= $v['stok'] ?>
                                    <?php else: ?>
                                        <span style="color: #e74c3c;">Habis</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($v['kondisi']) ?: '-' ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </table>
                    <?php endif; ?>
                </td>
                <td class="text-center">
                    <span class="badge <?= $status_class ?> mb-1" style="margin-bottom: 4px;"><?= htmlspecialchars($item['status']) ?></span><br>
                    <span class="badge <?= $transaksi_class ?>"><?= htmlspecialchars($item['transaksi']) ?></span>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <div class="footer">
        <p style="margin: 0;"><strong>Total Item:</strong> <?= count($items) ?> item | <strong>Total Stok Fisik:</strong> <?= $total_stok_keseluruhan ?> unit</p>
        <p style="margin: 5px 0 0 0;">Dicetak otomatis oleh Sistem Informasi Katalog & PO On Time Adventure</p>
    </div>
</body>
</html>
