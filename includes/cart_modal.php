<?php
/**
 * cart_modal.php - Modal Keranjang PO
 */
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// ── Fetch cart item details from DB ──
if (!function_exists('generateCsrfToken')) {
    function generateCsrfToken() {
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }
}

$cartItems   = [];
$grandTotal  = 0;
$csrfToken   = generateCsrfToken();

if (!empty($_SESSION['keranjang'])) {
    $ids          = array_keys($_SESSION['keranjang']);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    $sql = "SELECT v.id_varian, v.keterangan_varian, v.harga_sewa_per_hari, v.stok_tersedia,
                   v.catatan_kondisi, i.nama_brand, i.nama_seri, i.gambar, i.jenis_transaksi
            FROM varian_item v
            JOIN item i ON v.id_item = i.id_item
            WHERE v.id_varian IN ($placeholders)";

    $stmt = $db->prepare($sql);
    $stmt->execute($ids);
    $rows = $stmt->fetchAll();

    foreach ($rows as $row) {
        $qty = $_SESSION['keranjang'][$row['id_varian']];
        $row['jumlah']   = $qty;
        $row['subtotal'] = $row['harga_sewa_per_hari'] * $qty;
        $grandTotal     += $row['subtotal'];
        $cartItems[]     = $row;
    }

    $validIds = array_column($rows, 'id_varian');
    foreach ($ids as $id) {
        if (!in_array($id, $validIds)) {
            unset($_SESSION['keranjang'][$id]);
        }
    }
}
?>

<!-- Cart Modal -->
<div class="modal fade" id="cartModal" tabindex="-1" aria-labelledby="cartModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title d-flex align-items-center" id="cartModalLabel">
                    <i class="ri-shopping-cart-2-line me-2 fs-20"></i> Keranjang Pre-Order
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4 bg-light">
                
                <?php if (empty($cartItems)): ?>
                    <div class="text-center py-5">
                        <i class="ri-shopping-cart-2-line fs-80 text-muted mb-3 d-block"></i>
                        <h5 class="fw-semibold">Keranjang Masih Kosong</h5>
                        <p class="text-muted mb-4">Belum ada item di keranjang. Jelajahi katalog kami untuk menemukan peralatan camping yang Anda butuhkan.</p>
                        <a href="<?= $base_url ?>katalog.php" class="btn btn-primary btn-wave rounded-pill">⛺ Jelajahi Katalog</a>
                    </div>
                <?php else: ?>
                    <div class="row g-4">
                        <!-- Cart Table -->
                        <div class="col-xl-8 col-lg-12">
                            <div class="card custom-card h-100 mb-0 shadow-sm border-0">
                                <div class="card-header border-bottom">
                                    <div class="card-title">Daftar Item</div>
                                </div>
                                <div class="card-body p-0">
                                    <ul class="list-group list-group-flush">
                                        <?php foreach ($cartItems as $item): ?>
                                            <li class="list-group-item p-3">
                                                <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start align-items-sm-center gap-3">
                                                    
                                                    <!-- Item Details -->
                                                    <div class="d-flex align-items-center gap-3">
                                                        <?php
                                                        $has_image = false;
                                                        $img_path = '';
                                                        if (!empty($item['gambar'])) {
                                                            $file_path = __DIR__ . '/../assets/img/' . $item['gambar'];
                                                            if (file_exists($file_path)) {
                                                                $has_image = true;
                                                                $img_path = $base_url . 'assets/img/' . htmlspecialchars($item['gambar']);
                                                            }
                                                        }
                                                        ?>
                                                        <?php if ($has_image): ?>
                                                            <img src="<?= $img_path ?>" alt="Img" class="rounded shadow-sm" style="width: 70px; height: 70px; object-fit: cover;">
                                                        <?php else: ?>
                                                            <div class="bg-light rounded d-flex align-items-center justify-content-center shadow-sm" style="width: 70px; height: 70px;">
                                                                <i class="ri-image-line text-muted fs-24"></i>
                                                            </div>
                                                        <?php endif; ?>
                                                        <div>
                                                            <h6 class="mb-1 fw-bold text-wrap" style="max-width: 250px; line-height: 1.3;">
                                                                <span class="badge <?= $item['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> p-1 ms-0 me-1" style="font-size: 10px;"><?= htmlspecialchars($item['jenis_transaksi']) ?></span>
                                                                <?= htmlspecialchars($item['nama_brand'] . ' ' . $item['nama_seri']) ?>
                                                            </h6>
                                                            <span class="badge bg-primary-transparent mb-1"><?= htmlspecialchars($item['keterangan_varian']) ?></span>
                                                            <div class="text-muted fs-13">Harga: Rp <?= number_format($item['harga_sewa_per_hari'], 0, ',', '.') ?>
                                                                <?php if($item['jenis_transaksi'] === 'Sewa'): ?> / hari<?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>

                                                    <!-- Controls & Price -->
                                                    <div class="d-flex flex-row flex-sm-column align-items-center align-items-sm-end justify-content-between w-100 w-sm-auto mt-2 mt-sm-0">
                                                        <div class="fw-bold text-primary fs-15 mb-sm-2 mb-0 order-2 order-sm-1">
                                                            Sub: Rp <?= number_format($item['subtotal'], 0, ',', '.') ?>
                                                        </div>
                                                        
                                                        <div class="d-flex align-items-center gap-2 order-1 order-sm-2">
                                                            <!-- Quantity Control -->
                                                            <div class="input-group input-group-sm" style="width: 100px;">
                                                                <form method="POST" action="<?= $base_url ?>keranjang_po.php" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                    <input type="hidden" name="action" value="update">
                                                                    <input type="hidden" name="id_varian" value="<?= $item['id_varian'] ?>">
                                                                    <input type="hidden" name="jumlah" value="<?= $item['jumlah'] - 1 ?>">
                                                                    <button class="btn btn-outline-primary px-2" type="submit">-</button>
                                                                </form>
                                                                <input type="text" class="form-control text-center px-1 fw-bold" value="<?= $item['jumlah'] ?>" readonly>
                                                                <form method="POST" action="<?= $base_url ?>keranjang_po.php" class="d-inline">
                                                                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                    <input type="hidden" name="action" value="update">
                                                                    <input type="hidden" name="id_varian" value="<?= $item['id_varian'] ?>">
                                                                    <input type="hidden" name="jumlah" value="<?= $item['jumlah'] + 1 ?>">
                                                                    <button class="btn btn-outline-primary px-2" type="submit" <?= $item['jumlah'] >= $item['stok_tersedia'] ? 'disabled' : '' ?>>+</button>
                                                                </form>
                                                            </div>
                                                            
                                                            <!-- Delete Button -->
                                                            <form method="POST" action="<?= $base_url ?>keranjang_po.php" onsubmit="return confirm('Hapus item ini dari keranjang?');" class="ms-2">
                                                                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                                                                <input type="hidden" name="action" value="hapus">
                                                                <input type="hidden" name="id_varian" value="<?= $item['id_varian'] ?>">
                                                                <button type="submit" class="btn btn-sm btn-icon btn-danger-light btn-wave rounded-circle shadow-sm">
                                                                    <i class="ri-delete-bin-line"></i>
                                                                </button>
                                                            </form>
                                                        </div>
                                                    </div>

                                                </div>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                </div>
                            </div>
                        </div>

                        <!-- PO Submission Form -->
                        <div class="col-xl-4 col-lg-12">
                            <div class="card custom-card h-100 mb-0 shadow-sm border-0 bg-primary-transparent">
                                <div class="card-header border-bottom-0 pb-2">
                                    <div class="card-title fw-bold text-primary">📋 Ringkasan & Pengajuan</div>
                                </div>
                                <div class="card-body">
                                    <form action="<?= $base_url ?>submit_po.php" method="POST" id="po-form-modal">
                                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                                        <div class="mb-3">
                                            <label for="tgl_mulai_modal" class="form-label text-dark fw-semibold">Tanggal Mulai Sewa</label>
                                            <input type="date" id="tgl_mulai_modal" name="tgl_mulai_sewa" class="form-control border-primary border-opacity-25" required min="<?= date('Y-m-d') ?>">
                                        </div>
                                        <div class="mb-3">
                                            <label for="tgl_selesai_modal" class="form-label text-dark fw-semibold">Tanggal Selesai Sewa</label>
                                            <input type="date" id="tgl_selesai_modal" name="tgl_selesai_sewa" class="form-control border-primary border-opacity-25" required min="<?= date('Y-m-d') ?>">
                                        </div>

                                        <div class="mb-4">
                                            <label for="catatan_pelanggan" class="form-label text-dark fw-semibold">Catatan Tambahan <span class="text-muted fw-normal fs-12">(Opsional)</span></label>
                                            <textarea id="catatan_pelanggan" name="catatan_pelanggan" class="form-control border-primary border-opacity-25" rows="2" placeholder="Contoh: Ambil jam 3 sore ya..."></textarea>
                                        </div>

                                        <div class="card custom-card bg-white shadow-sm mb-4">
                                            <div class="card-body p-3">
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="text-muted fs-14">Durasi Sewa</span>
                                                    <span class="fw-semibold fs-14" id="durasi-display-modal">— hari</span>
                                                </div>
                                                <div class="d-flex justify-content-between mb-2">
                                                    <span class="text-muted fs-14">Total Harga/Hari</span>
                                                    <span class="fw-semibold fs-14">Rp <?= number_format($grandTotal, 0, ',', '.') ?></span>
                                                </div>
                                                <hr class="border-dashed">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span class="fw-bold fs-15 text-dark">Estimasi Total</span>
                                                    <span class="fw-bold fs-22 text-primary" id="estimasi-total-modal">Rp 0</span>
                                                </div>
                                            </div>
                                        </div>

                                        <button type="submit" class="btn btn-primary btn-wave w-100 btn-lg shadow-sm" id="submit-po-btn-modal" disabled>
                                            <i class="ri-send-plane-line me-2"></i> Ajukan Pre-Order
                                        </button>
                                        <p class="text-center text-muted fs-11 mt-3 mb-0"><i class="ri-information-line"></i> Pastikan tanggal sewa sudah benar.</p>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const tglMulai    = document.getElementById('tgl_mulai_modal');
    const tglSelesai  = document.getElementById('tgl_selesai_modal');
    const durasiDisp  = document.getElementById('durasi-display-modal');
    const estimasiEl  = document.getElementById('estimasi-total-modal');
    const submitBtn   = document.getElementById('submit-po-btn-modal');

    if (!tglMulai || !tglSelesai) return;

    const grandTotalPerDay = <?= $grandTotal ?>;

    function formatRupiah(num) {
        return 'Rp ' + num.toLocaleString('id-ID');
    }

    function calculate() {
        const mulai   = tglMulai.value;
        const selesai = tglSelesai.value;

        if (!mulai || !selesai) {
            durasiDisp.textContent = '— hari';
            estimasiEl.textContent = 'Rp 0';
            submitBtn.disabled = true;
            return;
        }

        const d1    = new Date(mulai);
        const d2    = new Date(selesai);
        const diff  = Math.ceil((d2 - d1) / (1000 * 60 * 60 * 24));

        if (diff <= 0) {
            durasiDisp.textContent = 'Tanggal tidak valid';
            durasiDisp.classList.add('text-danger');
            estimasiEl.textContent = 'Rp 0';
            submitBtn.disabled = true;
            return;
        }

        durasiDisp.classList.remove('text-danger');
        durasiDisp.textContent = diff + ' hari';

        const total = grandTotalPerDay * diff;
        estimasiEl.textContent = formatRupiah(total);
        submitBtn.disabled = false;
    }

    tglMulai.addEventListener('change', function () {
        if (this.value) {
            const nextDay = new Date(this.value);
            nextDay.setDate(nextDay.getDate() + 1);
            tglSelesai.min = nextDay.toISOString().split('T')[0];

            if (tglSelesai.value && tglSelesai.value <= this.value) {
                tglSelesai.value = '';
            }
        }
        calculate();
    });

    tglSelesai.addEventListener('change', calculate);
    
    <?php if (isset($_SESSION['open_cart']) && $_SESSION['open_cart']): ?>
    var cartModal = new bootstrap.Modal(document.getElementById('cartModal'));
    cartModal.show();
    <?php unset($_SESSION['open_cart']); endif; ?>
});
</script>
