/**
 * Sensor Platform / FileVault / DeviceVault v3 - Frontend Interactions
 * Compliant with UI Design Guide (30s auto-refresh, modals, copy tokens, toast notifications)
 */

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initAutoRefresh();
    initModals();
    initCopyButtons();
});

// -------------------------------------------------------------
// Toast Notification Engine
// -------------------------------------------------------------
window.showToast = function(message, type = 'info') {
    let container = document.getElementById('toast-container');
    if (!container) {
        container = document.createElement('div');
        container.id = 'toast-container';
        document.body.appendChild(container);
    }

    const toast = document.createElement('div');
    toast.className = `toast ${type}`;
    toast.innerHTML = `
        <div style="flex: 1; font-size: 13px; font-weight: 500;">${escapeHtml(message)}</div>
        <button onclick="this.parentElement.remove()" style="background: none; border: none; cursor: pointer; color: #94a3b8; font-size: 16px;">&times;</button>
    `;
    container.appendChild(toast);

    setTimeout(() => {
        if (toast.parentElement) {
            toast.style.transition = 'opacity 0.3s ease, transform 0.3s ease';
            toast.style.opacity = '0';
            toast.style.transform = 'translateX(100%)';
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
};

// -------------------------------------------------------------
// Sidebar & Mobile Navigation
// -------------------------------------------------------------
function initSidebar() {
    const toggleBtn = document.getElementById('mobile-menu-toggle');
    const sidebar = document.querySelector('.sidebar');
    const backdrop = document.getElementById('sidebar-backdrop');

    if (toggleBtn && sidebar && backdrop) {
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('open');
            backdrop.classList.toggle('active');
        });

        backdrop.addEventListener('click', () => {
            sidebar.classList.remove('open');
            backdrop.classList.remove('active');
        });
    }

    // Bell icon notification toast
    const bellBtn = document.getElementById('notification-bell-btn');
    if (bellBtn) {
        bellBtn.addEventListener('click', () => {
            window.showToast("You're all caught up — no new notifications.", 'info');
        });
    }
}

// -------------------------------------------------------------
// 30-Second Auto-Refresh Engine (Tab-Visibility Aware)
// -------------------------------------------------------------
let autoRefreshTimer = null;
let lastRefreshedAt = Date.now();
let inFlightRefresh = false;

function initAutoRefresh() {
    const isDashboard = window.location.pathname === '/admin/dashboard';
    if (!isDashboard) return;

    startAutoRefreshTimer();

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            const elapsed = Date.now() - lastRefreshedAt;
            if (elapsed >= 30000) {
                refreshDashboardData();
            }
            startAutoRefreshTimer();
        } else {
            if (autoRefreshTimer) {
                clearInterval(autoRefreshTimer);
                autoRefreshTimer = null;
            }
        }
    });
}

function startAutoRefreshTimer() {
    if (autoRefreshTimer) clearInterval(autoRefreshTimer);
    autoRefreshTimer = setInterval(() => {
        if (document.visibilityState === 'visible') {
            refreshDashboardData();
        }
    }, 30000);
}

async function refreshDashboardData() {
    if (inFlightRefresh) return;
    inFlightRefresh = true;

    const refreshIndicator = document.getElementById('auto-refresh-indicator');
    if (refreshIndicator) refreshIndicator.classList.add('animate-spin');

    try {
        const res = await fetch('/api/admin/sensors/stats');
        if (res.ok) {
            const json = await res.json();
            if (json.success && json.totalSensors !== undefined) {
                updateDashboardDOM(json);
                lastRefreshedAt = Date.now();
            }
        }
    } catch (e) {
        console.warn('Auto-refresh poll skipped:', e);
    } finally {
        inFlightRefresh = false;
        if (refreshIndicator) refreshIndicator.classList.remove('animate-spin');
    }
}

function updateDashboardDOM(stats) {
    // Update StatCards
    setStatValue('stat-total-sensors', stats.totalSensors);
    setStatValue('stat-active-sensors', stats.activeSensors);
    setStatValue('stat-deactive-sensors', stats.deactiveSensors);
    setStatValue('stat-pending-requests', stats.pendingRequests);
    setStatValue('stat-approved-requests', stats.approvedRequests);
    setStatValue('stat-rejected-requests', stats.rejectedRequests);
    setStatValue('stat-total-files', stats.totalFiles);
    setStatValue('stat-total-storage', formatBytes(stats.totalStorageBytes));
    setStatValue('stat-uploads-24h', stats.uploadsLast24h);

    // Update distribution bar
    const total = stats.totalSensors || 0;
    const activePercent = total > 0 ? (stats.activeSensors / total) * 100 : 0;
    const deactivePercent = total > 0 ? (stats.deactiveSensors / total) * 100 : 0;

    const activeBar = document.getElementById('dist-bar-active');
    const deactiveBar = document.getElementById('dist-bar-deactive');
    if (activeBar) activeBar.style.width = `${activePercent}%`;
    if (deactiveBar) deactiveBar.style.width = `${deactivePercent}%`;
}

function setStatValue(elementId, value) {
    const el = document.getElementById(elementId);
    if (el) el.textContent = value;
}

// -------------------------------------------------------------
// Modals & Token Reveal
// -------------------------------------------------------------
function initModals() {
    document.querySelectorAll('[data-close-modal]').forEach(btn => {
        btn.addEventListener('click', () => {
            const modal = btn.closest('.modal-overlay');
            if (modal) modal.classList.remove('active');
        });
    });
}

window.openModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.add('active');
};

window.closeModal = function(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) modal.classList.remove('active');
};

window.showTokenModal = function(deviceId, rawToken) {
    document.getElementById('modal-token-device-id').textContent = deviceId;
    document.getElementById('modal-token-input').value = rawToken;
    openModal('token-display-modal');
};

function initCopyButtons() {
    document.querySelectorAll('[data-copy-target]').forEach(btn => {
        btn.addEventListener('click', async () => {
            const targetId = btn.getAttribute('data-copy-target');
            const target = document.getElementById(targetId);
            if (target) {
                const text = target.value || target.textContent;
                try {
                    await navigator.clipboard.writeText(text);
                    window.showToast('Copied to clipboard!', 'success');
                } catch (e) {
                    target.select();
                    document.execCommand('copy');
                    window.showToast('Copied to clipboard!', 'success');
                }
            }
        });
    });
}

// -------------------------------------------------------------
// Admin Actions: Approve, Reject, Status Toggle, Configs
// -------------------------------------------------------------
window.approveRegistration = async function(requestId) {
    if (!confirm('Approve this device registration request?')) return;

    try {
        const res = await fetch(`/api/admin/history/${requestId}/approve`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        });
        const json = await res.json();
        if (json.success) {
            window.showTokenModal(json.deviceId, json.rawToken);
            // Reload page on modal close or refresh list
        } else {
            window.showToast(json.error || 'Approval failed', 'error');
        }
    } catch (e) {
        window.showToast('Network error during approval', 'error');
    }
};

window.rejectRegistration = async function(requestId) {
    const reason = prompt('Please enter the reason for rejecting this device registration:');
    if (!reason || !reason.trim()) return;

    try {
        const res = await fetch(`/api/admin/history/${requestId}/reject`, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ reason: reason.trim() })
        });
        const json = await res.json();
        if (json.success) {
            window.showToast('Registration request rejected', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Rejection failed', 'error');
        }
    } catch (e) {
        window.showToast('Network error during rejection', 'error');
    }
};

window.toggleSensorStatus = async function(deviceId, newStatus) {
    const actionName = newStatus === 'ACTIVE' ? 'Activate' : 'Deactivate';
    if (!confirm(`Are you sure you want to ${actionName} sensor ${deviceId}?`)) return;

    try {
        const res = await fetch(`/api/admin/sensors/${deviceId}/status`, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ status: newStatus })
        });
        const json = await res.json();
        if (json.success) {
            window.showToast(`Sensor status changed to ${newStatus}`, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Status update failed', 'error');
        }
    } catch (e) {
        window.showToast('Network error during status update', 'error');
    }
};

window.regenerateToken = async function(deviceId) {
    if (!confirm(`Warning: Regenerating the token for ${deviceId} will revoke the existing token. Proceed?`)) return;

    try {
        const res = await fetch(`/api/admin/sensors/${deviceId}/token`, { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            window.showTokenModal(json.deviceId, json.rawToken);
        } else {
            window.showToast(json.error || 'Token regeneration failed', 'error');
        }
    } catch (e) {
        window.showToast('Network error during token generation', 'error');
    }
};

window.activateConfig = async function(configId, version) {
    if (!confirm(`Are you sure you want to activate configuration v${version}? This will archive the currently active configuration.`)) return;

    try {
        const res = await fetch(`/api/admin/configuration/${configId}/activate`, { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            window.showToast(`Configuration v${version} activated!`, 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Activation failed', 'error');
        }
    } catch (e) {
        window.showToast('Network error during activation', 'error');
    }
};

window.deleteUploadedFile = async function(fileId, filename) {
    if (!confirm(`Permanently delete file "${filename}"? This action cannot be undone.`)) return;

    try {
        const res = await fetch(`/api/admin/files/${fileId}`, { method: 'DELETE' });
        const json = await res.json();
        if (json.success) {
            window.showToast('File deleted successfully', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Deletion failed', 'error');
        }
    } catch (e) {
        window.showToast('Network error during file deletion', 'error');
    }
};

window.deleteSensor = async function(deviceIdPk, deviceId) {
    if (!confirm(`Are you sure you want to PERMANENTLY DELETE sensor "${deviceId}" and all its ingested telemetry data?\n\nThis action cannot be undone.`)) {
        return;
    }

    try {
        const res = await fetch(`/api/admin/sensors/${encodeURIComponent(deviceIdPk)}`, {
            method: 'DELETE'
        });
        const json = await res.json();
        if (json.success) {
            window.showToast(`Sensor ${deviceId} has been permanently deleted`, 'success');
            setTimeout(() => {
                window.location.href = '/admin/sensors';
            }, 600);
        } else {
            window.showToast(json.error || 'Failed to delete sensor', 'error');
        }
    } catch (e) {
        window.showToast('Network error during sensor deletion', 'error');
    }
};

// -------------------------------------------------------------
// Utilities
// -------------------------------------------------------------
function escapeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

function formatBytes(bytes) {
    if (bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB', 'TB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}
