</main>
<!-- Main Content Ends -->

<!-- Footer -->
<footer class="site-footer">
    <div class="footer-container">
        <div class="footer-brand">
            <span class="brand-icon"><i class="fa-duotone fa-solid fa-bus"></i></span>
            <span class="brand-text">Sawari</span>
            <p class="footer-tagline">Navigating Nepal's public transport, made simple.</p>
        </div>
        <div class="footer-links">
            <div class="footer-col">
                <h4>Navigate</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/">Home</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/map.php">Find Route</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h4>Contribute</h4>
                <ul>
                    <li><a href="<?= BASE_URL ?>/pages/auth/register.php">Become an Agent</a></li>
                    <li><a href="<?= BASE_URL ?>/pages/auth/login.php">Agent Login</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>&copy; <?= date('Y') ?> Sawari. Built for the people of Nepal.</p>
        </div>
    </div>
</footer>

<!-- Common Scripts -->
<script src="<?= BASE_URL ?>/assets/js/utils.js"></script>

<!-- Leaflet JS (loaded on map pages) -->
<?php if (isset($useLeaflet) && $useLeaflet): ?>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
        integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
<?php endif; ?>

<!-- Page-specific Scripts -->
<?php if (isset($pageJs) && is_array($pageJs)): ?>
    <?php foreach ($pageJs as $js): ?>
        <script src="<?= BASE_URL ?>/assets/js/<?= $js ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>
</body>

</html>