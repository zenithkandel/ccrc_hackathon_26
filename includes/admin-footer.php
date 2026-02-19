</main><!-- /.main-content -->

<!-- ===== Modal Container (reusable) ===== -->
<div class="modal-overlay is-hidden" id="modal-overlay">
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
<script src="<?= BASE_URL ?>/assets/js/admin.js"></script>
<script>
    // Initialize Feather Icons
    feather.replace({ 'stroke-width': 1.75 });
</script>
</body>
</html>