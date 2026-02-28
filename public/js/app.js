'use strict';

// ── Toast Notifications ──────────────────────────────────────────────────────
const Toast = (function () {
    function show(msg, type = 'info', duration = 3500) {
        const icons = {
            success: 'fa-circle-check',
            danger: 'fa-circle-xmark',
            warning: 'fa-triangle-exclamation',
            info: 'fa-circle-info',
        };
        const container = document.getElementById('toast-container');
        if (!container) return;

        const el = document.createElement('div');
        el.className = `toast toast-${type}`;
        el.innerHTML = `<i class="fa-solid ${icons[type] ?? icons.info}"></i><span>${msg}</span>`;
        container.appendChild(el);

        setTimeout(() => {
            el.style.opacity = '0';
            el.style.transform = 'translateX(100%)';
            el.style.transition = '300ms ease';
            setTimeout(() => el.remove(), 300);
        }, duration);
    }
    return { show };
})();

// ── OTP Digit Input ──────────────────────────────────────────────────────────
function initOtpInputs() {
    const digits = document.querySelectorAll('.otp-digit');
    if (!digits.length) return;

    const hidden = document.getElementById('otp-hidden');

    function sync() {
        if (hidden) hidden.value = [...digits].map(d => d.value).join('');
    }

    digits.forEach((digit, idx) => {
        digit.addEventListener('input', (e) => {
            // Only keep last character typed
            digit.value = digit.value.slice(-1).replace(/\D/, '');
            sync();
            if (digit.value && idx < digits.length - 1) {
                digits[idx + 1].focus();
            }
        });

        digit.addEventListener('keydown', (e) => {
            if (e.key === 'Backspace' && !digit.value && idx > 0) {
                digits[idx - 1].focus();
            }
        });

        digit.addEventListener('paste', (e) => {
            e.preventDefault();
            const text = (e.clipboardData || window.clipboardData).getData('text').replace(/\D/g, '');
            [...text].forEach((ch, i) => {
                if (digits[i]) digits[i].value = ch;
            });
            sync();
            const next = Math.min(text.length, digits.length - 1);
            digits[next].focus();
        });
    });
}

// ── OTP Countdown ────────────────────────────────────────────────────────────
function initOtpTimer() {
    const el = document.getElementById('otp-countdown');
    if (!el) return;
    let left = parseInt(el.dataset.seconds || '600', 10);

    const interval = setInterval(() => {
        left--;
        const m = String(Math.floor(left / 60)).padStart(2, '0');
        const s = String(left % 60).padStart(2, '0');
        el.textContent = `${m}:${s}`;

        if (left <= 0) {
            clearInterval(interval);
            el.textContent = 'Expired';
            el.style.color = 'var(--danger)';
            const resend = document.getElementById('resend-btn');
            if (resend) resend.removeAttribute('disabled');
        }
    }, 1000);
}

// ── Auto-submit filter selects ───────────────────────────────────────────────
function initAutoFilters() {
    document.querySelectorAll('[data-auto-submit]').forEach(el => {
        el.addEventListener('change', () => el.closest('form').submit());
    });
}

// ── File upload drag & drop preview ─────────────────────────────────────────
function initFileDrop() {
    const zones = document.querySelectorAll('.file-drop');
    zones.forEach(zone => {
        const input = zone.querySelector('input[type="file"]');
        const label = zone.querySelector('.file-drop-label');

        zone.addEventListener('click', () => input.click());

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            zone.classList.add('drag-over');
        });

        zone.addEventListener('dragleave', () => zone.classList.remove('drag-over'));

        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            zone.classList.remove('drag-over');
            if (e.dataTransfer.files.length) {
                input.files = e.dataTransfer.files;
                updateLabel(e.dataTransfer.files[0]);
            }
        });

        input.addEventListener('change', () => {
            if (input.files.length) updateLabel(input.files[0]);
        });

        function updateLabel(file) {
            if (label) {
                label.textContent = `✔ ${file.name} (${(file.size / 1024).toFixed(0)} KB)`;
                label.style.fontWeight = '600';
                label.style.color = 'var(--primary)';
            }
        }
    });
}

// ── Delete confirmation ───────────────────────────────────────────────────────
function initDeleteConfirm() {
    document.querySelectorAll('[data-confirm]').forEach(el => {
        el.addEventListener('click', (e) => {
            const msg = el.dataset.confirm || 'Are you sure?';
            if (!window.confirm(msg)) e.preventDefault();
        });
    });
}

// ── Card entrance animation on scroll ────────────────────────────────────────
function initCardAnimations() {
    if (!('IntersectionObserver' in window)) return;
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(e => {
            if (e.isIntersecting) {
                e.target.classList.add('card-appear');
                obs.unobserve(e.target);
            }
        });
    }, { threshold: 0.08 });

    document.querySelectorAll('.material-card').forEach(c => {
        c.style.opacity = '0';
        obs.observe(c);
    });
}

// ── Submit loading state ─────────────────────────────────────────────────────
function initSubmitLoading() {
    document.querySelectorAll('form[data-loading]').forEach(form => {
        form.addEventListener('submit', () => {
            const btn = form.querySelector('[type="submit"]');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<i class="fa-solid fa-circle-notch spin"></i> ' + (btn.dataset.loadingText || 'Please wait…');
            }
        });
    });
}

// ── Role tab on register page ────────────────────────────────────────────────
function initRoleTabs() {
    const tabs = document.querySelectorAll('.role-tab');
    const input = document.getElementById('role-input');
    if (!tabs.length || !input) return;

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            tabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');
            input.value = tab.dataset.role;
        });
    });
}

// ── Bootstrap ────────────────────────────────────────────────────────────────
document.addEventListener('DOMContentLoaded', () => {
    initOtpInputs();
    initOtpTimer();
    initAutoFilters();
    initFileDrop();
    initDeleteConfirm();
    initCardAnimations();
    initSubmitLoading();
    initRoleTabs();
});
