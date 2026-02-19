/**
 * Sawari — Shared JavaScript Utilities
 * 
 * Common helper functions used across all pages.
 */

const SawariUtils = (() => {
    // Base URL (set from PHP, fallback for standalone use)
    const BASE_URL = document.querySelector('meta[name="base-url"]')?.content
        || window.location.origin + '/test_sawari';

    /**
     * Wrapper around fetch() with common defaults.
     * 
     * @param {string} url     - API endpoint (relative to BASE_URL)
     * @param {object} options - Fetch options override
     * @returns {Promise<object>} Parsed JSON response
     */
    async function apiFetch(url, options = {}) {
        const fullUrl = url.startsWith('http') ? url : `${BASE_URL}/${url.replace(/^\//, '')}`;

        const defaults = {
            headers: {
                'Accept': 'application/json',
            },
        };

        // If body is FormData, don't set Content-Type (browser sets it with boundary)
        if (options.body && !(options.body instanceof FormData)) {
            defaults.headers['Content-Type'] = 'application/json';
            if (typeof options.body === 'object') {
                options.body = JSON.stringify(options.body);
            }
        }

        const config = { ...defaults, ...options, headers: { ...defaults.headers, ...options.headers } };

        try {
            const response = await fetch(fullUrl, config);
            const data = await response.json();

            if (!response.ok) {
                throw { status: response.status, ...data };
            }

            return data;
        } catch (error) {
            if (error.status) throw error;
            console.error('API Fetch Error:', error);
            throw { success: false, message: 'Network error. Please check your connection.' };
        }
    }

    /**
     * Show a temporary toast notification.
     * 
     * @param {string} message - Message to display
     * @param {string} type    - 'success', 'error', 'warning', 'info'
     * @param {number} duration - Duration in ms (default: 3000)
     */
    function showToast(message, type = 'info', duration = 3000) {
        // Remove existing toast
        const existing = document.querySelector('.toast-notification');
        if (existing) existing.remove();

        const toast = document.createElement('div');
        toast.className = `toast-notification toast-${type}`;
        toast.innerHTML = `
            <span class="toast-text">${escapeHTML(message)}</span>
            <button class="toast-close" onclick="this.parentElement.remove()"><i class="fa-solid fa-xmark"></i></button>
        `;

        // Add styles if not already present
        if (!document.querySelector('#toast-styles')) {
            const style = document.createElement('style');
            style.id = 'toast-styles';
            style.textContent = `
                .toast-notification {
                    position: fixed; top: 80px; right: 20px; z-index: 5000;
                    padding: 12px 20px; border-radius: 8px; font-size: 0.9375rem;
                    display: flex; align-items: center; gap: 12px;
                    box-shadow: 0 10px 25px rgba(0,0,0,0.15);
                    animation: toastIn 0.3s ease;
                    max-width: 400px;
                }
                .toast-success { background: #dcfce7; color: #16a34a; border: 1px solid #16a34a; }
                .toast-error   { background: #fee2e2; color: #dc2626; border: 1px solid #dc2626; }
                .toast-warning { background: #fef3c7; color: #d97706; border: 1px solid #d97706; }
                .toast-info    { background: #cffafe; color: #0891b2; border: 1px solid #0891b2; }
                .toast-close { background:none; border:none; font-size:1.25rem; cursor:pointer; color:inherit; opacity:0.7; }
                .toast-close:hover { opacity:1; }
                @keyframes toastIn { from { opacity:0; transform: translateX(40px); } to { opacity:1; transform: translateX(0); } }
            `;
            document.head.appendChild(style);
        }

        document.body.appendChild(toast);
        setTimeout(() => {
            toast.style.animation = 'toastIn 0.3s ease reverse';
            setTimeout(() => toast.remove(), 300);
        }, duration);
    }

    /**
     * Escape HTML special characters to prevent XSS.
     * 
     * @param {string} str 
     * @returns {string}
     */
    function escapeHTML(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    /**
     * Format a datetime string to a human-friendly format.
     * 
     * @param {string} datetime - ISO or MySQL datetime string
     * @returns {string}
     */
    function formatDate(datetime) {
        if (!datetime) return 'N/A';
        const d = new Date(datetime);
        if (isNaN(d.getTime())) return 'N/A';
        return d.toLocaleDateString('en-US', {
            year: 'numeric', month: 'short', day: 'numeric',
            hour: '2-digit', minute: '2-digit'
        });
    }

    /**
     * Convert datetime to "time ago" string.
     * 
     * @param {string} datetime
     * @returns {string}
     */
    function timeAgo(datetime) {
        if (!datetime) return 'N/A';
        const now = new Date();
        const then = new Date(datetime);
        const diffMs = now - then;
        const diffSeconds = Math.floor(diffMs / 1000);
        const diffMinutes = Math.floor(diffSeconds / 60);
        const diffHours = Math.floor(diffMinutes / 60);
        const diffDays = Math.floor(diffHours / 24);

        if (diffDays > 30) return formatDate(datetime);
        if (diffDays > 0) return `${diffDays} day${diffDays > 1 ? 's' : ''} ago`;
        if (diffHours > 0) return `${diffHours} hour${diffHours > 1 ? 's' : ''} ago`;
        if (diffMinutes > 0) return `${diffMinutes} minute${diffMinutes > 1 ? 's' : ''} ago`;
        return 'just now';
    }

    /**
     * Debounce a function call.
     * 
     * @param {Function} func  - Function to debounce
     * @param {number}   delay - Delay in ms
     * @returns {Function}
     */
    function debounce(func, delay = 300) {
        let timer;
        return function (...args) {
            clearTimeout(timer);
            timer = setTimeout(() => func.apply(this, args), delay);
        };
    }

    /**
     * Show or hide a loading overlay.
     * 
     * @param {boolean} show 
     */
    function toggleLoading(show) {
        let overlay = document.querySelector('.loading-overlay');
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.className = 'loading-overlay';
            overlay.innerHTML = '<div class="spinner" style="width:48px;height:48px;border-width:4px;"></div>';
            document.body.appendChild(overlay);
        }
        overlay.classList.toggle('active', show);
    }

    /**
     * Open a modal by selector.
     * 
     * @param {string} selector - CSS selector for the modal overlay
     */
    function openModal(selector) {
        const modal = document.querySelector(selector);
        if (modal) modal.classList.add('active');
    }

    /**
     * Close a modal by selector.
     * 
     * @param {string} selector - CSS selector for the modal overlay
     */
    function closeModal(selector) {
        const modal = document.querySelector(selector);
        if (modal) modal.classList.remove('active');
    }

    /**
     * Confirm action with a simple dialog.
     * 
     * @param {string} message 
     * @returns {boolean}
     */
    function confirmAction(message = 'Are you sure?') {
        return window.confirm(message);
    }

    /**
     * Get status badge HTML.
     * 
     * @param {string} status 
     * @returns {string}
     */
    function statusBadge(status) {
        const cls = {
            pending: 'badge-pending',
            approved: 'badge-approved',
            accepted: 'badge-accepted',
            rejected: 'badge-rejected',
            reviewed: 'badge-info',
            resolved: 'badge-approved',
        }[status] || 'badge-info';

        return `<span class="badge ${cls}">${escapeHTML(status)}</span>`;
    }

    // Mobile nav toggle
    document.addEventListener('DOMContentLoaded', () => {
        const toggle = document.getElementById('navToggle');
        const links = document.getElementById('navLinks');
        if (toggle && links) {
            toggle.addEventListener('click', () => {
                links.classList.toggle('active');
            });
        }
    });

    // Public API
    return {
        BASE_URL,
        apiFetch,
        showToast,
        escapeHTML,
        formatDate,
        timeAgo,
        debounce,
        toggleLoading,
        openModal,
        closeModal,
        confirmAction,
        statusBadge,
    };
})();
