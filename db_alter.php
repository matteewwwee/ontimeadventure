<?php
require 'config/database.php';
$db = getDB();
try {
    $db->exec("ALTER TABLE item ADD COLUMN jenis_transaksi ENUM('Sewa', 'Beli') NOT NULL DEFAULT 'Sewa' AFTER deskripsi_umum");
    echo "Success";
} catch (PDOException $e) {
    if (strpos($e->getMessage(), 'Duplicate column name') !== false) {
        echo "Already exists";
    } else {
        echo "Error: " . $e->getMessage();
    }
}
