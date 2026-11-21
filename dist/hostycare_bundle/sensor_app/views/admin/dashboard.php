<?php
declare(strict_types=1);

/**
 * Helper to format bytes
 */
function formatSizeUnits($bytes) {
    if ($bytes >= 1073741824) {
        return number_format($bytes / 1073741824, 2) . ' GB';
    } elseif ($bytes >= 1048576) {
        return number_format($bytes / 1048576, 2) . ' MB';
    } elseif ($bytes >= 1024) {
        return number_format($bytes / 1024, 2) . ' KB';
    } elseif ($bytes > 1) {
        return $bytes . ' bytes';
    } elseif ($bytes == 1) {
        return $bytes . ' byte';
    } else {
        return '0 B';
    }
}

$totalSensors = $stats['totalSensors'] ?? 0;
$activeSensors = $stats['activeSensors'] ?? 0;
$deactiveSensors = $stats['deactiveSensors'] ?? 0;
$activePercent = $totalSensors > 0 ? round(($activeSensors / $totalSensors) * 100) : 0;
$deactivePercent = $totalSensors > 0 ? (100 - $activePercent) : 0;
?>

<!-- Auto-Refresh Header Subtitle -->
<div style="display: flex; align-items: center; justify-content: space-between; margin-top: -10px; margin-bottom: 6px;">
    <p style="font-size: 13px; color: var(--slate-500);">Live telemetry metrics and device ingestion oversight</p>
    <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--slate-400);">
        <svg id="auto-refresh-indicator" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
        <span>Auto-refreshing (30s)</span>
    </div>
</div>

<!-- SECTION 1: 9 StatCards Grid -->
<div class="grid-stats">
    <!-- 1. Total Sensors -->
    <div class="stat-card">
        <div class="stat-icon neutral">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total sensors</span>
            <span id="stat-total-sensors" class="stat-value"><?= $totalSensors ?></span>
        </div>
    </div>

    <!-- 2. Active Sensors -->
    <div class="stat-card">
        <div class="stat-icon success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Active sensors</span>
            <span id="stat-active-sensors" class="stat-value"><?= $activeSensors ?></span>
        </div>
    </div>

    <!-- 3. Deactive Sensors -->
    <div class="stat-card">
        <div class="stat-icon neutral">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Deactive sensors</span>
            <span id="stat-deactive-sensors" class="stat-value"><?= $deactiveSensors ?></span>
        </div>
    </div>

    <!-- 4. Pending Requests -->
    <div class="stat-card">
        <div class="stat-icon warning">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Pending requests</span>
            <span id="stat-pending-requests" class="stat-value"><?= $stats['pendingRequests'] ?? 0 ?></span>
        </div>
    </div>

    <!-- 5. Approved Requests -->
    <div class="stat-card">
        <div class="stat-icon success">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Approved requests</span>
            <span id="stat-approved-requests" class="stat-value"><?= $stats['approvedRequests'] ?? 0 ?></span>
        </div>
    </div>

    <!-- 6. Rejected Requests -->
    <div class="stat-card">
        <div class="stat-icon danger">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Rejected requests</span>
            <span id="stat-rejected-requests" class="stat-value"><?= $stats['rejectedRequests'] ?? 0 ?></span>
        </div>
    </div>

    <!-- 7. Total Files -->
    <div class="stat-card">
        <div class="stat-icon neutral">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" x2="12" y1="22" y2="12"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total files</span>
            <span id="stat-total-files" class="stat-value"><?= $stats['totalFiles'] ?? 0 ?></span>
        </div>
    </div>

    <!-- 8. Total Storage Used -->
    <div class="stat-card">
        <div class="stat-icon neutral">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="22" x2="2" y1="12" y2="12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" x2="6.01" y1="16" y2="16"/><line x1="10" x2="10.01" y1="16" y2="16"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Total storage used</span>
            <span id="stat-total-storage" class="stat-value"><?= formatSizeUnits($stats['totalStorageBytes'] ?? 0) ?></span>
        </div>
    </div>

    <!-- 9. Uploads in Last 24h -->
    <div class="stat-card">
        <div class="stat-icon accent">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
        </div>
        <div class="stat-info">
            <span class="stat-label">Uploads in last 24h</span>
            <span id="stat-uploads-24h" class="stat-value"><?= $stats['uploadsLast24h'] ?? 0 ?></span>
        </div>
    </div>
</div>

<!-- SECTION 2: Two-Column Cards (Recent Uploads & Recent Requests) -->
<div class="grid-2col">
    <!-- Recent Uploads -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Recent uploads</h3>
                <p class="card-desc">Latest files uploaded by authenticated sensors</p>
            </div>
            <a href="/admin/files" class="btn btn-ghost btn-sm">View all &rarr;</a>
        </div>
        <div class="card-content" style="padding: 0;">
            <?php if (empty($stats['recentUploads'])): ?>
                <div style="padding: 24px; text-align: center; color: var(--slate-400);">No files uploaded yet.</div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($stats['recentUploads'] as $upload): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--slate-100);">
                            <div>
                                <div style="font-weight: 500; color: var(--slate-800); font-size: 13.5px;"><?= htmlspecialchars($upload['original_file_name']) ?></div>
                                <div style="font-size: 11.5px; color: var(--slate-500); display: flex; gap: 8px; margin-top: 2px;">
                                    <span class="font-mono"><?= htmlspecialchars($upload['device_id']) ?></span>
                                    <span>&bull;</span>
                                    <span><?= formatSizeUnits($upload['file_size_bytes']) ?></span>
                                </div>
                            </div>
                            <div style="font-size: 11.5px; color: var(--slate-400); white-space: nowrap;">
                                <?= date('M j, H:i', strtotime($upload['uploaded_at'])) ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Recent Registration Requests -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Recent registration requests</h3>
                <p class="card-desc">Devices waiting for approval or recently reviewed</p>
            </div>
            <a href="/admin/history" class="btn btn-ghost btn-sm">View all &rarr;</a>
        </div>
        <div class="card-content" style="padding: 0;">
            <?php if (empty($stats['recentRequests'])): ?>
                <div style="padding: 24px; text-align: center; color: var(--slate-400);">No registration requests recorded.</div>
            <?php else: ?>
                <div style="display: flex; flex-direction: column;">
                    <?php foreach ($stats['recentRequests'] as $req): ?>
                        <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--slate-100);">
                            <div>
                                <div class="font-mono" style="font-weight: 600; color: var(--slate-800);"><?= htmlspecialchars($req['device_id']) ?></div>
                                <div style="font-size: 11.5px; color: var(--slate-400); margin-top: 2px;">
                                    Submitted <?= date('M j, H:i', strtotime($req['submitted_at'])) ?>
                                </div>
                            </div>
                            <div>
                                <?php
                                $badgeClass = match($req['status']) {
                                    'APPROVED', 'AUTO_APPROVED' => 'badge-success',
                                    'PENDING' => 'badge-warning',
                                    'REJECTED', 'CANCELLED' => 'badge-danger',
                                    default => 'badge-neutral'
                                };
                                ?>
                                <span class="badge <?= $badgeClass ?>"><?= htmlspecialchars($req['status']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- SECTION 3: Two-Column Cards (Distribution & Activity Charts) -->
<div class="grid-2col">
    <!-- Sensor Status Distribution -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Sensor status distribution</h3>
                <p class="card-desc">Active vs. Deactive deployment ratio</p>
            </div>
        </div>
        <div class="card-content">
            <div class="dist-bar-track" role="img" aria-label="Sensor status distribution bar">
                <div id="dist-bar-active" class="dist-bar-active" style="width: <?= $activePercent ?>%;"></div>
                <div id="dist-bar-deactive" class="dist-bar-deactive" style="width: <?= $deactivePercent ?>%;"></div>
            </div>
            <div class="dist-legend">
                <div>
                    <span class="legend-dot" style="background-color: var(--success-500);"></span>
                    <span>Active: <strong><?= $activeSensors ?></strong> (<?= $activePercent ?>%)</span>
                </div>
                <div>
                    <span class="legend-dot" style="background-color: var(--slate-300);"></span>
                    <span>Deactive: <strong><?= $deactiveSensors ?></strong> (<?= $deactivePercent ?>%)</span>
                </div>
            </div>
        </div>
    </div>

    <!-- 7-Day Upload Activity Chart -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Upload activity (last 7 days)</h3>
                <p class="card-desc">Daily ingestion volume</p>
            </div>
        </div>
        <div class="card-content">
            <?php
            // Calculate max count for scaling
            $maxCount = 1;
            foreach ($stats['activity7d'] as $d) {
                if ($d['count'] > $maxCount) $maxCount = $d['count'];
            }
            ?>
            <div class="chart-container" role="img" aria-label="Upload activity chart for last 7 days">
                <?php if (empty($stats['activity7d'])): ?>
                    <div style="flex: 1; text-align: center; color: var(--slate-400); align-self: center;">No activity recorded in the last 7 days.</div>
                <?php else: ?>
                    <?php foreach ($stats['activity7d'] as $act): 
                        $heightPercent = max(10, round(($act['count'] / $maxCount) * 100));
                    ?>
                        <div class="chart-bar-wrap" title="<?= htmlspecialchars($act['upload_date']) ?>: <?= $act['count'] ?> uploads">
                            <div class="chart-bar" style="height: <?= $heightPercent ?>%;"></div>
                            <span class="chart-label"><?= substr($act['day_name'] ?? '', 0, 3) ?></span>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
