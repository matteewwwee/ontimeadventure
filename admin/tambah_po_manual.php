<?php
session_start();
$base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false || strpos($_SERVER['HTTP_HOST'], 'ngrok') !== false) ? '/ontimeadventure/' : '/';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth_check.php';

requireAdmin();
$db = getDB();

// Proses Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_po'])) {
    $no_hp = trim($_POST['no_hp'] ?? '');
    $nama = trim($_POST['nama'] ?? '');
    $tgl_mulai = $_POST['tgl_mulai_sewa'] ?? '';
    $tgl_selesai = $_POST['tgl_selesai_sewa'] ?? '';
    $items = $_POST['items'] ?? []; // Array of ['id_varian' => x, 'jumlah' => y, 'harga' => z]
    $catatan = trim($_POST['catatan_pelanggan'] ?? '');
    
    if (empty($no_hp) || empty($nama) || empty($tgl_mulai) || empty($tgl_selesai) || empty($items)) {
        $_SESSION['flash_error'] = "Semua field wajib diisi dan minimal pilih 1 barang!";
    } else {
        try {
            $db->beginTransaction();
            
            // Cek user by no_hp
            $stmt = $db->prepare("SELECT id_user FROM users WHERE no_hp = ?");
            $stmt->execute([$no_hp]);
            $user = $stmt->fetch();
            
            if ($user) {
                $id_user = $user['id_user'];
            } else {
                // Buat user baru
                $pin = password_hash('1234', PASSWORD_DEFAULT);
                $stmt = $db->prepare("INSERT INTO users (no_hp, pin, nama, role) VALUES (?, ?, ?, 'pelanggan')");
                $stmt->execute([$no_hp, $pin, $nama]);
                $id_user = $db->lastInsertId();
            }
            
            // Hitung selisih hari
            $start = new DateTime($tgl_mulai);
            $end = new DateTime($tgl_selesai);
            $diff = $start->diff($end)->days;
            if ($diff < 1) $diff = 1; // Minimal 1 hari sewa
            
            // Hitung estimasi harga
            $total_harga = 0;
            foreach ($items as $item) {
                $total_harga += ((int)$item['harga'] * (int)$item['jumlah'] * $diff);
            }
            
            // Insert PO
            $admin_nama = $_SESSION['user']['nama'] ?? 'Admin';
            $stmt = $db->prepare("INSERT INTO pengajuan_po (id_user, tgl_mulai_sewa, tgl_selesai_sewa, estimasi_total_harga, catatan_pelanggan, status_po, waktu_diambil, admin_penyetuju, admin_penyerah) VALUES (?, ?, ?, ?, ?, 'Barang Diambil', NOW(), ?, ?)");
            $stmt->execute([$id_user, $tgl_mulai, $tgl_selesai, $total_harga, $catatan, $admin_nama, $admin_nama]);
            $id_po = $db->lastInsertId();
            
            // Insert Detail PO
            $stmt_detail = $db->prepare("INSERT INTO detail_po (id_po, id_varian, jumlah_pesan, harga_satuan_saat_pesan) VALUES (?, ?, ?, ?)");
            foreach ($items as $item) {
                $stmt_detail->execute([$id_po, $item['id_varian'], $item['jumlah'], $item['harga']]);
            }
            
            $db->commit();
            $_SESSION['flash_success'] = "Pesanan manual berhasil dibuat dan status menjadi 'Barang Diambil'.";
            header("Location: kelola_po.php");
            exit;
        } catch (Exception $e) {
            $db->rollBack();
            $_SESSION['flash_error'] = "Gagal membuat pesanan: " . $e->getMessage();
        }
    }
}

// Fetch Data untuk Modal Pilih Barang
$stmt = $db->query("
    SELECT v.id_varian, v.keterangan_varian, v.harga_sewa_per_hari, v.stok_tersedia, v.gambar as v_img, i.nama_brand, i.nama_seri, i.gambar as i_img
    FROM varian_item v
    JOIN item i ON v.id_item = i.id_item
    WHERE i.status_item = 'Aktif' AND i.jenis_transaksi = 'Sewa'
    ORDER BY i.nama_brand, i.nama_seri
");
$katalog = $stmt->fetchAll();

$pageTitle = 'Kasir / PO Manual';
require_once __DIR__ . '/../includes/header.php';
?>

<div class="container-fluid mt-4">
    <style>
        /* Fix Swal z-index over Bootstrap Modal */
        .swal2-container {
            z-index: 10000 !important;
        }
        
        /* Mobile responsive tables */
        @media (max-width: 767.98px) {
            /* Cart Table */
            #cartTable thead { display: none; }
            #cartTable tbody tr {
                display: block;
                margin-bottom: 1rem;
                border: 1px solid #e2e8f0;
                border-radius: 0.5rem;
                padding: 0.75rem;
                position: relative;
            }
            #cartTable tbody td {
                display: flex;
                justify-content: space-between;
                align-items: center;
                padding: 0.5rem 0 !important;
                border: none !important;
                border-bottom: 1px solid #f1f5f9 !important;
            }
            #cartTable tbody td:last-child {
                border-bottom: none !important;
                justify-content: flex-end;
            }
            #cartTable tbody td:first-child {
                flex-direction: column;
                align-items: flex-start;
                background-color: #f8fafc;
                padding: 0.75rem !important;
                border-radius: 0.375rem;
                margin-bottom: 0.5rem;
            }
            #cartTable tbody td::before {
                font-weight: 600;
                color: #64748b;
            }
            #cartTable tbody td:nth-child(2)::before { content: "Harga/Hari:"; }
            #cartTable tbody td:nth-child(3)::before { content: "Jumlah:"; }
            #cartTable tbody td:nth-child(4)::before { content: "Subtotal:"; }
            
            /* Footer Keranjang */
            #cartFooter tr { display: flex; flex-direction: column; text-align: right; }
            #cartFooter td { display: block; width: 100%; border: none !important; text-align: right !important; padding: 0.5rem 0 !important; }

            /* Modal Katalog Table */
            #katalogTable thead { display: none; }
            #katalogTable tbody tr {
                display: flex;
                flex-wrap: wrap;
                margin-bottom: 1rem;
                border: 1px solid #e2e8f0;
                border-radius: 0.5rem;
                padding: 0.75rem;
                position: relative;
            }
            #katalogTable tbody td {
                border: none !important;
                padding: 0.25rem 0 !important;
            }
            #katalogTable tbody td:first-child { width: 100%; margin-bottom: 0.5rem; }
            #katalogTable tbody td:nth-child(2) { width: 50%; }
            #katalogTable tbody td:nth-child(3) { width: 50%; text-align: right !important; }
            #katalogTable tbody td:nth-child(4) { width: 50%; font-size: 1rem; }
            #katalogTable tbody td:nth-child(5) { width: 50%; text-align: right !important; }
        }
    </style>

    <div class="d-flex align-items-center justify-content-between mb-4">
        <h4 class="mb-0 fw-semibold">Kasir / PO Manual</h4>
        <a href="kelola_po.php" class="btn btn-secondary btn-sm"><i class="ri-arrow-left-line align-middle me-1"></i> Kembali</a>
    </div>

    <form method="POST" id="formManualPO">
        <div class="row">
            <!-- Kolom Kiri: Data Pelanggan & Waktu -->
            <div class="col-lg-4 mb-4">
                <div class="card custom-card">
                    <div class="card-header border-bottom">
                        <h6 class="card-title mb-0">Informasi Pelanggan</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nomor HP (WhatsApp)</label>
                            <input type="text" name="no_hp" id="inputNoHp" class="form-control" placeholder="Contoh: 08123456789" required>
                            <div class="form-text">Jika nomor HP baru, akun dengan PIN '1234' otomatis dibuat.</div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Nama Lengkap</label>
                            <input type="text" name="nama" id="inputNama" class="form-control" placeholder="Nama Pelanggan" required>
                        </div>
                    </div>
                </div>

                <div class="card custom-card mt-3">
                    <div class="card-header border-bottom">
                        <h6 class="card-title mb-0">Durasi Sewa</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Mulai Sewa</label>
                            <input type="date" name="tgl_mulai_sewa" id="tgl_mulai" class="form-control" value="<?= date('Y-m-d') ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Tanggal Selesai Sewa</label>
                            <input type="date" name="tgl_selesai_sewa" id="tgl_selesai" class="form-control" value="<?= date('Y-m-d', strtotime('+1 day')) ?>" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-semibold">Catatan (Opsional)</label>
                            <textarea name="catatan_pelanggan" class="form-control" rows="2" placeholder="Catatan khusus..."></textarea>
                        </div>
                        <div class="alert alert-primary mb-0 d-flex align-items-center">
                            <i class="ri-information-line fs-20 me-2"></i>
                            <div>Durasi: <strong id="durasiLabel">1</strong> Hari</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Kolom Kanan: Keranjang Barang -->
            <div class="col-lg-8 mb-4">
                <div class="card custom-card h-100">
                    <div class="card-header border-bottom d-flex justify-content-between align-items-center">
                        <h6 class="card-title mb-0">Keranjang Barang</h6>
                        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#modalPilihBarang">
                            <i class="ri-add-line align-middle me-1"></i> Pilih Barang
                        </button>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover text-nowrap mb-0" id="cartTable">
                                <thead class="table-light">
                                    <tr>
                                        <th>Barang</th>
                                        <th class="text-end">Harga/Hari</th>
                                        <th class="text-center" style="width: 120px;">Jumlah</th>
                                        <th class="text-end">Subtotal</th>
                                        <th class="text-center" style="width: 50px;">Aksi</th>
                                    </tr>
                                </thead>
                                <tbody id="cartBody">
                                    <tr id="emptyCartRow">
                                        <td colspan="5" class="text-center text-muted py-4">
                                            <i class="ri-shopping-cart-2-line fs-24 d-block mb-2"></i>
                                            Belum ada barang di keranjang
                                        </td>
                                    </tr>
                                </tbody>
                                <tfoot class="border-top" style="display: none;" id="cartFooter">
                                    <tr>
                                        <td colspan="3" class="text-end fw-bold">Total Harga (<span id="totalHariFooter">1</span> Hari):</td>
                                        <td colspan="2" class="text-end fw-bold text-primary fs-16">
                                            Rp <span id="grandTotalLabel">0</span>
                                        </td>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                    <div class="card-footer text-end">
                        <button type="submit" name="submit_po" id="btnSubmitPO" class="btn btn-success" disabled>
                            <i class="ri-save-line align-middle me-1"></i> Buat Pesanan
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal Pilih Barang -->
<div class="modal fade" id="modalPilihBarang" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Pilih Barang</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="p-3 border-bottom">
                    <input type="text" id="searchBarang" class="form-control" placeholder="Cari nama barang...">
                </div>
                <div class="table-responsive">
                    <table class="table table-hover text-nowrap mb-0" id="katalogTable">
                        <thead class="table-light">
                            <tr>
                                <th>Barang</th>
                                <th>Varian</th>
                                <th class="text-center">Stok</th>
                                <th class="text-end">Harga/Hari</th>
                                <th class="text-center">Aksi</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($katalog as $k): 
                                $img = !empty($k['v_img']) ? $k['v_img'] : (!empty($k['i_img']) ? $k['i_img'] : '');
                                $namaLengkap = $k['nama_brand'] . ' ' . $k['nama_seri'];
                                $searchStr = strtolower($namaLengkap . ' ' . $k['keterangan_varian']);
                            ?>
                            <tr class="item-row" data-search="<?= htmlspecialchars($searchStr) ?>">
                                <td>
                                    <div class="d-flex align-items-center">
                                        <span class="avatar avatar-md me-3 bg-light text-muted border rounded">
                                            <?php if($img && file_exists(__DIR__.'/../assets/img/'.$img)): ?>
                                                <img src="<?= $base_url ?>assets/img/<?= htmlspecialchars($img) ?>" alt="img" style="object-fit: cover; width:100%; height:100%; border-radius:inherit;">
                                            <?php else: ?>
                                                <i class="ri-image-line fs-18"></i>
                                            <?php endif; ?>
                                        </span>
                                        <div class="fw-semibold text-dark">
                                            <?= htmlspecialchars($namaLengkap) ?>
                                        </div>
                                    </div>
                                </td>
                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($k['keterangan_varian']) ?></span></td>
                                <td class="text-center">
                                    <?php if ($k['stok_tersedia'] > 0): ?>
                                        <span class="badge bg-success-transparent text-success"><?= $k['stok_tersedia'] ?></span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-transparent text-danger">Habis</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-end text-primary fw-semibold">Rp <?= number_format($k['harga_sewa_per_hari'], 0, ',', '.') ?></td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-sm btn-primary-ghost btn-add-item" 
                                        data-id="<?= $k['id_varian'] ?>"
                                        data-nama="<?= htmlspecialchars($namaLengkap) ?>"
                                        data-varian="<?= htmlspecialchars($k['keterangan_varian']) ?>"
                                        data-harga="<?= $k['harga_sewa_per_hari'] ?>"
                                        data-stok="<?= $k['stok_tersedia'] ?>"
                                        <?= $k['stok_tersedia'] <= 0 ? 'disabled' : '' ?>>
                                        <i class="ri-add-line"></i> Pilih
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    let cart = [];
    const tglMulai = document.getElementById('tgl_mulai');
    const tglSelesai = document.getElementById('tgl_selesai');
    const durasiLabel = document.getElementById('durasiLabel');
    const totalHariFooter = document.getElementById('totalHariFooter');
    
    // Pencarian Barang di Modal
    document.getElementById('searchBarang').addEventListener('input', function(e) {
        const query = e.target.value.toLowerCase();
        document.querySelectorAll('#katalogTable .item-row').forEach(row => {
            const text = row.getAttribute('data-search');
            if (text.includes(query)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    });

    // Hitung Hari
    function getDiffDays() {
        const start = new Date(tglMulai.value);
        const end = new Date(tglSelesai.value);
        let diffTime = end - start;
        let diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        if (diffDays < 1) diffDays = 1;
        return diffDays;
    }

    function updateDurasi() {
        const days = getDiffDays();
        durasiLabel.textContent = days;
        totalHariFooter.textContent = days;
        renderCart(); // Re-render to update subtotals
    }

    tglMulai.addEventListener('change', updateDurasi);
    tglSelesai.addEventListener('change', updateDurasi);

    // Format Rupiah
    function formatRupiah(angka) {
        return parseInt(angka).toLocaleString('id-ID');
    }

    // Render Keranjang
    function renderCart() {
        const cartBody = document.getElementById('cartBody');
        const cartFooter = document.getElementById('cartFooter');
        const emptyCartRow = document.getElementById('emptyCartRow');
        const btnSubmit = document.getElementById('btnSubmitPO');
        const grandTotalLabel = document.getElementById('grandTotalLabel');
        const days = getDiffDays();
        
        // Hapus semua baris item dari DOM (kecuali baris empty)
        document.querySelectorAll('#cartBody tr:not(#emptyCartRow)').forEach(tr => tr.remove());
        
        let grandTotal = 0;

        if (cart.length === 0) {
            if (emptyCartRow) emptyCartRow.style.display = '';
            cartFooter.style.display = 'none';
            btnSubmit.disabled = true;
        } else {
            if (emptyCartRow) emptyCartRow.style.display = 'none';
            cartFooter.style.display = '';
            btnSubmit.disabled = false;

            cart.forEach((item, index) => {
                const subtotal = item.harga * item.jumlah * days;
                grandTotal += subtotal;

                const tr = document.createElement('tr');
                tr.innerHTML = `
                    <td class="text-wrap">
                        <div class="fw-semibold text-dark">${item.nama}</div>
                        <div class="fs-12 text-muted">${item.varian}</div>
                        <input type="hidden" name="items[${index}][id_varian]" value="${item.id}">
                        <input type="hidden" name="items[${index}][harga]" value="${item.harga}">
                    </td>
                    <td class="text-end">Rp ${formatRupiah(item.harga)}</td>
                    <td class="text-center">
                        <div class="input-group input-group-sm w-100 mx-auto flex-nowrap" style="max-width: 120px;">
                            <button class="btn btn-outline-secondary btn-min px-2" data-index="${index}" type="button">-</button>
                            <input type="number" name="items[${index}][jumlah]" class="form-control text-center input-qty px-1" data-index="${index}" value="${item.jumlah}" min="1" max="${item.stok}" readonly>
                            <button class="btn btn-outline-secondary btn-plus px-2" data-index="${index}" type="button" ${item.jumlah >= item.stok ? 'disabled' : ''}>+</button>
                        </div>
                    </td>
                    <td class="text-end fw-semibold text-primary">Rp ${formatRupiah(subtotal)}</td>
                    <td class="text-center">
                        <button type="button" class="btn btn-sm btn-danger-ghost btn-remove" data-index="${index}">
                            <i class="ri-delete-bin-line"></i>
                        </button>
                    </td>
                `;
                cartBody.appendChild(tr);
            });

            grandTotalLabel.textContent = formatRupiah(grandTotal);
            bindCartEvents();
        }
    }

    function bindCartEvents() {
        document.querySelectorAll('.btn-min').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                if (cart[index].jumlah > 1) {
                    cart[index].jumlah--;
                    renderCart();
                }
            });
        });

        document.querySelectorAll('.btn-plus').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                if (cart[index].jumlah < cart[index].stok) {
                    cart[index].jumlah++;
                    renderCart();
                }
            });
        });

        document.querySelectorAll('.btn-remove').forEach(btn => {
            btn.addEventListener('click', function() {
                const index = this.getAttribute('data-index');
                cart.splice(index, 1);
                renderCart();
            });
        });
    }

    // Tambah Barang ke Keranjang
    document.querySelectorAll('.btn-add-item').forEach(btn => {
        btn.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            const nama = this.getAttribute('data-nama');
            const varian = this.getAttribute('data-varian');
            const harga = parseInt(this.getAttribute('data-harga'));
            const stok = parseInt(this.getAttribute('data-stok'));

            // Cek apakah sudah ada di cart
            const existingIndex = cart.findIndex(item => item.id === id);
            if (existingIndex !== -1) {
                if (window.Swal) {
                    Swal.fire({
                        icon: 'info',
                        title: 'Sudah di Keranjang',
                        text: 'Barang ini sudah ada di keranjang. Anda bisa menambah/mengurangi jumlahnya langsung di tabel keranjang.',
                        confirmButtonColor: '#3085d6'
                    });
                } else {
                    alert('Barang ini sudah ada di keranjang.');
                }
                return;
            }

            // Tanyakan jumlah yang ingin disewa
            if (window.Swal) {
                Swal.fire({
                    title: 'Masukkan Jumlah',
                    html: `Berapa banyak <b>${nama} (${varian})</b> yang ingin disewa?<br><small class="text-muted">Tersedia: ${stok} unit</small>`,
                    input: 'number',
                    inputAttributes: {
                        min: 1,
                        max: stok,
                        step: 1
                    },
                    inputValue: 1,
                    showCancelButton: true,
                    confirmButtonText: 'Tambahkan ke Keranjang',
                    cancelButtonText: 'Batal',
                    confirmButtonColor: '#28a745',
                    inputValidator: (value) => {
                        if (!value || parseInt(value) < 1) {
                            return 'Jumlah minimal 1!';
                        }
                        if (parseInt(value) > stok) {
                            return `Maksimal hanya bisa menyewa ${stok} unit!`;
                        }
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        const jumlahPesan = parseInt(result.value);
                        cart.push({
                            id: id,
                            nama: nama,
                            varian: varian,
                            harga: harga,
                            stok: stok,
                            jumlah: jumlahPesan
                        });
                        renderCart();
                        
                        Swal.fire({
                            toast: true,
                            position: 'top-end',
                            icon: 'success',
                            title: 'Berhasil ditambahkan',
                            showConfirmButton: false,
                            timer: 1500
                        });
                    }
                });
            } else {
                // Fallback jika Swal tidak load
                let qty = prompt(`Masukkan jumlah untuk ${nama} (Maks: ${stok}):`, "1");
                if (qty !== null) {
                    qty = parseInt(qty);
                    if (qty >= 1 && qty <= stok) {
                        cart.push({
                            id: id,
                            nama: nama,
                            varian: varian,
                            harga: harga,
                            stok: stok,
                            jumlah: qty
                        });
                        renderCart();
                    } else {
                        alert("Jumlah tidak valid atau melebihi batas tersisa.");
                    }
                }
            }
        });
    });

    // Validasi Form sebelum submit
    document.getElementById('formManualPO').addEventListener('submit', function(e) {
        if (cart.length === 0) {
            e.preventDefault();
            if (window.Swal) {
                Swal.fire({
                    icon: 'error',
                    title: 'Oops...',
                    text: 'Keranjang barang tidak boleh kosong!',
                    confirmButtonColor: '#d33'
                });
            } else {
                alert('Keranjang barang tidak boleh kosong!');
            }
        }
    });

    // Inisialisasi tampilan
    updateDurasi();
});
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
