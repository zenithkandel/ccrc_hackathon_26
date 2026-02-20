<?php
/**
 * SAWARI — Admin Login Page
 * 
 * Standalone page (no sidebar/header layout).
 * If already logged in, redirect to dashboard.
 */

require_once __DIR__ . '/../../api/config.php';

// Already logged in? Go to dashboard.
if (isAdminLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/admin/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login — Sawari</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--color-neutral-50);
        }

        .login-wrapper {
            width: 100%;
            max-width: 400px;
            padding: var(--space-4);
        }

        .login-brand {
            text-align: center;
            margin-bottom: var(--space-8);
        }

        .login-brand-name {
            font-size: var(--text-3xl);
            font-weight: var(--font-bold);
            color: var(--color-neutral-900);
            letter-spacing: var(--tracking-tight);
        }

        .login-brand-sub {
            font-size: var(--text-sm);
            color: var(--color-neutral-500);
            margin-top: var(--space-1);
        }

        .login-card {
            background: var(--color-white);
            border: 1px solid var(--color-neutral-200);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
        }

        .login-title {
            font-size: var(--text-xl);
            font-weight: var(--font-semibold);
            color: var(--color-neutral-900);
            margin-bottom: var(--space-1);
        }

        .login-subtitle {
            font-size: var(--text-sm);
            color: var(--color-neutral-500);
            margin-bottom: var(--space-6);
        }

        .login-error {
            display: none;
            margin-bottom: var(--space-4);
        }

        .login-footer {
            text-align: center;
            margin-top: var(--space-6);
            font-size: var(--text-xs);
            color: var(--color-neutral-400);
        }
    </style>
</head>

<body>

    <div class="login-wrapper">
        <!-- Brand -->
        <div class="login-brand">
            <div class="login-brand-name">Sawari</div>
            <p class="login-brand-sub">Public Transport Navigation</p>
        </div>

        <!-- Login Card -->
        <div class="login-card">
            <h1 class="login-title">Welcome back</h1>
            <p class="login-subtitle">Sign in to admin dashboard</p>

            <!-- Error alert (hidden by default) -->
            <div class="alert alert-danger login-error" id="login-error">
                <span class="alert-icon"><i data-feather="alert-circle"></i></span>
                <span class="alert-content" id="login-error-text"></span>
            </div>

            <form id="login-form" novalidate>
                <div class="form-group">
                    <label class="form-label" for="email">Email</label>
                    <div class="input-group">
                        <span class="input-icon"><i data-feather="mail"></i></span>
                        <input type="email" id="email" name="email" class="form-input" placeholder="admin@sawari.com"
                            autocomplete="email" required>
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="input-group">
                        <span class="input-icon"><i data-feather="lock"></i></span>
                        <input type="password" id="password" name="password" class="form-input"
                            placeholder="Enter your password" autocomplete="current-password" required>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-lg" id="login-btn">
                    Sign in
                </button>
            </form>
        </div>

        <div class="login-footer">
            Sawari &mdash; Nepal Public Transport Navigation
        </div>
    </div>

    <script>
        feather.replace({ 'stroke-width': 1.75 });

        const BASE_URL = '<?= BASE_URL ?>';
        const form = document.getElementById('login-form');
        const errorBox = document.getElementById('login-error');
        const errorText = document.getElementById('login-error-text');
        const loginBtn = document.getElementById('login-btn');

        form.addEventListener('submit', async function (e) {
            e.preventDefault();

            // Hide previous error
            errorBox.style.display = 'none';

            const email = document.getElementById('email').value.trim();
            const password = document.getElementById('password').value;

            if (!email || !password) {
                showError('Please enter both email and password.');
                return;
            }

            // Show loading state
            loginBtn.classList.add('is-loading');
            loginBtn.disabled = true;

            try {
                const formData = new FormData();
                formData.append('email', email);
                formData.append('password', password);

                const response = await fetch(BASE_URL + '/api/admins.php?action=login', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.success) {
                    // Redirect to dashboard
                    window.location.href = data.redirect || BASE_URL + '/pages/admin/dashboard.php';
                } else {
                    showError(data.message || 'Login failed.');
                }
            } catch (err) {
                showError('Network error. Please check your connection.');
            } finally {
                loginBtn.classList.remove('is-loading');
                loginBtn.disabled = false;
            }
        });

        function showError(message) {
            errorText.textContent = message;
            errorBox.style.display = 'flex';
        }
    </script>

</body>

</html>