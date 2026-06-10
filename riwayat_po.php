<?php
session_start();
$base_url = '/ontimeadventure/';
require_once __DIR__ . '/config/database.php';
require_once __DIR__ . '/includes/auth_check.php';

$db = getDB();
$id_user = $_SESSION['id_user'];

$pageTitle = 'Riwayat';
require_once __DIR__ . '/includes/header.php';

// Ambil semua PO milik user
$stmt = $db->prepare("
    SELECT * FROM pengajuan_po 
    WHERE id_user = :id_user 
    ORDER BY tgl_pengajuan DESC
");
$stmt->execute(['id_user' => $id_user]);
$riwayat_po = $stmt->fetchAll();

// Ambil item yang bisa diulas (Status Selesai dan tidak dibatalkan)
$stmt_ulasan = $db->prepare("
    SELECT dp.*, p.status_po, p.id_po, p.tgl_selesai_sewa, p.tgl_pengajuan, vi.keterangan_varian, i.nama_brand, i.nama_seri, i.gambar, i.id_item, i.jenis_transaksi,
           ri.id_review, ri.rating, ri.komentar, ri.foto as review_foto, ri.status_review, ri.tanggal as review_tanggal
    FROM detail_po dp
    JOIN pengajuan_po p ON dp.id_po = p.id_po
    JOIN varian_item vi ON dp.id_varian = vi.id_varian
    JOIN item i ON vi.id_item = i.id_item
    LEFT JOIN review_item ri ON ri.id_detail = dp.id_detail
    WHERE p.id_user = :id_user 
      AND p.status_po IN ('Selesai', 'Selesai (Barang Kembali)')
      AND dp.status_detail != 'Dibatalkan'
    ORDER BY p.tgl_pengajuan DESC, dp.id_detail ASC
");
$stmt_ulasan->execute(['id_user' => $id_user]);
$ulasan_items = $stmt_ulasan->fetchAll();

// Fungsi untuk menentukan class badge status
function getStatusBadgeClass($status) {
    switch ($status) {
        case 'Menunggu Pengecekan': return 'bg-warning-transparent text-warning';
        case 'Barang Siap': return 'bg-info-transparent text-info';
        case 'Ada Barang Kosong': return 'bg-danger-transparent text-danger';
        case 'Selesai': return 'bg-success-transparent text-success';
        case 'Dibatalkan': return 'bg-secondary-transparent text-secondary';
        default: return 'bg-light text-dark';
    }
}
?>

<div class="container mt-4 mb-5">
    <!-- Page Header -->
    <div class="d-flex align-items-center justify-content-between mb-4">
        <div>
            <h4 class="mb-1 fw-semibold">Riwayat</h4>
            <p class="text-muted mb-0">Pantau status pengajuan sewa dan ulasan Anda di sini.</p>
        </div>
    </div>

    <!-- Nav Tabs -->
    <ul class="nav nav-tabs mb-4 border-bottom-0" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active fw-semibold fs-15 px-4" id="riwayat-tab" data-bs-toggle="tab" data-bs-target="#tab-riwayat" type="button" role="tab" aria-controls="tab-riwayat" aria-selected="true">Riwayat PO</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link fw-semibold fs-15 px-4" id="ulasan-tab" data-bs-toggle="tab" data-bs-target="#tab-ulasan" type="button" role="tab" aria-controls="tab-ulasan" aria-selected="false">Ulasan</button>
        </li>
    </ul>

    <div class="tab-content" id="myTabContent">
        <!-- Tab Riwayat PO -->
        <div class="tab-pane fade show active" id="tab-riwayat" role="tabpanel" aria-labelledby="riwayat-tab">
            <?php if (empty($riwayat_po)): ?>
        <div class="row justify-content-center">
            <div class="col-md-6 text-center py-5">
                <i class="ri-file-list-3-line fs-100 text-muted mb-3 d-block"></i>
                <h5 class="fw-semibold">Belum ada Riwayat PO</h5>
                <p class="text-muted mb-4">Anda belum pernah melakukan pengajuan sewa.</p>
                <a href="<?= $base_url ?>katalog.php" class="btn btn-primary btn-wave rounded-pill">Lihat Katalog</a>
            </div>
        </div>
    <?php else: ?>
          <div class="d-flex gap-2 mb-4 mt-2 pb-2 overflow-auto hide-scrollbar" style="white-space: nowrap; flex-wrap: nowrap; -webkit-overflow-scrolling: touch;">
              <button class="btn btn-danger rounded-pill px-4 fs-13 btn-filter-po active flex-shrink-0" data-filter="Semua"><i class="ri-list-check align-middle me-1"></i> Semua</button>
              <button class="btn btn-outline-warning rounded-pill px-4 fs-13 btn-filter-po flex-shrink-0" data-filter="Menunggu Pengecekan"><i class="ri-file-list-3-line align-middle me-1"></i> Menunggu Pengecekan</button>
              <button class="btn btn-outline-primary rounded-pill px-4 fs-13 btn-filter-po flex-shrink-0" data-filter="Barang Siap"><i class="ri-box-3-line align-middle me-1"></i> Barang Siap</button>
              <button class="btn btn-outline-success rounded-pill px-4 fs-13 btn-filter-po flex-shrink-0" data-filter="Selesai"><i class="ri-checkbox-circle-line align-middle me-1"></i> Selesai</button>
              <button class="btn btn-outline-secondary rounded-pill px-4 fs-13 btn-filter-po flex-shrink-0" data-filter="Dibatalkan"><i class="ri-close-circle-line align-middle me-1"></i> Dibatalkan</button>
          </div>
          <div class="row" id="riwayat-container">
              <?php foreach ($riwayat_po as $po): ?>
                  <div class="col-12 mb-4 po-item" data-status="<?= htmlspecialchars($po['status_po']) ?>">
                    <div class="card custom-card">
                        <div class="card-header justify-content-between">
                            <div class="card-title">
                                PO-<?= str_pad($po['id_po'], 5, '0', STR_PAD_LEFT) ?>
                                <span class="d-block fs-12 text-muted fw-normal mt-1">Diajukan pada: <?= date('d M Y H:i', strtotime($po['tgl_pengajuan'])) ?></span>
                            </div>
                            <div>
                                <span class="badge <?= getStatusBadgeClass($po['status_po']) ?> fs-12">
                                    <?= htmlspecialchars($po['status_po']) ?>
                                </span>
                            </div>
                        </div>
                        <div class="card-body">
                            <div class="row gy-3 mb-4 bg-light p-3 rounded">
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                    <p class="text-muted fs-13 mb-1">Tanggal Mulai Sewa</p>
                                    <h6 class="fw-semibold mb-0"><?= date('d M Y', strtotime($po['tgl_mulai_sewa'])) ?></h6>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                    <p class="text-muted fs-13 mb-1">Tanggal Selesai Sewa</p>
                                    <h6 class="fw-semibold mb-0"><?= date('d M Y', strtotime($po['tgl_selesai_sewa'])) ?></h6>
                                </div>
                                <?php
                                $mulai = new DateTime($po['tgl_mulai_sewa']);
                                $selesai = new DateTime($po['tgl_selesai_sewa']);
                                $selisih = $mulai->diff($selesai)->days;
                                $selisih_hari = $selisih > 0 ? $selisih : 1;
                                ?>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                    <p class="text-muted fs-13 mb-1">Durasi</p>
                                    <h6 class="fw-semibold mb-0"><?= $selisih_hari ?> Hari</h6>
                                </div>
                                <div class="col-xl-3 col-lg-3 col-md-6 col-sm-6">
                                    <p class="text-muted fs-13 mb-1">Estimasi Total Harga</p>
                                    <h6 class="fw-semibold text-primary mb-0">Rp <?= number_format($po['estimasi_total_harga'], 0, ',', '.') ?></h6>
                                </div>
                            </div>
                            
                            <div class="row mb-4 bg-light p-3 rounded ms-0 me-0">
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-2">Catatan Anda:</h6>
                                    <p class="fs-13 text-muted mb-0"><?= !empty($po['catatan_pelanggan']) ? nl2br(htmlspecialchars($po['catatan_pelanggan'])) : '<i>Tidak ada catatan</i>' ?></p>
                                </div>
                                <div class="col-md-6">
                                    <h6 class="fw-semibold mb-2">Disetujui Oleh:</h6>
                                    <p class="fs-13 text-muted mb-0"><?= !empty($po['admin_penyetuju']) ? htmlspecialchars($po['admin_penyetuju']) : '<i>Belum disetujui</i>' ?></p>
                                    <?php if ($po['status_po'] === 'Selesai/Dibatalkan' || $po['status_po'] === 'Dibatalkan' || $po['status_po'] === 'Ada Barang Kosong'): ?>
                                        <h6 class="fw-semibold mt-3 mb-2 text-danger">Dibatalkan Oleh:</h6>
                                        <p class="fs-13 text-danger mb-0"><?= !empty($po['admin_pembatal']) ? htmlspecialchars($po['admin_pembatal']) : '<i>Sistem/Tidak tercatat</i>' ?></p>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <div class="accordion accordion-customicon1 accordion-primary" id="accordion-<?= $po['id_po'] ?>">
                                <div class="accordion-item">
                                    <h2 class="accordion-header" id="heading-<?= $po['id_po'] ?>">
                                        <button class="accordion-button collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#collapse-<?= $po['id_po'] ?>" aria-expanded="false" aria-controls="collapse-<?= $po['id_po'] ?>">
                                            Lihat Detail Item
                                        </button>
                                    </h2>
                                    <div id="collapse-<?= $po['id_po'] ?>" class="accordion-collapse collapse" aria-labelledby="heading-<?= $po['id_po'] ?>" data-bs-parent="#accordion-<?= $po['id_po'] ?>">
                                        <div class="accordion-body">
                                            <div class="table-responsive">
                                                <table class="table text-nowrap table-bordered text-center">
                                                    <thead class="table-light">
                                                        <tr>
                                                            <th style="width:50px">Gambar</th>
                                                            <th scope="col" class="text-start">Item</th>
                                                            <th scope="col">Varian</th>
                                                            <th scope="col">Harga Satuan</th>
                                                            <th scope="col">Jumlah</th>
                                                            <th scope="col">Subtotal</th>
                                                        </tr>
                                                    </thead>
                                                    <tbody>
                                                        <?php
                                                        $stmt_detail = $db->prepare("
                                                            SELECT dp.*, vi.keterangan_varian, i.nama_brand, i.nama_seri, i.gambar, i.jenis_transaksi, i.id_item,
                                                                   (SELECT id_review FROM review_item ri WHERE ri.id_detail = dp.id_detail LIMIT 1) as has_review
                                                            FROM detail_po dp
                                                            JOIN varian_item vi ON dp.id_varian = vi.id_varian
                                                            JOIN item i ON vi.id_item = i.id_item
                                                            WHERE dp.id_po = :id_po
                                                        ");
                                                        $stmt_detail->execute(['id_po' => $po['id_po']]);
                                                        $details = $stmt_detail->fetchAll();
                                                        
                                                        foreach ($details as $detail):
                                                            $is_batal = ($detail['status_detail'] === 'Dibatalkan');
                                                            $bg_class = $is_batal ? 'bg-danger-transparent text-muted text-decoration-line-through' : '';
                                                            $subtotal = $detail['jenis_transaksi'] === 'Sewa' 
                                                                ? $detail['harga_satuan_saat_pesan'] * $detail['jumlah_pesan'] * $selisih_hari 
                                                                : $detail['harga_satuan_saat_pesan'] * $detail['jumlah_pesan'];
                                                        ?>
                                                            <tr class="<?= $bg_class ?>">
                                                                <td>
                                                                    <?php if (!empty($detail['gambar'])): ?>
                                                                        <img src="assets/img/<?= htmlspecialchars($detail['gambar']) ?>" alt="Item" width="40" height="40" class="rounded border <?= $is_batal ? 'opacity-50' : '' ?>" style="object-fit:cover;">
                                                                    <?php else: ?>
                                                                        <div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted" style="width:40px;height:40px;font-size:10px;">NoImg</div>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td class="text-start">
                                                                    <span class="badge <?= $detail['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> fs-10 p-1 me-1"><?= htmlspecialchars($detail['jenis_transaksi']) ?></span>
                                                                    <span class="fw-semibold text-decoration-none"><?= htmlspecialchars($detail['nama_brand'] . ' ' . $detail['nama_seri']) ?></span>
                                                                    <?php if($is_batal): ?>
                                                                        <br><span class="badge bg-danger mt-1 text-decoration-none" style="font-size:10px;">Batal: <?= htmlspecialchars($detail['alasan_batal']) ?></span>
                                                                    <?php endif; ?>
                                                                </td>
                                                                <td><span class="badge bg-light text-dark <?= $is_batal ? 'text-decoration-line-through' : '' ?>"><?= htmlspecialchars($detail['keterangan_varian']) ?></span></td>
                                                                <td>Rp <?= number_format($detail['harga_satuan_saat_pesan'], 0, ',', '.') ?><?= $detail['jenis_transaksi'] === 'Sewa' ? ' <span class="fs-10 text-muted">/hari</span>' : '' ?></td>
                                                                <td><?= $detail['jumlah_pesan'] ?></td>
                                                                <td class="fw-semibold <?= $is_batal ? 'text-muted' : 'text-primary' ?>">Rp <?= number_format($subtotal, 0, ',', '.') ?></td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    </tbody>
                                                </table>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
        </div> <!-- End Tab Riwayat PO -->

        <!-- Tab Ulasan -->
        <div class="tab-pane fade" id="tab-ulasan" role="tabpanel" aria-labelledby="ulasan-tab">
            <?php if (empty($ulasan_items)): ?>
                <div class="row justify-content-center">
                    <div class="col-md-6 text-center py-5">
                        <i class="ri-star-smile-line fs-100 text-muted mb-3 d-block"></i>
                        <h5 class="fw-semibold">Belum ada barang untuk diulas</h5>
                        <p class="text-muted mb-4">Selesaikan pesanan Anda terlebih dahulu untuk memberikan ulasan.</p>
                    </div>
                </div>
            <?php else: ?>
                <div class="row">
                      <?php foreach ($ulasan_items as $item_ulas): ?>
                          <div class="col-12 mb-3">
                              <div class="card custom-card border border-light shadow-sm">
                                  <div class="card-body p-4">
                                      <div class="d-flex flex-column flex-md-row gap-4">
                                          <?php 
                                          $img_src = '';
                                          if (!empty($item_ulas['gambar'])) {
                                              $img_path = __DIR__ . '/assets/img/' . $item_ulas['gambar'];
                                              if (file_exists($img_path)) {
                                                  $img_src = $base_url . 'assets/img/' . htmlspecialchars($item_ulas['gambar']);
                                              }
                                          }
                                          ?>
                                          <?php if ($img_src): ?>
                                              <img src="<?= $img_src ?>" alt="Item" class="rounded border flex-shrink-0" style="width: 120px; height: 120px; object-fit: cover;">
                                          <?php else: ?>
                                              <div class="bg-light rounded border d-flex align-items-center justify-content-center text-muted flex-shrink-0" style="width: 120px; height: 120px; font-size: 12px;">NoImg</div>
                                          <?php endif; ?>
                                          
                                          <div class="flex-grow-1 w-100">
                                              <?php
                                              $has_review = !empty($item_ulas['id_review']);
                                              $is_editable = false;
                                              // Edit bisa dilakukan HANYA jika komentar dinonaktifkan (krn kata kotor) dan belum lewat 30 hari
                                              if ($has_review && $item_ulas['status_review'] === 'Nonaktif' && strtotime($item_ulas['tgl_selesai_sewa']) >= strtotime('-30 days')) {
                                                  $is_editable = true;
                                              }
                                              ?>
                                              
                                              <div class="d-flex flex-column flex-sm-row justify-content-between align-items-start mb-2">
                                                  <div>
                                                      <span class="badge <?= $item_ulas['jenis_transaksi'] === 'Beli' ? 'bg-danger' : 'bg-success' ?> fs-10 p-1 mb-2"><?= htmlspecialchars($item_ulas['jenis_transaksi']) ?></span>
                                                      <h5 class="fw-bold mb-1 text-dark"><?= htmlspecialchars($item_ulas['nama_brand'] . ' ' . $item_ulas['nama_seri']) ?></h5>
                                                      <p class="fs-13 text-muted mb-0">Varian: <?= htmlspecialchars($item_ulas['keterangan_varian']) ?></p>
                                                  </div>
                                                  
                                                  <div class="mt-2 mt-sm-0 flex-shrink-0">
                                                      <?php if (!$has_review): ?>
                                                          <button type="button" class="btn btn-outline-primary fw-semibold px-4 btn-review" 
                                                              data-iddetail="<?= $item_ulas['id_detail'] ?>"
                                                              data-idpo="<?= $item_ulas['id_po'] ?>"
                                                              data-iditem="<?= $item_ulas['id_item'] ?>"
                                                              data-namabrand="<?= htmlspecialchars($item_ulas['nama_brand'] . ' ' . $item_ulas['nama_seri']) ?>"
                                                              data-bs-toggle="modal" data-bs-target="#reviewModal">
                                                              Beri Ulasan
                                                          </button>
                                                      <?php else: ?>
                                                          <?php if ($is_editable): ?>
                                                          <button type="button" class="btn btn-outline-secondary fw-semibold px-4 btn-review" 
                                                              data-idreview="<?= $item_ulas['id_review'] ?>"
                                                              data-iddetail="<?= $item_ulas['id_detail'] ?>"
                                                              data-idpo="<?= $item_ulas['id_po'] ?>"
                                                              data-iditem="<?= $item_ulas['id_item'] ?>"
                                                              data-namabrand="<?= htmlspecialchars($item_ulas['nama_brand'] . ' ' . $item_ulas['nama_seri']) ?>"
                                                              data-rating="<?= $item_ulas['rating'] ?>"
                                                              data-komentar="<?= htmlspecialchars($item_ulas['komentar'] ?? '') ?>"
                                                              data-bs-toggle="modal" data-bs-target="#reviewModal">
                                                              Edit Ulasan
                                                          </button>
                                                          <?php endif; ?>
                                                      <?php endif; ?>
                                                  </div>
                                              </div>
                                              
                                              <?php if ($has_review): ?>
                                                  <div class="mt-3">
                                                      <div class="d-flex align-items-center gap-2 mb-2">
                                                          <h5 class="fw-bold mb-0 text-dark fs-18"><?= $item_ulas['rating'] ?></h5>
                                                          <div class="text-warning fs-18">
                                                              <?php for ($i=1; $i<=5; $i++): ?>
                                                                  <i class="<?= $i <= $item_ulas['rating'] ? 'ri-star-fill' : 'ri-star-line text-muted opacity-25' ?>"></i>
                                                              <?php endfor; ?>
                                                          </div>
                                                      </div>
                                                      <?php if ($item_ulas['status_review'] === 'Nonaktif'): ?>
                                                          <p class="fs-14 text-danger mb-2"><i class="ri-error-warning-line align-middle"></i> Komentar Anda disembunyikan karena terindikasi mengandung bahasa tidak pantas.</p>
                                                      <?php else: ?>
                                                          <p class="fs-14 text-dark mb-2"><?= nl2br(htmlspecialchars($item_ulas['komentar'] ?? '')) ?></p>
                                                      <?php endif; ?>
                                                      
                                                      <?php if (!empty($item_ulas['review_foto'])): ?>
                                                          <div class="mb-2">
                                                              <img src="<?= $base_url ?>assets/img/reviews/<?= htmlspecialchars($item_ulas['review_foto']) ?>" alt="Foto Ulasan" class="rounded border" style="width: 100px; height: 100px; object-fit: cover; cursor: pointer;" onclick="window.open(this.src, '_blank')">
                                                          </div>
                                                      <?php endif; ?>
                                                      
                                                      <p class="fs-12 text-muted mb-0"><i class="ri-calendar-line align-middle me-1"></i> <?= date('d M Y', strtotime($item_ulas['review_tanggal'] ?? $item_ulas['tgl_selesai_sewa'])) ?></p>
                                                  </div>
                                              <?php endif; ?>
                                              
                                          </div>
                                      </div>
                                  </div>
                              </div>
                          </div>
                      <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div> <!-- End Tab Ulasan -->
    </div> <!-- End Tab Content -->
</div>

<!-- Modal Review -->
<div class="modal fade" id="reviewModal" tabindex="-1" aria-labelledby="reviewModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header bg-warning text-white">
                <h5 class="modal-title fs-16" id="reviewModalLabel"><i class="ri-star-smile-line me-1"></i> Beri Ulasan</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="submit_review.php" method="POST" enctype="multipart/form-data" id="reviewForm">
                <div class="modal-body bg-light">
                    <input type="hidden" name="id_po" id="id_po">
                    <input type="hidden" name="id_detail" id="id_detail">
                    <input type="hidden" name="id_item" id="id_item">
                    <input type="hidden" name="rating" id="rating_value" value="0">
                    
                    <p class="mb-3 text-center fs-15 fw-semibold text-primary" id="reviewItemName"></p>
                    
                    <div class="mb-3 text-center">
                        <label class="form-label fw-semibold d-block">Rating Anda <span class="text-danger">*</span></label>
                        <div class="rating-stars d-flex justify-content-center gap-2 fs-24 text-muted cursor-pointer" id="starContainer">
                            <i class="ri-star-line star-rating" data-val="1"></i>
                            <i class="ri-star-line star-rating" data-val="2"></i>
                            <i class="ri-star-line star-rating" data-val="3"></i>
                            <i class="ri-star-line star-rating" data-val="4"></i>
                            <i class="ri-star-line star-rating" data-val="5"></i>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-13">Komentar (Opsional)</label>
                        <textarea class="form-control" name="komentar" id="komentar" rows="3" placeholder="Bagaimana pengalaman Anda menggunakan barang ini?"></textarea>
                    </div>

                    <div class="mb-3">
                        <label class="form-label fs-13">Foto Barang (Opsional)</label>
                        <input type="file" class="form-control" name="foto" accept="image/jpeg, image/png, image/webp">
                        <small class="text-muted fs-11">Maks. 2MB (JPG/PNG/WEBP)</small>
                    </div>
                </div>
                <div class="modal-footer bg-light">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-warning text-white fw-semibold">Kirim Ulasan</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Modal Data Setter using Event Delegation
    document.querySelectorAll('.btn-review').forEach(btn => {
        btn.addEventListener('click', function() {
            let id_detail = this.getAttribute('data-iddetail');
            let id_po = this.getAttribute('data-idpo');
            let id_item = this.getAttribute('data-iditem');
            let nama_brand = this.getAttribute('data-namabrand');
            
            document.getElementById('id_detail').value = id_detail;
            document.getElementById('id_po').value = id_po;
            document.getElementById('id_item').value = id_item;
            document.getElementById('reviewItemName').innerText = nama_brand;
            
            // Handle Edit Mode
            let id_review = this.getAttribute('data-idreview');
            let rating = this.getAttribute('data-rating');
            let komentar = this.getAttribute('data-komentar');
            
            if (id_review) {
                let hiddenInput = document.getElementById('id_review');
                if(!hiddenInput) {
                    hiddenInput = document.createElement('input');
                    hiddenInput.type = 'hidden';
                    hiddenInput.name = 'id_review';
                    hiddenInput.id = 'id_review';
                    document.getElementById('reviewForm').appendChild(hiddenInput);
                }
                hiddenInput.value = id_review;
                document.getElementById('komentar').value = komentar;
                
                if (rating) {
                    document.querySelectorAll('.star-rating').forEach(star => {
                        let val = parseInt(star.getAttribute('data-val'));
                        if (val <= rating) {
                            star.classList.replace('ri-star-line', 'ri-star-fill');
                            star.classList.replace('text-muted', 'text-warning');
                        } else {
                            star.classList.replace('ri-star-fill', 'ri-star-line');
                            star.classList.replace('text-warning', 'text-muted');
                        }
                    });
                    document.getElementById('rating_value').value = rating;
                }
            } else {
                let hiddenInput = document.getElementById('id_review');
                if(hiddenInput) hiddenInput.remove();
                
                document.getElementById('komentar').value = '';
                document.getElementById('rating_value').value = '0';
                document.querySelectorAll('.star-rating').forEach(star => {
                    star.classList.replace('ri-star-fill', 'ri-star-line');
                    star.classList.replace('text-warning', 'text-muted');
                });
            }
        });
    });

    // Star Click Logic
    document.querySelectorAll('.star-rating').forEach(star => {
        star.addEventListener('click', function() {
            let val = parseInt(this.getAttribute('data-val'));
            document.getElementById('rating_value').value = val;
            
            document.querySelectorAll('.star-rating').forEach(s => {
                let sVal = parseInt(s.getAttribute('data-val'));
                if (sVal <= val) {
                    s.classList.remove('ri-star-line', 'text-muted');
                    s.classList.add('ri-star-fill', 'text-warning');
                } else {
                    s.classList.remove('ri-star-fill', 'text-warning');
                    s.classList.add('ri-star-line', 'text-muted');
                }
            });
        });
        
        // Add hover effect
        star.style.cursor = 'pointer';
    });
});
</script>

<script>
  let currentFilterValue = 'Semua';

  function applyCurrentFilter() {
      const poItems = document.querySelectorAll('.po-item');
      poItems.forEach(item => {
          if (currentFilterValue === 'Semua') {
              item.style.display = 'block';
          } else {
              const itemStatus = item.getAttribute('data-status');
              if (itemStatus && (itemStatus.includes(currentFilterValue) || itemStatus === currentFilterValue)) {
                  item.style.display = 'block';
              } else {
                  item.style.display = 'none';
              }
          }
      });
  }

  document.addEventListener('DOMContentLoaded', function() {
      const filterBtns = document.querySelectorAll('.btn-filter-po');
      const filterConfig = {
          'Semua': 'danger',
          'Menunggu Pengecekan': 'warning',
          'Barang Siap': 'primary',
          'Selesai': 'success',
          'Dibatalkan': 'secondary'
      };

      filterBtns.forEach(btn => {
          btn.addEventListener('click', function() {
              filterBtns.forEach(b => {
                  b.classList.remove('active');
                  const type = filterConfig[b.getAttribute('data-filter')];
                  b.classList.remove(`btn-${type}`);
                  if (!b.classList.contains(`btn-outline-${type}`)) {
                      b.classList.add(`btn-outline-${type}`);
                  }
              });
              
              this.classList.add('active');
              const type = filterConfig[this.getAttribute('data-filter')];
              this.classList.remove(`btn-outline-${type}`);
              this.classList.add(`btn-${type}`);

              currentFilterValue = this.getAttribute('data-filter');
              applyCurrentFilter();
          });
      });
  });

  // Skrip AJAX Polling untuk Real-time Updates
  setInterval(() => {
      fetch(window.location.href)
      .then(response => response.text())
      .then(html => {
          let parser = new DOMParser();
          let doc = parser.parseFromString(html, 'text/html');
          let newContainer = doc.getElementById('riwayat-container');
          let oldContainer = document.getElementById('riwayat-container');
          
          if (newContainer && oldContainer) {
              // Simpan status accordion yang sedang terbuka
              let openAccordions = [];
              oldContainer.querySelectorAll('.accordion-collapse.show').forEach(acc => {
                  openAccordions.push(acc.id);
              });
              
              // Ganti isi HTML dengan yang terbaru
              oldContainer.innerHTML = newContainer.innerHTML;
              
              // Kembalikan status accordion yang terbuka
              openAccordions.forEach(id => {
                  let acc = document.getElementById(id);
                  if(acc) {
                      acc.classList.add('show');
                      let btn = document.querySelector(`[data-bs-target="#${id}"]`);
                      if(btn) {
                          btn.classList.remove('collapsed');
                          btn.setAttribute('aria-expanded', 'true');
                      }
                  }
              });
              
              // Terapkan kembali filter yang sedang aktif
              applyCurrentFilter();
          }
      })
      .catch(err => console.error('Gagal mengambil pembaruan:', err));
  }, 3000);
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
