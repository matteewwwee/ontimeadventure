<?php
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_GET['id']) || !ctype_digit($_GET['id'])) {
    echo json_encode(['error' => 'Invalid ID']);
    exit;
}
$item_id = (int) $_GET['id'];

try {
    $db = getDB();
    
    // First check if item is Sewa
    $stmt_item = $db->prepare("SELECT jenis_transaksi FROM item WHERE id_item = ?");
    $stmt_item->execute([$item_id]);
    $item = $stmt_item->fetch();
    
    if (!$item || $item['jenis_transaksi'] !== 'Sewa') {
        echo json_encode(['data' => []]);
        exit;
    }

    $stmt_booked = $db->prepare("
        SELECT d.id_varian, p.tgl_mulai_sewa, p.tgl_selesai_sewa, d.jumlah_pesan, p.status_po 
        FROM detail_po d
        JOIN pengajuan_po p ON d.id_po = p.id_po
        WHERE p.status_po NOT IN ('Dibatalkan', 'Selesai (Barang Kembali)', 'Selesai/Dibatalkan')
          AND p.tgl_selesai_sewa >= CURDATE()
          AND d.id_varian IN (SELECT id_varian FROM varian_item WHERE id_item = :id)
        ORDER BY p.tgl_mulai_sewa ASC
    ");
    $stmt_booked->execute([':id' => $item_id]);
    $booked_data = $stmt_booked->fetchAll(PDO::FETCH_ASSOC);
    
    $booked_dates = [];
    foreach ($booked_data as $b) {
        $id_v = $b['id_varian'];
        if (!isset($booked_dates[$id_v])) {
            $booked_dates[$id_v] = [];
        }
        
        $is_pending = ($b['status_po'] === 'Menunggu Pengecekan'); 
        $booked_dates[$id_v][] = [
            'tgl_mulai' => date('d M Y', strtotime($b['tgl_mulai_sewa'])),
            'tgl_selesai' => date('d M Y', strtotime($b['tgl_selesai_sewa'])),
            'jumlah' => $b['jumlah_pesan'],
            'is_pending' => $is_pending
        ];
    }
    
    echo json_encode(['data' => $booked_dates]);
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
