<?php
declare(strict_types=1);
?>

<div style="max-width: 960px; margin: 0 auto;">
    <!-- Page Header -->
    <div style="margin-bottom: 24px;">
        <h2 style="font-size: 20px; font-weight: 700; color: var(--slate-900); margin: 0 0 6px 0;">
            Settings & Credential Management
        </h2>
        <p style="font-size: 13.5px; color: var(--slate-500); margin: 0;">
            Update access credentials for administrators and the public portal unlock screen.
        </p>
    </div>

    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(420px, 1fr)); gap: 24px;">
        <!-- Card 1: Admin Credentials -->
        <div class="card">
            <div class="card-header" style="border-bottom: 1px solid var(--slate-100); padding-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #eff6ff; display: flex; align-items: center; justify-content: center; color: #2563eb;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path></svg>
                    </div>
                    <div>
                        <h3 class="card-title" style="margin: 0; font-size: 16px;">Admin Dashboard Credentials</h3>
                        <p class="card-desc" style="margin: 0; font-size: 12.5px;">Controls login at <code>/admin/login</code></p>
                    </div>
                </div>
            </div>

            <div class="card-content" style="padding: 20px;">
                <div id="admin-cred-alert" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px;"></div>

                <form onsubmit="handleAdminCredSubmit(event)">
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" for="admin_username">Admin Username</label>
                        <input 
                            type="text" 
                            id="admin_username" 
                            class="input font-mono" 
                            value="<?= htmlspecialchars($settings['admin']['username'] ?? 'admin') ?>" 
                            required
                        >
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label class="form-label" for="admin_password">New Password</label>
                        <input 
                            type="password" 
                            id="admin_password" 
                            class="input" 
                            placeholder="Leave blank to keep current password"
                        >
                        <span style="font-size: 11.5px; color: var(--slate-400); margin-top: 4px; display: block;">Min 4 characters.</span>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label" for="admin_confirm_password">Confirm New Password</label>
                        <input 
                            type="password" 
                            id="admin_confirm_password" 
                            class="input" 
                            placeholder="Re-enter new password"
                        >
                    </div>

                    <button type="submit" id="admin-save-btn" class="btn btn-primary" style="width: 100%;">
                        Save Admin Credentials
                    </button>
                </form>
            </div>
        </div>

        <!-- Card 2: Public Portal User Credentials -->
        <div class="card">
            <div class="card-header" style="border-bottom: 1px solid var(--slate-100); padding-bottom: 14px;">
                <div style="display: flex; align-items: center; gap: 10px;">
                    <div style="width: 36px; height: 36px; border-radius: 8px; background: #eef2ff; display: flex; align-items: center; justify-content: center; color: #4f46e5;">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                    </div>
                    <div>
                        <h3 class="card-title" style="margin: 0; font-size: 16px;">Homepage Portal Credentials</h3>
                        <p class="card-desc" style="margin: 0; font-size: 12.5px;">Controls unlock modal at homepage (<code>/</code>)</p>
                    </div>
                </div>
            </div>

            <div class="card-content" style="padding: 20px;">
                <div id="user-cred-alert" style="display: none; padding: 10px 14px; border-radius: 6px; font-size: 13px; margin-bottom: 16px;"></div>

                <form onsubmit="handleUserCredSubmit(event)">
                    <div style="margin-bottom: 14px;">
                        <label class="form-label" for="user_username">User Portal Username</label>
                        <input 
                            type="text" 
                            id="user_username" 
                            class="input font-mono" 
                            value="<?= htmlspecialchars($settings['portalUser']['username'] ?? 'user') ?>" 
                            required
                        >
                    </div>

                    <div style="margin-bottom: 14px;">
                        <label class="form-label" for="user_password">New Password</label>
                        <input 
                            type="password" 
                            id="user_password" 
                            class="input" 
                            placeholder="Leave blank to keep current password"
                        >
                        <span style="font-size: 11.5px; color: var(--slate-400); margin-top: 4px; display: block;">Default is <code>password</code>. Min 4 characters.</span>
                    </div>

                    <div style="margin-bottom: 20px;">
                        <label class="form-label" for="user_confirm_password">Confirm New Password</label>
                        <input 
                            type="password" 
                            id="user_confirm_password" 
                            class="input" 
                            placeholder="Re-enter new password"
                        >
                    </div>

                    <button type="submit" id="user-save-btn" class="btn btn-primary" style="width: 100%; background: #4f46e5;">
                        Save User Credentials
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Security Information Box -->
    <div style="margin-top: 24px; padding: 16px 20px; background: #ffffff; border: 1px solid var(--slate-200); border-radius: var(--radius-md); display: flex; align-items: flex-start; gap: 14px;">
        <span style="font-size: 20px;">💡</span>
        <div style="font-size: 13px; color: var(--slate-600); line-height: 1.5;">
            <strong>Immediate Security Effect:</strong> Any updated username or password is saved with standard bcrypt cryptographic hashing in the database. Changes take effect on the very next login attempt for both the admin panel and public portal. All credential updates are automatically recorded in the <a href="/admin/audit" style="color: var(--primary-600); font-weight: 500;">Audit Logs</a>.
        </div>
    </div>
</div>

<script>
async function handleAdminCredSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('admin-save-btn');
    const alertBox = document.getElementById('admin-cred-alert');
    const username = document.getElementById('admin_username').value.trim();
    const password = document.getElementById('admin_password').value;
    const confirm = document.getElementById('admin_confirm_password').value;

    alertBox.style.display = 'none';

    if (password && password !== confirm) {
        showAlert(alertBox, 'error', 'New admin password and confirmation do not match.');
        return;
    }

    if (password && password.length < 4) {
        showAlert(alertBox, 'error', 'Admin password must be at least 4 characters.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const res = await fetch('/api/admin/settings/admin-credentials', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (res.ok && data.success) {
            showAlert(alertBox, 'success', data.message || 'Admin credentials updated successfully!');
            document.getElementById('admin_password').value = '';
            document.getElementById('admin_confirm_password').value = '';
            if (window.showToast) window.showToast('Admin credentials saved!', 'success');
        } else {
            showAlert(alertBox, 'error', data.error || 'Failed to update admin credentials.');
        }
    } catch (err) {
        showAlert(alertBox, 'error', 'Network error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save Admin Credentials';
    }
}

async function handleUserCredSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('user-save-btn');
    const alertBox = document.getElementById('user-cred-alert');
    const username = document.getElementById('user_username').value.trim();
    const password = document.getElementById('user_password').value;
    const confirm = document.getElementById('user_confirm_password').value;

    alertBox.style.display = 'none';

    if (password && password !== confirm) {
        showAlert(alertBox, 'error', 'New user password and confirmation do not match.');
        return;
    }

    if (password && password.length < 4) {
        showAlert(alertBox, 'error', 'User password must be at least 4 characters.');
        return;
    }

    btn.disabled = true;
    btn.textContent = 'Saving...';

    try {
        const res = await fetch('/api/admin/settings/user-credentials', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ username, password })
        });
        const data = await res.json();

        if (res.ok && data.success) {
            showAlert(alertBox, 'success', data.message || 'User credentials updated successfully!');
            document.getElementById('user_password').value = '';
            document.getElementById('user_confirm_password').value = '';
            if (window.showToast) window.showToast('Portal user credentials saved!', 'success');
        } else {
            showAlert(alertBox, 'error', data.error || 'Failed to update user credentials.');
        }
    } catch (err) {
        showAlert(alertBox, 'error', 'Network error: ' + err.message);
    } finally {
        btn.disabled = false;
        btn.textContent = 'Save User Credentials';
    }
}

function showAlert(el, type, msg) {
    el.style.display = 'block';
    if (type === 'success') {
        el.style.background = 'var(--success-50, #f0fdf4)';
        el.style.border = '1px solid var(--success-500, #22c55e)';
        el.style.color = 'var(--success-700, #15803d)';
    } else {
        el.style.background = 'var(--danger-50, #fef2f2)';
        el.style.border = '1px solid var(--danger-500, #ef4444)';
        el.style.color = 'var(--danger-700, #b91c1c)';
    }
    el.innerHTML = msg;
}
</script>
