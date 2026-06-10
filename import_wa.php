<?php
require_once __DIR__ . '/config/database.php';
$db = getDB();

// 1. Truncate Tables
$db->exec("SET FOREIGN_KEY_CHECKS = 0;");
$db->exec("TRUNCATE TABLE detail_po;");
$db->exec("TRUNCATE TABLE pengajuan_po;");
$db->exec("TRUNCATE TABLE varian_item;");
$db->exec("TRUNCATE TABLE item;");
$db->exec("TRUNCATE TABLE kategori_item;");
$db->exec("SET FOREIGN_KEY_CHECKS = 1;");

// Hapus file gambar lama
function deleteDirFiles($dir) {
    if (is_dir($dir)) {
        $files = glob($dir . '*');
        foreach($files as $file){ 
            if(is_file($file)) unlink($file); 
        }
    }
}
deleteDirFiles(__DIR__ . '/assets/img/asli/');
deleteDirFiles(__DIR__ . '/assets/img/');

// 2. Insert Kategori
$kategori = [
    'Sepatu' => '👞',
    'Tas / Carrier' => '🎒',
    'Tenda & Matras' => '⛺',
    'Alat Masak' => '🍳',
    'Penerangan' => '🔦',
    'Aksesoris' => '🪢',
    'Pakaian' => '👕'
];
$cat_ids = [];
foreach ($kategori as $nama => $ikon) {
    $stmt = $db->prepare("INSERT INTO kategori_item (nama_kategori, ikon) VALUES (?, ?)");
    $stmt->execute([$nama, $ikon]);
    $cat_ids[$nama] = $db->lastInsertId();
}

// 3. Mapping Data
// Format: [Brand, Seri, Kategori, Harga, Stok, Kondisi, [Images], Varian_Ket]
$data = [
    // SEPATU
    ['K2', 'Uk 40', 'Sepatu', 35000, 1, '80%', ['IMG-20260602-WA0010.jpg'], 'Uk 40'],
    ['Spalding', 'Uk 40', 'Sepatu', 35000, 1, '85%', ['IMG-20260602-WA0015.jpg'], 'Uk 40'],
    ['K2', 'Uk 37,5', 'Sepatu', 35000, 1, '90%', ['IMG-20260602-WA0020.jpg'], 'Uk 37.5'],
    ['Call mountain', 'Uk 42', 'Sepatu', 35000, 1, '65 alas rusak', ['IMG-20260602-WA0018.jpg'], 'Uk 42'],
    ['Guochao', 'Uk 41', 'Sepatu', 35000, 1, '70%', ['IMG-20260602-WA0027.jpg'], 'Uk 41'],
    ['Goretex', 'Uk 42', 'Sepatu', 35000, 1, '65', ['IMG-20260602-WA0025.jpg'], 'Uk 42'],
    ['Millet', 'Uk 41', 'Sepatu', 35000, 1, '80%', ['IMG-20260602-WA0030.jpg'], 'Uk 41 (80%)'],
    ['Co-Trek', 'Uk 41', 'Sepatu', 35000, 1, '80%', ['IMG-20260602-WA0033.jpg'], 'Uk 41'],
    ['Nepa', 'Uk 41', 'Sepatu', 35000, 1, '85%', ['IMG-20260602-WA0036.jpg'], 'Uk 41'],
    ['Arei', 'Uk 41', 'Sepatu', 35000, 1, '80%', ['IMG-20260602-WA0039.jpg'], 'Uk 41'],
    ['Pro WorldCup', 'Uk 41', 'Sepatu', 35000, 1, '75%', ['IMG-20260602-WA0040.jpg'], 'Uk 41'],
    ['Converse', 'Uk 40', 'Sepatu', 35000, 1, '75%', ['IMG-20260602-WA0049.jpg'], 'Uk 40'],
    ['Eider', 'Uk 42,5', 'Sepatu', 35000, 1, '75', ['IMG-20260602-WA0047.jpg'], 'Uk 42.5'],
    ['Karrimor', 'Uk 39,5', 'Sepatu', 35000, 1, '80%', ['IMG-20260602-WA0053.jpg'], 'Uk 39.5'],
    ['Millet', 'Uk 41 (2)', 'Sepatu', 35000, 1, '60%', ['IMG-20260602-WA0065.jpg'], 'Uk 41 (60%)'],
    ['Letsure', 'Uk 39', 'Sepatu', 35000, 1, '75%', ['IMG-20260602-WA0062.jpg'], 'Uk 39'],
    ['Conae', 'Uk 36', 'Sepatu', 35000, 1, '95', ['IMG-20260602-WA0060.jpg'], 'Uk 36'],
    ['Arei', 'Uk 40', 'Sepatu', 35000, 1, '75%', ['IMG-20260602-WA0068.jpg'], 'Uk 40'],
    ['Grifon', 'Uk 42', 'Sepatu', 35000, 1, '95', ['IMG-20260602-WA0082.jpg'], 'Uk 42'],
    ['The north face', 'Uk 40', 'Sepatu', 35000, 1, '65', ['IMG-20260602-WA0079.jpg'], 'Uk 40'],
    ['Hang ten', 'Uk 38', 'Sepatu', 35000, 1, '80', ['IMG-20260602-WA0076.jpg'], 'Uk 38'],
    ['Xgrib', 'Uk 42', 'Sepatu', 35000, 1, '80', ['IMG-20260602-WA0073.jpg'], 'Uk 42'],

    // TREKKING POLE (Aksesoris)
    ['Trekking Pole', 'Haoyang', 'Aksesoris', 10000, 2, 'Aman', ['IMG-20260602-WA0227.jpg'], 'Haoyang'],
    ['Trekking Pole', 'Antishock', 'Aksesoris', 10000, 1, 'Aman', ['IMG-20260602-WA0224.jpg'], 'Antishock'],
    ['Trekking Pole', 'Matougui', 'Aksesoris', 10000, 1, 'Aman', ['IMG-20260602-WA0218.jpg'], 'Matougui'],
    ['Trekking Pole', 'Spanspace', 'Aksesoris', 10000, 1, 'Aman', ['IMG-20260602-WA0215.jpg'], 'Spanspace'],

    // PENERANGAN
    ['Headlamp', '5 Biji', 'Penerangan', 10000, 5, 'Aman', ['IMG-20260602-WA0212.jpg'], '5 pcs'],
    ['Headlamp', '2 Biji', 'Penerangan', 10000, 2, 'Aman', ['IMG-20260602-WA0209.jpg'], '2 pcs'],
    ['Headlamp', 'Single', 'Penerangan', 10000, 1, 'Aman', ['IMG-20260602-WA0206.jpg'], '1 pc'],
    ['Senter', 'Senter', 'Penerangan', 10000, 1, 'Aman', ['IMG-20260602-WA0203.jpg'], 'Senter'],
    ['Lampu Tenda', '3 Biji', 'Penerangan', 10000, 3, 'Aman', ['IMG-20260602-WA0200.jpg'], '3 pcs'],
    ['Lampu Tenda', '2 Biji', 'Penerangan', 10000, 2, 'Aman', ['IMG-20260602-WA0197.jpg'], '2 pcs'],

    // ALAT MASAK
    ['Kompor', 'Portabel', 'Alat Masak', 15000, 5, 'Aman', ['IMG-20260602-WA0188.jpg'], 'Portabel'],
    ['Kompor', 'Micro', 'Alat Masak', 15000, 1, 'Aman', ['IMG-20260602-WA0185.jpg'], 'Micro (Mincrove)'],
    ['Gelas', 'Kecil', 'Alat Masak', 5000, 2, 'Aman', ['IMG-20260602-WA0182.jpg'], 'Gelas Kecil'],
    ['Nesting', 'Nesting', 'Alat Masak', 15000, 3, 'Aman', ['IMG-20260602-WA0179.jpg'], 'Nesting'],
    ['Jergen', 'Lipat 5L', 'Alat Masak', 5000, 3, 'Aman', ['IMG-20260602-WA0113.jpg'], 'Jergen 5 Liter'],

    // AKSESORIS LAIN
    ['Emergency Blanket', '50 Biji', 'Aksesoris', 5000, 50, 'Baru', ['IMG-20260602-WA0194.jpg'], 'Emergency Blanket'],
    ['Hand Warmer', '50 Biji', 'Aksesoris', 5000, 50, 'Baru', ['IMG-20260602-WA0191.jpg'], 'Hand Warmer'],
    
    // TENDA & MATRAS
    ['Matras', 'Aluminium Double Foil Buble', 'Tenda & Matras', 10000, 3, 'Aman', ['IMG-20260602-WA0167.jpg'], 'Aluminium Buble'],
    ['Matras', 'Aluminium Double Foil', 'Tenda & Matras', 10000, 2, 'Aman', ['IMG-20260602-WA0164.jpg'], 'Aluminium'],
    ['Matras', 'Karet Spons', 'Tenda & Matras', 5000, 10, 'Aman', ['IMG-20260602-WA0176.jpg'], 'Spons Hitam'],

    // TAS / CARRIER
    ['Consina', '75 Liter', 'Tas / Carrier', 45000, 1, 'Aman', ['IMG-20260602-WA0173.jpg'], '75 Liter'],
    ['The North Face', '60 Liter', 'Tas / Carrier', 40000, 1, 'Aman', ['IMG-20260602-WA0170.jpg'], '60 Liter'],
    ['Kilimanjaro', '45 Liter', 'Tas / Carrier', 35000, 1, 'Aman', ['IMG-20260602-WA0161.jpg'], '45 Liter'],
    ['Bogaboo', '45 Liter', 'Tas / Carrier', 35000, 1, 'Aman', ['IMG-20260602-WA0158.jpg'], '45 Liter'],
    ['The North Face', 'Daypack 20 L', 'Tas / Carrier', 25000, 1, 'Aman', ['IMG-20260602-WA0155.jpg'], 'Daypack 20 L'],
    ['Forester', 'Daypack 20 L', 'Tas / Carrier', 25000, 1, 'Aman', ['IMG-20260602-WA0152.jpg'], 'Daypack 20 L'],
    ['Kalibre', 'Hydropack 10 L', 'Tas / Carrier', 15000, 1, 'Aman', ['IMG-20260602-WA0149.jpg'], 'Hydropack 10 L'],
    ['Klo', 'Hydropack 15 L', 'Tas / Carrier', 20000, 1, 'Aman', ['IMG-20260602-WA0146.jpg'], 'Hydropack 15 L'],
    ['Zoya', '10 L', 'Tas / Carrier', 15000, 1, 'Aman', ['IMG-20260602-WA0143.jpg'], '10 L'],
    ['Antarestar', '10 L', 'Tas / Carrier', 15000, 1, 'Aman', ['IMG-20260602-WA0140.jpg'], '10 L'],
    ['GreenForest', '10 L', 'Tas / Carrier', 15000, 1, 'Aman', ['IMG-20260602-WA0137.jpg'], '10 L'],
    ['Reptil kobra', '60 L', 'Tas / Carrier', 35000, 1, 'Aman', ['IMG-20260602-WA0134.jpg'], '60 L'],

    // PAKAIAN
    ['The redface', 'Base Layer', 'Pakaian', 10000, 1, 'Aman', ['IMG-20260602-WA0131.jpg'], 'Base Layer'],
    ['Kolping', 'Jaket 1', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0128.jpg'], 'Kolping'],
    ['Kolping', 'Jaket 2', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0125.jpg'], 'Kolping'],
    ['Tuscaropa', 'Jaket', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0122.jpg'], 'Tuscaropa'],
    ['Freex', 'Jaket', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0119.jpg'], 'Freex'],
    ['Eiger', 'Jaket', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0116.jpg'], 'Eiger'],
    ['Rigi', 'Jaket', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0110.jpg'], 'Rigi'],
    ['Treksta', 'Jaket', 'Pakaian', 25000, 1, 'Aman', ['IMG-20260602-WA0107.jpg'], 'Treksta'],
    ['Topi Eiger', 'Eiger', 'Pakaian', 5000, 1, 'Aman', ['IMG-20260602-WA0091.jpg'], 'Eiger'],
    ['Topi', 'Topi Standar', 'Pakaian', 5000, 1, 'Aman', ['IMG-20260602-WA0088.jpg'], 'Topi Standar'],
];

$wa_dir = __DIR__ . '/data dari wa/';
$target_img_dir = __DIR__ . '/assets/img/';
$target_asli_dir = __DIR__ . '/assets/img/asli/';

foreach ($data as $d) {
    $brand = $d[0];
    $seri = $d[1];
    $kategori = $d[2];
    $harga = $d[3];
    $stok = $d[4];
    $kondisi = $d[5];
    $images = $d[6];
    $varian_ket = $d[7];

    $id_kategori = $cat_ids[$kategori] ?? 1;

    $img_name = null;
    if (count($images) > 0) {
        $source = $wa_dir . $images[0];
        if (file_exists($source)) {
            $ext = strtolower(pathinfo($source, PATHINFO_EXTENSION));
            $new_name = time() . '_' . uniqid() . '.' . $ext;
            
            // Simpan asli
            copy($source, $target_asli_dir . $new_name);
            // Simpan copy-an sebagai gambar "crop default" agar tidak error
            copy($source, $target_img_dir . $new_name);
            
            $img_name = $new_name;
        }
    }

    $stmt = $db->prepare("INSERT INTO item (id_kategori, nama_brand, nama_seri, deskripsi_umum, gambar, gambar_asli) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->execute([$id_kategori, $brand, $seri, "Diimport dari WhatsApp", $img_name, $img_name]);
    $id_item = $db->lastInsertId();

    $stmt = $db->prepare("INSERT INTO varian_item (id_item, keterangan_varian, harga_sewa_per_hari, stok_tersedia, catatan_kondisi) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$id_item, $varian_ket, $harga, $stok, $kondisi]);
}

echo "Import Data WhatsApp Berhasil!\n";
