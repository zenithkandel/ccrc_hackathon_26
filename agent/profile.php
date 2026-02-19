<?php
/**
 * Agent: Profile Management — Sawari
 * 
 * View/edit profile info, change password, see contribution stats.
 */

$pageTitle = 'Profile — Agent — Sawari';
$pageCss = ['admin.css', 'agent.css'];
$bodyClass = 'admin-page agent-page';
$pageJs = ['agent/agent.js'];
$currentPage = 'profile';

require_once __DIR__ . '/../../includes/header.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/validation.php';

requireAuth('agent');

$pdo = getDBConnection();
$agentId = getCurrentUserId();

// Fetch agent data
$stmt = $pdo->prepare('SELECT * FROM agents WHERE agent_id = :id');
$stmt->execute(['id' => $agentId]);
$agent = $stmt->fetch();

$summary = json_decode($agent['contributions_summary'] ?? '{}', true);
$locationCount = $summary['location'] ?? 0;
$routeCount = $summary['route'] ?? 0;
$vehicleCount = $summary['vehicle'] ?? 0;
$totalContribs = $locationCount + $routeCount + $vehicleCount;

$csrfToken = generateCSRFToken();
?>

<div class="admin-layout">
    <?php require_once __DIR__ . '/../../includes/agent-sidebar.php'; ?>

    <div class="admin-content">
        <div class="admin-page-header">
            <h1>My Profile</h1>
        </div>

        <!-- Profile Card -->
        <div class="profile-card">
            <div class="profile-header">
                <?php if ($agent['image_path']): ?>
                    <img src="<?= BASE_URL ?>/<?= sanitize($agent['image_path']) ?>" alt="Profile" class="profile-avatar">
                <?php else: ?>
                    <div class="profile-avatar-placeholder">
                        <?= strtoupper(substr($agent['name'], 0, 1)) ?>
                    </div>
                <?php endif; ?>
                <div class="profile-name"><?= sanitize($agent['name']) ?></div>
                <div class="profile-email"><?= sanitize($agent['email']) ?></div>
            </div>

            <div class="profile-body">
                <!-- Info Grid -->
                <div class="profile-info-grid">
                    <div class="profile-info-item">
                        <div class="label">Phone</div>
                        <div class="value"><?= sanitize($agent['phone_number'] ?: 'Not set') ?></div>
                    </div>
                    <div class="profile-info-item">
                        <div class="label">Member Since</div>
                        <div class="value"><?= formatDateTime($agent['joined_at'], 'M d, Y') ?></div>
                    </div>
                    <div class="profile-info-item">
                        <div class="label">Last Login</div>
                        <div class="value"><?= $agent['last_login'] ? timeAgo($agent['last_login']) : 'First session' ?>
                        </div>
                    </div>
                    <div class="profile-info-item">
                        <div class="label">Total Contributions</div>
                        <div class="value"><?= $totalContribs ?></div>
                    </div>
                </div>

                <!-- Contribution Stats -->
                <h3 style="font-size: 1rem; margin-bottom: var(--space-md);">Contributions Breakdown</h3>
                <div class="contrib-stats">
                    <div class="contrib-stat">
                        <span class="stat-count"><?= $locationCount ?></span>
                        <span class="stat-label"><i class="fa-duotone fa-solid fa-location-dot"></i> Locations</span>
                    </div>
                    <div class="contrib-stat">
                        <span class="stat-count"><?= $routeCount ?></span>
                        <span class="stat-label"><i class="fa-duotone fa-solid fa-route"></i> Routes</span>
                    </div>
                    <div class="contrib-stat">
                        <span class="stat-count"><?= $vehicleCount ?></span>
                        <span class="stat-label"><i class="fa-duotone fa-solid fa-bus"></i> Vehicles</span>
                    </div>
                </div>

                <!-- Edit Profile Form -->
                <h3 style="font-size: 1.125rem; margin-bottom: var(--space-lg);">Edit Profile</h3>
                <form id="profileForm" class="profile-form" enctype="multipart/form-data">
                    <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                    <div class="form-group">
                        <label class="form-label">Profile Photo</label>
                        <div class="image-upload-area" onclick="document.getElementById('profileImage').click()">
                            <?php if ($agent['image_path']): ?>
                                <img src="<?= BASE_URL ?>/<?= sanitize($agent['image_path']) ?>" alt="" id="profilePreview">
                            <?php else: ?>
                                <div class="upload-placeholder" id="profilePreview">
                                    <span>📷</span>
                                    Upload
                                </div>
                            <?php endif; ?>
                        </div>
                        <input type="file" id="profileImage" name="image" accept="image/jpeg,image/png,image/webp"
                            style="display:none;" onchange="previewImage(this)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Name</label>
                        <input type="text" name="name" class="form-input" value="<?= sanitize($agent['name']) ?>"
                            required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Email</label>
                        <input type="email" class="form-input" value="<?= sanitize($agent['email']) ?>" disabled
                            readonly>
                        <div class="form-hint">Email cannot be changed.</div>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" name="phone_number" class="form-input"
                            value="<?= sanitize($agent['phone_number'] ?? '') ?>" placeholder="98XXXXXXXX">
                    </div>

                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </form>

                <!-- Change Password -->
                <div class="password-section">
                    <h3>Change Password</h3>
                    <form id="passwordForm" class="profile-form">
                        <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">

                        <div class="form-group">
                            <label class="form-label">Current Password</label>
                            <input type="password" name="current_password" class="form-input" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">New Password</label>
                            <input type="password" name="new_password" class="form-input" required minlength="8">
                            <div class="form-hint">At least 8 characters with 1 letter and 1 number.</div>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Confirm New Password</label>
                            <input type="password" name="confirm_password" class="form-input" required>
                        </div>

                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    const BASE = '<?= BASE_URL ?>';

    function previewImage(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function (e) {
                const area = document.querySelector('.image-upload-area');
                area.innerHTML = `<img src="${e.target.result}" alt="" id="profilePreview">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }

    // ─── Profile Update ──────────────────────────────────────
    document.getElementById('profileForm').addEventListener('submit', async function (e) {
        e.preventDefault();
        const formData = new FormData(this);

        await AgentUtils.apiAction(`${BASE}/api/agents/update.php`, formData, {
            onSuccess: (result) => {
                SawariUtils.showToast('Profile updated successfully!', 'success');
                setTimeout(() => location.reload(), 1000);
            }
        });
    });

    // ─── Password Change ─────────────────────────────────────
    document.getElementById('passwordForm').addEventListener('submit', async function (e) {
        e.preventDefault();

        const newPw = this.querySelector('[name="new_password"]').value;
        const confirmPw = this.querySelector('[name="confirm_password"]').value;

        if (newPw !== confirmPw) {
            SawariUtils.showToast('Passwords do not match.', 'error');
            return;
        }

        const formData = new FormData(this);

        await AgentUtils.apiAction(`${BASE}/api/agents/change-password.php`, formData, {
            onSuccess: () => {
                this.reset();
                document.querySelector('#passwordForm [name="csrf_token"]').value = '<?= $csrfToken ?>';
            }
        });
    });
</script>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>