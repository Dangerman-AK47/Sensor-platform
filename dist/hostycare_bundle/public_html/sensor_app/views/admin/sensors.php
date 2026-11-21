<?php
declare(strict_types=1);

function formatBytesShort($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 1) . ' KB';
    return $bytes . ' B';
}
?>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="card-title">Registered Sensors</h3>
            <p class="card-desc">Provisioned ESP32 devices communicating with the platform</p>
        </div>
        
        <!-- Search & Filter Form -->
        <form method="GET" action="/admin/sensors" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
            <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search by Device ID..." class="input" style="width: 220px;">
            <select name="status" class="select" style="width: 130px;" onchange="this.form.submit()">
                <option value="" <?= empty($status) ? 'selected' : '' ?>>All Statuses</option>
                <option value="ACTIVE" <?= ($status ?? '') === 'ACTIVE' ? 'selected' : '' ?>>Active</option>
                <option value="DEACTIVE" <?= ($status ?? '') === 'DEACTIVE' ? 'selected' : '' ?>>Deactive</option>
            </select>
            <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
            <?php if (!empty($search) || !empty($status)): ?>
                <a href="/admin/sensors" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Status</th>
                        <th>Hardware Model</th>
                        <th>Sensor / FW Version</th>
                        <th>Last Seen</th>
                        <th>Uploads</th>
                        <th>Storage</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['devices'])): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No registered devices found matching criteria.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['devices'] as $dev): ?>
                            <tr>
                                <td>
                                    <a href="/admin/sensors/<?= urlencode($dev['device_id']) ?>" class="font-mono" style="font-weight: 600; color: var(--accent-500); text-decoration: none;">
                                        <?= htmlspecialchars($dev['device_id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php if ($dev['status'] === 'ACTIVE'): ?>
                                        <span class="badge badge-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-neutral">Deactive</span>
                                    <?php endif; ?>
                                </td>
                                <td style="color: var(--slate-600);"><?= htmlspecialchars($dev['hardware_model'] ?? '—') ?></td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?= htmlspecialchars($dev['sensor_version'] ?? '—') ?> / <?= htmlspecialchars($dev['firmware_version'] ?? '—') ?>
                                </td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?= !empty($dev['last_seen_at']) ? date('M j, H:i', strtotime($dev['last_seen_at'])) : 'Never' ?>
                                </td>
                                <td><strong><?= $dev['upload_count'] ?></strong></td>
                                <td style="font-size: 12px;"><?= formatBytesShort($dev['total_storage_bytes']) ?></td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="/admin/sensors/<?= urlencode($dev['device_id']) ?>" class="btn btn-ghost btn-sm">View</a>
                                    <?php if ($dev['status'] === 'ACTIVE'): ?>
                                        <button onclick="toggleSensorStatus('<?= htmlspecialchars($dev['id']) ?>', 'DEACTIVE')" class="btn btn-ghost btn-sm" style="color: var(--danger-500);">Deactivate</button>
                                    <?php else: ?>
                                        <button onclick="toggleSensorStatus('<?= htmlspecialchars($dev['id']) ?>', 'ACTIVE')" class="btn btn-ghost btn-sm" style="color: var(--success-700);">Activate</button>
                                    <?php endif; ?>
                                    <button onclick="regenerateToken('<?= htmlspecialchars($dev['id']) ?>')" class="btn btn-secondary btn-sm">Token</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination Footer -->
    <?php if (($data['totalPages'] ?? 1) > 1): ?>
        <div class="card-footer" style="justify-content: space-between;">
            <span style="font-size: 12.5px; color: var(--slate-500);">
                Showing page <?= $data['page'] ?> of <?= $data['totalPages'] ?> (<?= $data['total'] ?> total devices)
            </span>
            <div style="display: flex; gap: 6px;">
                <?php if ($data['page'] > 1): ?>
                    <a href="?page=<?= $data['page'] - 1 ?>&search=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($data['page'] < $data['totalPages']): ?>
                    <a href="?page=<?= $data['page'] + 1 ?>&search=<?= urlencode($search ?? '') ?>&status=<?= urlencode($status ?? '') ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
