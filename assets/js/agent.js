/**
 * SAWARI — Agent JS Controller
 *
 * Extends the `Sawari` namespace with utilities for agent pages:
 *   api()         – AJAX helper (shared pattern with admin.js)
 *   toast()       – toast notifications
 *   modal()       – reusable modal open/close
 *   confirm()     – confirmation dialog
 *   escape()      – HTML-escape helper
 *   setLoading()  – button loading state
 *   emptyRow()    – empty table state
 *   pagination()  – pagination renderer
 *   sidebar / dropdown / modal close wiring
 */

/* ─── Namespace ───────────────────────────────────────────── */
var Sawari = Sawari || {};

(function () {
    'use strict';

    /* ── Constants ────────────────────────────────────────── */
    var BASE = document.querySelector('meta[name="base-url"]');
    var CSRF = document.querySelector('meta[name="csrf-token"]');
    Sawari.baseUrl = BASE ? BASE.content : '';
    Sawari.csrfToken = CSRF ? CSRF.content : '';

    /* ── HTML Escape ─────────────────────────────────────── */
    Sawari.escape = function (str) {
        if (!str) return '';
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    };

    /* ── API Helper ──────────────────────────────────────── */
    Sawari.api = function (endpoint, action, data, method) {
        var url = Sawari.baseUrl + '/api/' + endpoint + '.php?action=' + encodeURIComponent(action);

        if (!method) {
            method = data ? 'POST' : 'GET';
        }

        var opts = { method: method, credentials: 'same-origin' };

        if (method === 'POST') {
            var fd;
            if (data instanceof FormData) {
                fd = data;
            } else {
                fd = new FormData();
                if (data) {
                    Object.keys(data).forEach(function (k) {
                        fd.append(k, data[k]);
                    });
                }
            }
            fd.append('csrf_token', Sawari.csrfToken);
            opts.body = fd;
        } else if (method === 'GET' && data) {
            var params = [];
            Object.keys(data).forEach(function (k) {
                params.push(encodeURIComponent(k) + '=' + encodeURIComponent(data[k]));
            });
            if (params.length) url += '&' + params.join('&');
        }

        return fetch(url, opts)
            .then(function (res) { return res.json(); })
            .then(function (json) {
                if (json && json.error) {
                    Sawari.toast(json.error, 'danger');
                }
                return json;
            })
            .catch(function (err) {
                Sawari.toast('Network error. Please try again.', 'danger');
                throw err;
            });
    };

    /* ── Toasts ──────────────────────────────────────────── */
    Sawari.toast = function (message, type, duration) {
        type = type || 'info';
        duration = duration || 4000;

        var container = document.getElementById('toast-container');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container';
            document.body.appendChild(container);
        }

        var toast = document.createElement('div');
        toast.className = 'toast toast-' + type;

        var icons = { success: 'check-circle', danger: 'x-circle', warning: 'alert-triangle', info: 'info' };
        var icon = icons[type] || 'info';

        toast.innerHTML =
            '<i data-feather="' + icon + '" class="toast-icon"></i>' +
            '<span class="toast-message">' + Sawari.escape(message) + '</span>' +
            '<button class="toast-close" onclick="this.parentElement.remove()"><i data-feather="x"></i></button>';

        container.appendChild(toast);
        feather.replace({ 'stroke-width': 1.75 });

        requestAnimationFrame(function () {
            toast.classList.add('toast-show');
        });

        setTimeout(function () {
            toast.classList.add('toast-hide');
            setTimeout(function () { toast.remove(); }, 300);
        }, duration);
    };

    /* ── Modal ───────────────────────────────────────────── */
    Sawari.modal = function (title, bodyHtml, footerHtml) {
        var overlay = document.getElementById('modal-overlay');
        if (!overlay) return;

        if (!title) {
            overlay.classList.remove('active');
            return;
        }

        document.getElementById('modal-title').textContent = title;
        document.getElementById('modal-body').innerHTML = bodyHtml || '';
        var footer = document.getElementById('modal-footer');
        if (footerHtml) {
            footer.innerHTML = footerHtml;
            footer.style.display = '';
        } else {
            footer.innerHTML = '';
            footer.style.display = 'none';
        }

        overlay.classList.add('active');
        feather.replace({ 'stroke-width': 1.75 });
    };

    /* ── Confirm Dialog ──────────────────────────────────── */
    Sawari.confirm = function (message, onConfirm, confirmLabel, confirmClass) {
        confirmLabel = confirmLabel || 'Confirm';
        confirmClass = confirmClass || 'btn-primary';

        var body = '<p style="margin:0;">' + Sawari.escape(message) + '</p>';
        var footer =
            '<button class="btn btn-secondary" onclick="Sawari.modal()">Cancel</button>' +
            '<button class="btn ' + confirmClass + '" id="confirm-action-btn">' + confirmLabel + '</button>';

        Sawari.modal('Confirm Action', body, footer);

        document.getElementById('confirm-action-btn').addEventListener('click', function () {
            Sawari.modal();
            if (onConfirm) onConfirm();
        });
    };

    /* ── Loading State ───────────────────────────────────── */
    Sawari.setLoading = function (btn, loading) {
        if (loading) {
            btn.disabled = true;
            btn.dataset.originalText = btn.innerHTML;
            btn.classList.add('is-loading');
            btn.innerHTML = '<span class="spinner spinner-sm"></span>';
        } else {
            btn.disabled = false;
            btn.classList.remove('is-loading');
            btn.innerHTML = btn.dataset.originalText || btn.innerHTML;
        }
    };

    /* ── Empty Table Row ─────────────────────────────────── */
    Sawari.emptyRow = function (cols, message) {
        return '<tr><td colspan="' + cols + '" class="text-center text-muted" style="padding:var(--space-10) var(--space-4);">' +
            '<div class="empty-state">' +
            '<i data-feather="inbox" class="empty-state-icon"></i>' +
            '<p style="margin:var(--space-2) 0 0;">' + Sawari.escape(message || 'No items found.') + '</p>' +
            '</div></td></tr>';
    };

    /* ── Pagination ──────────────────────────────────────── */
    Sawari.pagination = function (container, page, totalPages, onPageChange) {
        if (!container || totalPages <= 1) {
            if (container) container.innerHTML = '';
            return;
        }

        var html = '<div class="pagination">';

        html += '<button class="pagination-btn' + (page <= 1 ? ' disabled' : '') + '" data-page="' + (page - 1) + '"' + (page <= 1 ? ' disabled' : '') + '>';
        html += '<i data-feather="chevron-left" style="width:14px;height:14px;"></i></button>';

        var start = Math.max(1, page - 2);
        var end = Math.min(totalPages, start + 4);
        start = Math.max(1, end - 4);

        for (var i = start; i <= end; i++) {
            html += '<button class="pagination-btn' + (i === page ? ' active' : '') + '" data-page="' + i + '">' + i + '</button>';
        }

        html += '<button class="pagination-btn' + (page >= totalPages ? ' disabled' : '') + '" data-page="' + (page + 1) + '"' + (page >= totalPages ? ' disabled' : '') + '>';
        html += '<i data-feather="chevron-right" style="width:14px;height:14px;"></i></button>';

        html += '</div>';
        container.innerHTML = html;
        feather.replace({ 'stroke-width': 1.75 });

        container.querySelectorAll('.pagination-btn:not(.disabled)').forEach(function (btn) {
            btn.addEventListener('click', function () {
                onPageChange(parseInt(this.dataset.page, 10));
            });
        });
    };

    /* ── Status Badge Helper ─────────────────────────────── */
    Sawari.statusBadge = function (status) {
        var classes = {
            pending: 'badge-warning',
            approved: 'badge-success',
            rejected: 'badge-danger'
        };
        return '<span class="badge ' + (classes[status] || 'badge-neutral') + '">' + Sawari.escape(status) + '</span>';
    };

    /* ── Type Badge Helper ───────────────────────────────── */
    Sawari.typeBadge = function (type) {
        var classes = {
            location: 'badge-primary',
            vehicle: 'badge-accent',
            route: 'badge-success'
        };
        return '<span class="badge ' + (classes[type] || 'badge-neutral') + '">' + Sawari.escape(type) + '</span>';
    };

    /* ── Format Time Ago ─────────────────────────────────── */
    Sawari.timeAgo = function (dateStr) {
        if (!dateStr) return '—';
        var now = Date.now();
        var then = new Date(dateStr).getTime();
        var diff = Math.floor((now - then) / 1000);

        if (diff < 60) return 'just now';
        if (diff < 3600) return Math.floor(diff / 60) + 'm ago';
        if (diff < 86400) return Math.floor(diff / 3600) + 'h ago';
        if (diff < 604800) return Math.floor(diff / 86400) + 'd ago';

        return new Date(dateStr).toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });
    };

    /* ── Sidebar & UI Wiring ─────────────────────────────── */

    function initSidebar() {
        var toggle = document.getElementById('sidebar-toggle');
        var sidebar = document.getElementById('sidebar');
        var backdrop = document.getElementById('sidebar-backdrop');

        if (!toggle || !sidebar) return;

        toggle.addEventListener('click', function () {
            sidebar.classList.toggle('open');
            if (backdrop) backdrop.classList.toggle('active');
        });

        if (backdrop) {
            backdrop.addEventListener('click', function () {
                sidebar.classList.remove('open');
                backdrop.classList.remove('active');
            });
        }
    }

    function initDropdowns() {
        var trigger = document.getElementById('user-dropdown-btn');
        var menu = document.getElementById('user-dropdown');
        if (trigger && menu) {
            trigger.addEventListener('click', function (e) {
                e.stopPropagation();
                menu.classList.toggle('active');
            });
        }

        document.addEventListener('click', function () {
            document.querySelectorAll('.dropdown-menu.active').forEach(function (m) {
                m.classList.remove('active');
            });
        });
    }

    function initModalClose() {
        var overlay = document.getElementById('modal-overlay');
        if (!overlay) return;

        var closeBtn = document.getElementById('modal-close');
        if (closeBtn) {
            closeBtn.addEventListener('click', function () { Sawari.modal(); });
        }

        overlay.addEventListener('click', function (e) {
            if (e.target === overlay) Sawari.modal();
        });

        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && overlay.classList.contains('active')) {
                Sawari.modal();
            }
        });
    }

    function initLogout() {
        var logoutBtn = document.getElementById('logout-btn');
        if (!logoutBtn) return;

        logoutBtn.addEventListener('click', function (e) {
            e.preventDefault();
            Sawari.confirm('Are you sure you want to log out?', function () {
                Sawari.api('agents', 'logout').then(function () {
                    window.location.href = Sawari.baseUrl + '/pages/agent/login.php';
                });
            }, 'Log Out', 'btn-danger');
        });
    }

    /* ── Boot ─────────────────────────────────────────────── */
    document.addEventListener('DOMContentLoaded', function () {
        initSidebar();
        initDropdowns();
        initModalClose();
        initLogout();
    });

})();
