<?php
require_once __DIR__ . '/config/database.php';

try {
    $db = getDB();
    
    // Add columns if they don't exist
    $queries = [
        "ALTER TABLE pengajuan_po ADD COLUMN admin_penyetuju VARCHAR(100) NULL AFTER waktu_kembali",
        "ALTER TABLE pengajuan_po ADD COLUMN admin_penyerah VARCHAR(100) NULL AFTER admin_penyetuju",
        "ALTER TABLE pengajuan_po ADD COLUMN admin_penerima VARCHAR(100) NULL AFTER admin_penyerah",
        "ALTER TABLE pengajuan_po ADD COLUMN catatan_pelanggan TEXT NULL AFTER estimasi_total_harga"
    ];

    foreach ($queries as $q) {
        try {
            $db->exec($q);
            echo "Successfully executed: $q\n";
        } catch (PDOException $e) {
            // Ignore if column already exists
            echo "Skipped (might already exist): $q -> " . $e->getMessage() . "\n";
        }
    }
    echo "Migration completed.";
} catch (Exception $e) {
    echo "Connection failed: " . $e->getMessage();
}
