<?php
declare(strict_types=1);
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Version Check Telemetry Metrics</h3>
            <p class="card-desc">Aggregated outcomes of periodic sensor configuration checks</p>
        </div>
    </div>
    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Telemetry Outcome</th>
                        <th>Cumulative Check Count</th>
                        <th>Last Reported Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($metrics)): ?>
                        <tr>
                            <td colspan="4" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No version check metrics logged yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($metrics as $m): ?>
                            <tr>
                                <td>
                                    <a href="/admin/sensors/<?= urlencode($m['device_id']) ?>" class="font-mono" style="font-weight: 600; color: var(--accent-500); text-decoration: none;">
                                        <?= htmlspecialchars($m['device_id']) ?>
                                    </a>
                                </td>
                                <td>
                                    <?php
                                    $bClass = match($m['result']) {
                                        'CURRENT' => 'badge-success',
                                        'UPDATE_AVAILABLE' => 'badge-accent',
                                        'SENSOR_DEACTIVATED' => 'badge-danger',
                                        'NO_CONFIGURATION_AVAILABLE' => 'badge-warning',
                                        default => 'badge-neutral'
                                    };
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= htmlspecialchars($m['result']) ?></span>
                                </td>
                                <td>
                                    <strong style="color: var(--slate-900); font-size: 14px;"><?= $m['total_checks'] ?> pings</strong>
                                </td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?= date('M j, Y', strtotime($m['last_date'])) ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
