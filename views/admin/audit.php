<?php
declare(strict_types=1);
?>

<div class="card">
    <div class="card-header">
        <div>
            <h3 class="card-title">Administrative Audit Trail</h3>
            <p class="card-desc">Immutable record of security and administrative operations</p>
        </div>
    </div>
    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Administrator</th>
                        <th>Action</th>
                        <th>Entity</th>
                        <th>Target ID</th>
                        <th>IP Address</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['logs'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No audit events recorded yet.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['logs'] as $log): ?>
                            <tr>
                                <td style="font-size: 12px; color: var(--slate-600); white-space: nowrap;">
                                    <?= date('M j, Y H:i:s', strtotime($log['created_at'])) ?>
                                </td>
                                <td>
                                    <strong style="color: var(--slate-900); font-size: 13px;">
                                        <?= htmlspecialchars($log['username']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <span class="badge badge-accent"><?= htmlspecialchars($log['action']) ?></span>
                                </td>
                                <td style="font-size: 12px; color: var(--slate-600);">
                                    <?= htmlspecialchars($log['entity_type']) ?>
                                </td>
                                <td class="font-mono" style="font-size: 12px; color: var(--slate-700);">
                                    <?= htmlspecialchars($log['entity_id'] ?? '—') ?>
                                </td>
                                <td class="font-mono" style="font-size: 12px; color: var(--slate-500);">
                                    <?= htmlspecialchars($log['ip_address'] ?? '—') ?>
                                </td>
                                <td style="font-size: 11.5px; color: var(--slate-500); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($log['metadata'] ?? '') ?>">
                                    <?= htmlspecialchars($log['metadata'] ?? '—') ?>
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
                Page <?= $data['page'] ?> of <?= $data['totalPages'] ?> (<?= $data['total'] ?> total audit records)
            </span>
            <div style="display: flex; gap: 6px;">
                <?php if ($data['page'] > 1): ?>
                    <a href="?page=<?= $data['page'] - 1 ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($data['page'] < $data['totalPages']): ?>
                    <a href="?page=<?= $data['page'] + 1 ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
