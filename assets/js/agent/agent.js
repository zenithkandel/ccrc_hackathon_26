/**
 * Sawari — Agent Dashboard JavaScript
 * 
 * Shared agent utilities: sidebar toggle, modal helpers,
 * contribution form handlers, drag-and-drop stop ordering.
 * Extends patterns from admin.js for agent-specific flows.
 */

const AgentUtils = (() => {
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
     * Perform an agent API action.
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
            console.error('Agent API Error:', err);
            if (opts.onError) opts.onError({ success: false, message: 'Network error' });
            return { success: false, message: 'Network error' };
        }
    }

    /**
     * Open a modal and optionally populate it with data.
     * @param {string} selector - Modal overlay CSS selector
     * @param {object} data - Key-value pairs to fill form fields
     */
    function openFormModal(selector, data = {}) {
        const modal = document.querySelector(selector);
        if (!modal) return;

        const form = modal.querySelector('form');
        if (form && Object.keys(data).length === 0) {
            form.reset();
        }

        Object.entries(data).forEach(([key, value]) => {
            const field = modal.querySelector(`[name="${key}"]`);
            if (field) {
                if (field.type === 'file') return;
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
                updateIndexes(list);
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
            const afterEl = getDragAfterElement(list, e.clientY);
            if (afterEl) {
                list.insertBefore(dragItem, afterEl);
            } else {
                list.appendChild(dragItem);
            }
        });
    }

    function getDragAfterElement(container, y) {
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

    function updateIndexes(list) {
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
        apiAction,
        openFormModal,
        closeFormModal,
        initDragSort,
        initModals,
    };
})();
