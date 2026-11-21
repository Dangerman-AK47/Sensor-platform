<?php
declare(strict_types=1);
?>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="card-title">Registration Requests History</h3>
            <p class="card-desc">Review device enrollment requests and grant bearer authorization</p>
        </div>

        <div style="display: flex; gap: 6px; align-items: center;">
            <a href="/admin/history" class="btn <?= empty($status) ? 'btn-primary' : 'btn-secondary' ?> btn-sm">All</a>
            <a href="/admin/history?status=PENDING" class="btn <?= ($status ?? '') === 'PENDING' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Pending</a>
            <a href="/admin/history?status=APPROVED" class="btn <?= ($status ?? '') === 'APPROVED' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Approved</a>
            <a href="/admin/history?status=REJECTED" class="btn <?= ($status ?? '') === 'REJECTED' ? 'btn-primary' : 'btn-secondary' ?> btn-sm">Rejected</a>
        </div>
    </div>

    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Device ID</th>
                        <th>Status</th>
                        <th>Submitted At</th>
                        <th>Requester Info</th>
                        <th>Reviewed By / Date</th>
                        <th>Review Reason</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['requests'])): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No registration requests recorded.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['requests'] as $req): ?>
                            <tr>
                                <td>
                                    <strong class="font-mono" style="font-size: 13.5px; color: var(--slate-900);">
                                        <?= htmlspecialchars($req['device_id']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <?php
                                    $bClass = match($req['status']) {
                                        'APPROVED', 'AUTO_APPROVED' => 'badge-success',
                                        'PENDING' => 'badge-warning',
                                        'REJECTED', 'CANCELLED' => 'badge-danger',
                                        default => 'badge-neutral'
                                    };
                                    ?>
                                    <span class="badge <?= $bClass ?>"><?= htmlspecialchars($req['status']) ?></span>
                                </td>
                                <td style="font-size: 12px; color: var(--slate-600);"><?= date('M j, Y H:i', strtotime($req['submitted_at'])) ?></td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?php 
                                    $meta = json_decode($req['requester_metadata'] ?? '{}', true) ?: [];
                                    echo htmlspecialchars(($meta['ip'] ?? 'IP: —') . ' | ' . ($meta['hardwareModel'] ?? 'ESP32'));
                                    ?>
                                </td>
                                <td style="font-size: 12px; color: var(--slate-500);">
                                    <?php if (!empty($req['reviewed_at'])): ?>
                                        <?= htmlspecialchars($req['reviewer_username'] ?? 'admin') ?> &bull; <?= date('M j, H:i', strtotime($req['reviewed_at'])) ?>
                                    <?php else: ?>
                                        <span style="color: var(--slate-400);">Awaiting Review</span>
                                    <?php endif; ?>
                                </td>
                                <td style="font-size: 12px; color: var(--slate-600); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($req['review_reason'] ?? '') ?>">
                                    <?= htmlspecialchars($req['review_reason'] ?? '—') ?>
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <?php if ($req['status'] === 'PENDING'): ?>
                                        <button onclick="approveRegistration('<?= htmlspecialchars($req['id']) ?>')" class="btn btn-primary btn-sm">Approve</button>
                                        <button onclick="rejectRegistration('<?= htmlspecialchars($req['id']) ?>')" class="btn btn-ghost btn-sm" style="color: var(--danger-500);">Reject</button>
                                    <?php else: ?>
                                        <span style="font-size: 12px; color: var(--slate-400);">Closed</span>
                                    <?php endif; ?>
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
                Page <?= $data['page'] ?> of <?= $data['totalPages'] ?> (<?= $data['total'] ?> total requests)
            </span>
            <div style="display: flex; gap: 6px;">
                <?php if ($data['page'] > 1): ?>
                    <a href="?page=<?= $data['page'] - 1 ?>&status=<?= urlencode($status ?? '') ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($data['page'] < $data['totalPages']): ?>
                    <a href="?page=<?= $data['page'] + 1 ?>&status=<?= urlencode($status ?? '') ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
