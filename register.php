<?php
/**
 * Register Page — On Time Adventure
 */

session_start();

$base_url = '/ontimeadventure/';

if (isset($_SESSION['id_user'])) {
    header('Location: ' . $base_url . 'katalog.php');
    exit;
}

require __DIR__ . '/config/database.php';

$error = '';
$nama  = '';
$no_hp = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nama         = trim($_POST['nama'] ?? '');
    $no_hp        = trim($_POST['no_hp'] ?? '');
    $pin          = trim($_POST['pin'] ?? '');
    $pin_confirm  = trim($_POST['pin_confirm'] ?? '');

    if ($nama === '' || $no_hp === '' || $pin === '' || $pin_confirm === '') {
        $error = 'Semua field wajib diisi.';
    } elseif (!preg_match('/^\d{4}$/', $pin)) {
        $error = 'PIN harus tepat 4 digit angka.';
    } elseif ($pin !== $pin_confirm) {
        $error = 'PIN dan Konfirmasi PIN tidak cocok.';
    } else {
        try {
            $db = getDB();

            $check = $db->prepare('SELECT COUNT(*) FROM users WHERE no_hp = :no_hp');
            $check->execute([':no_hp' => $no_hp]);

            if ((int)$check->fetchColumn() > 0) {
                $error = 'Nomor HP sudah terdaftar. Silakan login.';
            } else {
                $hashed = password_hash($pin, PASSWORD_BCRYPT);
                
                $stmt = $db->prepare(
                    'INSERT INTO users (no_hp, pin, nama, role) VALUES (:no_hp, :pin, :nama, :role)'
                );
                $stmt->execute([
                    ':no_hp' => $no_hp,
                    ':pin'   => $hashed,
                    ':nama'  => $nama,
                    ':role'  => 'pelanggan',
                ]);

                $_SESSION['flash_success'] = 'Akun berhasil dibuat! Silakan login.';
                header('Location: ' . $base_url . 'login.php');
                exit;
            }
        } catch (PDOException $e) {
            $error = 'Terjadi kesalahan sistem. Silakan coba lagi.';
        }
    }
}

$pageTitle = 'Daftar Akun';
require __DIR__ . '/includes/header.php';
?>

<style>
/* Hide footer but keep navbar */
.footer { display: none !important; }
.landing-main { 
    background-color: #f8fafc; /* Very soft cool gray/blue */
    min-height: 100vh;
}

.premium-card {
    background: #ffffff;
    border-radius: 28px;
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.08);
    padding: 3rem;
    border: 1px solid rgba(255,255,255,0.6);
    position: relative;
    overflow: hidden;
}

/* A subtle decorative blob inside the card for modern feel */
.premium-card::before {
    content: '';
    position: absolute;
    top: -50px;
    right: -50px;
    width: 150px;
    height: 150px;
    background: radial-gradient(circle, rgba(250,100,95,0.08) 0%, rgba(255,255,255,0) 70%);
    border-radius: 50%;
    z-index: 0;
}

.card-content-wrapper {
    position: relative;
    z-index: 1;
}

/* Beautiful Inputs */
.text-input-wrapper {
    border: 2px solid #f1f5f9;
    border-radius: 16px;
    padding: 6px 15px;
    display: flex;
    align-items: center;
    margin-bottom: 20px;
    background: #f8fafc;
    transition: all 0.3s ease;
}
.text-input-wrapper:focus-within {
    border-color: #FA645F;
    background: #fff;
    box-shadow: 0 0 0 4px rgba(250, 100, 95, 0.1);
}
.text-input-wrapper i {
    color: #94a3b8;
    font-size: 20px;
    margin-right: 10px;
}
.text-input-wrapper:focus-within i {
    color: #FA645F;
}
.text-input-wrapper input {
    border: none;
    background: transparent;
    outline: none;
    padding: 10px 0;
    flex-grow: 1;
    font-weight: 500;
    color: #1e293b;
    font-size: 15px;
}
/* Fix Chrome autofill blue background */
.text-input-wrapper input:-webkit-autofill,
.text-input-wrapper input:-webkit-autofill:hover, 
.text-input-wrapper input:-webkit-autofill:focus, 
.text-input-wrapper input:-webkit-autofill:active{
    -webkit-box-shadow: 0 0 0 30px #ffffff inset !important;
    -webkit-text-fill-color: #1e293b !important;
}

.btn-coral {
    background-color: #FA645F;
    color: white;
    border: none;
    border-radius: 16px;
    padding: 14px 20px;
    font-weight: 600;
    width: 100%;
    font-size: 16px;
    box-shadow: 0 8px 16px rgba(250, 100, 95, 0.25);
    transition: all 0.2s;
    display: flex;
    justify-content: center;
    align-items: center;
    gap: 10px;
}
.btn-coral:hover {
    color: white;
    transform: translateY(-2px);
    box-shadow: 0 10px 20px rgba(250, 100, 95, 0.3);
}

.step-container { display: none; }
.step-container.active { display: block; animation: fadeInStep 0.3s ease; }
@keyframes fadeInStep { from { opacity: 0; } to { opacity: 1; } }

.pin-dots { display: flex; justify-content: center; gap: 16px; margin: 30px 0; }
.pin-dot { width: 16px; height: 16px; border-radius: 50%; background-color: #e2e8f0; transition: all 0.2s; }
.pin-dot.filled { background-color: #FA645F; transform: scale(1.1); box-shadow: 0 0 10px rgba(250, 100, 95, 0.4); }

.keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: 15px; max-width: 280px; margin: 0 auto; }
.key-btn { border: none; background: #f8fafc; font-size: 24px; font-weight: 600; color: #1e293b; border-radius: 20px; cursor: pointer; transition: all 0.1s; width: 65px; height: 65px; margin: 0 auto; display: flex; align-items: center; justify-content: center; }
.key-btn:active { background: #e2e8f0; transform: scale(0.95); }
.key-btn.bg-transparent { background: transparent; }

.back-btn-top {
    position: absolute;
    top: -24px;
    left: -24px;
    background: transparent;
    border: none;
    font-size: 24px;
    color: #64748b;
    cursor: pointer;
    text-decoration: none;
    transition: color 0.2s;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 36px; height: 36px;
    border-radius: 50%;
}
.back-btn-top:hover { color: #1e293b; background: #f1f5f9; }

/* Dark Mode Support */
html[data-theme-mode="dark"] .landing-main {
    background-color: var(--body-bg) !important;
}
html[data-theme-mode="dark"] .premium-card {
    background: var(--custom-white);
    border-color: rgba(255,255,255,0.05);
    box-shadow: 0 20px 40px -10px rgba(0,0,0,0.5);
}
html[data-theme-mode="dark"] .text-dark { color: #f8fafc !important; }
html[data-theme-mode="dark"] .text-muted { color: #94a3b8 !important; }
html[data-theme-mode="dark"] .text-input-wrapper {
    background: rgba(0,0,0,0.2);
    border-color: rgba(255,255,255,0.05);
}
html[data-theme-mode="dark"] .text-input-wrapper:focus-within {
    background: rgba(0,0,0,0.4);
    border-color: #FA645F;
}
html[data-theme-mode="dark"] .text-input-wrapper input { color: #f8fafc; }
html[data-theme-mode="dark"] .text-input-wrapper input:-webkit-autofill,
html[data-theme-mode="dark"] .text-input-wrapper input:-webkit-autofill:hover, 
html[data-theme-mode="dark"] .text-input-wrapper input:-webkit-autofill:focus, 
html[data-theme-mode="dark"] .text-input-wrapper input:-webkit-autofill:active{
    -webkit-box-shadow: 0 0 0 30px #1e293b inset !important;
    -webkit-text-fill-color: #f8fafc !important;
}
html[data-theme-mode="dark"] .key-btn {
    background: rgba(255,255,255,0.05);
    color: #f8fafc;
}
html[data-theme-mode="dark"] .key-btn.bg-transparent { background: transparent; }
html[data-theme-mode="dark"] .key-btn:active { background: rgba(255,255,255,0.1); }
html[data-theme-mode="dark"] .pin-dot { background-color: rgba(255,255,255,0.1); }
html[data-theme-mode="dark"] .pin-dot.filled { background-color: #FA645F; }
html[data-theme-mode="dark"] .back-btn-top { color: #94a3b8; }
html[data-theme-mode="dark"] .back-btn-top:hover { color: #f8fafc; background: rgba(255,255,255,0.05); }
</style>

<div class="container py-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-md-8 col-lg-6 col-xl-5">
            <div class="premium-card mx-auto">
                <div class="card-content-wrapper">
                    
                    <div class="mb-4 text-center">
                        <div class="d-flex align-items-center justify-content-center mb-3">
                            <img src="<?= $base_url ?>logo-ontimeadventure.png" alt="On Time Adventure" style="height: 120px; object-fit: contain;">
                        </div>
                        <h3 class="fw-bold text-dark mb-1">Daftar Akun Baru</h3>
                        <p class="text-muted fs-15">Mulai petualanganmu bersama kami</p>
                    </div>

                    <?php if ($error): ?>
                        <div class="alert alert-danger rounded-3 fs-14 py-2 text-center shadow-sm border-0 mb-4" role="alert">
                            <?= htmlspecialchars($error) ?>
                        </div>
                    <?php endif; ?>

                    <form id="registerForm" method="POST" action="">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                        <input type="hidden" name="no_hp" id="realPhone" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>">
                        <input type="hidden" name="pin" id="realPin" value="">
                        
                        <!-- Dummy target for PIN confirmation logic -->
                        <input type="hidden" id="realConfirmPin" value="">

                        <!-- STEP 1: Data Diri -->
                        <div id="step1" class="step-container active">
                            <div class="mb-3">
                                <p class="text-muted mb-2 fs-14 fw-semibold">Nama Lengkap</p>
                                <div class="text-input-wrapper">
                                    <i class="ri-user-3-line"></i>
                                    <input type="text" id="nama" name="nama" placeholder="Masukkan nama lengkap" value="<?= htmlspecialchars($_POST['nama'] ?? '') ?>">
                                </div>
                            </div>
                            <div class="mb-4">
                                <p class="text-muted mb-2 fs-14 fw-semibold">Nomor Handphone</p>
                                <div class="text-input-wrapper mb-2">
                                    <i class="ri-phone-line"></i>
                                    <input type="tel" id="displayPhone" placeholder="0812 3456 7890" value="<?= htmlspecialchars($_POST['no_hp'] ?? '') ?>" onkeypress="if(event.key === 'Enter') { event.preventDefault(); goToStep2(); }">
                                </div>
                                <div class="d-flex align-items-start mt-1">
                                    <i class="ri-whatsapp-line text-success me-2 fs-16 mt-1"></i>
                                    <span class="fs-12 text-muted lh-sm">Pastikan menggunakan <strong>nomor WhatsApp yang aktif</strong> agar kami mudah menghubungi Anda jika ada kendala penyewaan.</span>
                                </div>
                            </div>
                            <div class="mt-4">
                                <button type="button" class="btn-coral" onclick="goToStep2()">
                                    Selanjutnya <i class="ri-arrow-right-line ms-1"></i>
                                </button>
                            </div>
                            <div class="text-center mt-4 fw-medium text-muted fs-14">
                                Sudah punya akun? <a href="<?= $base_url ?>login.php" class="text-primary fw-bold text-decoration-none">Login di sini</a>
                            </div>
                        </div>

                        <!-- STEP 2: Buat PIN -->
                        <div id="step2" class="step-container">
                            <button type="button" class="back-btn-top" onclick="goToStep1()"><i class="ri-arrow-left-line"></i></button>
                            <h5 class="mb-0 fw-bold text-dark text-center mt-2">Buat PIN Anda</h5>
                            <p class="text-center text-muted fs-13 mt-1">Gunakan 4 angka yang mudah diingat</p>
                            
                            <div class="pin-dots mt-4">
                                <div class="pin-dot" id="dot1"></div>
                                <div class="pin-dot" id="dot2"></div>
                                <div class="pin-dot" id="dot3"></div>
                                <div class="pin-dot" id="dot4"></div>
                            </div>

                            <div class="keypad mt-5">
                                <button type="button" class="key-btn" onclick="addPin('1')">1</button>
                                <button type="button" class="key-btn" onclick="addPin('2')">2</button>
                                <button type="button" class="key-btn" onclick="addPin('3')">3</button>
                                <button type="button" class="key-btn" onclick="addPin('4')">4</button>
                                <button type="button" class="key-btn" onclick="addPin('5')">5</button>
                                <button type="button" class="key-btn" onclick="addPin('6')">6</button>
                                <button type="button" class="key-btn" onclick="addPin('7')">7</button>
                                <button type="button" class="key-btn" onclick="addPin('8')">8</button>
                                <button type="button" class="key-btn" onclick="addPin('9')">9</button>
                                <div class="key-btn bg-transparent"></div>
                                <button type="button" class="key-btn" onclick="addPin('0')">0</button>
                                <button type="button" class="key-btn bg-transparent text-danger" onclick="removePin()"><i class="ri-delete-back-2-line fs-24"></i></button>
                            </div>
                        </div>

                        <!-- STEP 3: Konfirmasi PIN -->
                        <div id="step3" class="step-container">
                            <button type="button" class="back-btn-top" onclick="backToStep2()"><i class="ri-arrow-left-line"></i></button>
                            <h5 class="mb-0 fw-bold text-dark text-center mt-2">Konfirmasi PIN</h5>
                            <p class="text-center text-muted fs-13 mt-1">Masukkan ulang 4 angka PIN Anda</p>
                            
                            <div class="pin-dots mt-4">
                                <div class="pin-dot" id="dotC1"></div>
                                <div class="pin-dot" id="dotC2"></div>
                                <div class="pin-dot" id="dotC3"></div>
                                <div class="pin-dot" id="dotC4"></div>
                            </div>

                            <div class="keypad mt-5">
                                <button type="button" class="key-btn" onclick="addConfirmPin('1')">1</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('2')">2</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('3')">3</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('4')">4</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('5')">5</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('6')">6</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('7')">7</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('8')">8</button>
                                <button type="button" class="key-btn" onclick="addConfirmPin('9')">9</button>
                                <div class="key-btn bg-transparent"></div>
                                <button type="button" class="key-btn" onclick="addConfirmPin('0')">0</button>
                                <button type="button" class="key-btn bg-transparent text-danger" onclick="removeConfirmPin()"><i class="ri-delete-back-2-line fs-24"></i></button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let currentPin = "";
let confirmPin = "";

function goToStep2() {
    let nama = document.getElementById('nama').value.trim();
    let phone = document.getElementById('displayPhone').value.trim();
    
    if (nama === '') {
        Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Masukkan Nama Lengkap Anda!', confirmButtonColor: '#005a9c' });
        return;
    }
    if (phone === '') {
        Swal.fire({ icon: 'warning', title: 'Oops...', text: 'Masukkan Nomor Handphone Anda!', confirmButtonColor: '#005a9c' });
        return;
    }
    
    // Normalize phone
    phone = phone.replace(/\D/g,'');
    if (phone.startsWith('0')) {
        phone = phone.substring(1);
    } else if (phone.startsWith('62')) {
        phone = phone.substring(2);
    }
    
    document.getElementById('realPhone').value = "0" + phone;
    
    document.getElementById('step1').classList.remove('active');
    document.getElementById('step2').classList.add('active');
}

function goToStep1() {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step1').classList.add('active');
    currentPin = "";
    updateDots();
}

function goToStep3() {
    document.getElementById('step2').classList.remove('active');
    document.getElementById('step3').classList.add('active');
}

function backToStep2() {
    document.getElementById('step3').classList.remove('active');
    document.getElementById('step2').classList.add('active');
    confirmPin = "";
    updateConfirmDots();
}

function addPin(digit) {
    if (currentPin.length < 4) {
        currentPin += digit;
        updateDots();
    }
    
    if (currentPin.length === 4) {
        document.getElementById('realPin').value = currentPin;
        setTimeout(() => {
            goToStep3();
        }, 200);
    }
}

function removePin() {
    if (currentPin.length > 0) {
        currentPin = currentPin.slice(0, -1);
        updateDots();
    }
}

function updateDots() {
    for (let i = 1; i <= 4; i++) {
        let dot = document.getElementById('dot' + i);
        if (i <= currentPin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    }
}

function addConfirmPin(digit) {
    if (confirmPin.length < 4) {
        confirmPin += digit;
        updateConfirmDots();
    }
    
    if (confirmPin.length === 4) {
        if (confirmPin === currentPin) {
            // Success
            setTimeout(() => {
                document.getElementById('registerForm').submit();
            }, 200);
        } else {
            // Mismatch
            Swal.fire({
                icon: 'error',
                title: 'PIN Tidak Cocok',
                text: 'Silakan masukkan ulang PIN Anda.',
                confirmButtonColor: '#FA645F'
            }).then(() => {
                confirmPin = "";
                updateConfirmDots();
                backToStep2();
                currentPin = "";
                updateDots();
            });
        }
    }
}

function removeConfirmPin() {
    if (confirmPin.length > 0) {
        confirmPin = confirmPin.slice(0, -1);
        updateConfirmDots();
    }
}

function updateConfirmDots() {
    for (let i = 1; i <= 4; i++) {
        let dot = document.getElementById('dotC' + i);
        if (i <= confirmPin.length) {
            dot.classList.add('filled');
        } else {
            dot.classList.remove('filled');
        }
    }
}
</script>

<?php require __DIR__ . '/includes/footer.php'; ?>
