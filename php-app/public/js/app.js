/**
 * Connect IT — Main JavaScript
 */

'use strict';

// ── Mobile sidebar toggle ─────────────────────────────────────────────────────
(function () {
    const toggle  = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    if (!toggle || !sidebar) return;

    toggle.addEventListener('click', function (e) {
        e.stopPropagation();
        sidebar.classList.toggle('open');
        sidebar.classList.toggle('-translate-x-full');
    });

    // Close on outside click
    document.addEventListener('click', function (e) {
        if (sidebar.classList.contains('open') && !sidebar.contains(e.target) && e.target !== toggle) {
            sidebar.classList.remove('open');
            sidebar.classList.add('-translate-x-full');
        }
    });
})();

// ── Auto-dismiss flash messages ───────────────────────────────────────────────
(function () {
    const flash = document.getElementById('flashMsg');
    if (flash) {
        setTimeout(function () {
            flash.style.transition = 'opacity 0.4s';
            flash.style.opacity    = '0';
            setTimeout(function () { flash.remove(); }, 400);
        }, 5000);
    }
})();

// ── Confirm delete forms ──────────────────────────────────────────────────────
document.querySelectorAll('[data-confirm]').forEach(function (el) {
    el.addEventListener('submit', function (e) {
        if (!confirm(el.dataset.confirm || 'Are you sure?')) {
            e.preventDefault();
        }
    });
});

// ── Ticket filter: auto-submit on select change ───────────────────────────────
(function () {
    const filterForm = document.querySelector('form[data-autosubmit]');
    if (!filterForm) return;
    filterForm.querySelectorAll('select').forEach(function (sel) {
        sel.addEventListener('change', function () { filterForm.submit(); });
    });
})();

// ── SLA countdown timers ──────────────────────────────────────────────────────
document.querySelectorAll('[data-deadline]').forEach(function (el) {
    function update() {
        const deadline = new Date(el.dataset.deadline).getTime();
        const now      = Date.now();
        const diff     = deadline - now;

        if (diff <= 0) {
            el.textContent = 'Breached';
            el.classList.add('text-red-500');
            return;
        }

        const h = Math.floor(diff / 3600000);
        const m = Math.floor((diff % 3600000) / 60000);
        el.textContent = h + 'h ' + m + 'm remaining';

        if (diff < 3600000) {
            el.classList.add('text-red-500');
        } else if (diff < 7200000) {
            el.classList.add('text-yellow-500');
        } else {
            el.classList.add('text-green-600');
        }
    }

    update();
    setInterval(update, 60000);
});

// ── Utility: escape HTML ──────────────────────────────────────────────────────
window.escHtml = function (str) {
    const d = document.createElement('div');
    d.appendChild(document.createTextNode(String(str)));
    return d.innerHTML;
};
