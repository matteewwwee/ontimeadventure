<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? '/ontimeadventure/' : '/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

$flash_msg = '';

function saveBase64Image($base64Data) {
    if (preg_match('/^data:image\/(\w+);base64,/', $base64Data, $type)) {
        $data = substr($base64Data, strpos($base64Data, ',') + 1);
        $type = strtolower($type[1]);
        if (!in_array($type, ['jpg', 'jpeg', 'png', 'webp'])) return false;
        $data = base64_decode($data);
        if ($data === false) return false;
        $filename = time() . '_' . uniqid() . '.' . $type;
        $upload_dir = __DIR__ . '/../assets/img/';
        if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
        file_put_contents($upload_dir . $filename, $data);
        return $filename;
    }
    return false;
}

function handleOriginalUpload($file_array) {
    if (isset($file_array) && $file_array['error'] === UPLOAD_ERR_OK) {
        $tmp_name = $file_array['tmp_name'];
        $name = basename($file_array['name']);
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed) && $file_array['size'] <= 5000000) {
            $new_name = time() . '_asli_' . preg_replace("/[^a-zA-Z0-9.-]/", "_", $name);
            $upload_dir = __DIR__ . '/../assets/img/asli/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0777, true);
            if (move_uploaded_file($tmp_name, $upload_dir . $new_name)) {
                return $new_name;
            }
        }
    }
    return null;
}

function deleteImage($filename) {
    if ($filename && file_exists(__DIR__ . '/../assets/img/' . $filename)) {
        unlink(__DIR__ . '/../assets/img/' . $filename);
    }
}
function deleteOriginalImage($filename) {
    if ($filename && file_exists(__DIR__ . '/../assets/img/asli/' . $filename)) {
        unlink(__DIR__ . '/../assets/img/asli/' . $filename);
    }
}

// Handle POST actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    
    if ($action === 'add_item') {
        $id_kategori = $_POST['id_kategori'];
        $nama_brand = trim($_POST['nama_brand']);
        $nama_seri = trim($_POST['nama_seri']);
        $deskripsi_umum = trim($_POST['deskripsi_umum']);
        
        $jenis_transaksi = $_POST['jenis_transaksi'] ?? 'Sewa';
        $status_item = $_POST['status_item'] ?? 'Aktif';
        
        $gambar = '';
        if (!empty($_POST['gambar_base64'])) {
            $saved = saveBase64Image($_POST['gambar_base64']);
            if ($saved) $gambar = $saved;
        }
        
        $gambar_asli = handleOriginalUpload($_FILES['gambar_asli_file'] ?? null) ?: handleOriginalUpload($_FILES['gambar_asli_cam'] ?? null);
        
        $stmt = $db->prepare("INSERT INTO item (id_kategori, nama_brand, nama_seri, deskripsi_umum, jenis_transaksi, status_item, gambar, gambar_asli) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_kategori, $nama_brand, $nama_seri, $deskripsi_umum, $jenis_transaksi, $status_item, $gambar, $gambar_asli]);
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Item berhasil ditambahkan!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        
    } elseif ($action === 'edit_item') {
        $id = $_POST['id_item'];
        $id_kategori = $_POST['id_kategori'];
        $nama_brand = trim($_POST['nama_brand']);
        $nama_seri = trim($_POST['nama_seri']);
        $deskripsi_umum = trim($_POST['deskripsi_umum']);
        $jenis_transaksi = $_POST['jenis_transaksi'] ?? 'Sewa';
        $status_item = $_POST['status_item'] ?? 'Aktif';
        
        $update_sql = "";
        $params = [$id_kategori, $nama_brand, $nama_seri, $deskripsi_umum, $jenis_transaksi, $status_item];
        
        if (!empty($_POST['edit_gambar_base64'])) {
            $saved = saveBase64Image($_POST['edit_gambar_base64']);
            if ($saved) {
                $old = $db->prepare("SELECT gambar FROM item WHERE id_item = ?");
                $old->execute([$id]);
                deleteImage($old->fetchColumn());
                
                $update_sql .= ", gambar = ?";
                $params[] = $saved;
            }
        }
        
        $new_asli = handleOriginalUpload($_FILES['edit_gambar_asli_file'] ?? null) ?: handleOriginalUpload($_FILES['edit_gambar_asli_cam'] ?? null);
        if ($new_asli) {
            $old_asli = $db->prepare("SELECT gambar_asli FROM item WHERE id_item = ?");
            $old_asli->execute([$id]);
            deleteOriginalImage($old_asli->fetchColumn());
            
            $update_sql .= ", gambar_asli = ?";
            $params[] = $new_asli;
        }
        
        $params[] = $id;
        $stmt = $db->prepare("UPDATE item SET id_kategori=?, nama_brand=?, nama_seri=?, deskripsi_umum=?, jenis_transaksi=?, status_item=? $update_sql WHERE id_item=?");
        $stmt->execute($params);
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Item berhasil diperbarui!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        
    } elseif ($action === 'delete_item') {
        $id = $_POST['id_item'];
        $img = $db->prepare("SELECT gambar, gambar_asli FROM item WHERE id_item = ?");
        $img->execute([$id]);
        $row = $img->fetch();
        if ($row) {
            deleteImage($row['gambar']);
            deleteOriginalImage($row['gambar_asli']);
        }
        
        $vars = $db->prepare("SELECT gambar, gambar_asli FROM varian_item WHERE id_item = ?");
        $vars->execute([$id]);
        foreach ($vars->fetchAll() as $v) {
            deleteImage($v['gambar']);
            deleteOriginalImage($v['gambar_asli']);
        }
        
        $stmt = $db->prepare("DELETE FROM item WHERE id_item = ?");
        $stmt->execute([$id]);
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Item dihapus!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        
    } elseif ($action === 'add_varian') {
        $id_item = $_POST['id_item'];
        $keterangan = trim($_POST['keterangan_varian']);
        $harga = (int)$_POST['harga_sewa_per_hari'];
        $stok = (int)$_POST['stok_tersedia'];
        $catatan = trim($_POST['catatan_kondisi']);
        
        $gambar_varian = null;
        if (!empty($_POST['gambar_varian_base64'])) {
            $saved = saveBase64Image($_POST['gambar_varian_base64']);
            if ($saved) $gambar_varian = $saved;
        }
        $gambar_asli = handleOriginalUpload($_FILES['gambar_varian_asli_file'] ?? null) ?: handleOriginalUpload($_FILES['gambar_varian_asli_cam'] ?? null);

        $stmt = $db->prepare("INSERT INTO varian_item (id_item, keterangan_varian, harga_sewa_per_hari, stok_tersedia, catatan_kondisi, gambar, gambar_asli) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$id_item, $keterangan, $harga, $stok, $catatan, $gambar_varian, $gambar_asli]);
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Varian berhasil ditambahkan!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        
    } elseif ($action === 'edit_varian') {
        $id = $_POST['id_varian'];
        $keterangan = trim($_POST['keterangan_varian']);
        $harga = (int)$_POST['harga_sewa_per_hari'];
        $stok = (int)$_POST['stok_tersedia'];
        $catatan = trim($_POST['catatan_kondisi']);
        
        $update_sql = "";
        $params = [$keterangan, $harga, $stok, $catatan];
        
        if (!empty($_POST['edit_gambar_varian_base64'])) {
            $saved = saveBase64Image($_POST['edit_gambar_varian_base64']);
            if ($saved) {
                $old = $db->prepare("SELECT gambar FROM varian_item WHERE id_varian = ?");
                $old->execute([$id]);
                deleteImage($old->fetchColumn());
                
                $update_sql .= ", gambar = ?";
                $params[] = $saved;
            }
        }
        
        $new_asli = handleOriginalUpload($_FILES['edit_gambar_varian_asli_file'] ?? null) ?: handleOriginalUpload($_FILES['edit_gambar_varian_asli_cam'] ?? null);
        if ($new_asli) {
            $old_asli = $db->prepare("SELECT gambar_asli FROM varian_item WHERE id_varian = ?");
            $old_asli->execute([$id]);
            deleteOriginalImage($old_asli->fetchColumn());
            
            $update_sql .= ", gambar_asli = ?";
            $params[] = $new_asli;
        }
        
        $params[] = $id;
        $stmt = $db->prepare("UPDATE varian_item SET keterangan_varian=?, harga_sewa_per_hari=?, stok_tersedia=?, catatan_kondisi=? $update_sql WHERE id_varian=?");
        $stmt->execute($params);
        $flash_msg = '<div class="alert alert-success alert-dismissible fade show" role="alert"><i class="ri-check-line me-1 align-middle fs-16"></i> Varian diperbarui!<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>';
        
    } elseif ($action === 'delete_varian') {
        $id = $_POST['id_varian'];
        $img = $db->prepare("SELECT gambar, gambar_asli FROM varian_item WHERE id_varian = ?");
        $img->execute([$id]);
        $row = $img->fetch();
        if ($row) {
            deleteImage($row['gambar']);
            deleteOriginalImage($row['gambar_asli']);
        }
        $stmt = $db->prepare("DELETE FROM varian_item WHERE id_varian = ?");
        $stmt->execute([$id]);
    }
}

// Data
$kategori_list = $db->query("SELECT * FROM kategori_item")->fetchAll();
$items = $db->query("
    SELECT i.*, k.nama_kategori,
    (SELECT COUNT(*) FROM varian_item v WHERE v.id_item = i.id_item) as total_varian,
    (SELECT MIN(v.harga_sewa_per_hari) FROM varian_item v WHERE v.id_item = i.id_item) as min_harga,
    (SELECT MAX(v.harga_sewa_per_hari) FROM varian_item v WHERE v.id_item = i.id_item) as max_harga
    FROM item i
    JOIN kategori_item k ON i.id_kategori = k.id_kategori
    ORDER BY i.id_item DESC
")->fetchAll();

if (!function_exists('formatRupiah')) {
    function formatRupiah($angka) { return 'Rp ' . number_format((int)$angka, 0, ',', '.'); }
}

$pageTitle = 'Kelola Item';
require_once __DIR__ . '/../includes/header.php';
?>

<!-- Include Cropper.js CSS -->
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css">
<style>
    .cropper-container { max-height: 400px; width: 100%; display: flex; justify-content: center; }
    .cropper-container img { max-width: 100%; display: block; }
    
    /* CSS Grid View for Table (Catalog Style) */
    .grid-view-active .table-responsive { overflow: visible !important; }
    .grid-view-active table, .grid-view-active thead, .grid-view-active th { display: block; }
    .grid-view-active thead tr { display: none; }
    
    .grid-view-active tbody { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr)); 
        gap: 15px; 
        padding: 15px; 
        background-color: transparent; 
        border-radius: 0.5rem;
    }
    
    .grid-view-active tr.item-row { 
        display: flex; flex-direction: column;
        border: 1px solid var(--default-border, #e8e8e8); border-radius: 0.5rem; 
        background-color: transparent; margin-bottom: 0; padding: 0; 
        box-shadow: 0 2px 5px rgba(0,0,0,0.04); 
        overflow: hidden; height: 100%;
    }
    
    .grid-view-active tr.varian-row { display: none; }
    .grid-view-active tr.varian-row.show-row { display: block; grid-column: 1 / -1; margin-bottom: 10px; }
    
    .grid-view-active th.col-no, .grid-view-active td.col-no { display: none !important; }
    
    /* First TD: Image + Title Header */
    .grid-view-active tr.item-row td:nth-of-type(2) { border-bottom: 1px solid var(--default-border, #eee); padding: 0 !important; text-align: center !important; display: block; }
    .grid-view-active tr.item-row td:nth-of-type(2) .d-flex { flex-direction: column; align-items: stretch !important; }
    .grid-view-active tr.item-row td:nth-of-type(2) .avatar { width: 100% !important; height: 220px !important; border-radius: 0 !important; margin: 0 !important; background-color: transparent; }
    .grid-view-active tr.item-row td:nth-of-type(2) .avatar img { width: 100%; height: 100%; object-fit: cover; border-radius: 0; }
    .grid-view-active tr.item-row td:nth-of-type(2) .avatar i { font-size: 60px !important; }
    .grid-view-active tr.item-row td:nth-of-type(2) .d-flex > div:last-child { padding: 15px 15px 20px 15px; text-align: center; width: 100%; }
    .grid-view-active tr.item-row td:nth-of-type(2) .fs-14 { font-size: 1.2rem !important; margin-top: 8px; }
    
    /* Other TDs (Kategori, Varian) */
    .grid-view-active tr.item-row td { 
        border: none; border-bottom: 1px solid var(--default-border, #eee); 
        padding: 12px 15px 12px 40% !important; 
        text-align: right !important; position: relative; 
        display: flex; align-items: center; justify-content: flex-end; 
    }
    .grid-view-active tr.item-row td:before { 
        position: absolute; top: 15px; left: 15px; width: 35%; 
        text-align: left; font-weight: 600; color: var(--text-muted, #6c757d); font-size: 0.85rem; 
    }
    .grid-view-active tr.item-row td:nth-of-type(3):before { content: "Kategori"; }
    .grid-view-active tr.item-row td:nth-of-type(4):before { content: "Varian"; }
    .grid-view-active tr.item-row td:nth-of-type(5):before { display: none; }
    
    /* Aksi TD */
    .grid-view-active tr.item-row td:last-child { 
        border-bottom: 0; padding: 15px !important; 
        justify-content: center; display: flex; flex-wrap: wrap; 
        gap: 8px; background-color: transparent; margin-top: auto; 
    }
    
    /* Nested Varian Table Grid Styling */
    .grid-view-active .varian-row .table-responsive { overflow: visible !important; }
    .grid-view-active .varian-row table, 
    .grid-view-active .varian-row thead, 
    .grid-view-active .varian-row tbody, 
    .grid-view-active .varian-row th, 
    .grid-view-active .varian-row td, 
    .grid-view-active .varian-row tr { display: block; }
    
    .grid-view-active .varian-row thead tr { display: none; }
    .grid-view-active .varian-row tbody { 
        display: grid; 
        grid-template-columns: repeat(auto-fill, minmax(220px, 1fr)); 
        gap: 15px; background: transparent; padding: 0;
    }
    
    .grid-view-active .varian-row tbody tr { 
        border: 1px solid var(--default-border, #dee2e6); border-radius: 0.5rem; background-color: transparent; padding: 15px; position: relative; display: flex; flex-direction: column;
    }
    
    .grid-view-active .varian-row tbody td { 
        border: none !important; padding: 6px 0 6px 35% !important; text-align: right !important; min-height: 32px; display: flex; align-items: center; justify-content: flex-end; width: 100% !important; white-space: normal;
    }
    .grid-view-active .varian-row tbody td:before { 
        position: absolute; left: 15px; top: auto; width: 30%; text-align: left; font-weight: 600; color: var(--text-muted, #6c757d); font-size: 0.8rem;
    }
    
    .grid-view-active .varian-row tbody td:nth-of-type(1):before { display: none; }
    .grid-view-active .varian-row tbody td:nth-of-type(2):before { content: "Keterangan"; }
    .grid-view-active .varian-row tbody td:nth-of-type(3):before { content: "Harga"; }
    .grid-view-active .varian-row tbody td:nth-of-type(4):before { content: "Tersedia"; }
    .grid-view-active .varian-row tbody td:nth-of-type(5):before { content: "Kondisi"; }
    .grid-view-active .varian-row tbody td:nth-of-type(6):before { display: none; }
    
    .grid-view-active .varian-row tbody td:nth-of-type(1) { padding-left: 0 !important; justify-content: center; margin-bottom: 15px; }
    .grid-view-active .varian-row tbody td:nth-of-type(1) img, 
    .grid-view-active .varian-row tbody td:nth-of-type(1) .bg-light { width: 100px !important; height: 100px !important; border-radius: 0.3rem; }
    .grid-view-active .varian-row tbody td:nth-of-type(1) .ri-image-line { font-size: 3rem !important; }
    
    .grid-view-active .varian-row tbody td:nth-of-type(6) { justify-content: center; padding: 15px 0 0 0 !important; margin-top: auto; border-top: 1px dashed var(--default-border, #e8e8e8) !important; display: flex; gap: 5px; }
    .grid-view-active .varian-row tbody td:nth-of-type(6) form { margin: 0; flex-grow: 1; display: flex; }
    .grid-view-active .varian-row tbody td:nth-of-type(6) .btn { flex-grow: 1; }
</style>

<div class="container-fluid mt-4">
    <div class="row align-items-center mb-4 gy-3">
        <div class="col-12 col-md-auto me-auto">
            <h4 class="mb-0 fw-semibold">Kelola Item & Varian</h4>
        </div>
        <div class="col-12 col-md-auto d-flex flex-column flex-md-row gap-2">
            <a href="export_item.php" target="_blank" class="btn btn-danger w-100 w-md-auto">
                <i class="ri-file-pdf-line me-1"></i> Cetak PDF
            </a>
            <button class="btn btn-primary w-100 w-md-auto" data-bs-toggle="modal" data-bs-target="#addItemModal">
                <i class="ri-add-circle-line me-1"></i> Tambah Item Baru
            </button>
        </div>
    </div>

    <?= $flash_msg ?>

    <!-- Modal Tambah Item -->
    <div class="modal fade" id="addItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="ri-add-circle-line me-2"></i> Tambah Item Baru</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
            <form method="POST" action="" enctype="multipart/form-data">
                <input type="hidden" name="action" value="add_item">
                <input type="hidden" name="gambar_base64" id="gambar_base64">
                
                <div class="row gy-3">
                    <div class="col-xl-6">
                        <label class="form-label text-default">Kategori</label>
                        <select name="id_kategori" class="form-select" required>
                            <option value="">-- Pilih Kategori --</option>
                            <?php foreach ($kategori_list as $k): ?>
                                <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-xl-6">
                        <label class="form-label text-default">Nama Brand</label>
                        <input type="text" name="nama_brand" class="form-control" required placeholder="Contoh: Eiger">
                    </div>
                    <div class="col-xl-6">
                        <label class="form-label text-default">Nama Seri</label>
                        <input type="text" name="nama_seri" class="form-control" required placeholder="Contoh: Shira 4P">
                    </div>
                    <div class="col-xl-6">
                        <label class="form-label text-default">Jenis Transaksi</label>
                        <select name="jenis_transaksi" class="form-select" required>
                            <option value="Sewa">Disewakan (Harga per Hari)</option>
                            <option value="Beli">Dijual / Beli (Harga Beli Putus)</option>
                        </select>
                    </div>
                    <div class="col-xl-6">
                        <label class="form-label text-default">Status Item</label>
                        <select name="status_item" class="form-select" required>
                            <option value="Aktif">Aktif (Tampil di Katalog)</option>
                            <option value="Non-Aktif">Non-Aktif (Sembunyikan)</option>
                        </select>
                    </div>
                    <div class="col-xl-6">
                        <label class="form-label text-default">Gambar (Akan disimpan aslinya juga)</label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="document.getElementById('img_item_file').click()"><i class="ri-upload-cloud-line me-1"></i> Unggah File</button>
                            <button type="button" class="btn btn-outline-success btn-sm flex-grow-1" onclick="document.getElementById('img_item_cam').click()"><i class="ri-camera-line me-1"></i> Kamera</button>
                        </div>
                        <input type="file" name="gambar_asli_file" id="img_item_file" class="d-none crop-trigger" data-target-input="gambar_base64" data-preview-img="gambar_preview" accept="image/jpeg,image/png,image/webp">
                        <input type="file" name="gambar_asli_cam" id="img_item_cam" class="d-none crop-trigger" data-target-input="gambar_base64" data-preview-img="gambar_preview" accept="image/jpeg,image/png,image/webp" capture="environment">
                        <div class="mt-2 d-none" id="gambar_preview_container">
                            <img id="gambar_preview" src="" class="img-thumbnail" style="max-height: 100px;">
                            <span class="badge bg-success ms-2"><i class="ri-check-line"></i> Gambar siap</span>
                        </div>
                    </div>
                    <div class="col-xl-12">
                        <label class="form-label text-default">Deskripsi Umum</label>
                        <textarea name="deskripsi_umum" class="form-control" rows="3" placeholder="Deskripsi untuk algoritma rekomendasi..."></textarea>
                    </div>
                    <div class="col-xl-12">
                        <button type="submit" class="btn btn-primary btn-wave w-100 mt-2"><i class="ri-save-line me-1"></i> Simpan Item</button>
                    </div>
                </div>
            </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal Preview -->
    <div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow">
                <div class="modal-header border-bottom-0 pb-0">
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body text-center pt-0 pb-4">
                    <img id="previewImage" src="" class="img-fluid rounded mb-3 shadow-sm" style="max-height: 250px; width: 100%; object-fit: cover;">
                    <div id="previewNoImage" class="bg-light rounded mb-3 d-flex align-items-center justify-content-center shadow-sm" style="height: 250px; display: none;">
                        <i class="ri-image-line fs-1 text-muted"></i>
                    </div>
                    <span class="d-block text-muted fs-12 fw-semibold text-uppercase ls-1 mb-1" id="previewBrand">BRAND</span>
                    <h4 class="fw-bold text-dark mb-3" id="previewSeri">Nama Seri</h4>
                    <div class="d-flex justify-content-center gap-2 mb-3">
                        <span class="badge bg-primary-transparent text-primary px-3 py-2" id="previewKat"><i class="ri-price-tag-3-line me-1"></i> Kategori</span>
                        <span class="badge bg-info-transparent text-info px-3 py-2" id="previewVarian"><i class="ri-list-check me-1"></i> 0 Varian</span>
                    </div>
                    <div class="p-3 bg-light rounded text-center mt-3">
                        <span class="d-block text-muted fs-12 mb-1">Estimasi Harga Sewa / Hari</span>
                        <h5 class="fw-bold text-success mb-0 fs-18" id="previewHarga">Rp 0</h5>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- List Items -->
    <div class="card custom-card">
        <div class="card-header d-block pb-3">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <div class="card-title mb-0">Daftar Item & Varian</div>
            </div>
            
            <div class="d-flex flex-wrap flex-md-nowrap align-items-center gap-2">
                <!-- Search -->
                <div class="flex-grow-1 w-100 w-md-auto" style="min-width: 250px;">
                    <div class="input-group input-group-sm w-100">
                        <span class="input-group-text bg-light"><i class="ri-search-line"></i></span>
                        <input type="text" id="searchInput" class="form-control" placeholder="Cari barang...">
                    </div>
                </div>
                
                <!-- Jenis Transaksi -->
                <div class="flex-grow-1 flex-md-grow-0" style="min-width: 130px;">
                    <select id="filterTransaksi" class="form-select form-select-sm w-100">
                        <option value="">Semua Tipe</option>
                        <option value="sewa">Sewa</option>
                        <option value="beli">Beli</option>
                    </select>
                </div>
                
                <!-- Status -->
                <div class="flex-grow-1 flex-md-grow-0" style="min-width: 130px;">
                    <select id="filterStatus" class="form-select form-select-sm w-100">
                        <option value="">Semua Status</option>
                        <option value="aktif">Aktif</option>
                        <option value="non-aktif">Non-Aktif</option>
                    </select>
                </div>
                
                <!-- Kategori -->
                <div class="flex-grow-1 flex-md-grow-0" style="min-width: 150px;">
                    <select id="filterKategori" class="form-select form-select-sm w-100">
                        <option value="">Semua Kategori</option>
                        <?php foreach ($kategori_list as $k): ?>
                            <option value="<?= htmlspecialchars(strtolower($k['nama_kategori'])) ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Urutan -->
                <div class="flex-grow-1 flex-md-grow-0" style="min-width: 150px;">
                    <select id="sortUrutan" class="form-select form-select-sm w-100">
                        <option value="baru">Terbaru (Default)</option>
                        <option value="nama_asc">Nama (A - Z)</option>
                        <option value="nama_desc">Nama (Z - A)</option>
                        <option value="harga_asc">Harga Terendah</option>
                        <option value="harga_desc">Harga Tertinggi</option>
                    </select>
                </div>
                
                <!-- Buttons -->
                <div class="flex-grow-0" style="min-width: 80px;">
                    <div class="btn-group btn-group-sm w-100" role="group">
                        <button type="button" class="btn btn-primary w-50" id="btnListView" title="List View"><i class="ri-list-check"></i></button>
                        <button type="button" class="btn btn-outline-primary w-50" id="btnGridView" title="Grid View"><i class="ri-grid-fill"></i></button>
                    </div>
                </div>
            </div>
        </div>
        <div class="card-body p-0" id="itemTableContainer">
            <div class="table-responsive">
                <table class="table table-bordered mb-0">
                    <thead class="table-light">
                        <tr>
                            <th class="text-center col-no" style="width: 50px;">No</th>
                            <th>Item</th>
                            <th class="text-center">Kategori</th>
                            <th class="text-center">Varian</th>
                            <th class="text-center">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $no = 1; foreach ($items as $item): ?>
                            <?php 
                            $img_url = !empty($item['gambar']) ? $base_url . 'assets/img/' . htmlspecialchars($item['gambar']) : '';
                            $img_asli_url = !empty($item['gambar_asli']) ? $base_url . 'assets/img/asli/' . htmlspecialchars($item['gambar_asli']) : '';
                            $minH = $item['min_harga'] ?? 0;
                            $maxH = $item['max_harga'] ?? 0;
                            $hargaTxt = ($minH === $maxH) ? formatRupiah($minH) : formatRupiah($minH) . ' - ' . formatRupiah($maxH);
                            if ($minH == 0) $hargaTxt = "N/A";
                            ?>
                            <tr class="item-row" data-id="<?= $item['id_item'] ?>" data-harga="<?= $minH ?>" data-nama="<?= htmlspecialchars(strtolower($item['nama_brand'] . ' ' . $item['nama_seri'])) ?>" data-kategori="<?= htmlspecialchars(strtolower($item['nama_kategori'])) ?>" data-transaksi="<?= strtolower($item['jenis_transaksi']) ?>" data-status="<?= strtolower($item['status_item']) ?>">
                                <td class="text-center fw-semibold text-muted col-no align-middle"><?= $no++ ?></td>
                                <td class="fw-semibold">
                                    <div class="d-flex align-items-center">
                                        <?php if ($img_url): ?>
                                            <span class="avatar avatar-md bg-light me-2">
                                                <img src="<?= $img_url ?>" alt="" style="object-fit: cover;">
                                            </span>
                                        <?php else: ?>
                                            <span class="avatar avatar-md bg-light me-2"><i class="ri-image-line fs-20 text-muted"></i></span>
                                        <?php endif; ?>
                                        <div>
                                            <span class="d-block mb-0 text-muted fs-11 text-uppercase fw-normal"><?= htmlspecialchars($item['nama_brand']) ?></span>
                                            <span class="d-block mb-0 fw-bold fs-14 text-dark"><?= htmlspecialchars($item['nama_seri']) ?></span>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex flex-column gap-1 align-items-center justify-content-center">
                                        <span class="badge bg-light text-dark"><?= htmlspecialchars($item['nama_kategori']) ?></span>
                                        <span class="badge <?= $item['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> text-white"><?= htmlspecialchars($item['jenis_transaksi']) ?></span>
                                        <span class="badge <?= $item['status_item'] === 'Aktif' ? 'bg-primary' : 'bg-secondary' ?> text-white"><?= htmlspecialchars($item['status_item']) ?></span>
                                    </div>
                                </td>
                                <td class="text-center">
                                    <span class="badge <?= $item['total_varian'] > 0 ? 'bg-primary-transparent text-primary' : 'bg-danger-transparent text-danger' ?>">
                                        <?= $item['total_varian'] ?> Varian
                                    </span>
                                </td>
                                <td class="text-center">
                                    <div class="d-flex justify-content-center flex-wrap gap-1">
                                        <button class="btn btn-sm btn-dark btn-wave flex-grow-1 flex-md-grow-0" onclick="showPreview('<?= htmlspecialchars(addslashes($item['nama_brand'])) ?>', '<?= htmlspecialchars(addslashes($item['nama_seri'])) ?>', '<?= htmlspecialchars(addslashes($item['nama_kategori'])) ?>', '<?= $img_url ?>', '<?= $hargaTxt ?>', <?= $item['total_varian'] ?>)"><i class="ri-eye-line me-1"></i> Preview</button>
                                        
                                        <button class="btn btn-sm btn-warning btn-wave flex-grow-1 flex-md-grow-0" onclick="editItem(<?= $item['id_item'] ?>, <?= $item['id_kategori'] ?>, '<?= htmlspecialchars(addslashes($item['nama_brand'])) ?>', '<?= htmlspecialchars(addslashes($item['nama_seri'])) ?>', '<?= htmlspecialchars(addslashes($item['deskripsi_umum'])) ?>', '<?= $img_url ?>', '<?= $img_asli_url ?>', '<?= $item['jenis_transaksi'] ?>', '<?= $item['status_item'] ?>')"><i class="ri-edit-line me-1"></i> Edit</button>

                                        <button class="btn btn-sm btn-info btn-wave flex-grow-1 flex-md-grow-0" type="button" data-bs-toggle="collapse" data-bs-target="#varianCollapse-<?= $item['id_item'] ?>">
                                            <i class="ri-list-check me-1"></i> Varian
                                        </button>
                                        
                                        <form method="POST" action="" class="d-inline flex-grow-1 flex-md-grow-0 m-0" onsubmit="confirmDelete(event, 'Yakin hapus item ini? Semua varian juga akan terhapus.');">
                                            <input type="hidden" name="action" value="delete_item">
                                            <input type="hidden" name="id_item" value="<?= $item['id_item'] ?>">
                                            <button type="submit" class="btn btn-sm btn-danger btn-wave w-100"><i class="ri-delete-bin-line me-1"></i> Hapus</button>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                            
                            <!-- Collapse Varian Data -->
                            <tr class="varian-row">
                                <td colspan="5" class="p-0 border-0">
                                    <div class="collapse" id="varianCollapse-<?= $item['id_item'] ?>">
                                        <div class="card card-body border-0 shadow-none bg-light m-3">
                                            
                                            <!-- Tambah Varian Form -->
                                            <form method="POST" action="" class="mb-4" enctype="multipart/form-data">
                                                <input type="hidden" name="action" value="add_varian">
                                                <input type="hidden" name="id_item" value="<?= $item['id_item'] ?>">
                                                <input type="hidden" name="gambar_varian_base64" id="add_var_base64_<?= $item['id_item'] ?>">

                                                <div class="row gx-2 gy-2 align-items-end">
                                                    <div class="col-xl-2 col-sm-6">
                                                        <label class="form-label fs-12 text-muted mb-1">Keterangan (Warna/Ukuran)</label>
                                                        <input type="text" name="keterangan_varian" class="form-control form-control-sm" placeholder="Cth: Merah / XL" required>
                                                    </div>
                                                    <div class="col-xl-2 col-sm-6">
                                                        <label class="form-label fs-12 text-muted mb-1">Harga/Hari</label>
                                                        <input type="number" name="harga_sewa_per_hari" class="form-control form-control-sm" placeholder="25000" required>
                                                    </div>
                                                    <div class="col-xl-1 col-sm-4">
                                                        <label class="form-label fs-12 text-muted mb-1">Tersedia</label>
                                                        <input type="number" name="stok_tersedia" class="form-control form-control-sm" value="1" min="0" required>
                                                    </div>
                                                    <div class="col-xl-2 col-sm-8">
                                                        <label class="form-label fs-12 text-muted mb-1">Kondisi</label>
                                                        <input type="text" name="catatan_kondisi" class="form-control form-control-sm" placeholder="Aman">
                                                    </div>
                                                    <div class="col-xl-3 col-sm-12">
                                                        <label class="form-label fs-12 text-muted mb-1">Gambar Varian</label>
                                                        <div class="d-flex gap-1 mb-1">
                                                            <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1 px-1" onclick="document.getElementById('var_file_<?= $item['id_item'] ?>').click()"><i class="ri-upload-cloud-line"></i> File</button>
                                                            <button type="button" class="btn btn-outline-success btn-sm flex-grow-1 px-1" onclick="document.getElementById('var_cam_<?= $item['id_item'] ?>').click()"><i class="ri-camera-line"></i> Kam</button>
                                                        </div>
                                                        <input type="file" name="gambar_varian_asli_file" id="var_file_<?= $item['id_item'] ?>" class="d-none crop-trigger" data-target-input="add_var_base64_<?= $item['id_item'] ?>" data-preview-img="add_var_img_<?= $item['id_item'] ?>" accept="image/jpeg,image/png,image/webp">
                                                        <input type="file" name="gambar_varian_asli_cam" id="var_cam_<?= $item['id_item'] ?>" class="d-none crop-trigger" data-target-input="add_var_base64_<?= $item['id_item'] ?>" data-preview-img="add_var_img_<?= $item['id_item'] ?>" accept="image/jpeg,image/png,image/webp" capture="environment">
                                                        <div id="add_var_img_<?= $item['id_item'] ?>_container" class="mt-1 d-none">
                                                            <img id="add_var_img_<?= $item['id_item'] ?>" src="" class="img-thumbnail" style="max-height: 40px;">
                                                        </div>
                                                    </div>
                                                    <div class="col-xl-2 col-sm-12">
                                                        <button type="submit" class="btn btn-sm btn-primary w-100"><i class="ri-add-line"></i> Tambah</button>
                                                    </div>
                                                </div>
                                            </form>

                                            <!-- List Varian Table -->
                                            <?php
                                            $varians = $db->prepare("SELECT * FROM varian_item WHERE id_item = ?");
                                            $varians->execute([$item['id_item']]);
                                            $list_varian = $varians->fetchAll();
                                            ?>
                                            <?php if(count($list_varian) > 0): ?>
                                                <div class="table-responsive">
                                                    <table class="table table-sm table-bordered text-nowrap mb-0 bg-white align-middle">
                                                        <thead class="table-light">
                                                            <tr>
                                                                <th>Gambar</th>
                                                                <th>Keterangan</th>
                                                                <th>Harga/Hari</th>
                                                                <th>Tersedia</th>
                                                                <th>Kondisi</th>
                                                                <th class="text-center">Aksi</th>
                                                            </tr>
                                                        </thead>
                                                        <tbody>
                                                            <?php foreach ($list_varian as $v): ?>
                                                                <?php 
                                                                $v_img = !empty($v['gambar']) ? $base_url . 'assets/img/' . $v['gambar'] : ''; 
                                                                $v_img_asli = !empty($v['gambar_asli']) ? $base_url . 'assets/img/asli/' . $v['gambar_asli'] : '';
                                                                ?>
                                                                <tr>
                                                                    <td style="width: 50px;">
                                                                        <?php if ($v_img): ?>
                                                                            <img src="<?= htmlspecialchars($v_img) ?>" class="img-thumbnail" style="width: 40px; height: 40px; object-fit: cover;">
                                                                        <?php else: ?>
                                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                                                                                <i class="ri-image-line text-muted"></i>
                                                                            </div>
                                                                        <?php endif; ?>
                                                                    </td>
                                                                    <td><span class="fw-semibold"><?= htmlspecialchars($v['keterangan_varian']) ?></span></td>
                                                                    <td>Rp <?= number_format($v['harga_sewa_per_hari'], 0, ',', '.') ?></td>
                                                                    <td>
                                                                        <span class="badge <?= $v['stok_tersedia'] > 0 ? 'bg-success-transparent text-success' : 'bg-danger-transparent text-danger' ?>"><?= $v['stok_tersedia'] ?></span>
                                                                    </td>
                                                                    <td><span class="fs-12 text-muted"><?= htmlspecialchars($v['catatan_kondisi']) ?></span></td>
                                                                    <td class="text-center">
                                                                        <button type="button" class="btn btn-sm btn-warning-ghost px-2 py-1 me-1" onclick="editVarian(<?= $v['id_varian'] ?>, '<?= htmlspecialchars(addslashes($v['keterangan_varian'])) ?>', <?= $v['harga_sewa_per_hari'] ?>, <?= $v['stok_tersedia'] ?>, '<?= htmlspecialchars(addslashes($v['catatan_kondisi'])) ?>', '<?= $v_img ?>', '<?= $v_img_asli ?>')"><i class="ri-edit-line"></i></button>

                                                                        <form method="POST" action="" class="d-inline" onsubmit="return confirm('Hapus varian ini?');">
                                                                            <input type="hidden" name="action" value="delete_varian">
                                                                            <input type="hidden" name="id_varian" value="<?= $v['id_varian'] ?>">
                                                                            <button type="submit" class="btn btn-sm btn-danger-ghost px-2 py-1"><i class="ri-close-line"></i></button>
                                                                        </form>
                                                                    </td>
                                                                </tr>
                                                            <?php endforeach; ?>
                                                        </tbody>
                                                    </table>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center p-3 text-muted fs-13">Item ini belum memiliki varian.</div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        
                        <?php if(empty($items)): ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">Belum ada item</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Modal Preview Card -->
<div class="modal fade" id="previewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" style="width: 320px;">
        <div class="modal-content border-0 bg-transparent shadow-none">
            <div class="modal-body p-0">
                <div class="card custom-card product-card mb-0 shadow">
                    <div class="card-body">
                        <div class="product-image">
                            <img id="prevImg" src="" class="img-fluid rounded" style="width:100%; height:200px; object-fit:cover;" alt="...">
                        </div>
                        <div class="mt-3">
                            <span class="d-block fs-12 text-muted mb-1" id="prevBrand">Brand</span>
                            <h6 class="fw-semibold mb-1" id="prevSeri">Nama Seri</h6>
                            <p class="mb-2 fs-13 text-muted">
                                <span class="badge bg-light text-dark"><i class="ri-price-tag-3-line me-1"></i><span id="prevKat">Kategori</span></span>
                            </p>
                            <div class="d-flex align-items-center justify-content-between">
                                <h6 class="fw-semibold text-success mb-0">
                                    <span id="prevHarga">Rp 0</span>
                                    <span class="fs-11 text-muted fw-normal">/hari</span>
                                </h6>
                                <div>
                                    <span class="badge bg-success-transparent" id="prevStokBadge"><i class="ri-checkbox-circle-line me-1"></i> <span id="prevStok">0</span> Varian</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-0 d-flex justify-content-center">
                <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Tutup Preview</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal Edit Item -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_item">
                    <input type="hidden" name="id_item" id="edit_item_id">
                    <input type="hidden" name="edit_gambar_base64" id="edit_gambar_base64">
                    
                    <div class="row gy-3">
                        <div class="col-xl-6">
                            <label class="form-label">Kategori</label>
                            <select name="id_kategori" id="edit_item_kat" class="form-select" required>
                                <?php foreach ($kategori_list as $k): ?>
                                    <option value="<?= $k['id_kategori'] ?>"><?= htmlspecialchars($k['nama_kategori']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-xl-6">
                            <label class="form-label">Nama Brand</label>
                            <input type="text" name="nama_brand" id="edit_item_brand" class="form-control" required>
                        </div>
                        <div class="col-xl-6">
                            <label class="form-label">Nama Seri</label>
                            <input type="text" name="nama_seri" id="edit_item_seri" class="form-control" required>
                        </div>
                        <div class="col-xl-6">
                            <label class="form-label text-default">Jenis Transaksi</label>
                            <select name="jenis_transaksi" id="edit_jenis_transaksi" class="form-select" required>
                                <option value="Sewa">Disewakan (Harga per Hari)</option>
                                <option value="Beli">Dijual / Beli (Harga Beli Putus)</option>
                            </select>
                        </div>
                        <div class="col-xl-6">
                            <label class="form-label text-default">Status Item</label>
                            <select name="status_item" id="edit_status_item" class="form-select" required>
                                <option value="Aktif">Aktif (Tampil di Katalog)</option>
                                <option value="Non-Aktif">Non-Aktif (Sembunyikan)</option>
                            </select>
                        </div>
                        <div class="col-xl-12">
                            <label class="form-label">Ganti Gambar (Simpan asli)</label>
                            <div class="d-flex gap-2 mb-2">
                                <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="document.getElementById('edit_item_file').click()"><i class="ri-upload-cloud-line me-1"></i> File</button>
                                <button type="button" class="btn btn-outline-success btn-sm flex-grow-1" onclick="document.getElementById('edit_item_cam').click()"><i class="ri-camera-line me-1"></i> Kamera</button>
                            </div>
                            <input type="file" name="edit_gambar_asli_file" id="edit_item_file" class="d-none crop-trigger" data-target-input="edit_gambar_base64" data-preview-img="edit_item_img_preview" accept="image/jpeg,image/png,image/webp">
                            <input type="file" name="edit_gambar_asli_cam" id="edit_item_cam" class="d-none crop-trigger" data-target-input="edit_gambar_base64" data-preview-img="edit_item_img_preview" accept="image/jpeg,image/png,image/webp" capture="environment">
                            
                            <div class="d-flex align-items-center mt-2">
                                <img id="edit_item_img_preview" src="" class="img-thumbnail me-3" style="max-height: 80px;">
                                <button type="button" id="btnRecropItem" class="btn btn-sm btn-outline-primary d-none"><i class="ri-crop-line"></i> Crop Ulang Gambar Asli</button>
                            </div>
                        </div>
                        <div class="col-xl-12">
                            <label class="form-label">Deskripsi Umum</label>
                            <textarea name="deskripsi_umum" id="edit_item_desc" class="form-control" rows="3"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Edit Varian -->
<div class="modal fade" id="editVarianModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
        <form method="POST" action="" enctype="multipart/form-data">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Varian</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" name="action" value="edit_varian">
                    <input type="hidden" name="id_varian" id="edit_var_id">
                    <input type="hidden" name="edit_gambar_varian_base64" id="edit_var_base64">
                    
                    <div class="mb-3">
                        <label class="form-label">Keterangan (Warna/Ukuran)</label>
                        <input type="text" name="keterangan_varian" id="edit_var_ket" class="form-control" required>
                    </div>
                    <div class="row">
                        <div class="col-6 mb-3">
                            <label class="form-label">Harga/Hari</label>
                            <input type="number" name="harga_sewa_per_hari" id="edit_var_harga" class="form-control" required>
                        </div>
                        <div class="col-6 mb-3">
                            <label class="form-label">Tersedia</label>
                            <input type="number" name="stok_tersedia" id="edit_var_stok" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kondisi</label>
                        <input type="text" name="catatan_kondisi" id="edit_var_kondisi" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Ganti Gambar Varian</label>
                        <div class="d-flex gap-2 mb-2">
                            <button type="button" class="btn btn-outline-primary btn-sm flex-grow-1" onclick="document.getElementById('edit_var_file').click()"><i class="ri-upload-cloud-line me-1"></i> Unggah File</button>
                            <button type="button" class="btn btn-outline-success btn-sm flex-grow-1" onclick="document.getElementById('edit_var_cam').click()"><i class="ri-camera-line me-1"></i> Kamera</button>
                        </div>
                        <input type="file" name="edit_gambar_varian_asli_file" id="edit_var_file" class="d-none crop-trigger" data-target-input="edit_var_base64" data-preview-img="edit_var_img_preview" accept="image/jpeg,image/png,image/webp">
                        <input type="file" name="edit_gambar_varian_asli_cam" id="edit_var_cam" class="d-none crop-trigger" data-target-input="edit_var_base64" data-preview-img="edit_var_img_preview" accept="image/jpeg,image/png,image/webp" capture="environment">
                        <div class="d-flex align-items-center mt-2">
                            <img id="edit_var_img_preview" src="" class="img-thumbnail me-3" style="max-height: 80px;">
                            <button type="button" id="btnRecropVarian" class="btn btn-sm btn-outline-primary d-none"><i class="ri-crop-line"></i> Crop Ulang Gambar Asli</button>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-primary">Simpan Perubahan</button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Modal Cropper JS (Reused for all uploads) -->
<div class="modal fade" id="cropperModal" tabindex="-1" aria-labelledby="cropperModalLabel" aria-hidden="true" data-bs-backdrop="static">
    <div class="modal-dialog modal-lg modal-dialog-centered" style="z-index: 1060;">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="cropperModalLabel">Crop Gambar (1:1)</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="cropper-container bg-dark">
                    <img id="imageToCrop" src="">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-light" data-bs-dismiss="modal">Batal</button>
                <button type="button" class="btn btn-primary" id="cropImageBtn">Gunakan Gambar</button>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
<script src="https://cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js"></script>
<script>
function showPreview(brand, seri, kat, img, harga, varian) {
    document.getElementById('prevBrand').innerText = brand;
    document.getElementById('prevSeri').innerText = seri;
    document.getElementById('prevKat').innerText = kat;
    document.getElementById('prevHarga').innerText = harga;
    document.getElementById('prevStok').innerText = varian;
    let prevImg = document.getElementById('prevImg');
    if (img && img !== '') { prevImg.src = img; } else { prevImg.src = 'data:image/svg+xml;charset=UTF-8,%3Csvg%20width%3D%22200%22%20height%3D%22200%22%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20viewBox%3D%220%200%20200%20200%22%20preserveAspectRatio%3D%22none%22%3E%3Crect%20width%3D%22200%22%20height%3D%22200%22%20fill%3D%22%23eee%22%2F%3E%3Ctext%20x%3D%22100%22%20y%3D%22100%22%20fill%3D%22%23aaa%22%20dy%3D%22.3em%22%20style%3D%22text-anchor%3Amiddle%3Bfont-size%3A24px%3B%22%3ENo%20Image%3C%2Ftext%3E%3C%2Fsvg%3E'; }
    document.getElementById('prevStokBadge').className = varian === 0 ? 'badge bg-danger-transparent' : 'badge bg-success-transparent';
}

function openRecropper(img_asli_url, targetInputId, targetPreviewId) {
    fetch(img_asli_url).then(res => res.blob()).then(blob => {
        const reader = new FileReader();
        reader.onload = function(e) {
            window.currentInputTarget = targetInputId;
            window.currentPreviewTarget = targetPreviewId;
            document.getElementById('imageToCrop').src = e.target.result;
            if(!window.bsCropperModal) window.bsCropperModal = new bootstrap.Modal(document.getElementById('cropperModal'));
            window.bsCropperModal.show();
        };
        reader.readAsDataURL(blob);
    }).catch(err => {
        alert("Gagal memuat gambar asli. Mungkin gambar asli tidak tersimpan sebelumnya.");
    });
}

window.showPreview = function(brand, seri, kat, imgUrl, hargaTxt, totalVarian) {
    document.getElementById('previewBrand').textContent = brand;
    document.getElementById('previewSeri').textContent = seri;
    document.getElementById('previewKat').innerHTML = '<i class="ri-price-tag-3-line me-1"></i> ' + kat;
    document.getElementById('previewVarian').innerHTML = '<i class="ri-list-check me-1"></i> ' + totalVarian + ' Varian';
    document.getElementById('previewHarga').textContent = hargaTxt;
    
    const imgEl = document.getElementById('previewImage');
    const noImgEl = document.getElementById('previewNoImage');
    
    if (imgUrl && imgUrl.trim() !== '') {
        imgEl.src = imgUrl;
        imgEl.style.display = 'block';
        noImgEl.style.setProperty('display', 'none', 'important');
    } else {
        imgEl.style.display = 'none';
        noImgEl.style.setProperty('display', 'flex', 'important');
    }
    
    new bootstrap.Modal(document.getElementById('previewModal')).show();
};

function editItem(id, idKat, brand, seri, desc, img, img_asli, jenis_transaksi, status_item) {
    document.getElementById('edit_item_id').value = id;
    document.getElementById('edit_item_kat').value = idKat;
    document.getElementById('edit_item_brand').value = brand;
    document.getElementById('edit_item_seri').value = seri;
    document.getElementById('edit_item_desc').value = desc;
    if(jenis_transaksi) {
        document.getElementById('edit_jenis_transaksi').value = jenis_transaksi;
    } else {
        document.getElementById('edit_jenis_transaksi').value = 'Sewa';
    }
    if(status_item) {
        document.getElementById('edit_status_item').value = status_item;
    } else {
        document.getElementById('edit_status_item').value = 'Aktif';
    }
    document.getElementById('edit_item_img_preview').src = img || '';
    document.getElementById('edit_gambar_base64').value = '';
    
    let btnRecrop = document.getElementById('btnRecropItem');
    if (img_asli && img_asli !== '') {
        btnRecrop.classList.remove('d-none');
        btnRecrop.onclick = () => openRecropper(img_asli, 'edit_gambar_base64', 'edit_item_img_preview');
    } else {
        btnRecrop.classList.add('d-none');
    }
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

function editVarian(id, ket, harga, stok, kondisi, img, img_asli) {
    document.getElementById('edit_var_id').value = id;
    document.getElementById('edit_var_ket').value = ket;
    document.getElementById('edit_var_harga').value = harga;
    document.getElementById('edit_var_stok').value = stok;
    document.getElementById('edit_var_kondisi').value = kondisi;
    document.getElementById('edit_var_img_preview').src = img || '';
    document.getElementById('edit_var_base64').value = '';
    
    let btnRecrop = document.getElementById('btnRecropVarian');
    if (img_asli && img_asli !== '') {
        btnRecrop.classList.remove('d-none');
        btnRecrop.onclick = () => openRecropper(img_asli, 'edit_var_base64', 'edit_var_img_preview');
    } else {
        btnRecrop.classList.add('d-none');
    }
    new bootstrap.Modal(document.getElementById('editVarianModal')).show();
}

document.addEventListener("DOMContentLoaded", function() {
    let cropper;
    const imageToCrop = document.getElementById('imageToCrop');
    const cropperModalEl = document.getElementById('cropperModal');

    cropperModalEl.addEventListener('hidden.bs.modal', function () {
        if (cropper) { cropper.destroy(); cropper = null; }
        imageToCrop.src = '';
    });

    document.querySelectorAll('.crop-trigger').forEach(input => {
        input.addEventListener('change', function(e) {
            const files = e.target.files;
            if (files && files.length > 0) {
                const reader = new FileReader();
                reader.onload = function(event) {
                    window.currentInputTarget = input.getAttribute('data-target-input');
                    window.currentPreviewTarget = input.getAttribute('data-preview-img');
                    imageToCrop.src = event.target.result;
                    if (!window.bsCropperModal) window.bsCropperModal = new bootstrap.Modal(cropperModalEl);
                    window.bsCropperModal.show();
                };
                reader.readAsDataURL(files[0]);
            }
        });
    });

    cropperModalEl.addEventListener('shown.bs.modal', function () {
        cropper = new Cropper(imageToCrop, {
            aspectRatio: 1, 
            viewMode: 1,
            autoCropArea: 1,
        });
    });

    document.getElementById('cropImageBtn').addEventListener('click', function() {
        if (!cropper) return;
        const canvas = cropper.getCroppedCanvas({ width: 800, height: 800, imageSmoothingQuality: 'high' });
        const base64Image = canvas.toDataURL('image/jpeg', 0.85);

        if (window.currentInputTarget) document.getElementById(window.currentInputTarget).value = base64Image;
        if (window.currentPreviewTarget) {
            document.getElementById(window.currentPreviewTarget).src = base64Image;
            const container = document.getElementById(window.currentPreviewTarget + '_container');
            if (container) container.classList.remove('d-none');
        }
        if (window.bsCropperModal) window.bsCropperModal.hide();
    });

    // View Toggle Logic
    const btnList = document.getElementById('btnListView');
    const btnGrid = document.getElementById('btnGridView');
    const tableContainer = document.getElementById('itemTableContainer');
    
    // Check local storage for preference
    if(localStorage.getItem('adminItemView') === 'grid') {
        setGridView();
    } else if (!localStorage.getItem('adminItemView') && window.innerWidth < 768) {
        // Auto grid for mobile on first visit
        setGridView();
    }
    
    btnList.addEventListener('click', () => {
        tableContainer.classList.remove('grid-view-active');
        btnList.classList.replace('btn-outline-primary', 'btn-primary');
        btnGrid.classList.replace('btn-primary', 'btn-outline-primary');
        localStorage.setItem('adminItemView', 'list');
    });
    
    btnGrid.addEventListener('click', () => {
        setGridView();
    });
    
    function setGridView() {
        tableContainer.classList.add('grid-view-active');
        btnGrid.classList.replace('btn-outline-primary', 'btn-primary');
        btnList.classList.replace('btn-primary', 'btn-outline-primary');
        localStorage.setItem('adminItemView', 'grid');
    }
    
    // Handle Varian Collapse in Grid View
    document.querySelectorAll('.collapse').forEach(col => {
        col.addEventListener('show.bs.collapse', function () {
            let row = this.closest('tr.varian-row');
            if (row) row.classList.add('show-row');
        });
        col.addEventListener('hidden.bs.collapse', function () {
            let row = this.closest('tr.varian-row');
            if (row) row.classList.remove('show-row');
        });
    });

    // Search, Filter & Sort Logic
    const searchInput = document.getElementById('searchInput');
    const filterKategori = document.getElementById('filterKategori');
    const filterTransaksi = document.getElementById('filterTransaksi');
    const filterStatus = document.getElementById('filterStatus');
    const sortUrutan = document.getElementById('sortUrutan');
    const tbody = document.querySelector('#itemTableContainer tbody');
    const itemRows = document.querySelectorAll('tr.item-row');

    // Create pairs of [itemRow, varianRow] for sorting
    let rowPairs = [];
    itemRows.forEach(row => {
        let next = row.nextElementSibling;
        if (next && next.classList.contains('varian-row')) {
            rowPairs.push({ item: row, varian: next });
        }
    });

    function filterAndSortItems() {
        const query = searchInput.value.toLowerCase();
        const cat = filterKategori.value.toLowerCase();
        const trans = filterTransaksi ? filterTransaksi.value.toLowerCase() : '';
        const stat = filterStatus ? filterStatus.value.toLowerCase() : '';
        const sortVal = sortUrutan.value;

        // Sort pairs
        rowPairs.sort((a, b) => {
            if (sortVal === 'baru') {
                return parseInt(b.item.getAttribute('data-id')) - parseInt(a.item.getAttribute('data-id'));
            } else if (sortVal === 'harga_asc') {
                return parseInt(a.item.getAttribute('data-harga')) - parseInt(b.item.getAttribute('data-harga'));
            } else if (sortVal === 'harga_desc') {
                return parseInt(b.item.getAttribute('data-harga')) - parseInt(a.item.getAttribute('data-harga'));
            } else if (sortVal === 'nama_asc') {
                return a.item.getAttribute('data-nama').localeCompare(b.item.getAttribute('data-nama'));
            } else if (sortVal === 'nama_desc') {
                return b.item.getAttribute('data-nama').localeCompare(a.item.getAttribute('data-nama'));
            }
            return 0;
        });

        // Re-append to DOM and apply filters
        rowPairs.forEach(pair => {
            tbody.appendChild(pair.item);
            tbody.appendChild(pair.varian);

            const nama = pair.item.getAttribute('data-nama') || '';
            const kategori = pair.item.getAttribute('data-kategori') || '';
            const transaksi = pair.item.getAttribute('data-transaksi') || '';
            const status = pair.item.getAttribute('data-status') || '';
            
            const matchName = nama.includes(query);
            const matchCat = cat === '' || kategori === cat;
            const matchTrans = trans === '' || transaksi === trans;
            const matchStat = stat === '' || status === stat;
            
            if (matchName && matchCat && matchTrans && matchStat) {
                pair.item.style.display = '';
            } else {
                pair.item.style.display = 'none';
                pair.varian.style.display = 'none'; 
                pair.varian.classList.remove('show-row');
                let collapse = pair.varian.querySelector('.collapse');
                if (collapse && collapse.classList.contains('show')) {
                    collapse.classList.remove('show'); 
                }
            }
        });
    }

    if(searchInput) searchInput.addEventListener('input', filterAndSortItems);
    if(filterKategori) filterKategori.addEventListener('change', filterAndSortItems);
    if(filterTransaksi) filterTransaksi.addEventListener('change', filterAndSortItems);
    if(filterStatus) filterStatus.addEventListener('change', filterAndSortItems);
    if(sortUrutan) sortUrutan.addEventListener('change', filterAndSortItems);
});
</script>
