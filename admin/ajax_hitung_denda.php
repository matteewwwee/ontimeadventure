<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id_po'])) {
    $id_po = (int)$_POST['id_po'];

    try {
        $stmt = $db->prepare("SELECT tgl_selesai_sewa, waktu_diambil FROM pengajuan_po WHERE id_po = ?");
        $stmt->execute([$id_po]);
        $po = $stmt->fetch();

        if (!$po || empty($po['waktu_diambil'])) {
            echo json_encode(['denda' => 0, 'hari_telat' => 0, 'pesan' => 'Barang belum diambil.']);
            exit;
        }

        $stmt_harga = $db->prepare("
            SELECT SUM(harga_satuan_saat_pesan * jumlah_pesan) as sewa_harian 
            FROM detail_po 
            WHERE id_po = ? AND (status_detail IS NULL OR status_detail != 'Dibatalkan')
        ");
        $stmt_harga->execute([$id_po]);
        $sewa_harian = (int)$stmt_harga->fetchColumn();

        $jam_diambil = date('H:i:s', strtotime($po['waktu_diambil']));
        $tgl_selesai = $po['tgl_selesai_sewa'];
        
        $deadline_str = $tgl_selesai . ' ' . $jam_diambil;
        $deadline_time = strtotime($deadline_str);
        
        $toleransi_time = strtotime('+3 hours', $deadline_time);
        
        $waktu_sekarang = time();
        $hari_telat = 0;
        $total_denda = 0;

        if ($waktu_sekarang > $toleransi_time) {
            $selisih_detik = $waktu_sekarang - $deadline_time;
            $hari_telat = ceil($selisih_detik / (24 * 60 * 60));
            $total_denda = $hari_telat * $sewa_harian;
        }

        echo json_encode([
            'denda' => $total_denda,
            'hari_telat' => $hari_telat,
            'sewa_harian' => $sewa_harian,
            'deadline' => date('d M Y H:i', $deadline_time),
            'toleransi' => date('d M Y H:i', $toleransi_time),
            'sekarang' => date('d M Y H:i', $waktu_sekarang)
        ]);

    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
}
