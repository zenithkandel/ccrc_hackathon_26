<?php
/**
 * Registration Page — Sawari
 * 
 * New agent registration form.
 */

$pageTitle = 'Register — Sawari';
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
            <h1>Become an Agent</h1>
            <p>Help map Nepal's public transport network</p>
        </div>

        <form class="auth-form" id="registerForm" action="<?= BASE_URL ?>/api/auth/register.php" method="POST"
            enctype="multipart/form-data">
            <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

            <div class="form-group">
                <label for="name">Full Name <span style="color:var(--color-danger)">*</span></label>
                <input type="text" id="name" name="name" class="form-input" placeholder="Enter your full name" required
                    minlength="2" autocomplete="name">
            </div>

            <div class="form-group">
                <label for="email">Email Address <span style="color:var(--color-danger)">*</span></label>
                <input type="email" id="email" name="email" class="form-input" placeholder="Enter your email" required
                    autocomplete="email">
            </div>

            <div class="form-group">
                <label for="phone_number">Phone Number</label>
                <input type="tel" id="phone_number" name="phone_number" class="form-input" placeholder="98XXXXXXXX"
                    autocomplete="tel">
                <span class="form-hint">Nepali mobile number (optional)</span>
            </div>

            <div class="form-group">
                <label for="password">Password <span style="color:var(--color-danger)">*</span></label>
                <div class="form-input-group">
                    <input type="password" id="password" name="password" class="form-input"
                        placeholder="Create a strong password" required minlength="8" autocomplete="new-password">
                    <button type="button" class="toggle-password" data-target="password"
                        aria-label="Toggle password visibility">👁</button>
                </div>
                <div class="password-strength">
                    <div class="password-strength-bar" id="passwordStrengthBar"></div>
                </div>
                <span class="form-hint">Min 8 characters, at least 1 letter and 1 number</span>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password <span style="color:var(--color-danger)">*</span></label>
                <div class="form-input-group">
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input"
                        placeholder="Repeat your password" required autocomplete="new-password">
                    <button type="button" class="toggle-password" data-target="confirm_password"
                        aria-label="Toggle password visibility">👁</button>
                </div>
                <span class="form-error" id="passwordMatchError" style="display:none">Passwords do not match</span>
            </div>

            <div class="form-group">
                <label>Profile Photo (optional)</label>
                <label class="image-upload-area" for="image">
                    <div class="image-preview" id="imagePreview">📷</div>
                    <div class="image-upload-text">
                        <span class="upload-label">Click to upload</span>
                        <span class="upload-hint">JPG, PNG or WebP. Max 5MB.</span>
                    </div>
                </label>
                <input type="file" id="image" name="image" accept="image/jpeg,image/png,image/webp"
                    style="display:none">
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="terms" name="terms" required>
                    <label for="terms">I agree to contribute responsibly and provide accurate transport information for
                        the benefit of all users.</label>
                </div>
            </div>

            <button type="submit" class="auth-btn" id="registerBtn">
                Create Account
            </button>
        </form>

        <div class="auth-footer">
            <p>Already have an account? <a href="<?= BASE_URL ?>/pages/auth/login.php">Sign in</a></p>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', () => {
        // Toggle password visibility
        document.querySelectorAll('.toggle-password').forEach(btn => {
            btn.addEventListener('click', () => {
                const target = document.getElementById(btn.dataset.target);
                if (target) {
                    const isPassword = target.type === 'password';
                    target.type = isPassword ? 'text' : 'password';
                    btn.textContent = isPassword ? '🙈' : '👁';
                }
            });
        });

        // Password strength indicator
        const passwordInput = document.getElementById('password');
        const strengthBar = document.getElementById('passwordStrengthBar');

        passwordInput.addEventListener('input', () => {
            const val = passwordInput.value;
            let strength = 0;
            if (val.length >= 8) strength++;
            if (/[A-Z]/.test(val) && /[a-z]/.test(val)) strength++;
            if (/[0-9]/.test(val) && /[A-Za-z]/.test(val)) strength++;
            if (/[^A-Za-z0-9]/.test(val)) strength++;

            strengthBar.className = 'password-strength-bar';
            if (val.length === 0) {
                strengthBar.style.width = '0';
            } else if (strength <= 1) {
                strengthBar.classList.add('weak');
            } else if (strength <= 2) {
                strengthBar.classList.add('medium');
            } else {
                strengthBar.classList.add('strong');
            }
        });

        // Password match validation
        const confirmInput = document.getElementById('confirm_password');
        const matchError = document.getElementById('passwordMatchError');

        confirmInput.addEventListener('input', () => {
            if (confirmInput.value && confirmInput.value !== passwordInput.value) {
                matchError.style.display = 'block';
                confirmInput.classList.add('error');
            } else {
                matchError.style.display = 'none';
                confirmInput.classList.remove('error');
            }
        });

        // Image preview
        const imageInput = document.getElementById('image');
        const imagePreview = document.getElementById('imagePreview');

        imageInput.addEventListener('change', () => {
            const file = imageInput.files[0];
            if (file) {
                if (file.size > 5 * 1024 * 1024) {
                    SawariUtils.showToast('Image must be under 5MB', 'error');
                    imageInput.value = '';
                    return;
                }
                const reader = new FileReader();
                reader.onload = (e) => {
                    imagePreview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                reader.readAsDataURL(file);
            }
        });

        // Prevent double-submit
        const form = document.getElementById('registerForm');
        const btn = document.getElementById('registerBtn');

        form.addEventListener('submit', (e) => {
            // Client-side validation
            if (passwordInput.value !== confirmInput.value) {
                e.preventDefault();
                SawariUtils.showToast('Passwords do not match', 'error');
                return;
            }
            btn.disabled = true;
            btn.textContent = 'Creating account...';
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>