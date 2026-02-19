/**
 * Sawari — Admin Dashboard JavaScript
 * 
 * Shared admin utilities: sidebar toggle, CRUD helpers,
 * pagination, modal management, and table operations.
 */

const AdminUtils = (() => {
    /**
     * Initialize sidebar toggle for mobile.
     */
    function initSidebar() {
        const sidebar = document.querySelector('.admin-sidebar');
        const toggle = document.querySelector('.sidebar-toggle');
        const overlay = document.querySelector('.sidebar-overlay');

        if (toggle && sidebar) {
            toggle.addEventListener('click', () => {
                sidebar.classList.toggle('open');
                overlay?.classList.toggle('active');
            });
        }

        if (overlay && sidebar) {
            overlay.addEventListener('click', () => {
                sidebar.classList.remove('open');
                overlay.classList.remove('active');
            });
        }
    }

    /**
     * Build pagination HTML.
     * @param {object} paging - { currentPage, totalPages, totalItems }
     * @param {string} baseUrl - Base URL with query params (without page=)
     * @returns {string} HTML
     */
    function renderPagination(paging, baseUrl) {
        if (paging.totalPages <= 1) return '';

        const separator = baseUrl.includes('?') ? '&' : '?';
        let html = '<div class="pagination">';

        // Previous
        if (paging.currentPage > 1) {
            html += `<a href="${baseUrl}${separator}page=${paging.currentPage - 1}">&laquo; Prev</a>`;
        } else {
            html += `<span class="disabled">&laquo; Prev</span>`;
        }

        // Page numbers
        const start = Math.max(1, paging.currentPage - 2);
        const end = Math.min(paging.totalPages, paging.currentPage + 2);

        if (start > 1) {
            html += `<a href="${baseUrl}${separator}page=1">1</a>`;
            if (start > 2) html += `<span class="disabled">...</span>`;
        }

        for (let i = start; i <= end; i++) {
            if (i === paging.currentPage) {
                html += `<span class="active">${i}</span>`;
            } else {
                html += `<a href="${baseUrl}${separator}page=${i}">${i}</a>`;
            }
        }

        if (end < paging.totalPages) {
            if (end < paging.totalPages - 1) html += `<span class="disabled">...</span>`;
            html += `<a href="${baseUrl}${separator}page=${paging.totalPages}">${paging.totalPages}</a>`;
        }

        // Next
        if (paging.currentPage < paging.totalPages) {
            html += `<a href="${baseUrl}${separator}page=${paging.currentPage + 1}">Next &raquo;</a>`;
        } else {
            html += `<span class="disabled">Next &raquo;</span>`;
        }

        html += '</div>';
        return html;
    }

    /**
     * Perform an admin API action (create, update, delete, respond).
     * @param {string} url - API endpoint 
     * @param {object|FormData} data - POST data
     * @param {object} opts - { method, onSuccess, onError }
     */
    async function apiAction(url, data, opts = {}) {
        const method = opts.method || 'POST';

        try {
            SawariUtils.toggleLoading(true);

            const fetchOpts = { method };

            if (data instanceof FormData) {
                fetchOpts.body = data;
            } else {
                fetchOpts.headers = { 'Content-Type': 'application/x-www-form-urlencoded' };
                fetchOpts.body = new URLSearchParams(data).toString();
            }

            const response = await fetch(url, fetchOpts);
            const result = await response.json();

            SawariUtils.toggleLoading(false);

            if (result.success) {
                SawariUtils.showToast(result.message || 'Operation successful', 'success');
                if (opts.onSuccess) opts.onSuccess(result);
            } else {
                SawariUtils.showToast(result.message || 'Operation failed', 'error');
                if (opts.onError) opts.onError(result);
            }

            return result;
        } catch (err) {
            SawariUtils.toggleLoading(false);
            SawariUtils.showToast('Network error. Please try again.', 'error');
            console.error('API Action Error:', err);
            if (opts.onError) opts.onError({ success: false, message: 'Network error' });
            return { success: false, message: 'Network error' };
        }
    }

    /**
     * Handle delete action with confirmation.
     * @param {string} url - Delete API endpoint
     * @param {string} entityName - Name of entity being deleted
     * @param {Function} onSuccess - Callback after successful delete
     */
    async function confirmDelete(url, entityName, onSuccess) {
        if (!SawariUtils.confirmAction(`Are you sure you want to delete "${entityName}"? This cannot be undone.`)) {
            return;
        }

        const result = await apiAction(url, {}, {
            onSuccess: () => {
                if (onSuccess) onSuccess();
                else location.reload();
            }
        });

        return result;
    }

    /**
     * Open a modal and optionally populate it with data.
     * @param {string} selector - Modal overlay CSS selector
     * @param {object} data - Key-value pairs to fill form fields
     */
    function openFormModal(selector, data = {}) {
        const modal = document.querySelector(selector);
        if (!modal) return;

        // Reset form if present
        const form = modal.querySelector('form');
        if (form && Object.keys(data).length === 0) {
            form.reset();
        }

        // Fill fields with data
        Object.entries(data).forEach(([key, value]) => {
            const field = modal.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'file') return; // skip file inputs
                field.value = value ?? '';
            }
        });

        modal.classList.add('active');
    }

    /**
     * Close a modal.
     * @param {string} selector
     */
    function closeFormModal(selector) {
        const modal = document.querySelector(selector);
        if (modal) modal.classList.remove('active');
    }

    /**
     * Build a base URL with current filter params for pagination links.
     * @param {object} params - Current filter parameters
     * @returns {string}
     */
    function buildFilterUrl(params) {
        const url = new URL(window.location.href);
        Object.entries(params).forEach(([key, value]) => {
            if (value) {
                url.searchParams.set(key, value);
            } else {
                url.searchParams.delete(key);
            }
        });
        url.searchParams.delete('page'); // Remove page when filters change
        return url.pathname + '?' + url.searchParams.toString();
    }

    /**
     * Initialize draggable list for route stop ordering.
     * @param {HTMLElement} list - The UL/OL element containing stop items
     * @param {Function} onReorder - Callback when order changes, receives ordered array of location IDs
     */
    function initDragSort(list, onReorder) {
        if (!list) return;

        let dragItem = null;

        list.addEventListener('dragstart', (e) => {
            dragItem = e.target.closest('.stop-item');
            if (dragItem) {
                dragItem.style.opacity = '0.5';
                e.dataTransfer.effectAllowed = 'move';
            }
        });

        list.addEventListener('dragend', () => {
            if (dragItem) {
                dragItem.style.opacity = '1';
                dragItem = null;
                _updateStopIndexes(list);
                if (onReorder) {
                    const ids = [...list.querySelectorAll('.stop-item')]
                        .map(item => parseInt(item.dataset.locationId));
                    onReorder(ids);
                }
            }
        });

        list.addEventListener('dragover', (e) => {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            const afterEl = _getDragAfterElement(list, e.clientY);
            if (afterEl) {
                list.insertBefore(dragItem, afterEl);
            } else {
                list.appendChild(dragItem);
            }
        });
    }

    function _getDragAfterElement(container, y) {
        const items = [...container.querySelectorAll('.stop-item:not([style*="opacity: 0.5"])')];
        return items.reduce((closest, child) => {
            const box = child.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) {
                return { offset, element: child };
            }
            return closest;
        }, { offset: Number.NEGATIVE_INFINITY }).element;
    }

    function _updateStopIndexes(list) {
        list.querySelectorAll('.stop-item').forEach((item, i) => {
            const indexEl = item.querySelector('.stop-index');
            if (indexEl) indexEl.textContent = i + 1;
        });
    }

    /**
     * Set up close-on-backdrop-click for all modals.
     */
    function initModals() {
        document.querySelectorAll('.modal-overlay').forEach(overlay => {
            overlay.addEventListener('click', (e) => {
                if (e.target === overlay) {
                    overlay.classList.remove('active');
                }
            });
        });

        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', () => {
                btn.closest('.modal-overlay')?.classList.remove('active');
            });
        });

        // ESC key closes modals
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                document.querySelectorAll('.modal-overlay.active').forEach(m => {
                    m.classList.remove('active');
                });
            }
        });
    }

    // Auto-init on DOM ready
    document.addEventListener('DOMContentLoaded', () => {
        initSidebar();
        initModals();
    });

    return {
        initSidebar,
        renderPagination,
        apiAction,
        confirmDelete,
        openFormModal,
        closeFormModal,
        buildFilterUrl,
        initModals,
        initDragSort,
    };
})();
