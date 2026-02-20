<?php
/**
 * SAWARI — Add Vehicle (Agent)
 * 
 * Form to register a new vehicle with image upload and route assignment.
 */

require_once __DIR__ . '/../../includes/auth-agent.php';

$pageTitle = 'Add Vehicle';
$currentPage = 'add-vehicle';

require_once __DIR__ . '/../../includes/agent-header.php';
?>

<div style="max-width:640px;">
    <div class="card">
        <div class="card-header">
            <h3 class="card-title">Vehicle Information</h3>
        </div>
        <div class="card-body">
            <form id="vehicle-form" enctype="multipart/form-data">
                <!-- Vehicle Name -->
                <div class="form-group">
                    <label class="form-label" for="veh-name">Vehicle / Service Name <span
                            style="color:var(--color-danger-500);">*</span></label>
                    <input type="text" id="veh-name" class="form-input" placeholder="e.g. Sajha Yatayat Bus" required>
                </div>

                <!-- Description -->
                <div class="form-group" style="margin-top:var(--space-4);">
                    <label class="form-label" for="veh-desc">Description</label>
                    <textarea id="veh-desc" class="form-input" rows="3"
                        placeholder="Vehicle details, capacity, color, route info, etc."></textarea>
                </div>

                <!-- Image Upload -->
                <div class="form-group" style="margin-top:var(--space-4);">
                    <label class="form-label">Vehicle Photo</label>
                    <div id="image-dropzone"
                        style="border:2px dashed var(--color-neutral-200);border-radius:var(--radius-lg);padding:var(--space-6);text-align:center;cursor:pointer;transition:border-color var(--transition-fast);">
                        <i data-feather="camera"
                            style="width:32px;height:32px;color:var(--color-neutral-300);margin-bottom:var(--space-2);"></i>
                        <p style="font-size:var(--text-sm);color:var(--color-neutral-500);margin:0;">Click to upload or
                            drag and drop</p>
                        <p style="font-size:var(--text-xs);color:var(--color-neutral-400);margin:var(--space-1) 0 0;">
                            JPG, PNG, or WebP — max 2 MB</p>
                        <img id="image-preview"
                            style="display:none;max-width:200px;max-height:150px;margin:var(--space-3) auto 0;border-radius:var(--radius-md);"
                            alt="Preview">
                    </div>
                    <input type="file" id="veh-image" accept="image/jpeg,image/png,image/webp" style="display:none;">
                </div>

                <!-- Electric Toggle -->
                <div class="form-group" style="margin-top:var(--space-4);">
                    <label style="display:flex;align-items:center;gap:var(--space-3);cursor:pointer;">
                        <input type="checkbox" id="veh-electric"
                            style="width:18px;height:18px;accent-color:var(--color-primary-600);">
                        <span>
                            <span class="form-label" style="margin:0;display:block;">Electric Vehicle</span>
                            <span style="font-size:var(--text-xs);color:var(--color-neutral-400);">Check if this is an
                                electric/EV vehicle</span>
                        </span>
                    </label>
                </div>

                <!-- Operating Hours -->
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:var(--space-3);margin-top:var(--space-4);">
                    <div class="form-group">
                        <label class="form-label" for="veh-starts">Service Starts</label>
                        <input type="time" id="veh-starts" class="form-input" value="06:00">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="veh-stops">Service Ends</label>
                        <input type="time" id="veh-stops" class="form-input" value="20:00">
                    </div>
                </div>

                <!-- Notes -->
                <div class="form-group" style="margin-top:var(--space-4);">
                    <label class="form-label" for="veh-notes">Notes for Reviewer</label>
                    <textarea id="veh-notes" class="form-input" rows="2"
                        placeholder="Any additional context"></textarea>
                </div>

                <button type="submit" class="btn btn-primary" id="submit-btn"
                    style="width:100%;margin-top:var(--space-6);">
                    <i data-feather="send" style="width:16px;height:16px;"></i>
                    Submit for Review
                </button>
            </form>
        </div>
    </div>
</div>

<script>
    (function () {
        'use strict';

        // Image upload zone
        var dropzone = document.getElementById('image-dropzone');
        var fileInput = document.getElementById('veh-image');
        var preview = document.getElementById('image-preview');

        dropzone.addEventListener('click', function () { fileInput.click(); });

        dropzone.addEventListener('dragover', function (e) {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--color-primary-400)';
        });
        dropzone.addEventListener('dragleave', function () {
            dropzone.style.borderColor = 'var(--color-neutral-200)';
        });
        dropzone.addEventListener('drop', function (e) {
            e.preventDefault();
            dropzone.style.borderColor = 'var(--color-neutral-200)';
            if (e.dataTransfer.files.length) {
                fileInput.files = e.dataTransfer.files;
                showPreview(e.dataTransfer.files[0]);
            }
        });

        fileInput.addEventListener('change', function () {
            if (fileInput.files.length) showPreview(fileInput.files[0]);
        });

        function showPreview(file) {
            if (file.size > 2 * 1024 * 1024) {
                Sawari.toast('Image must be under 2 MB.', 'warning');
                fileInput.value = '';
                return;
            }
            var reader = new FileReader();
            reader.onload = function (e) {
                preview.src = e.target.result;
                preview.style.display = 'block';
            };
            reader.readAsDataURL(file);
        }

        // Form submit
        document.getElementById('vehicle-form').addEventListener('submit', function (e) {
            e.preventDefault();

            var name = document.getElementById('veh-name').value.trim();
            if (!name) { Sawari.toast('Vehicle name is required.', 'warning'); return; }

            var btn = document.getElementById('submit-btn');
            Sawari.setLoading(btn, true);

            var fd = new FormData();
            fd.append('name', name);
            fd.append('description', document.getElementById('veh-desc').value.trim());
            fd.append('electric', document.getElementById('veh-electric').checked ? '1' : '0');
            fd.append('starts_at', document.getElementById('veh-starts').value);
            fd.append('stops_at', document.getElementById('veh-stops').value);
            fd.append('notes', document.getElementById('veh-notes').value.trim());

            if (fileInput.files.length) {
                fd.append('image', fileInput.files[0]);
            }

            Sawari.api('vehicles', 'submit', fd).then(function (res) {
                Sawari.setLoading(btn, false);
                if (res.success) {
                    Sawari.toast(res.message, 'success');
                    document.getElementById('vehicle-form').reset();
                    preview.style.display = 'none';
                    preview.src = '';
                } else {
                    Sawari.toast(res.message || 'Submission failed.', 'danger');
                }
            }).catch(function () {
                Sawari.setLoading(btn, false);
            });
        });
    })();
</script>

<?php require_once __DIR__ . '/../../includes/agent-footer.php'; ?>