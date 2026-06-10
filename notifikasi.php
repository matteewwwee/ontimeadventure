<?php
/**
 * notifikasi.php - Halaman Daftar Notifikasi
 */
session_start();
require_once __DIR__ . '/config/database.php';

// Cek login
if (!isset($_SESSION['id_user'])) {
    $_SESSION['flash_error'] = 'Silakan login untuk melihat notifikasi Anda.';
    header('Location: login.php');
    exit;
}

$db = getDB();
$base_url = '/ontimeadventure/';
$id_user = $_SESSION['id_user'];

// Tandai semua notifikasi sebagai sudah dibaca saat halaman dibuka
$stmt_update = $db->prepare("UPDATE notifikasi SET is_read = 1 WHERE id_user = ? AND is_read = 0");
$stmt_update->execute([$id_user]);

// Query semua notifikasi
$stmt_notif = $db->prepare("SELECT * FROM notifikasi WHERE id_user = ? ORDER BY created_at DESC LIMIT 50");
$stmt_notif->execute([$id_user]);
$notifs = $stmt_notif->fetchAll();

$pageTitle = 'Notifikasi Saya';
require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-lg-8 col-md-10">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h4 class="fw-bold mb-1"><i class="ri-notification-3-fill text-primary me-2"></i>Notifikasi</h4>
                    <p class="text-muted fs-14 mb-0">Pembaruan terbaru tentang pesanan Anda.</p>
                </div>
            </div>

            <div class="card custom-card border-0 shadow-sm">
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php if (empty($notifs)): ?>
                            <div class="p-5 text-center text-muted">
                                <i class="ri-notification-off-line fs-50 mb-3"></i>
                                <h5>Belum ada notifikasi</h5>
                                <p>Anda belum memiliki pemberitahuan apapun saat ini.</p>
                            </div>
                        <?php else: ?>
                            <?php foreach ($notifs as $n): 
                                $bgClass = $n['is_read'] == 0 ? 'bg-primary-transparent' : '';
                                $link = !empty($n['tautan']) ? $n['tautan'] : 'javascript:void(0);';
                                $time = date('d M Y, H:i', strtotime($n['created_at']));
                            ?>
                                <a href="<?= htmlspecialchars($link) ?>" class="list-group-item list-group-item-action d-flex p-4 <?= $bgClass ?>">
                                    <div class="me-3">
                                        <span class="avatar avatar-md bg-primary rounded-circle"><i class="ri-information-line fs-20 text-white"></i></span>
                                    </div>
                                    <div class="flex-grow-1">
                                        <div class="d-flex justify-content-between align-items-center mb-1">
                                            <h6 class="mb-0 fw-semibold fs-15"><?= htmlspecialchars($n['judul']) ?></h6>
                                            <span class="text-muted fs-12"><i class="ri-time-line me-1"></i><?= $time ?></span>
                                        </div>
                                        <p class="mb-0 text-muted fs-14"><?= htmlspecialchars($n['pesan']) ?></p>
                                    </div>
                                </a>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
