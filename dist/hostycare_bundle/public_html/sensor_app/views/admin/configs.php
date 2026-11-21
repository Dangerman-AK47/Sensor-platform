<?php
declare(strict_types=1);

function formatCfgBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
    <div>
        <p style="font-size: 13.5px; color: var(--slate-500);">Over-the-air global configuration distribution for ESP32 fleet</p>
    </div>
    <button onclick="openModal('upload-config-modal')" class="btn btn-primary btn-md">
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
        Upload New Configuration
    </button>
</div>

<!-- Active Configuration Highlight Card -->
<div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--accent-500);">
    <div class="card-header">
        <div>
            <h3 class="card-title">Currently Active Configuration</h3>
            <p class="card-desc">Distributed to sensors on version-check and download requests</p>
        </div>
        <div>
            <?php if ($activeConfig): ?>
                <span class="badge badge-success">Active Deployment</span>
            <?php else: ?>
                <span class="badge badge-warning">No Active Configuration</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-content">
        <?php if ($activeConfig): ?>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
                <div>
                    <span class="stat-label">Active Semver Version</span>
                    <div class="font-mono" style="font-size: 22px; font-weight: 700; color: var(--accent-500); margin-top: 4px;">
                        v<?= htmlspecialchars($activeConfig['config_version']) ?>
                    </div>
                </div>
                <div>
                    <span class="stat-label">Configuration File</span>
                    <div style="font-weight: 500; color: var(--slate-900); margin-top: 4px;">
                        <?= htmlspecialchars($activeConfig['original_file_name']) ?> (<?= formatCfgBytes($activeConfig['file_size_bytes']) ?>)
                    </div>
                </div>
                <div>
                    <span class="stat-label">Activated At</span>
                    <div style="font-size: 13px; color: var(--slate-700); margin-top: 4px;">
                        <?= !empty($activeConfig['activated_at']) ? date('M j, Y H:i:s', strtotime($activeConfig['activated_at'])) : '—' ?>
                    </div>
                </div>
                <div>
                    <span class="stat-label">SHA-256 Integrity Hash</span>
                    <div class="font-mono" style="font-size: 11.5px; color: var(--slate-500); margin-top: 4px; word-break: break-all;">
                        <?= htmlspecialchars($activeConfig['checksum'] ?? '—') ?>
                    </div>
                </div>
            </div>
        <?php else: ?>
            <div style="padding: 16px; background-color: var(--warning-50); border: 1px solid var(--warning-500); border-radius: var(--radius-md); color: var(--warning-700); font-size: 13px;">
                <strong>Notice:</strong> There is no configuration currently marked as ACTIVE. Sensors performing version-check calls will receive <code>NO_CONFIGURATION_AVAILABLE</code>. Upload a new configuration below and click Activate.
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Configurations History Table -->
<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Version History & Deployments</h3>
            <p class="card-desc">All uploaded configurations (Semver forward-only progression)</p>
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
                    <?php if (empty($configs)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No configurations uploaded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($configs as $cfg): ?>
                            <tr>
                                <td>
                                    <strong class="font-mono" style="font-size: 14px; color: var(--slate-900);">
                                        v<?= htmlspecialchars($cfg['config_version']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $bClass = match($cfg['status']) {
                                        'ACTIVE' => 'badge-success',
                                        'INACTIVE' => 'badge-warning',
                                        'ARCHIVED' => 'badge-neutral',
                                        default => 'badge-neutral'
                                    };
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= htmlspecialchars($cfg['status']) ?></span>
                                </td>
                                <td style="font-weight: 500; color: var(--slate-800);"><?= htmlspecialchars($cfg['original_file_name']) ?></td>
                                <td style="font-size: 12px;"><?= formatCfgBytes($cfg['file_size_bytes']) ?></td>
                                <td class="font-mono" style="font-size: 11px; color: var(--slate-400);" title="<?= htmlspecialchars($cfg['checksum'] ?? '') ?>">
                                    <?= substr($cfg['checksum'] ?? '', 0, 16) ?>...
                                </td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?= htmlspecialchars($cfg['uploader_username'] ?? 'admin') ?> &bull; <?= date('M j, Y', strtotime($cfg['created_at'])) ?>
                                </td>
                                <td style="text-align: right;">
                                    <?php if ($cfg['status'] === 'INACTIVE'): ?>
                                        <button onclick="activateConfig('<?= htmlspecialchars($cfg['id']) ?>', '<?= htmlspecialchars($cfg['config_version']) ?>')" class="btn btn-primary btn-sm">
                                            Activate
                                        </button>
                                    <?php elseif ($cfg['status'] === 'ACTIVE'): ?>
                                        <span style="font-size: 12px; font-weight: 500; color: var(--success-700);">Active</span>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--slate-400);">Archived</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Upload Configuration Modal -->
<div id="upload-config-modal" class="modal-overlay">
    <div class="modal-box">
        <form id="upload-config-form" onsubmit="handleConfigUpload(event)">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Upload Global Configuration</h3>
                    <p class="card-desc">Must follow strict semver (MAJOR.MINOR.PATCH)</p>
                </div>
                <button type="button" data-close-modal class="btn btn-ghost btn-sm">&times;</button>
            </div>
            <div class="card-content">
                <div class="form-group">
                    <label class="form-label">Version String (e.g. 1.2.0):</label>
                    <input type="text" name="version" required placeholder="1.2.0" class="input font-mono" pattern="^(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)$" title="Strict semver format like 1.2.0">
                </div>
                <div class="form-group">
                    <label class="form-label">Configuration File (.json, .bin, .conf):</label>
                    <input type="file" name="file" required class="input" style="padding-top: 5px;">
                </div>
                <div style="font-size: 12px; color: var(--slate-500); line-height: 1.4;">
                    * Uploaded configs are initially marked <strong>INACTIVE</strong>. You can inspect the version record and click Activate when ready for fleet rollout.
                </div>
            </div>
            <div class="card-footer">
                <button type="button" data-close-modal class="btn btn-secondary btn-sm">Cancel</button>
                <button id="upload-config-submit-btn" type="submit" class="btn btn-primary btn-sm">Upload Configuration</button>
            </div>
        </form>
    </div>
</div>

<script>
async function handleConfigUpload(e) {
    e.preventDefault();
    const form = e.target;
    const btn = document.getElementById('upload-config-submit-btn');
    btn.disabled = true;
    btn.textContent = 'Uploading...';

    const formData = new FormData(form);

    try {
        const res = await fetch('/api/admin/configuration', {
            method: 'POST',
            body: formData
        });
        const json = await res.json();
        if (json.success) {
            window.showToast('Configuration uploaded successfully!', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Upload failed', 'error');
            btn.disabled = false;
            btn.textContent = 'Upload Configuration';
        }
    } catch (err) {
        window.showToast('Network error during upload', 'error');
        btn.disabled = false;
        btn.textContent = 'Upload Configuration';
    }
}
</script>
