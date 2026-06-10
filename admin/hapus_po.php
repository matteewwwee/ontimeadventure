<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

// Pastikan hanya admin yang bisa mengakses
requireAdmin();

$db = getDB();

if (isset($_GET['id'])) {
    $id_po = (int)$_GET['id'];
    
    try {
        $db->beginTransaction();
        
        // Hapus detail_po terlebih dahulu (Foreign Key)
        $stmtDetail = $db->prepare("DELETE FROM detail_po WHERE id_po = ?");
        $stmtDetail->execute([$id_po]);
        
        // Hapus data utama pengajuan_po
        $stmtPO = $db->prepare("DELETE FROM pengajuan_po WHERE id_po = ?");
        $stmtPO->execute([$id_po]);
        
        $db->commit();
        header('Location: kelola_po.php?msg=deleted');
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        header('Location: kelola_po.php?msg=error_delete');
        exit;
    }
}

// Jika tidak ada ID
header('Location: kelola_po.php');
exit;
