<?php
/**
 * Koneksi Database PDO - On Time Adventure
 * Host: localhost | User: root | Password: (kosong) | DB: ontimeadventure
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'ontimeadventure');
define('DB_USER', 'root');
define('DB_PASS', '');

function getDB(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4";
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
            ]);
            
            // Auto-update status PO yang sudah lewat tanggal selesainya menjadi 'Selesai (Barang Belum Kembali)'
            // Hanya berlaku untuk PO yang berstatus 'Barang Diambil' (artinya sedang disewa/dipegang pelanggan)
            $pdo->exec("UPDATE pengajuan_po SET status_po = 'Selesai (Barang Belum Kembali)' WHERE status_po = 'Barang Diambil' AND tgl_selesai_sewa < CURDATE()");
            
        } catch (PDOException $e) {
            die("Koneksi database gagal: " . $e->getMessage());
        }
    }
    return $pdo;
}
