<?php
/**
 * keranjang_po.php - Endpoint Controller Keranjang PO
 */
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$db = getDB();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';

if (!isset($_SESSION['keranjang'])) {
    $_SESSION['keranjang'] = [];
}

$referer = $_SERVER['HTTP_REFERER'] ?? $base_url . 'katalog.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    if (!validateCsrfToken()) {
        $_SESSION['flash_error'] = 'Token keamanan tidak valid. Silakan coba lagi.';
        header('Location: ' . $referer);
        exit;
    }

    $action = $_POST['action'];

    switch ($action) {
        case 'tambah':
            $id_varian = (int) ($_POST['id_varian'] ?? 0);
            $jumlah    = max(1, (int) ($_POST['jumlah'] ?? 1));

            if ($id_varian > 0) {
                $stmt = $db->prepare('SELECT stok_tersedia FROM varian_item WHERE id_varian = ?');
                $stmt->execute([$id_varian]);
                $varian = $stmt->fetch();

                if ($varian && $varian['stok_tersedia'] > 0) {
                    $existing = $_SESSION['keranjang'][$id_varian] ?? 0;
                    $newQty   = $existing + $jumlah;

                    if ($newQty > $varian['stok_tersedia']) {
                        $newQty = $varian['stok_tersedia'];
                        $_SESSION['flash_warning'] = 'Jumlah disesuaikan dengan stok tersedia (' . $varian['stok_tersedia'] . ' unit).';
                    } else {
                        $_SESSION['flash_success'] = 'Item berhasil ditambahkan ke keranjang.';
                    }

                    $_SESSION['keranjang'][$id_varian] = $newQty;
                } else {
                    $_SESSION['flash_error'] = 'Varian tidak ditemukan atau stok habis.';
                }
            }
            break;

        case 'update':
            $id_varian = (int) ($_POST['id_varian'] ?? 0);
            $jumlah    = (int) ($_POST['jumlah'] ?? 0);

            if ($id_varian > 0 && isset($_SESSION['keranjang'][$id_varian])) {
                if ($jumlah <= 0) {
                    unset($_SESSION['keranjang'][$id_varian]);
                    $_SESSION['flash_success'] = 'Item dihapus dari keranjang.';
                } else {
                    $stmt = $db->prepare('SELECT stok_tersedia FROM varian_item WHERE id_varian = ?');
                    $stmt->execute([$id_varian]);
                    $varian = $stmt->fetch();

                    if ($varian) {
                        $jumlah = min($jumlah, $varian['stok_tersedia']);
                        $_SESSION['keranjang'][$id_varian] = $jumlah;
                        $_SESSION['open_cart'] = true;
                    }
                }
            }
            break;

        case 'hapus':
            $id_varian = (int) ($_POST['id_varian'] ?? 0);
            if ($id_varian > 0 && isset($_SESSION['keranjang'][$id_varian])) {
                unset($_SESSION['keranjang'][$id_varian]);
                $_SESSION['flash_success'] = 'Item dihapus dari keranjang.';
                $_SESSION['open_cart'] = true;
            }
            break;
    }
}

// Ensure the user doesn't end up on a blank page if accessed directly via GET
header('Location: ' . $referer);
exit;
