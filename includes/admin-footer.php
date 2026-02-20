</main><!-- /.main-content -->

<!-- ===== Mobile Bottom Navigation ===== -->
<nav class="bottom-nav" id="bottom-nav">
    <a href="<?= BASE_URL ?>/pages/admin/dashboard.php"
        class="bottom-nav-item <?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">
        <i data-feather="grid"></i>
        <span>Home</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/manage-locations.php"
        class="bottom-nav-item <?= ($currentPage ?? '') === 'locations' ? 'active' : '' ?>">
        <i data-feather="map-pin"></i>
        <span>Locations</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/contributions.php"
        class="bottom-nav-item <?= ($currentPage ?? '') === 'contributions' ? 'active' : '' ?>">
        <i data-feather="inbox"></i>
        <span>Review</span>
    </a>
    <a href="<?= BASE_URL ?>/pages/admin/manage-alerts.php"
        class="bottom-nav-item <?= ($currentPage ?? '') === 'alerts' ? 'active' : '' ?>">
        <i data-feather="alert-triangle"></i>
        <span>Alerts</span>
    </a>
    <button class="bottom-nav-item" id="bottom-nav-more">
        <i data-feather="menu"></i>
        <span>More</span>
    </button>
</nav>

<!-- ===== Modal Container (reusable) ===== -->
<div class="modal-overlay" id="modal-overlay">
    <div class="modal" id="modal">
        <div class="modal-header">
            <h3 id="modal-title">Modal</h3>
            <button class="modal-close" id="modal-close" aria-label="Close">
                <i data-feather="x"></i>
            </button>
        </div>
        <div class="modal-body" id="modal-body"></div>
        <div class="modal-footer" id="modal-footer"></div>
    </div>
</div>

<!-- Scripts -->
<script>
    // Initialize Feather Icons
    feather.replace({ 'stroke-width': 1.75 });
</script>
</body>

</html>