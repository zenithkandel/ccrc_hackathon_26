<?php
/**
 * Login Page — Sawari
 * 
 * Agent and Admin login form with role tabs.
 */

$pageTitle = 'Login — Sawari';
$pageCss = ['auth.css'];
$bodyClass = 'auth-page';

require_once __DIR__ . '/../../includes/header.php';

// If already logged in, redirect to dashboard
if (isLoggedIn()) {
    redirect(isAdmin() ? 'pages/admin/dashboard.php' : 'pages/agent/dashboard.php');
}

$csrfToken = generateCSRFToken();
?>

<div class="auth-container">
    <div class="auth-card">
        <div class="auth-header">
            <span class="brand-icon"><i class="fa-duotone fa-solid fa-bus"></i></span>
            <h1>Welcome Back</h1>
            <p>Sign in to your Sawari account</p>
        </div>

        <!-- Role Tabs -->
        <div class="role-tabs">
            <button type="button" class="role-tab active" data-role="agent">Agent</button>
            <button type="button" class="role-tab" data-role="admin">Admin</button>
        </div>

        <form class="auth-form" id="loginForm" action="<?= BASE_URL ?>/api/auth/login.php" method="POST">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
            <input type="hidden" name="role" id="roleInput" value="agent">

            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required
                    autocomplete="email">
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <div class="form-input-group">
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Enter your password" required autocomplete="current-password">
                    <button type="button" class="toggle-password" aria-label="Toggle password visibility">👁</button>
                </div>
            </div>

            <button type="submit" class="auth-btn" id="loginBtn">
                Sign In
            </button>
        </form>

        <div class="auth-footer">
            <p>Don't have an account? <a href="<?= BASE_URL ?>/pages/auth/register.php">Register as Agent</a></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Role tab switching
        const tabs = document.querySelectorAll('.role-tab');
        const roleInput = document.getElementById('roleInput');

        tabs.forEach(tab => {
            tab.addEventListener('click', () => {
                tabs.forEach(t => t.classList.remove('active'));
                tab.classList.add('active');
                roleInput.value = tab.dataset.role;
            });
        });

        // Toggle password visibility
        const toggleBtn = document.querySelector('.toggle-password');
        const passwordInput = document.getElementById('password');

        if (toggleBtn && passwordInput) {
            toggleBtn.addEventListener('click', () => {
                const isPassword = passwordInput.type === 'password';
                passwordInput.type = isPassword ? 'text' : 'password';
                toggleBtn.textContent = isPassword ? '🙈' : '👁';
            });
        }

        // Prevent double-submit
        const form = document.getElementById('loginForm');
        const btn = document.getElementById('loginBtn');

        form.addEventListener('submit', () => {
            btn.disabled = true;
            btn.textContent = 'Signing in...';
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>