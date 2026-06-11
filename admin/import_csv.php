<?php
session_start();
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';
requireAdmin();

$db = getDB();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['csv_file'])) {
    $file = $_FILES['csv_file'];
    
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['flash_msg'] = '<div class="alert alert-danger alert-dismissible fade show"><i class="ri-error-warning-line me-1"></i>Gagal mengunggah file.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        header("Location: kelola_item.php");
        exit;
    }
    
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if ($ext !== 'csv') {
        $_SESSION['flash_msg'] = '<div class="alert alert-danger alert-dismissible fade show"><i class="ri-error-warning-line me-1"></i>Format file harus .csv<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
        header("Location: kelola_item.php");
        exit;
    }
    
    if (($handle = fopen($file['tmp_name'], "r")) !== FALSE) {
        $headers = fgetcsv($handle, 1000, ",");
        
        // Remove BOM from first header if present
        if(substr($headers[0], 0, 3) == pack("CCC",0xef,0xbb,0xbf)) {
            $headers[0] = substr($headers[0], 3);
        }
        
        // Expected headers mapping
        $expected = ['Kategori', 'Brand', 'Seri', 'Deskripsi Umum', 'Jenis Transaksi (Sewa/Jual)', 'Status Item (Aktif/Arsip)', 'Varian/Ukuran', 'Harga Sewa/Hari', 'Stok', 'Catatan Kondisi'];
        
        if ($headers !== $expected) {
            $_SESSION['flash_msg'] = '<div class="alert alert-danger alert-dismissible fade show"><i class="ri-error-warning-line me-1"></i>Format header CSV tidak sesuai template. Pastikan Anda menggunakan file hasil Export atau Download Template.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            header("Location: kelola_item.php");
            exit;
        }
        
        $db->beginTransaction();
        try {
            $countNewItem = 0;
            $countNewVarian = 0;
            $countUpdateVarian = 0;
            
            while (($data = fgetcsv($handle, 1000, ",")) !== FALSE) {
                // Skip empty rows
                if (empty(array_filter($data))) continue;
                
                $kategori_name = trim($data[0] ?? '');
                $brand = trim($data[1] ?? '');
                $seri = trim($data[2] ?? '');
                $deskripsi = trim($data[3] ?? '');
                $jenis = trim($data[4] ?? 'Sewa');
                $status = trim($data[5] ?? 'Aktif');
                $varian_ket = trim($data[6] ?? '');
                $harga = (int)($data[7] ?? 0);
                $stok = (int)($data[8] ?? 0);
                $catatan = trim($data[9] ?? '');
                
                if (empty($kategori_name) || empty($brand) || empty($seri)) {
                    continue; // Skip invalid rows
                }
                
                // 1. Kategori
                $stmt = $db->prepare("SELECT id_kategori FROM kategori_item WHERE nama_kategori = ?");
                $stmt->execute([$kategori_name]);
                $id_kat = $stmt->fetchColumn();
                
                if (!$id_kat) {
                    $ins_kat = $db->prepare("INSERT INTO kategori_item (nama_kategori) VALUES (?)");
                    $ins_kat->execute([$kategori_name]);
                    $id_kat = $db->lastInsertId();
                }
                
                // 2. Item
                $stmt = $db->prepare("SELECT id_item FROM item WHERE nama_brand = ? AND nama_seri = ?");
                $stmt->execute([$brand, $seri]);
                $id_item = $stmt->fetchColumn();
                
                if (!$id_item) {
                    $ins_item = $db->prepare("INSERT INTO item (id_kategori, nama_brand, nama_seri, deskripsi_umum, jenis_transaksi, status_item) VALUES (?, ?, ?, ?, ?, ?)");
                    $ins_item->execute([$id_kat, $brand, $seri, $deskripsi, $jenis, $status]);
                    $id_item = $db->lastInsertId();
                    $countNewItem++;
                } else {
                    // Update item
                    $up_item = $db->prepare("UPDATE item SET id_kategori = ?, deskripsi_umum = ?, jenis_transaksi = ?, status_item = ? WHERE id_item = ?");
                    $up_item->execute([$id_kat, $deskripsi, $jenis, $status, $id_item]);
                }
                
                // 3. Varian
                $stmt = $db->prepare("SELECT id_varian FROM varian_item WHERE id_item = ? AND keterangan_varian = ?");
                $stmt->execute([$id_item, $varian_ket]);
                $id_varian = $stmt->fetchColumn();
                
                if (!$id_varian) {
                    $ins_var = $db->prepare("INSERT INTO varian_item (id_item, keterangan_varian, harga_sewa_per_hari, stok_tersedia, catatan_kondisi) VALUES (?, ?, ?, ?, ?)");
                    $ins_var->execute([$id_item, $varian_ket, $harga, $stok, $catatan]);
                    $countNewVarian++;
                } else {
                    $up_var = $db->prepare("UPDATE varian_item SET harga_sewa_per_hari = ?, stok_tersedia = ?, catatan_kondisi = ? WHERE id_varian = ?");
                    $up_var->execute([$harga, $stok, $catatan, $id_varian]);
                    $countUpdateVarian++;
                }
            }
            
            $db->commit();
            fclose($handle);
            
            $_SESSION['flash_msg'] = '<div class="alert alert-success alert-dismissible fade show"><i class="ri-check-line me-1"></i>Import berhasil! <b>'.$countNewItem.'</b> item baru, <b>'.$countNewVarian.'</b> varian baru, <b>'.$countUpdateVarian.'</b> varian diperbarui.<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            header("Location: kelola_item.php");
            exit;
            
        } catch (Exception $e) {
            $db->rollBack();
            fclose($handle);
            $_SESSION['flash_msg'] = '<div class="alert alert-danger alert-dismissible fade show"><i class="ri-error-warning-line me-1"></i>Gagal import: ' . $e->getMessage() . '<button type="button" class="btn-close" data-bs-dismiss="alert"></button></div>';
            header("Location: kelola_item.php");
            exit;
        }
    }
}
header("Location: kelola_item.php");
exit;
