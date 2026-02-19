<?php
/**
 * SAWARI — Agent Login & Registration Page
 * 
 * Standalone page (no sidebar layout).
 * Two tabs: Login and Register. AJAX form submission.
 */

require_once __DIR__ . '/../../api/config.php';

// Already logged in? Go to dashboard
if (isAgentLoggedIn()) {
    header('Location: ' . BASE_URL . '/pages/agent/dashboard.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Login — Sawari</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/global.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/components.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/agent.css">
    <script src="https://unpkg.com/feather-icons"></script>
    <meta name="csrf-token" content="<?= e(csrfToken()) ?>">
    <meta name="base-url" content="<?= e(BASE_URL) ?>">
    <style>
        body {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            background: var(--color-neutral-50);
            padding: var(--space-4);
        }

        .auth-container {
            width: 100%;
            max-width: 420px;
        }

        .auth-brand {
            text-align: center;
            margin-bottom: var(--space-8);
        }

        .auth-brand h1 {
            font-size: var(--text-2xl);
            font-weight: var(--font-bold);
            color: var(--color-neutral-900);
            letter-spacing: var(--tracking-tight);
            margin: 0;
        }

        .auth-brand p {
            font-size: var(--text-sm);
            color: var(--color-neutral-500);
            margin: var(--space-1) 0 0;
        }

        .auth-card {
            background: var(--color-white);
            border: 1px solid var(--color-neutral-200);
            border-radius: var(--radius-xl);
            padding: var(--space-8);
        }

        .auth-tabs {
            display: flex;
            gap: var(--space-1);
            margin-bottom: var(--space-6);
            background: var(--color-neutral-100);
            border-radius: var(--radius-md);
            padding: 3px;
        }

        .auth-tab {
            flex: 1;
            padding: var(--space-2) var(--space-4);
            font-size: var(--text-sm);
            font-weight: var(--font-medium);
            text-align: center;
            border: none;
            background: transparent;
            border-radius: var(--radius-sm);
            cursor: pointer;
            color: var(--color-neutral-500);
            transition: all var(--transition-fast);
        }

        .auth-tab.active {
            background: var(--color-white);
            color: var(--color-neutral-900);
            box-shadow: var(--shadow-sm);
        }

        .auth-panel {
            display: none;
        }

        .auth-panel.active {
            display: block;
        }

        .form-group+.form-group {
            margin-top: var(--space-4);
        }

        .auth-submit {
            width: 100%;
            margin-top: var(--space-6);
        }

        .auth-footer {
            text-align: center;
            margin-top: var(--space-6);
            font-size: var(--text-sm);
            color: var(--color-neutral-500);
        }

        .auth-footer a {
            color: var(--color-primary-600);
            font-weight: var(--font-medium);
            text-decoration: none;
        }

        .auth-footer a:hover {
            text-decoration: underline;
        }

        .password-toggle {
            position: absolute;
            right: var(--space-3);
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            cursor: pointer;
            color: var(--color-neutral-400);
            padding: var(--space-1);
            display: flex;
        }

        .password-toggle:hover {
            color: var(--color-neutral-600);
        }

        .form-input-wrap {
            position: relative;
        }
    </style>
</head>

<body>
    <!-- Toast Container -->
    <div id="toast-container" class="toast-container"></div>

    <div class="auth-container">
        <div class="auth-brand">
            <h1>Sawari</h1>
            <p>Agent Portal</p>
        </div>

        <div class="auth-card">
            <!-- Tabs -->
            <div class="auth-tabs">
                <button class="auth-tab active" data-tab="login">Sign In</button>
                <button class="auth-tab" data-tab="register">Register</button>
            </div>

            <!-- Login Form -->
            <div class="auth-panel active" id="panel-login">
                <form id="login-form" autocomplete="on">
                    <div class="form-group">
                        <label class="form-label" for="login-email">Email</label>
                        <input type="email" id="login-email" class="form-input" placeholder="agent@example.com" required
                            autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="login-password">Password</label>
                        <div class="form-input-wrap">
                            <input type="password" id="login-password" class="form-input"
                                placeholder="Enter your password" required autocomplete="current-password">
                            <button type="button" class="password-toggle" data-target="login-password"
                                aria-label="Toggle password visibility">
                                <i data-feather="eye" style="width:18px;height:18px;"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary auth-submit" id="login-btn">
                        <i data-feather="log-in" style="width:16px;height:16px;"></i>
                        Sign In
                    </button>
                </form>
            </div>

            <!-- Register Form -->
            <div class="auth-panel" id="panel-register">
                <form id="register-form" autocomplete="on">
                    <div class="form-group">
                        <label class="form-label" for="reg-name">Full Name</label>
                        <input type="text" id="reg-name" class="form-input" placeholder="Your full name" required
                            autocomplete="name">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-email">Email</label>
                        <input type="email" id="reg-email" class="form-input" placeholder="agent@example.com" required
                            autocomplete="email">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-phone">Phone (optional)</label>
                        <input type="tel" id="reg-phone" class="form-input" placeholder="+977 98XXXXXXXX"
                            autocomplete="tel">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-password">Password</label>
                        <div class="form-input-wrap">
                            <input type="password" id="reg-password" class="form-input"
                                placeholder="Minimum 6 characters" required minlength="6" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-target="reg-password"
                                aria-label="Toggle password visibility">
                                <i data-feather="eye" style="width:18px;height:18px;"></i>
                            </button>
                        </div>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="reg-password-confirm">Confirm Password</label>
                        <div class="form-input-wrap">
                            <input type="password" id="reg-password-confirm" class="form-input"
                                placeholder="Re-enter your password" required minlength="6" autocomplete="new-password">
                            <button type="button" class="password-toggle" data-target="reg-password-confirm"
                                aria-label="Toggle password visibility">
                                <i data-feather="eye" style="width:18px;height:18px;"></i>
                            </button>
                        </div>
                    </div>
                    <button type="submit" class="btn btn-primary auth-submit" id="register-btn">
                        <i data-feather="user-plus" style="width:16px;height:16px;"></i>
                        Create Account
                    </button>
                </form>
            </div>
        </div>

        <div class="auth-footer">
            Are you an admin? <a href="<?= BASE_URL ?>/pages/admin/login.php">Admin login</a>
        </div>
    </div>

    <script src="<?= BASE_URL ?>/assets/js/agent.js"></script>
    <script>
        feather.replace({ 'stroke-width': 1.75 });

        (function () {
            'use strict';

            // Tab switching
            document.querySelectorAll('.auth-tab').forEach(function (tab) {
                tab.addEventListener('click', function () {
                    document.querySelectorAll('.auth-tab').forEach(function (t) { t.classList.remove('active'); });
                    document.querySelectorAll('.auth-panel').forEach(function (p) { p.classList.remove('active'); });
                    tab.classList.add('active');
                    document.getElementById('panel-' + tab.dataset.tab).classList.add('active');
                });
            });

            // Password visibility toggle
            document.querySelectorAll('.password-toggle').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    var input = document.getElementById(btn.dataset.target);
                    var isPassword = input.type === 'password';
                    input.type = isPassword ? 'text' : 'password';
                    btn.innerHTML = '<i data-feather="' + (isPassword ? 'eye-off' : 'eye') + '" style="width:18px;height:18px;"></i>';
                    feather.replace({ 'stroke-width': 1.75 });
                });
            });

            // Login
            document.getElementById('login-form').addEventListener('submit', function (e) {
                e.preventDefault();
                var btn = document.getElementById('login-btn');
                Sawari.setLoading(btn, true);

                Sawari.api('agents', 'login', {
                    email: document.getElementById('login-email').value.trim(),
                    password: document.getElementById('login-password').value
                }).then(function (res) {
                    if (res.success) {
                        Sawari.toast('Welcome back, ' + res.agent.name + '!', 'success');
                        setTimeout(function () {
                            window.location.href = Sawari.baseUrl + '/pages/agent/dashboard.php';
                        }, 600);
                    } else {
                        Sawari.toast(res.message || 'Login failed.', 'danger');
                        Sawari.setLoading(btn, false);
                    }
                }).catch(function () {
                    Sawari.setLoading(btn, false);
                });
            });

            // Register
            document.getElementById('register-form').addEventListener('submit', function (e) {
                e.preventDefault();

                var pass = document.getElementById('reg-password').value;
                var confirm = document.getElementById('reg-password-confirm').value;

                if (pass !== confirm) {
                    Sawari.toast('Passwords do not match.', 'warning');
                    return;
                }

                var btn = document.getElementById('register-btn');
                Sawari.setLoading(btn, true);

                Sawari.api('agents', 'register', {
                    name: document.getElementById('reg-name').value.trim(),
                    email: document.getElementById('reg-email').value.trim(),
                    phone: document.getElementById('reg-phone').value.trim(),
                    password: pass
                }).then(function (res) {
                    if (res.success) {
                        Sawari.toast('Account created! You can now sign in.', 'success');
                        // Switch to login tab
                        document.querySelector('.auth-tab[data-tab="login"]').click();
                        document.getElementById('login-email').value = document.getElementById('reg-email').value;
                        document.getElementById('register-form').reset();
                    } else {
                        Sawari.toast(res.message || 'Registration failed.', 'danger');
                    }
                    Sawari.setLoading(btn, false);
                }).catch(function () {
                    Sawari.setLoading(btn, false);
                });
            });
        })();
    </script>
</body>

</html>