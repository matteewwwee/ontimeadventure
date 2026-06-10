/**
 * ============================================================
 * ON TIME ADVENTURE — app.js
 * Core JavaScript for interactivity
 * ============================================================
 */

(function () {
  'use strict';

  /* ──────────────────────────────────────────────────────
     1. TOAST NOTIFICATION SYSTEM
     ────────────────────────────────────────────────────── */
  const TOAST_ICONS = {
    success: '✅',
    error: '❌',
    info: 'ℹ️',
    warning: '⚠️',
  };

  /**
   * Show a toast notification
   * @param {string} message - The message to display
   * @param {'success'|'error'|'info'|'warning'} type - Toast type
   * @param {number} duration - Auto-dismiss duration in ms (default 4000)
   */
  window.showToast = function (message, type = 'success', duration = 4000) {
    let container = document.querySelector('.toast-container');
    if (!container) {
      container = document.createElement('div');
      container.className = 'toast-container';
      document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast toast-${type}`;
    toast.innerHTML = `
      <span class="toast-icon">${TOAST_ICONS[type] || TOAST_ICONS.info}</span>
      <span class="toast-message">${escapeHtml(message)}</span>
      <button class="toast-close" aria-label="Tutup">&times;</button>
    `;

    container.appendChild(toast);

    // Close button handler
    toast.querySelector('.toast-close').addEventListener('click', () => dismissToast(toast));

    // Auto-dismiss
    const timer = setTimeout(() => dismissToast(toast), duration);
    toast._timer = timer;
  };

  function dismissToast(toast) {
    if (toast._dismissed) return;
    toast._dismissed = true;
    clearTimeout(toast._timer);
    toast.classList.add('removing');
    toast.addEventListener('animationend', () => toast.remove());
  }

  /* ──────────────────────────────────────────────────────
     2. MOBILE MENU TOGGLE (HAMBURGER)
     ────────────────────────────────────────────────────── */
  function initMobileMenu() {
    const toggle = document.querySelector('.navbar-toggle');
    const nav = document.querySelector('.navbar-nav');

    if (!toggle || !nav) return;

    toggle.addEventListener('click', () => {
      toggle.classList.toggle('active');
      nav.classList.toggle('open');
      document.body.style.overflow = nav.classList.contains('open') ? 'hidden' : '';
    });

    // Close mobile menu when clicking a nav link
    nav.querySelectorAll('.nav-link').forEach((link) => {
      link.addEventListener('click', () => {
        toggle.classList.remove('active');
        nav.classList.remove('open');
        document.body.style.overflow = '';
      });
    });
  }

  /* ──────────────────────────────────────────────────────
     3. NAVBAR SCROLL EFFECT
     ────────────────────────────────────────────────────── */
  function initNavbarScroll() {
    const navbar = document.querySelector('.navbar');
    if (!navbar) return;

    const onScroll = () => {
      navbar.classList.toggle('scrolled', window.scrollY > 40);
    };

    window.addEventListener('scroll', onScroll, { passive: true });
    onScroll();
  }

  /* ──────────────────────────────────────────────────────
     4. CONFIRM DELETE MODAL
     ────────────────────────────────────────────────────── */
  window.confirmDelete = function (formId, itemName = 'item ini') {
    const overlay = document.getElementById('modal-delete');
    if (!overlay) return createInlineConfirm(formId, itemName);

    const msgEl = overlay.querySelector('.modal-body');
    if (msgEl) {
      msgEl.textContent = `Apakah Anda yakin ingin menghapus ${itemName}? Tindakan ini tidak dapat dibatalkan.`;
    }

    overlay.classList.add('active');

    const confirmBtn = overlay.querySelector('.btn-confirm-delete');
    const cancelBtn = overlay.querySelector('.btn-cancel-delete');
    const closeBtn = overlay.querySelector('.modal-close');

    function closeModal() {
      overlay.classList.remove('active');
    }

    function handleConfirm() {
      const form = document.getElementById(formId);
      if (form) form.submit();
      closeModal();
    }

    // Attach one-time listeners
    confirmBtn?.addEventListener('click', handleConfirm, { once: true });
    cancelBtn?.addEventListener('click', closeModal, { once: true });
    closeBtn?.addEventListener('click', closeModal, { once: true });
    overlay.addEventListener('click', (e) => {
      if (e.target === overlay) closeModal();
    }, { once: true });
  };

  function createInlineConfirm(formId, itemName) {
    if (confirm(`Apakah Anda yakin ingin menghapus ${itemName}?`)) {
      const form = document.getElementById(formId);
      if (form) form.submit();
    }
  }

  /* ──────────────────────────────────────────────────────
     5. MODAL GENERIC OPEN / CLOSE
     ────────────────────────────────────────────────────── */
  window.openModal = function (id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.add('active');
  };

  window.closeModal = function (id) {
    const modal = document.getElementById(id);
    if (modal) modal.classList.remove('active');
  };

  function initModals() {
    document.querySelectorAll('.modal-overlay').forEach((overlay) => {
      overlay.addEventListener('click', (e) => {
        if (e.target === overlay) overlay.classList.remove('active');
      });

      const closeBtn = overlay.querySelector('.modal-close');
      closeBtn?.addEventListener('click', () => overlay.classList.remove('active'));
    });
  }

  /* ──────────────────────────────────────────────────────
     6. FORM VALIDATION HELPERS
     ────────────────────────────────────────────────────── */

  /**
   * Validate Indonesian phone number (08xx / +628xx)
   */
  window.validatePhone = function (value) {
    const cleaned = value.replace(/[\s\-]/g, '');
    return /^(\+62|62|0)8[1-9]\d{7,11}$/.test(cleaned);
  };

  /**
   * Validate PIN is exactly 4 digits
   */
  window.validatePIN = function (value) {
    return /^\d{4}$/.test(value);
  };

  /**
   * Show inline validation error
   */
  window.setFieldError = function (input, message) {
    input.classList.add('is-invalid');
    input.classList.remove('is-valid');
    let err = input.parentElement.querySelector('.form-error');
    if (!err) {
      err = document.createElement('span');
      err.className = 'form-error';
      input.parentElement.appendChild(err);
    }
    err.textContent = message;
  };

  /**
   * Clear inline validation error
   */
  window.clearFieldError = function (input) {
    input.classList.remove('is-invalid');
    input.classList.add('is-valid');
    const err = input.parentElement.querySelector('.form-error');
    if (err) err.remove();
  };

  /* ──────────────────────────────────────────────────────
     7. AUTO-FORMAT PHONE NUMBER INPUT
     ────────────────────────────────────────────────────── */
  function initPhoneFormat() {
    document.querySelectorAll('input[data-format="phone"]').forEach((input) => {
      input.addEventListener('input', (e) => {
        let val = e.target.value.replace(/[^\d+]/g, '');
        // Keep + only at start
        if (val.indexOf('+') > 0) {
          val = val.replace(/\+/g, '');
        }
        e.target.value = val;
      });
    });
  }

  /* ──────────────────────────────────────────────────────
     8. PIN INPUT AUTO-FOCUS (4 separate boxes)
     ────────────────────────────────────────────────────── */
  function initPinInputs() {
    document.querySelectorAll('.pin-input-group').forEach((group) => {
      const inputs = group.querySelectorAll('input');
      inputs.forEach((input, index) => {
        input.addEventListener('input', (e) => {
          const val = e.target.value.replace(/\D/g, '');
          e.target.value = val.slice(0, 1);
          if (val && index < inputs.length - 1) {
            inputs[index + 1].focus();
          }
        });

        input.addEventListener('keydown', (e) => {
          if (e.key === 'Backspace' && !e.target.value && index > 0) {
            inputs[index - 1].focus();
          }
        });

        // Paste support
        input.addEventListener('paste', (e) => {
          e.preventDefault();
          const pasted = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
          for (let i = 0; i < inputs.length && i < pasted.length; i++) {
            inputs[i].value = pasted[i];
          }
          const nextIndex = Math.min(pasted.length, inputs.length - 1);
          inputs[nextIndex].focus();
        });
      });
    });
  }

  /* ──────────────────────────────────────────────────────
     9. DATE PICKER VALIDATION
     ────────────────────────────────────────────────────── */
  function initDateValidation() {
    const startInput = document.getElementById('tgl_mulai_sewa');
    const endInput = document.getElementById('tgl_selesai_sewa');
    if (!startInput || !endInput) return;

    // Set minimum date to today
    const today = new Date().toISOString().split('T')[0];
    startInput.setAttribute('min', today);

    function validateDates() {
      if (startInput.value) {
        endInput.setAttribute('min', startInput.value);

        // If end date is before start, clear it
        if (endInput.value && endInput.value <= startInput.value) {
          endInput.value = '';
          setFieldError(endInput, 'Tanggal selesai harus setelah tanggal mulai');
        } else if (endInput.value) {
          clearFieldError(endInput);
        }
      }
    }

    startInput.addEventListener('change', validateDates);
    endInput.addEventListener('change', validateDates);
  }

  /* ──────────────────────────────────────────────────────
     10. RENTAL ESTIMATION CALCULATOR
     ────────────────────────────────────────────────────── */
  window.calculateRentalEstimation = function (pricePerDay, startDate, endDate, quantity = 1) {
    const start = new Date(startDate);
    const end = new Date(endDate);
    const diffTime = end - start;
    const days = Math.ceil(diffTime / (1000 * 60 * 60 * 24));

    if (days <= 0) return 0;
    return days * pricePerDay * quantity;
  };

  /**
   * Format number to Indonesian Rupiah
   */
  window.formatRupiah = function (number) {
    return new Intl.NumberFormat('id-ID', {
      style: 'currency',
      currency: 'IDR',
      minimumFractionDigits: 0,
      maximumFractionDigits: 0,
    }).format(number);
  };

  function initRentalCalculator() {
    const form = document.getElementById('form-po');
    if (!form) return;

    const startInput = form.querySelector('#tgl_mulai_sewa');
    const endInput = form.querySelector('#tgl_selesai_sewa');
    const estimationEl = form.querySelector('#estimasi-total');

    if (!startInput || !endInput || !estimationEl) return;

    function recalculate() {
      if (!startInput.value || !endInput.value) {
        estimationEl.textContent = formatRupiah(0);
        return;
      }

      // Sum up all items in the PO form (could be multiple variants)
      let total = 0;
      form.querySelectorAll('[data-harga]').forEach((el) => {
        const price = parseInt(el.dataset.harga, 10) || 0;
        const qtyInput = el.closest('tr')?.querySelector('.qty-input');
        const qty = qtyInput ? parseInt(qtyInput.value, 10) || 1 : 1;
        total += calculateRentalEstimation(price, startInput.value, endInput.value, qty);
      });

      estimationEl.textContent = formatRupiah(total);
    }

    startInput.addEventListener('change', recalculate);
    endInput.addEventListener('change', recalculate);
    form.addEventListener('input', (e) => {
      if (e.target.classList.contains('qty-input')) recalculate();
    });
  }

  /* ──────────────────────────────────────────────────────
     11. CART QUANTITY +/- BUTTONS
     ────────────────────────────────────────────────────── */
  function initQuantityControls() {
    document.addEventListener('click', (e) => {
      const btn = e.target.closest('.qty-btn');
      if (!btn) return;

      const control = btn.closest('.qty-control');
      const input = control?.querySelector('input');
      if (!input) return;

      let val = parseInt(input.value, 10) || 1;
      const min = parseInt(input.min, 10) || 1;
      const max = parseInt(input.max, 10) || 999;

      if (btn.dataset.action === 'increment') {
        val = Math.min(val + 1, max);
      } else if (btn.dataset.action === 'decrement') {
        val = Math.max(val - 1, min);
      }

      input.value = val;
      input.dispatchEvent(new Event('input', { bubbles: true }));
    });
  }

  /* ──────────────────────────────────────────────────────
     12. FADE-IN ON SCROLL (IntersectionObserver)
     ────────────────────────────────────────────────────── */
  function initScrollReveal() {
    const elements = document.querySelectorAll('.reveal');
    if (!elements.length) return;

    const observer = new IntersectionObserver(
      (entries) => {
        entries.forEach((entry) => {
          if (entry.isIntersecting) {
            entry.target.classList.add('visible');
            observer.unobserve(entry.target);
          }
        });
      },
      { threshold: 0.1, rootMargin: '0px 0px -40px 0px' }
    );

    elements.forEach((el) => observer.observe(el));
  }

  /* ──────────────────────────────────────────────────────
     13. IMAGE PREVIEW ON UPLOAD
     ────────────────────────────────────────────────────── */
  function initImagePreview() {
    document.querySelectorAll('input[type="file"][data-preview]').forEach((input) => {
      input.addEventListener('change', (e) => {
        const file = e.target.files[0];
        const previewId = e.target.dataset.preview;
        const previewEl = document.getElementById(previewId);
        if (!file || !previewEl) return;

        if (!file.type.startsWith('image/')) {
          showToast('File harus berupa gambar', 'error');
          e.target.value = '';
          return;
        }

        const reader = new FileReader();
        reader.onload = (ev) => {
          previewEl.src = ev.target.result;
          previewEl.style.display = 'block';
        };
        reader.readAsDataURL(file);
      });
    });
  }

  /* ──────────────────────────────────────────────────────
     14. ADMIN SIDEBAR TOGGLE (MOBILE)
     ────────────────────────────────────────────────────── */
  function initSidebarToggle() {
    const toggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', () => {
      sidebar.classList.toggle('open');
    });

    // Close sidebar on click outside (mobile)
    document.addEventListener('click', (e) => {
      if (
        sidebar.classList.contains('open') &&
        !sidebar.contains(e.target) &&
        !toggle.contains(e.target)
      ) {
        sidebar.classList.remove('open');
      }
    });
  }

  /* ──────────────────────────────────────────────────────
     15. SMOOTH SCROLL FOR ANCHOR LINKS
     ────────────────────────────────────────────────────── */
  function initSmoothScroll() {
    document.querySelectorAll('a[href^="#"]').forEach((anchor) => {
      anchor.addEventListener('click', (e) => {
        const targetId = anchor.getAttribute('href');
        if (targetId === '#') return;

        const target = document.querySelector(targetId);
        if (target) {
          e.preventDefault();
          const navbarHeight = parseInt(
            getComputedStyle(document.documentElement).getPropertyValue('--navbar-height'),
            10
          ) || 72;
          const top = target.getBoundingClientRect().top + window.scrollY - navbarHeight - 16;
          window.scrollTo({ top, behavior: 'smooth' });
        }
      });
    });
  }

  /* ──────────────────────────────────────────────────────
     16. UTILITY — HTML ESCAPE
     ────────────────────────────────────────────────────── */
  function escapeHtml(str) {
    const div = document.createElement('div');
    div.appendChild(document.createTextNode(str));
    return div.innerHTML;
  }

  /* ──────────────────────────────────────────────────────
     17. FLASH MESSAGES → TOAST CONVERSION
     ────────────────────────────────────────────────────── */
  function initFlashToasts() {
    document.querySelectorAll('[data-flash]').forEach((el) => {
      const type = el.dataset.flash || 'info';
      const message = el.textContent.trim();
      if (message) {
        showToast(message, type);
      }
      el.remove();
    });
  }

  /* ──────────────────────────────────────────────────────
     BOOT — DOM READY
     ────────────────────────────────────────────────────── */
  document.addEventListener('DOMContentLoaded', () => {
    initMobileMenu();
    initNavbarScroll();
    initModals();
    initPhoneFormat();
    initPinInputs();
    initDateValidation();
    initRentalCalculator();
    initQuantityControls();
    initScrollReveal();
    initImagePreview();
    initSidebarToggle();
    initSmoothScroll();
    initFlashToasts();
  });
})();

/* === SCROLL REVEAL ANIMATIONS === */
document.addEventListener('DOMContentLoaded', () => {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.1, rootMargin: '0px 0px -50px 0px' });
    document.querySelectorAll('.fade-up').forEach(el => observer.observe(el));
});

