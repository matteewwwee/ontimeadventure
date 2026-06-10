<?php
/**
 * ============================================================
 * ON TIME ADVENTURE — Shared Footer Template (Vyzor UI)
 * ============================================================
 */
if (!isset($base_url)) {
    $base_url = (strpos($_SERVER['HTTP_HOST'], 'localhost') !== false || strpos($_SERVER['HTTP_HOST'], '127.0.0.1') !== false) ? '/ontimeadventure/' : '/';
}
$isAdminArea = strpos($_SERVER['PHP_SELF'], '/admin/') !== false;
$currentYear = date('Y');
$startYear   = 2024;
$yearDisplay = ($currentYear > $startYear) ? "{$startYear} - {$currentYear}" : "{$startYear}";
?>

    </div>
    <!-- End::app-content -->

    <!-- Footer Start -->
    <footer class="footer mt-auto py-3 bg-white text-center">
        <div class="container">
            <span class="text-muted"> Copyright © <span id="year"><?= $yearDisplay ?></span> <a
                    href="javascript:void(0);" class="text-dark fw-semibold">On Time Adventure</a>.
                Designed with <span class="bi bi-heart-fill text-danger"></span> by <a href="javascript:void(0);">
                    <span class="fw-semibold text-primary text-decoration-underline">Your Team</span>
                </a> All
                rights
                reserved
            </span>
        </div>
    </footer>
    <!-- Footer End -->

</div>
<!-- End::page -->

<?php if (isset($isLoggedIn) && $isLoggedIn && !$isAdminArea): ?>
    <!-- Floating Cart Button -->
    <button type="button" id="cart-fab-btn" class="btn btn-primary rounded-circle shadow-lg d-flex justify-content-center align-items-center cart-fab-responsive" 
            style="position: fixed; z-index: 1050; border: none; transition: transform 0.2s; cursor: grab;">
        <i class="ri-shopping-cart-2-line text-white cart-icon-responsive"></i>
        <?php if (isset($cartCount) && $cartCount > 0): ?>
            <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger shadow-sm border border-light" style="font-size: 0.75rem;">
                <?= $cartCount ?>
                <span class="visually-hidden">item di keranjang</span>
            </span>
        <?php endif; ?>
    </button>
    <style>
        .cart-fab-responsive {
            bottom: 30px; 
            right: 30px; 
            width: 60px; 
            height: 60px;
        }
        .cart-icon-responsive {
            font-size: 24px;
        }
        @media (max-width: 768px) {
            .cart-fab-responsive {
                bottom: 20px; 
                right: 20px; 
                width: 50px; 
                height: 50px;
            }
            .cart-icon-responsive {
                font-size: 20px;
            }
        }
        #cart-fab-btn:hover {
            transform: scale(1.08);
        }
        #cart-fab-btn:active {
            cursor: grabbing;
        }
    </style>

    <!-- Include Cart Modal -->
    <?php require_once __DIR__ . '/cart_modal.php'; ?>
<?php endif; ?>

<!-- Scroll To Top -->
<div class="scrollToTop">
    <span class="arrow"><i class="ri-arrow-up-s-fill fs-20"></i></span>
</div>
<div id="responsive-overlay"></div>
<!-- Scroll To Top -->

<!-- Popper JS -->
<script src="<?= $base_url ?>assets/vyzor/libs/@popperjs/core/umd/popper.min.js"></script>

<!-- Bootstrap JS -->
<script src="<?= $base_url ?>assets/vyzor/libs/bootstrap/js/bootstrap.bundle.min.js"></script>

<!-- Defaultmenu JS -->
<script src="<?= $base_url ?>assets/vyzor/js/defaultmenu.js"></script>

<!-- Node Waves JS-->
<script src="<?= $base_url ?>assets/vyzor/libs/node-waves/waves.min.js"></script>

<!-- Sticky JS -->
<script src="<?= $base_url ?>assets/vyzor/js/sticky.js"></script>

<?php if($isAdminArea): ?>
<!-- Simplebar JS -->
<script src="<?= $base_url ?>assets/vyzor/libs/simplebar/simplebar.min.js"></script>
<?php endif; ?>

<!-- Custom-Switcher JS -->
<script src="<?= $base_url ?>assets/vyzor/js/custom-switcher.js"></script>

<!-- SweetAlert2 -->
<script src="<?= $base_url ?>assets/vyzor/libs/sweetalert2/sweetalert2.min.js"></script>

<!-- Custom JS -->
<script src="<?= $base_url ?>assets/vyzor/js/custom.js"></script>

<script>
    // Inisialisasi Tooltips & Toasts
    document.addEventListener("DOMContentLoaded", function() {
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        <?php if(isset($_SESSION['flash_success'])): ?>
            Swal.fire({
                title: "Berhasil!",
                html: "<?= addslashes($_SESSION['flash_success']) ?>",
                icon: "success",
                confirmButtonColor: '#3085d6',
            });
            <?php unset($_SESSION['flash_success']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['flash_error'])): ?>
            Swal.fire({
                title: "Gagal!",
                html: "<?= addslashes($_SESSION['flash_error']) ?>",
                icon: "error",
                confirmButtonColor: '#d33',
            });
            <?php unset($_SESSION['flash_error']); ?>
        <?php endif; ?>

        <?php if(isset($_SESSION['flash_warning'])): ?>
            Swal.fire({
                title: "Peringatan Jadwal!",
                html: "<?= addslashes($_SESSION['flash_warning']) ?>",
                icon: "warning",
                confirmButtonColor: '#f8a536',
            });
            <?php unset($_SESSION['flash_warning']); ?>
        <?php endif; ?>
    });
</script>

<!-- Draggable FAB Logic -->
<?php if (isset($isLoggedIn) && $isLoggedIn && !$isAdminArea): ?>
<script>
document.addEventListener("DOMContentLoaded", function() {
    const fab = document.getElementById('cart-fab-btn');
    if (fab) {
        let isDragging = false;
        let startX, startY, initialX, initialY;
        
        function onStart(e) {
            isDragging = false;
            if(e.type === 'touchstart') {
                initialX = e.touches[0].clientX;
                initialY = e.touches[0].clientY;
            } else {
                initialX = e.clientX;
                initialY = e.clientY;
            }
            startX = fab.offsetLeft;
            startY = fab.offsetTop;
            
            document.addEventListener('mousemove', onMove);
            document.addEventListener('touchmove', onMove, {passive: false});
            document.addEventListener('mouseup', onEnd);
            document.addEventListener('touchend', onEnd);
            
            fab.style.transition = 'none';
        }
        
        function onMove(e) {
            let currentX = e.type === 'touchmove' ? e.touches[0].clientX : e.clientX;
            let currentY = e.type === 'touchmove' ? e.touches[0].clientY : e.clientY;
            
            let dx = currentX - initialX;
            let dy = currentY - initialY;
            
            if (Math.abs(dx) > 5 || Math.abs(dy) > 5) {
                isDragging = true;
                e.preventDefault();
                fab.style.left = (startX + dx) + 'px';
                fab.style.top = (startY + dy) + 'px';
                fab.style.bottom = 'auto';
                fab.style.right = 'auto';
            }
        }
        
        function onEnd(e) {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('touchmove', onMove);
            document.removeEventListener('mouseup', onEnd);
            document.removeEventListener('touchend', onEnd);
            
            fab.style.transition = 'transform 0.2s';
            
            if (isDragging) {
                setTimeout(() => { isDragging = false; }, 50);
            }
        }
        
        fab.addEventListener('mousedown', onStart);
        fab.addEventListener('touchstart', onStart, {passive: true});
        
        fab.addEventListener('click', function(e) {
            if (isDragging) {
                e.preventDefault();
                e.stopPropagation();
            } else {
                var modal = new bootstrap.Modal(document.getElementById('cartModal'));
                modal.show();
            }
        });
    }
});

</script>
<?php endif; ?>

<script>
function confirmDelete(event, text) {
    event.preventDefault();
    const form = event.target;
    Swal.fire({
        title: 'Konfirmasi',
        text: text,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then((result) => {
        if (result.isConfirmed) {
            form.submit();
        }
    });
}

<?php if (isset($_SESSION['id_user'])): ?>
// Sistem Notifikasi Real-Time (Smart Polling)
function fetchNotifications() {
    fetch('<?= $base_url ?>api_notifikasi.php?action=fetch')
        .then(response => response.json())
        .then(data => {
            if (data.status === 'success') {
                // Update badge counts
                const badges = document.querySelectorAll('.notif-badge');
                if (data.unread > 0) {
                    badges.forEach(b => {
                        b.style.display = 'inline-block';
                        b.innerText = data.unread;
                    });
                } else {
                    badges.forEach(b => {
                        b.style.display = 'none';
                    });
                }

                // Update notification list HTML
                const lists = document.querySelectorAll('.notif-list');
                let html = '';
                if (data.data.length > 0) {
                    data.data.forEach(n => {
                        let bgClass = n.is_read == 0 ? 'bg-primary-transparent' : '';
                        let link = n.tautan ? `href="${n.tautan}"` : 'href="javascript:void(0);"';
                        let time = new Date(n.created_at).toLocaleString('id-ID', {day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit'});
                        
                        html += `
                        <a ${link} class="dropdown-item d-flex align-items-center p-3 border-bottom ${bgClass}">
                            <div class="me-3">
                                <span class="avatar avatar-sm bg-primary rounded-circle"><i class="ri-information-line text-white"></i></span>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1 fs-13 fw-semibold text-wrap">${n.judul}</h6>
                                <p class="mb-0 text-muted fs-12 text-wrap">${n.pesan}</p>
                                <span class="text-muted fs-10 mt-1 d-block"><i class="ri-time-line me-1"></i>${time}</span>
                            </div>
                        </a>
                        `;
                    });
                } else {
                    html = '<div class="p-3 text-center text-muted fs-13">Belum ada notifikasi</div>';
                }
                
                lists.forEach(l => {
                    l.innerHTML = html;
                });
            }
        })
        .catch(err => console.error('Gagal memuat notifikasi:', err));
}

// Polling setiap 20 detik
setInterval(fetchNotifications, 20000);
// Fetch pertama kali saat halaman dimuat
fetchNotifications();

// Tandai dibaca saat dropdown dibuka
document.querySelectorAll('.btn-notif-toggle').forEach(btn => {
    btn.addEventListener('click', function() {
        const badges = document.querySelectorAll('.notif-badge');
        let hasUnread = false;
        badges.forEach(b => { if(b.style.display !== 'none') hasUnread = true; });
        
        if (hasUnread) {
            fetch('<?= $base_url ?>api_notifikasi.php?action=read')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        badges.forEach(b => { b.style.display = 'none'; b.innerText = '0'; });
                        document.querySelectorAll('.notif-list .dropdown-item').forEach(el => {
                            el.classList.remove('bg-primary-transparent');
                        });
                    }
                });
        }
    });
});
<?php endif; ?>
</script>

<!-- Global Image Preview Modal -->
<div class="modal fade" id="globalImagePreviewModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-fullscreen-md-down modal-lg">
        <div class="modal-content bg-transparent border-0 shadow-none">
            <div class="modal-header border-0 pb-0 pt-3 pe-3 justify-content-end position-absolute w-100 z-3" style="top:0; right:0;">
                <button type="button" class="btn btn-icon btn-dark rounded-circle shadow" data-bs-dismiss="modal" aria-label="Close" style="opacity: 0.8; width: 40px; height: 40px;">
                    <i class="ri-close-line fs-20 text-white"></i>
                </button>
            </div>
            <div class="modal-body text-center p-0 d-flex justify-content-center align-items-center" style="min-height: 80vh;" data-bs-dismiss="modal">
                <img id="globalPreviewModalImage" src="" class="img-fluid rounded shadow-lg" style="max-height: 85vh; object-fit: contain;">
            </div>
        </div>
    </div>
</div>

<script>
function showGlobalPreview(src) {
    const modalImg = document.getElementById('globalPreviewModalImage');
    if (modalImg) {
        modalImg.src = src;
        const modal = new bootstrap.Modal(document.getElementById('globalImagePreviewModal'));
        modal.show();
    }
}
</script>

</body>
</html>
