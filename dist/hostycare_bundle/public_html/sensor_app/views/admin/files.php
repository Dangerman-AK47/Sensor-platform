<?php
declare(strict_types=1);

function formatFileTableBytes($bytes) {
    if ($bytes >= 1048576) return round($bytes / 1048576, 2) . ' MB';
    if ($bytes >= 1024) return round($bytes / 1024, 2) . ' KB';
    return $bytes . ' B';
}
?>

<div class="card">
    <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
        <div>
            <h3 class="card-title">Uploaded Sensor Telemetry Files</h3>
            <p class="card-desc">Persisted files ingested via HTTP multipart uploads</p>
        </div>

        <form method="GET" action="/admin/files" style="display: flex; gap: 8px;">
            <input type="text" name="search" value="<?= htmlspecialchars($search ?? '') ?>" placeholder="Search filename or device ID..." class="input" style="width: 250px;">
            <button type="submit" class="btn btn-secondary btn-sm">Search</button>
            <?php if (!empty($search)): ?>
                <a href="/admin/files" class="btn btn-ghost btn-sm">Reset</a>
            <?php endif; ?>
        </form>
    </div>

    <div class="card-content" style="padding: 0;">
        <div class="table-responsive">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Upload ID</th>
                        <th>Filename</th>
                        <th>Device ID</th>
                        <th>Size</th>
                        <th>MIME Type</th>
                        <th>Uploaded Timestamp</th>
                        <th>SHA-256 Checksum</th>
                        <th style="text-align: right;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($data['files'])): ?>
                        <tr>
                            <td colspan="8" style="text-align: center; padding: 32px; color: var(--slate-400);">
                                No uploaded file records found.
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($data['files'] as $f): ?>
                            <tr>
                                <td class="font-mono" style="font-size: 12px; font-weight: 500; color: var(--slate-700);">
                                    <?= htmlspecialchars($f['upload_record_id']) ?>
                                </td>
                                <td>
                                    <strong style="color: var(--slate-900); font-size: 13.5px;">
                                        <?= htmlspecialchars($f['original_file_name']) ?>
                                    </strong>
                                </td>
                                <td>
                                    <a href="/admin/sensors/<?= urlencode($f['device_id']) ?>" class="font-mono" style="color: var(--accent-500); text-decoration: none;">
                                        <?= htmlspecialchars($f['device_id']) ?>
                                    </a>
                                </td>
                                <td style="font-size: 12px;"><?= formatFileTableBytes($f['file_size_bytes']) ?></td>
                                <td style="font-size: 12px; color: var(--slate-500);"><?= htmlspecialchars($f['mime_type']) ?></td>
                                <td style="font-size: 12px; color: var(--slate-500);"><?= date('M j, Y H:i:s', strtotime($f['uploaded_at'])) ?></td>
                                <td class="font-mono" style="font-size: 11px; color: var(--slate-400);" title="<?= htmlspecialchars($f['checksum'] ?? '') ?>">
                                    <?= substr($f['checksum'] ?? '', 0, 16) ?>...
                                </td>
                                <td style="text-align: right; white-space: nowrap;">
                                    <a href="/api/admin/files/<?= urlencode($f['id']) ?>/download" class="btn btn-secondary btn-sm">Download</a>
                                    <button onclick="deleteUploadedFile('<?= htmlspecialchars($f['id']) ?>', '<?= htmlspecialchars($f['original_file_name']) ?>')" class="btn btn-ghost btn-sm" style="color: var(--danger-500);">Delete</button>
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
                Page <?= $data['page'] ?> of <?= $data['totalPages'] ?> (<?= $data['total'] ?> total files)
            </span>
            <div style="display: flex; gap: 6px;">
                <?php if ($data['page'] > 1): ?>
                    <a href="?page=<?= $data['page'] - 1 ?>&search=<?= urlencode($search ?? '') ?>" class="btn btn-secondary btn-sm">&larr; Previous</a>
                <?php endif; ?>
                <?php if ($data['page'] < $data['totalPages']): ?>
                    <a href="?page=<?= $data['page'] + 1 ?>&search=<?= urlencode($search ?? '') ?>" class="btn btn-secondary btn-sm">Next &rarr;</a>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>
