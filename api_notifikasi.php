<?php
session_start();
require_once __DIR__ . '/config/database.php';

header('Content-Type: application/json');

if (!isset($_SESSION['id_user'])) {
    echo json_encode(['status' => 'error', 'message' => 'Unauthorized']);
    exit;
}

$db = getDB();
$id_user = $_SESSION['id_user'];
$action = $_GET['action'] ?? 'fetch';

if ($action === 'fetch') {
    try {
        // Hitung unread
        $stmt_count = $db->prepare("SELECT COUNT(id_notif) as unread FROM notifikasi WHERE id_user = ? AND is_read = 0");
        $stmt_count->execute([$id_user]);
        $count = $stmt_count->fetch()['unread'];

        // Ambil 5 notifikasi terbaru
        $stmt_list = $db->prepare("SELECT * FROM notifikasi WHERE id_user = ? ORDER BY created_at DESC LIMIT 5");
        $stmt_list->execute([$id_user]);
        $notifs = $stmt_list->fetchAll();

        echo json_encode([
            'status' => 'success',
            'unread' => $count,
            'data' => $notifs
        ]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} elseif ($action === 'read') {
    try {
        // Tandai semua sebagai sudah dibaca
        $stmt_update = $db->prepare("UPDATE notifikasi SET is_read = 1 WHERE id_user = ? AND is_read = 0");
        $stmt_update->execute([$id_user]);

        echo json_encode(['status' => 'success']);
    } catch (Exception $e) {
        echo json_encode(['status' => 'error', 'message' => $e->getMessage()]);
    }
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid action']);
}
