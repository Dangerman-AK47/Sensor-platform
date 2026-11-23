<?php
declare(strict_types=1);

function formatDetailBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div style="margin-bottom: 16px;">
    <a href="/admin/sensors" class="btn btn-ghost btn-sm" style="display: inline-flex; align-items: center; gap: 6px;">
        &larr; Back to Sensors List
    </a>
</div>

<div class="grid-2col">
    <!-- Device Summary Card -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title font-mono"><?= htmlspecialchars($device['device_id']) ?></h3>
                <p class="card-desc">Instance ID: <span class="font-mono" style="font-size: 11px;"><?= htmlspecialchars($device['id']) ?></span> &bull; Registered on <?= date('F j, Y, H:i', strtotime($device['registered_at'])) ?></p>
            </div>
            <div>
                <?php if ($device['status'] === 'ACTIVE'): ?>
                    <span class="badge badge-success">Active</span>
                <?php else: ?>
                    <span class="badge badge-neutral">Deactive</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="card-content">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div>
                    <span class="stat-label">Hardware Model</span>
                    <div style="font-weight: 500; color: var(--slate-900);"><?= htmlspecialchars($device['hardware_model'] ?? 'Unknown ESP32') ?></div>
                </div>
                <div>
                    <span class="stat-label">Last Heartbeat</span>
                    <div style="font-weight: 500; color: var(--slate-900);">
                        <?= !empty($device['last_seen_at']) ? date('M j, Y H:i:s', strtotime($device['last_seen_at'])) : 'Never seen' ?>
                    </div>
                </div>
                <div>
                    <span class="stat-label">Sensor Version</span>
                    <div class="font-mono" style="font-weight: 500; color: var(--slate-900);"><?= htmlspecialchars($device['sensor_version'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <span class="stat-label">Firmware Version</span>
                    <div class="font-mono" style="font-weight: 500; color: var(--slate-900);"><?= htmlspecialchars($device['firmware_version'] ?? 'N/A') ?></div>
                </div>
                <div>
                    <span class="stat-label">Total Ingestion Count</span>
                    <div style="font-weight: 600; font-size: 16px; color: var(--slate-900);"><?= $device['upload_count'] ?> files</div>
                </div>
                <div>
                    <span class="stat-label">Total Storage Footprint</span>
                    <div style="font-weight: 600; font-size: 16px; color: var(--slate-900);"><?= formatDetailBytes($device['total_storage_bytes']) ?></div>
                </div>
            </div>

            <!-- Management Controls -->
            <div style="display: flex; gap: 10px; border-top: 1px solid var(--slate-100); padding-top: 16px;">
                <?php if ($device['status'] === 'ACTIVE'): ?>
                    <button onclick="toggleSensorStatus('<?= htmlspecialchars($device['id']) ?>', 'DEACTIVE')" class="btn btn-secondary btn-sm" style="color: var(--danger-500);">
                        Deactivate Sensor
                    </button>
                <?php else: ?>
                    <button onclick="toggleSensorStatus('<?= htmlspecialchars($device['id']) ?>', 'ACTIVE')" class="btn btn-primary btn-sm">
                        Activate Sensor
                    </button>
                <?php endif; ?>
                
                <button onclick="regenerateToken('<?= htmlspecialchars($device['id']) ?>')" class="btn btn-secondary btn-sm">
                    Generate New Bearer Token
                </button>

                <button onclick="deleteSensor('<?= htmlspecialchars($device['id']) ?>', '<?= htmlspecialchars($device['device_id']) ?>')" class="btn btn-danger btn-sm" style="margin-left: auto;">
                    Delete Device
                </button>
            </div>
        </div>
    </div>

    <!-- Metadata JSON Inspection -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Device Telemetry Metadata</h3>
                <p class="card-desc">Provisioned attributes and hardware tags</p>
            </div>
        </div>
        <div class="card-content">
            <pre class="font-mono" style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 14px; font-size: 12px; color: var(--slate-800); max-height: 250px; overflow-y: auto;"><?= htmlspecialchars(json_encode(json_decode($device['metadata'] ?? '{}'), JSON_PRETTY_PRINT)) ?></pre>
        </div>
    </div>
</div>

<!-- Upload History for this Sensor -->
<div class="card" style="margin-top: 24px;">
    <div class="card-header">
        <div>
            <h3 class="card-title">Upload History for <?= htmlspecialchars($device['device_id']) ?></h3>
            <p class="card-desc">Most recent files ingested from this sensor</p>
        </div>
    </div>
    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Upload ID</th>
                        <th>Filename</th>
                        <th>Size</th>
                        <th>MIME Type</th>
                        <th>SHA-256 Checksum</th>
                        <th>Uploaded Timestamp</th>
                        <th style="text-align: right;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($device['recent_uploads'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 24px; color: var(--slate-400);">
                                No uploads recorded for this device yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($device['recent_uploads'] as $upl): ?>
                            <tr>
                                <td class="font-mono" style="font-size: 12px; font-weight: 500; color: var(--slate-700);"><?= htmlspecialchars($upl['upload_record_id']) ?></td>
                                <td style="font-weight: 500; color: var(--slate-900);"><?= htmlspecialchars($upl['original_file_name']) ?></td>
                                <td style="font-size: 12px;"><?= formatDetailBytes($upl['file_size_bytes']) ?></td>
                                <td style="font-size: 12px; color: var(--slate-500);"><?= htmlspecialchars($upl['mime_type']) ?></td>
                                <td class="font-mono" style="font-size: 11px; color: var(--slate-400);" title="<?= htmlspecialchars($upl['checksum'] ?? '') ?>">
                                    <?= substr($upl['checksum'] ?? '', 0, 16) ?>...
                                </td>
                                <td style="font-size: 12px; color: var(--slate-500);"><?= date('M j, Y H:i:s', strtotime($upl['uploaded_at'])) ?></td>
                                <td style="text-align: right;">
                                    <a href="/api/admin/files/<?= urlencode($upl['upload_record_id']) ?>/download" class="btn btn-secondary btn-sm">Download</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
