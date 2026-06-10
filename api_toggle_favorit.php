<?php
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Silakan login terlebih dahulu.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_item = (int)($_POST['id_item'] ?? 0);
    $id_user = $_SESSION['id_user'];

    if ($id_item <= 0) {
        echo json_encode(['status' => 'error', 'message' => 'Item tidak valid.']);
        exit;
    }

    try {
        $db = getDB();
        
        // Cek apakah item sudah difavoritkan
        $stmt_cek = $db->prepare("SELECT id_favorit FROM favorit_item WHERE id_user = ? AND id_item = ?");
        $stmt_cek->execute([$id_user, $id_item]);
        $exists = $stmt_cek->fetch();

        if ($exists) {
            // Hapus dari favorit
            $stmt_del = $db->prepare("DELETE FROM favorit_item WHERE id_user = ? AND id_item = ?");
            $stmt_del->execute([$id_user, $id_item]);
            echo json_encode(['status' => 'removed', 'message' => 'Dihapus dari favorit']);
        } else {
            // Tambah ke favorit
            $stmt_add = $db->prepare("INSERT INTO favorit_item (id_user, id_item) VALUES (?, ?)");
            $stmt_add->execute([$id_user, $id_item]);
            echo json_encode(['status' => 'added', 'message' => 'Ditambahkan ke favorit']);
        }
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => 'Terjadi kesalahan sistem.']);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Metode tidak diizinkan.']);
}
