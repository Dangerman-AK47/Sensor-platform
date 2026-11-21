<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign In — FileVault Admin</title>
    <link rel="stylesheet" href="/assets/css/styles.css">
    <link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🛡️</text></svg>">
</head>
<body style="min-height: 100vh; display: flex; align-items: center; justify-content: center; background-color: var(--slate-50); padding: 20px;">
    <div style="max-width: 400px; width: 100%;">
        <!-- Brand Header -->
        <div style="text-align: center; margin-bottom: 24px;">
            <div style="display: inline-flex; align-items: center; justify-content: center; width: 48px; height: 48px; background-color: var(--navy-900); border-radius: var(--radius-lg); margin-bottom: 12px;">
                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#3563E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                    <path d="m9 12 2 2 4-4"></path>
                </svg>
            </div>
            <h1 style="font-size: 20px; font-weight: 700; color: var(--slate-900);">FileVault Admin</h1>
            <p style="font-size: 13px; color: var(--slate-500); margin-top: 4px;">Enter administrative credentials to continue</p>
        </div>

        <!-- Login Card -->
        <div class="card">
            <div class="card-content" style="padding: 24px;">
                <!-- Error Banner -->
                <div id="login-error-banner" style="display: none; background-color: var(--danger-50); border: 1px solid var(--danger-500); border-radius: var(--radius-md); padding: 10px 14px; font-size: 13px; color: var(--danger-700); margin-bottom: 16px;">
                    Invalid credentials
                </div>

                <form id="login-form" onsubmit="handleLogin(event)">
                    <div class="form-group">
                        <label class="form-label" for="username">Username</label>
                        <input id="username" type="text" name="username" required autocomplete="username" autofocus class="input" placeholder="admin">
                    </div>

                    <div class="form-group">
                        <label class="form-label" for="password">Password</label>
                        <input id="password" type="password" name="password" required autocomplete="current-password" class="input" placeholder="••••••••">
                    </div>

                    <div style="margin-top: 24px;">
                        <button id="login-submit-btn" type="submit" class="btn btn-primary" style="width: 100%; height: 40px;">
                            Sign In to Dashboard
                        </button>
                    </div>
                </form>
            </div>
            <div class="card-footer" style="justify-content: center; font-size: 12px; color: var(--slate-400);">
                Default dev seed: <code>admin</code> / <code>admin123</code>
            </div>
        </div>
    </div>

    <script>
    async function handleLogin(e) {
        e.preventDefault();
        const btn = document.getElementById('login-submit-btn');
        const errBanner = document.getElementById('login-error-banner');
        
        btn.disabled = true;
        btn.textContent = 'Verifying credentials...';
        errBanner.style.display = 'none';

        const username = document.getElementById('username').value.trim();
        const password = document.getElementById('password').value.trim();

        try {
            const res = await fetch('/api/admin/login', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ username, password })
            });
            const json = await res.json();

            if (json.success && json.redirect) {
                window.location.href = json.redirect;
            } else {
                errBanner.textContent = json.error || 'Invalid credentials';
                errBanner.style.display = 'block';
                btn.disabled = false;
                btn.textContent = 'Sign In to Dashboard';
            }
        } catch (err) {
            errBanner.textContent = 'Network error during login';
            errBanner.style.display = 'block';
            btn.disabled = false;
            btn.textContent = 'Sign In to Dashboard';
        }
    }
    </script>
</body>
</html>
