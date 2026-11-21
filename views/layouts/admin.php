<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Dashboard') ?> — FileVault Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛡️</text></svg>">
</head>
<body>
    <div class="app-shell">
        <!-- Mobile Backdrop -->
        <div id="sidebar-backdrop" class="sidebar-backdrop"></div>

        <!-- Sidebar -->
        <aside class="sidebar">
            <div class="sidebar-brand">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3563E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
                <div>
                    <h2>FileVault Admin</h2>
                    <p>
                        <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        Secure admin session
                    </p>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="/admin/dashboard" class="nav-item <?= ($activeNav ?? '') === 'dashboard' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
                    <span>Overview</span>
                </a>
                <a href="/admin/sensors" class="nav-item <?= ($activeNav ?? '') === 'sensors' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>
                    <span>Sensors</span>
                </a>
                <a href="/admin/configs" class="nav-item <?= ($activeNav ?? '') === 'configs' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20 7h-9"/><path d="M14 17H5"/><circle cx="17" cy="17" r="3"/><circle cx="7" cy="7" r="3"/></svg>
                    <span>Configuration Settings</span>
                </a>
                <a href="/admin/history" class="nav-item <?= ($activeNav ?? '') === 'history' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M12 7v5l4 2"/></svg>
                    <span>Registration History</span>
                </a>
                <a href="/admin/files" class="nav-item <?= ($activeNav ?? '') === 'files' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" x2="12" y1="22" y2="12"/></svg>
                    <span>Uploaded Files</span>
                </a>
                <a href="/admin/version-check-metrics" class="nav-item <?= ($activeNav ?? '') === 'metrics' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" x2="12" y1="20" y2="10"/><line x1="18" x2="18" y1="20" y2="4"/><line x1="6" x2="6" y1="20" y2="16"/></svg>
                    <span>Version Check Metrics</span>
                </a>
                <a href="/admin/audit" class="nav-item <?= ($activeNav ?? '') === 'audit' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/><polyline points="14 2 14 8 20 8"/><line x1="16" x2="8" y1="13" y2="13"/><line x1="16" x2="8" y1="17" y2="17"/><line x1="10" x2="8" y1="9" y2="9"/></svg>
                    <span>Audit Logs</span>
                </a>
                <a href="/admin/settings" class="nav-item <?= ($activeNav ?? '') === 'settings' ? 'active' : '' ?>">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                    <span>Settings</span>
                </a>
                <a href="/upload" target="_blank" class="nav-item">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/></svg>
                    <span>Upload Portal ↗</span>
                </a>
            </nav>

            <div class="sidebar-footer">
                <form action="/api/admin/logout" method="POST" onsubmit="event.preventDefault(); fetch('/api/admin/logout', {method:'POST'}).then(() => window.location.href='/admin/login');">
                    <button type="submit" class="btn btn-ghost" style="width: 100%; justify-content: flex-start; color: var(--slate-300);">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>
                        <span>Sign Out</span>
                    </button>
                </form>
            </div>
        </aside>

        <!-- Main Content Shell -->
        <div class="main-wrapper">
            <!-- Header -->
            <header class="top-header">
                <div class="header-left">
                    <button id="mobile-menu-toggle" class="btn btn-ghost btn-sm" style="display: none; padding: 4px;" aria-label="Open menu">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="4" x2="20" y1="12" y2="12"/><line x1="4" x2="20" y1="6" y2="6"/><line x1="4" x2="20" y1="18" y2="18"/></svg>
                    </button>
                    <style>@media (max-width: 1024px) { #mobile-menu-toggle { display: inline-flex !important; } }</style>
                    <h1 class="page-title"><?= htmlspecialchars($title ?? 'Overview') ?></h1>
                </div>

                <div class="header-right">
                    <!-- Notification Bell -->
                    <button id="notification-bell-btn" class="btn btn-ghost btn-sm" title="Notifications" aria-label="Notifications" style="color: var(--slate-500); padding: 8px;">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10.3 21a1.94 1.94 0 0 0 3.4 0"/></svg>
                    </button>

                    <!-- User profile & Role Badge -->
                    <div class="user-profile">
                        <span style="font-size: 13px; font-weight: 500; color: var(--slate-700);"><?= htmlspecialchars($admin['username'] ?? 'admin') ?></span>
                        <span class="badge badge-accent"><?= htmlspecialchars($admin['role'] ?? 'ADMIN') ?></span>
                    </div>

                    <!-- Quick Logout -->
                    <button onclick="fetch('/api/admin/logout', {method:'POST'}).then(() => window.location.href='/admin/login');" class="btn btn-secondary btn-sm" title="Logout">
                        Logout
                    </button>
                </div>
            </header>

            <!-- Page Body -->
            <main class="content-body">
                <?= $content ?>
            </main>
        </div>
    </div>

    <!-- Token Display Modal (Shown ONCE on approval or regeneration) -->
    <div id="token-display-modal" class="modal-overlay">
        <div class="modal-box">
            <div class="card-header">
                <div>
                    <h3 class="card-title">Sensor Bearer Token</h3>
                    <p class="card-desc">For Device: <strong id="modal-token-device-id" class="font-mono"></strong></p>
                </div>
                <button data-close-modal class="btn btn-ghost btn-sm">&times;</button>
            </div>
            <div class="card-content">
                <div style="background-color: var(--warning-50); border: 1px solid var(--warning-500); border-radius: var(--radius-md); padding: 12px; margin-bottom: 16px; font-size: 12.5px; color: var(--warning-700);">
                    <strong>Important:</strong> This raw Bearer token is shown <strong>ONLY ONCE</strong> and cannot be retrieved later. Copy it now and flash it into your ESP32 device!
                </div>
                <div class="form-group">
                    <label class="form-label">Raw Token (CSPRNG 256-bit):</label>
                    <div style="display: flex; gap: 8px;">
                        <input id="modal-token-input" type="text" readonly class="input font-mono" style="font-size: 12px;">
                        <button data-copy-target="modal-token-input" class="btn btn-primary btn-sm">Copy</button>
                    </div>
                </div>
            </div>
            <div class="card-footer">
                <button data-close-modal onclick="window.location.reload();" class="btn btn-secondary btn-sm">I have safely saved this token</button>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="/assets/js/app.js"></script>
</body>
</html>
