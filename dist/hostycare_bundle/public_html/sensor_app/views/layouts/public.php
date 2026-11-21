<?php
declare(strict_types=1);
$isAuth = !empty($isAuth);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'Sensor Upload Portal') ?> — FileVault</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>📡</text></svg>">
    <style>
        .portal-locked {
            filter: blur(10px) brightness(0.96);
            pointer-events: none !important;
            user-select: none !important;
            transition: filter 0.3s ease, transform 0.3s ease;
        }
        .portal-modal-overlay {
            position: fixed;
            inset: 0;
            background: rgba(15, 23, 42, 0.45);
            backdrop-filter: blur(6px);
            -webkit-backdrop-filter: blur(6px);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 99999;
            padding: 16px;
        }
        .portal-modal-card {
            background: #ffffff;
            border-radius: 18px;
            box-shadow: 0 25px 50px -12px rgba(15, 23, 42, 0.25), 0 0 0 1px rgba(226, 232, 240, 0.9);
            max-width: 420px;
            width: 100%;
            padding: 32px 28px;
            box-sizing: border-box;
            animation: modalPopIn 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        }
        @keyframes modalPopIn {
            from { opacity: 0; transform: scale(0.94) translateY(8px); }
            to { opacity: 1; transform: scale(1) translateY(0); }
        }
        .portal-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 4px 12px;
            background: #fef3c7;
            border: 1px solid #fde68a;
            border-radius: 9999px;
            font-size: 11.5px;
            font-weight: 600;
            color: #92400e;
            margin-bottom: 14px;
        }
        .portal-pill-dot {
            width: 6px;
            height: 6px;
            border-radius: 50%;
            background: #d97706;
            display: inline-block;
        }
    </style>
</head>
<body class="bg-slate-50 min-h-screen">
    <!-- App Shell Container (Blurred when not authenticated) -->
    <div id="portal-content-wrapper" class="<?= !$isAuth ? 'portal-locked' : '' ?>" <?= !$isAuth ? 'aria-hidden="true"' : '' ?>>
        <header style="background: #ffffff; border-bottom: 1px solid var(--slate-200); padding: 14px 24px;">
            <div style="max-width: 1000px; margin: 0 auto; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 12px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <a href="/" style="display: flex; align-items: center; gap: 10px; text-decoration: none; color: inherit;">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3563E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            <path d="m9 12 2 2 4-4"></path>
                        </svg>
                        <span style="font-weight: 600; font-size: 16px; color: var(--slate-900);">FileVault / Sensor Portal</span>
                    </a>
                </div>
                <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                    <?php if ($isAuth): ?>
                        <a href="/" class="btn btn-ghost btn-sm">Console</a>
                        <a href="/upload" class="btn btn-ghost btn-sm">Upload</a>
                        <span class="badge badge-success" style="font-size: 12px; padding: 4px 10px;">
                            👤 <strong><?= htmlspecialchars($portalUser['username'] ?? 'user') ?></strong>
                        </span>
                        <button type="button" onclick="handlePortalLogout()" class="btn btn-secondary btn-sm" title="Lock portal and sign out">
                            🔒 Lock
                        </button>
                    <?php else: ?>
                        <span class="badge badge-warning" style="font-size: 11.5px; padding: 3px 8px;">
                            🔒 Locked
                        </span>
                    <?php endif; ?>
                    <a href="/admin/login" class="btn btn-primary btn-sm">Admin Portal &rarr;</a>
                </div>
            </div>
        </header>

        <main style="max-width: 800px; margin: 40px auto; padding: 0 20px;">
            <?= $content ?>
        </main>
    </div>

    <!-- Modal Login Over the App (Reference: Modal Login Over the App) -->
    <?php if (!$isAuth): ?>
    <div id="portal-auth-modal" class="portal-modal-overlay" role="dialog" aria-modal="true" aria-labelledby="portal-modal-title">
        <div class="portal-modal-card">
            <!-- Pill Indicator -->
            <div class="portal-pill">
                <span class="portal-pill-dot"></span>
                <span>Protected Portal</span>
            </div>

            <!-- Title & Prompt -->
            <h2 id="portal-modal-title" style="font-size: 22px; font-weight: 700; color: #0f172a; margin: 0 0 6px 0; letter-spacing: -0.02em;">
                Sign in to continue
            </h2>
            <p style="font-size: 13px; color: #64748b; line-height: 1.45; margin: 0 0 22px 0;">
                Authentication required. Enter your user credentials to access the sensor telemetry console.
            </p>

            <!-- Error Notification Banner -->
            <div id="portal-login-error" style="display: none; background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px;"></div>

            <!-- Login Form -->
            <form id="portal-login-form" onsubmit="handlePortalUnlock(event)">
                <div style="margin-bottom: 14px;">
                    <label for="portal-username" style="display: block; font-size: 12.5px; font-weight: 600; color: #334155; margin-bottom: 6px;">
                        Username
                    </label>
                    <input 
                        type="text" 
                        id="portal-username" 
                        name="username" 
                        value="user" 
                        required 
                        autocomplete="username"
                        class="input" 
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; background: #fff; box-sizing: border-box;"
                        placeholder="user"
                    >
                </div>

                <div style="margin-bottom: 20px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px;">
                        <label for="portal-password" style="font-size: 12.5px; font-weight: 600; color: #334155;">
                            Password
                        </label>
                        <span style="font-size: 11.5px; color: #94a3b8;">Default: password</span>
                    </div>
                    <input 
                        type="password" 
                        id="portal-password" 
                        name="password" 
                        required 
                        autocomplete="current-password"
                        class="input" 
                        style="width: 100%; border: 1px solid #cbd5e1; border-radius: 8px; padding: 10px 14px; font-size: 14px; background: #fff; box-sizing: border-box;"
                        placeholder="••••••••"
                        autofocus
                    >
                </div>

                <button 
                    type="submit" 
                    id="portal-unlock-btn" 
                    class="btn btn-primary" 
                    style="width: 100%; padding: 12px; font-size: 14.5px; font-weight: 600; background: #4f46e5; border-radius: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; border: none; cursor: pointer; color: #fff; box-shadow: 0 4px 12px rgba(79, 70, 229, 0.25);"
                >
                    Unlock
                </button>

                <div style="margin-top: 18px; text-align: center; font-size: 12.5px; color: #64748b;">
                    Need administrative controls? 
                    <a href="/admin/login" style="color: #4f46e5; text-decoration: none; font-weight: 600;">Sign in to Admin &rarr;</a>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

    <script>
    async function handlePortalUnlock(e) {
        e.preventDefault();
        const btn = document.getElementById('portal-unlock-btn');
        const errBox = document.getElementById('portal-login-error');
        const username = document.getElementById('portal-username').value.trim();
        const password = document.getElementById('portal-password').value.trim();

        btn.disabled = true;
        btn.textContent = 'Verifying...';
        errBox.style.display = 'none';

        try {
            const res = await fetch('/api/user/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const data = await res.json();
            if (res.ok && data.success) {
                const modal = document.getElementById('portal-auth-modal');
                const wrapper = document.getElementById('portal-content-wrapper');
                if (modal) {
                    modal.style.transition = 'opacity 0.22s ease, transform 0.22s ease';
                    modal.style.opacity = '0';
                    modal.style.transform = 'scale(0.95)';
                    setTimeout(() => {
                        modal.style.display = 'none';
                        if (wrapper) {
                            wrapper.classList.remove('portal-locked');
                            wrapper.removeAttribute('aria-hidden');
                        }
                        window.location.reload();
                    }, 220);
                }
            } else {
                errBox.textContent = data.error || 'Invalid credentials. Please verify your username and password.';
                errBox.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Unlock';
            }
        } catch (err) {
            errBox.textContent = 'Network error during unlock. Please try again.';
            errBox.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Unlock';
        }
    }

    async function handlePortalLogout() {
        try {
            await fetch('/api/user/logout', { method: 'POST' });
            window.location.reload();
        } catch (err) {
            window.location.reload();
        }
    }
    </script>
    <script src="/assets/js/app.js"></script>
</body>
</html>
