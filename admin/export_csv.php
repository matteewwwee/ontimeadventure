<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

$db = getDB();

// Fetch all items and variants
$stmt = $db->query("
    SELECT 
        k.nama_kategori,
        i.nama_brand, i.nama_seri, i.deskripsi_umum, i.jenis_transaksi, i.status_item,
        v.keterangan_varian, v.harga_sewa_per_hari, v.stok_tersedia, v.catatan_kondisi
    FROM item i
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    LEFT JOIN varian_item v ON i.id_item = v.id_item
    ORDER BY k.nama_kategori ASC, i.nama_brand ASC, i.nama_seri ASC
");

$filename = "Export_Katalog_OTA_" . date('Y-m-d_H-i') . ".csv";

// Set header for CSV download
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename=' . $filename);

$output = fopen('php://output', 'w');

// Set UTF-8 BOM so Excel opens it correctly with UTF-8 encoding
fputs($output, $bom =(chr(0xEF) . chr(0xBB) . chr(0xBF)));

// Output the column headings
fputcsv($output, [
    'Kategori', 
    'Brand', 
    'Seri', 
    'Deskripsi Umum', 
    'Jenis Transaksi (Sewa/Jual)', 
    'Status Item (Aktif/Arsip)', 
    'Varian/Ukuran', 
    'Harga Sewa/Hari', 
    'Stok', 
    'Catatan Kondisi'
]);

// Output the data rows
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    fputcsv($output, [
        $row['nama_kategori'],
        $row['nama_brand'],
        $row['nama_seri'],
        $row['deskripsi_umum'],
        $row['jenis_transaksi'],
        $row['status_item'],
        $row['keterangan_varian'],
        $row['harga_sewa_per_hari'],
        $row['stok_tersedia'],
        $row['catatan_kondisi']
    ]);
}

fclose($output);
exit();
