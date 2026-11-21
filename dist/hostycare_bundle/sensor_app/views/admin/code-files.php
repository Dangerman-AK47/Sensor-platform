<?php
declare(strict_types=1);

function formatCodeBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <p style="font-size: 13.5px; color: var(--slate-500);">Over-the-air firmware and code file distribution for ESP32 sensors</p>
    </div>
    <button onclick="openModal('upload-code-modal')" class="btn btn-primary btn-md">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
        Upload New Code File
    </button>
</div>

<!-- Active Code File Highlight Card -->
<div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--primary-600);">
    <div class="card-header">
        <div>
            <h3 class="card-title">Currently Active Code File</h3>
            <p class="card-desc">Served to sensors on code version-check and download requests</p>
        </div>
        <div>
            <?php if ($activeCode): ?>
                <span class="badge badge-success">Active Code Version</span>
            <?php else: ?>
                <span class="badge badge-warning">No Active Code File</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-content">
        <?php if ($activeCode): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <span class="stat-label">Active Version</span>
                    <div class="font-mono" style="font-size: 22px; font-weight: 700; color: var(--primary-600); margin-top: 4px;">
                        v<?= htmlspecialchars($activeCode['code_version']) ?>
                    </div>
                </div>
                <div>
                    <span class="stat-label">Code / Firmware File</span>
                    <div style="font-weight: 500; color: var(--slate-900); margin-top: 4px;">
                        <?= htmlspecialchars($activeCode['original_file_name']) ?> (<?= formatCodeBytes($activeCode['file_size_bytes']) ?>)
                    </div>
                </div>
                <div>
                    <span class="stat-label">Activated At</span>
                    <div style="font-size: 13px; color: var(--slate-700); margin-top: 4px;">
                        <?= !empty($activeCode['activated_at']) ? date('M j, Y H:i:s', strtotime($activeCode['activated_at'])) : '—' ?>
                    </div>
                </div>
                <div>
                    <span class="stat-label">SHA-256 Hash</span>
                    <div class="font-mono" style="font-size: 11.5px; color: var(--slate-500); margin-top: 4px; word-break: break-all;">
                        <?= htmlspecialchars($activeCode['checksum'] ?? '—') ?>
                    </div>
                </div>
            </div>
            <div style="margin-top: 16px; padding-top: 12px; border-top: 1px solid var(--slate-200); display: flex; gap: 8px;">
                <a href="/admin/code-files/download/<?= urlencode($activeCode['id']) ?>" class="btn btn-secondary btn-sm">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/></svg>
                    Download Active File
                </a>
            </div>
        <?php else: ?>
            <div style="padding: 16px; background-color: var(--warning-50); border: 1px solid var(--warning-500); border-radius: var(--radius-md); color: var(--warning-700); font-size: 13px;">
                <strong>Notice:</strong> There is no code file currently marked as ACTIVE. Sensors performing code check calls will receive <code>NO_CODE_AVAILABLE</code>. Upload a code file below and click Activate.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Code Files History Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Code Versions & Deployments</h3>
            <p class="card-desc">All uploaded code and firmware files</p>
        </div>
    </div>
    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Version</th>
                        <th>Status</th>
                        <th>Filename</th>
                        <th>File Size</th>
                        <th>SHA-256 Checksum</th>
                        <th>Uploaded By / Date</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($codeFiles)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No code files uploaded yet. Click "Upload New Code File" to deploy sensor code.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($codeFiles as $cf): ?>
                            <tr>
                                <td>
                                    <strong class="font-mono" style="font-size: 14px; color: var(--slate-900);">
                                        v<?= htmlspecialchars($cf['code_version']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $bClass = match($cf['status']) {
                                        'ACTIVE' => 'badge-success',
                                        'INACTIVE' => 'badge-warning',
                                        'ARCHIVED' => 'badge-neutral',
                                        default => 'badge-neutral'
                                    };
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= htmlspecialchars($cf['status']) ?></span>
                                </td>
                                <td style="font-weight: 500; color: var(--slate-800);"><?= htmlspecialchars($cf['original_file_name']) ?></td>
                                <td style="font-size: 12px;"><?= formatCodeBytes($cf['file_size_bytes']) ?></td>
                                <td class="font-mono" style="font-size: 11px; color: var(--slate-400);" title="<?= htmlspecialchars($cf['checksum'] ?? '') ?>">
                                    <?= substr($cf['checksum'] ?? '', 0, 16) ?>...
                                </td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?= htmlspecialchars($cf['uploader_username'] ?? 'admin') ?> &bull; <?= date('M j, Y', strtotime($cf['created_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: inline-flex; gap: 6px;">
                                        <a href="/admin/code-files/download/<?= urlencode($cf['id']) ?>" class="btn btn-ghost btn-sm" title="Download">
                                            Download
                                        </a>
                                        <?php if ($cf['status'] === 'INACTIVE' || $cf['status'] === 'ARCHIVED'): ?>
                                            <button onclick="activateCodeFile('<?= htmlspecialchars($cf['id']) ?>', '<?= htmlspecialchars($cf['code_version']) ?>')" class="btn btn-primary btn-sm">
                                                Activate
                                            </button>
                                            <button onclick="deleteCodeFile('<?= htmlspecialchars($cf['id']) ?>', '<?= htmlspecialchars($cf['code_version']) ?>')" class="btn btn-ghost btn-sm" style="color: var(--danger-600);" title="Delete">
                                                Delete
                                            </button>
                                        <?php elseif ($cf['status'] === 'ACTIVE'): ?>
                                            <span style="font-size: 12px; font-weight: 500; color: var(--success-700); padding: 4px 8px;">Active</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Code File Modal -->
<div id="upload-code-modal" class="modal-overlay">
    <div class="modal-box">
        <form id="upload-code-form" onsubmit="handleCodeUpload(event)">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Upload Sensor Code File</h3>
                    <p class="card-desc">Firmware, sketch, binary, or code script with version number</p>
                </div>
                <button type="button" data-close-modal class="btn btn-ghost btn-sm">&times;</button>
            </div>
            <div class="card-content">
                <div class="form-group">
                    <label class="form-label">Version String (e.g. 1.0.0, 2.1.0):</label>
                    <input type="text" name="version" required placeholder="1.0.0" class="input font-mono" pattern="^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$" title="Semver format e.g. 1.0.0">
                </div>
                <div class="form-group">
                    <label class="form-label">Code / Firmware File (.ino, .bin, .hex, .py, .c, .zip, .json):</label>
                    <input type="file" name="file" required class="input" style="padding-top: 5px;">
                </div>
                <div style="font-size: 12px; color: var(--slate-500); line-height: 1.4;">
                    * Uploaded code files are initially marked <strong>INACTIVE</strong>. You can inspect the record and click Activate when ready for sensor deployment.
                </div>
            </div>
            <div class="card-footer">
                <button type="button" data-close-modal class="btn btn-secondary btn-sm">Cancel</button>
                <button id="upload-code-submit-btn" type="submit" class="btn btn-primary btn-sm">Upload Code File</button>
            </div>
        </form>
    </div>
</div>

<script>
async function handleCodeUpload(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('upload-code-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Uploading...';

    const formData = new FormData(form);

    try {
        const res = await fetch('/api/admin/code-files/upload', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        if (json.success) {
            window.showToast('Code file uploaded successfully!', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Upload failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Upload Code File';
        }
    } catch (err) {
        window.showToast('Network error during upload', 'error');
        btn.disabled = false;
        btn.textContent = 'Upload Code File';
    }
}

async function activateCodeFile(id, version) {
    if (!confirm(`Activate code version v${version}? This will become the default code file served to sensors.`)) return;

    try {
        const res = await fetch('/api/admin/code-files/activate', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const json = await res.json();
        if (json.success) {
            window.showToast(`Code version v${version} activated!`, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Activation failed', 'error');
        }
    } catch (err) {
        window.showToast('Network error during activation', 'error');
    }
}

async function deleteCodeFile(id, version) {
    if (!confirm(`Delete code version v${version}? This cannot be undone.`)) return;

    try {
        const res = await fetch('/api/admin/code-files/delete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        const json = await res.json();
        if (json.success) {
            window.showToast(`Code file v${version} deleted.`, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Deletion failed', 'error');
        }
    } catch (err) {
        window.showToast('Network error during deletion', 'error');
    }
}
</script>
