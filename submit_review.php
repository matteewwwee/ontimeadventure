<?php
session_start();
require_once __DIR__ . '/config/database.php';

// Ensure user is logged in
if (!isset($_SESSION['id_user'])) {
    $_SESSION['flash_error'] = 'Anda harus login terlebih dahulu.';
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_user = $_SESSION['id_user'];
    $id_review = (int)($_POST['id_review'] ?? 0);
    $id_po = (int)($_POST['id_po'] ?? 0);
    $id_detail = (int)($_POST['id_detail'] ?? 0);
    $id_item = (int)($_POST['id_item'] ?? 0);
    $rating = (int)($_POST['rating'] ?? 0);
    $komentar = trim($_POST['komentar'] ?? '');

    // Validation
    if ($rating < 1 || $rating > 5) {
        $_SESSION['flash_error'] = 'Rating tidak valid.';
        header("Location: riwayat_po.php");
        exit;
    }

    try {
        $db = getDB();

        // 1. Verify that the user owns this PO and it's 'Selesai'
        $stmt_po = $db->prepare("
            SELECT status_po 
            FROM pengajuan_po 
            WHERE id_po = ? AND id_user = ?
        ");
        $stmt_po->execute([$id_po, $id_user]);
        $po = $stmt_po->fetch();

        if (!$po || !in_array($po['status_po'], ['Selesai (Barang Kembali)', 'Selesai'])) {
            $_SESSION['flash_error'] = 'Pesanan tidak valid atau belum selesai.';
            header("Location: riwayat_po.php");
            exit;
        }

        $existing_review = null;
        if ($id_review > 0) {
            // Edit mode: cek kepemilikan ulasan
            $stmt_cek = $db->prepare("SELECT id_review, foto FROM review_item WHERE id_review = ? AND id_user = ?");
            $stmt_cek->execute([$id_review, $id_user]);
            $existing_review = $stmt_cek->fetch();
            if (!$existing_review) {
                $_SESSION['flash_error'] = 'Ulasan tidak ditemukan atau Anda tidak berhak mengubahnya.';
                header("Location: riwayat_po.php");
                exit;
            }
        } else {
            // Insert mode: cek apakah sudah pernah diulas
            $stmt_cek = $db->prepare("SELECT id_review FROM review_item WHERE id_detail = ?");
            $stmt_cek->execute([$id_detail]);
            if ($stmt_cek->fetch()) {
                $_SESSION['flash_error'] = 'Anda sudah memberikan ulasan untuk barang ini.';
                header("Location: riwayat_po.php");
                exit;
            }
        }

        // 2. Auto-Filter Logic for Bad Words
        $stmt_kata = $db->query("SELECT kata FROM filter_kata");
        $bad_words = $stmt_kata->fetchAll(PDO::FETCH_COLUMN);
        
        $status_review = 'Aktif';
        $komentar_lower = strtolower($komentar);
        foreach ($bad_words as $word) {
            if (strpos($komentar_lower, strtolower($word)) !== false) {
                $status_review = 'Nonaktif';
                break;
            }
        }

        // 3. Handle File Upload (Optional)
        $foto_name = null;
        if (isset($_FILES['foto']) && $_FILES['foto']['error'] === UPLOAD_ERR_OK) {
            $allowed_ext = ['jpg', 'jpeg', 'png', 'webp'];
            $file_ext = strtolower(pathinfo($_FILES['foto']['name'], PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_ext)) {
                $_SESSION['flash_error'] = 'Format foto harus JPG, PNG, atau WEBP.';
                header("Location: riwayat_po.php");
                exit;
            }

            if ($_FILES['foto']['size'] > 2 * 1024 * 1024) { // Max 2MB
                $_SESSION['flash_error'] = 'Ukuran foto maksimal 2MB.';
                header("Location: riwayat_po.php");
                exit;
            }

            // Create unique filename
            $foto_name = time() . '_' . uniqid() . '.' . $file_ext;
            $tujuan = __DIR__ . '/assets/img/reviews/' . $foto_name;
            
            if (!move_uploaded_file($_FILES['foto']['tmp_name'], $tujuan)) {
                $_SESSION['flash_error'] = 'Gagal mengunggah foto.';
                header("Location: riwayat_po.php");
                exit;
            }
        }

        // 4. Save to Database
        if ($id_review > 0) {
            // Update mode
            if ($foto_name) {
                // Hapus foto lama jika ada
                if (!empty($existing_review['foto'])) {
                    $old_path = __DIR__ . '/assets/img/reviews/' . $existing_review['foto'];
                    if (file_exists($old_path)) unlink($old_path);
                }
                $stmt_upd = $db->prepare("UPDATE review_item SET rating = ?, komentar = ?, foto = ?, status_review = ? WHERE id_review = ?");
                $stmt_upd->execute([$rating, $komentar, $foto_name, $status_review, $id_review]);
            } else {
                $stmt_upd = $db->prepare("UPDATE review_item SET rating = ?, komentar = ?, status_review = ? WHERE id_review = ?");
                $stmt_upd->execute([$rating, $komentar, $status_review, $id_review]);
            }
            $_SESSION['flash_success'] = 'Ulasan Anda berhasil diperbarui!';
        } else {
            // Insert mode
            $stmt_insert = $db->prepare("
                INSERT INTO review_item (id_detail, id_po, id_item, id_user, rating, komentar, foto, status_review) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt_insert->execute([$id_detail, $id_po, $id_item, $id_user, $rating, $komentar, $foto_name, $status_review]);
            $_SESSION['flash_success'] = 'Terima kasih atas ulasan Anda!';
        }
        
    } catch (Exception $e) {
        $_SESSION['flash_error'] = 'Terjadi kesalahan: ' . $e->getMessage();
    }

    header("Location: riwayat_po.php");
    exit;
} else {
    header('Location: riwayat_po.php');
    exit;
}
