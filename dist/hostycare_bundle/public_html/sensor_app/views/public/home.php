<?php
declare(strict_types=1);
?>

<div style="margin-bottom: 32px; text-align: center;">
    <div style="display: inline-flex; align-items: center; justify-content: center; width: 56px; height: 56px; background-color: var(--navy-900); border-radius: var(--radius-lg); margin-bottom: 16px;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="#3563E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
            <path d="m9 12 2 2 4-4"></path>
        </svg>
    </div>
    <h1 style="font-size: 26px; font-weight: 700; color: var(--slate-900); letter-spacing: -0.02em;">Sensor Platform & DeviceVault</h1>
    <p style="font-size: 15px; color: var(--slate-500); max-width: 580px; margin: 8px auto 0 auto;">
        Centralized IoT sensor identity, heartbeat tracking, OTA configuration distribution, and telemetry file ingestion platform.
    </p>

    <!-- Quick Navigation Links -->
    <div style="display: flex; gap: 12px; justify-content: center; margin-top: 20px; flex-wrap: wrap;">
        <a href="/admin/dashboard" class="btn btn-primary btn-md">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect width="7" height="9" x="3" y="3" rx="1"/><rect width="7" height="5" x="14" y="3" rx="1"/><rect width="7" height="9" x="14" y="12" rx="1"/><rect width="7" height="5" x="3" y="16" rx="1"/></svg>
            Open Admin Dashboard
        </a>
        <a href="/upload" class="btn btn-secondary btn-md">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" x2="12" y1="3" y2="15"/></svg>
            Public File Upload Portal
        </a>
    </div>
</div>

<!-- ============================================================= -->
<!-- MANUAL DEVICE REGISTRATION TESTING CONSOLE -->
<!-- ============================================================= -->
<div class="card" style="margin-bottom: 24px; border-top: 4px solid var(--accent-500);">
    <div class="card-header">
        <div>
            <h2 class="card-title" style="font-size: 17px; display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#3563E9" stroke-width="2"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" x2="19" y1="8" y2="14"/><line x1="22" x2="16" y1="11" y2="11"/></svg>
                Manual ESP32 Device Registration Testing Tool
            </h2>
            <p class="card-desc">Simulate an ESP32 microcontroller submitting a new self-registration request (<code>POST /api/v1/sensors/register</code>)</p>
        </div>
        <span class="badge badge-accent">Live API Test</span>
    </div>

    <div class="card-content">
        <form id="manual-reg-form" onsubmit="handleManualRegistration(event)">
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 16px;">
                <div class="form-group">
                    <label class="form-label" for="reg_device_id">Device ID *</label>
                    <input id="reg_device_id" type="text" required placeholder="e.g. SENSOR-901" value="SENSOR-TEST-99" class="input font-mono" style="text-transform: uppercase;">
                    <span style="font-size: 11px; color: var(--slate-400); margin-top: 2px;">Stored in UPPERCASE</span>
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_hardware">Hardware Model</label>
                    <input id="reg_hardware" type="text" placeholder="e.g. ESP32-S3-WROOM" value="ESP32-S3-DevKit" class="input">
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_firmware">Firmware Version</label>
                    <input id="reg_firmware" type="text" placeholder="e.g. 2.1.0" value="2.1.0" class="input font-mono">
                </div>

                <div class="form-group">
                    <label class="form-label" for="reg_sensor_ver">Sensor Config Version</label>
                    <input id="reg_sensor_ver" type="text" placeholder="e.g. 1.0.0" value="1.0.0" class="input font-mono">
                </div>
            </div>

            <div class="form-group">
                <label class="form-label" for="reg_location">Deployment Location / Metadata (Optional)</label>
                <input id="reg_location" type="text" placeholder="e.g. Greenhouse 2, Lab Bench Beta" value="Testing Lab Bench" class="input">
            </div>

            <div style="display: flex; justify-content: flex-end; gap: 10px; margin-top: 8px;">
                <button id="reg-submit-btn" type="submit" class="btn btn-primary btn-md">
                    Submit Registration Request &rarr;
                </button>
            </div>
        </form>

        <!-- Live Registration Feedback Box -->
        <div id="reg-result-box" style="display: none; margin-top: 20px; border-radius: var(--radius-md); padding: 16px;">
            <div style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px;">
                <h4 id="reg-result-title" style="font-size: 14px; font-weight: 600;"></h4>
                <span id="reg-result-badge" class="badge"></span>
            </div>
            <p id="reg-result-message" style="font-size: 13px; margin-bottom: 12px;"></p>
            <div id="reg-result-details" style="font-size: 12px; background: rgba(0,0,0,0.04); border-radius: 6px; padding: 10px; font-family: monospace;"></div>
            <div id="reg-result-action" style="margin-top: 14px;"></div>
        </div>
    </div>
</div>

<!-- ============================================================= -->
<!-- QUICK TESTING TOOLS (Heartbeat & Version Check) -->
<!-- ============================================================= -->
<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 20px;">
    <!-- Heartbeat Tester -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Heartbeat Ping Tester</h3>
                <p class="card-desc">Test <code>POST /api/v1/sensors/heartbeat</code></p>
            </div>
        </div>
        <div class="card-content">
            <form onsubmit="handleHeartbeatTest(event)">
                <div class="form-group">
                    <label class="form-label">Device ID:</label>
                    <input id="hb-device-id" type="text" required value="SENSOR-001" class="input font-mono">
                </div>
                <div class="form-group">
                    <label class="form-label">Bearer Token:</label>
                    <input id="hb-token" type="text" required value="a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef" class="input font-mono" style="font-size: 11px;">
                </div>
                <button id="hb-btn" type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">Send Heartbeat Ping</button>
            </form>
            <div id="hb-result" style="display: none; margin-top: 12px; font-size: 12px; font-family: monospace; padding: 10px; border-radius: 6px; background: var(--slate-100);"></div>
        </div>
    </div>

    <!-- Version Check Tester -->
    <div class="card">
        <div class="card-header">
            <div>
                <h3 class="card-title">Version Check Tester</h3>
                <p class="card-desc">Test <code>POST /api/v1/sensors/version-check</code></p>
            </div>
        </div>
        <div class="card-content">
            <form onsubmit="handleVersionCheckTest(event)">
                <div class="form-group">
                    <label class="form-label">Device ID:</label>
                    <input id="vc-device-id" type="text" required value="SENSOR-001" class="input font-mono">
                </div>
                <div class="form-group">
                    <label class="form-label">Current Version on Sensor:</label>
                    <input id="vc-version" type="text" required value="1.0.0" class="input font-mono">
                </div>
                <div class="form-group">
                    <label class="form-label">Bearer Token:</label>
                    <input id="vc-token" type="text" required value="a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef" class="input font-mono" style="font-size: 11px;">
                </div>
                <button id="vc-btn" type="submit" class="btn btn-secondary btn-sm" style="width: 100%;">Check Configuration Version</button>
            </form>
            <div id="vc-result" style="display: none; margin-top: 12px; font-size: 12px; font-family: monospace; padding: 10px; border-radius: 6px; background: var(--slate-100);"></div>
        </div>
    </div>
</div>

<script>
async function handleManualRegistration(e) {
    e.preventDefault();
    const btn = document.getElementById('reg-submit-btn');
    const resultBox = document.getElementById('reg-result-box');
    const resultTitle = document.getElementById('reg-result-title');
    const resultBadge = document.getElementById('reg-result-badge');
    const resultMsg = document.getElementById('reg-result-message');
    const resultDetails = document.getElementById('reg-result-details');
    const resultAction = document.getElementById('reg-result-action');

    btn.disabled = true;
    btn.textContent = 'Submitting Request...';

    const deviceId = document.getElementById('reg_device_id').value.trim().toUpperCase();
    const hardwareModel = document.getElementById('reg_hardware').value.trim();
    const firmwareVersion = document.getElementById('reg_firmware').value.trim();
    const sensorVersion = document.getElementById('reg_sensor_ver').value.trim();
    const location = document.getElementById('reg_location').value.trim();

    const payload = {
        deviceId,
        hardwareModel,
        firmwareVersion,
        sensorVersion,
        metadata: { location, registered_via: 'web_testing_console' }
    };

    try {
        const res = await fetch('/api/v1/sensors/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        const json = await res.json();

        resultBox.style.display = 'block';

        if (res.status === 201 && (json.status === 'AUTO_APPROVED' || json.token)) {
            resultBox.style.backgroundColor = 'var(--success-50)';
            resultBox.style.border = '1px solid var(--success-500)';
            resultTitle.style.color = 'var(--success-700)';
            resultTitle.textContent = 'Device Registered & Auto-Approved!';
            resultBadge.className = 'badge badge-success';
            resultBadge.textContent = 'STATUS: AUTO-APPROVED';
            resultMsg.textContent = json.message || 'Your device has been immediately registered and authorized with a Bearer Token.';
            resultDetails.innerHTML = `
                <div style="margin-bottom: 8px;"><strong>Device ID:</strong> <span class="font-mono">${escapeHtml(json.deviceId)}</span></div>
                <div><strong>Issued Bearer Token:</strong></div>
                <div style="display: flex; gap: 8px; margin-top: 4px;">
                    <input id="auto-token-input" type="text" readonly class="input font-mono" style="font-size: 11.5px; background: #fff;" value="${escapeHtml(json.token)}">
                    <button type="button" onclick="navigator.clipboard.writeText('${json.token}'); window.showToast('Token copied to clipboard!', 'success');" class="btn btn-primary btn-sm">Copy</button>
                </div>
            `;
            resultAction.innerHTML = `
                <div style="display: flex; gap: 10px; flex-wrap: wrap;">
                    <a href="/admin/sensors/${encodeURIComponent(json.deviceId)}" class="btn btn-secondary btn-sm">View Sensor in Dashboard &rarr;</a>
                    <button type="button" onclick="prefillTestForms('${json.deviceId}', '${json.token}')" class="btn btn-primary btn-sm">Auto-Fill Ping & Version Testers Below &darr;</button>
                </div>
            `;
            window.showToast('Device auto-approved with Bearer token!', 'success');
        } else if (res.status === 201 && json.status === 'PENDING') {
            resultBox.style.backgroundColor = 'var(--warning-50)';
            resultBox.style.border = '1px solid var(--warning-500)';
            resultTitle.style.color = 'var(--warning-700)';
            resultTitle.textContent = 'Registration Request Queued!';
            resultBadge.className = 'badge badge-warning';
            resultBadge.textContent = 'STATUS: PENDING';
            resultMsg.textContent = json.message || 'Request successfully recorded in database.';
            resultDetails.textContent = JSON.stringify(json, null, 2);
            resultAction.innerHTML = `
                <a href="/admin/history" class="btn btn-primary btn-sm">
                    Open Registration History in Admin Dashboard to Approve &rarr;
                </a>
            `;
            window.showToast('Registration request queued for approval', 'warning');
        } else if (res.status === 409) {
            resultBox.style.backgroundColor = 'var(--danger-50)';
            resultBox.style.border = '1px solid var(--danger-500)';
            resultTitle.style.color = 'var(--danger-700)';
            resultTitle.textContent = 'Device Conflict (409)';
            resultBadge.className = 'badge badge-danger';
            resultBadge.textContent = 'ALREADY ACTIVE';
            resultMsg.textContent = json.error || 'This device is already registered and active in the database.';
            resultDetails.textContent = JSON.stringify(json, null, 2);
            resultAction.innerHTML = `<a href="/admin/sensors" class="btn btn-secondary btn-sm">View Sensor in Dashboard</a>`;
            window.showToast('Device is already active', 'error');
        } else {
            resultBox.style.backgroundColor = 'var(--danger-50)';
            resultBox.style.border = '1px solid var(--danger-500)';
            resultTitle.style.color = 'var(--danger-700)';
            resultTitle.textContent = 'Error Submitting Request';
            resultBadge.className = 'badge badge-danger';
            resultBadge.textContent = 'ERROR ' + res.status;
            resultMsg.textContent = json.error || 'An unexpected error occurred.';
            resultDetails.textContent = JSON.stringify(json, null, 2);
            resultAction.innerHTML = '';
        }
    } catch (err) {
        resultBox.style.display = 'block';
        resultBox.style.backgroundColor = 'var(--danger-50)';
        resultBox.style.border = '1px solid var(--danger-500)';
        resultTitle.textContent = 'Network Error';
        resultBadge.textContent = 'FAILED';
        resultMsg.textContent = 'Could not communicate with the local server.';
        resultDetails.textContent = err.message;
    } finally {
        btn.disabled = false;
        btn.textContent = 'Submit Registration Request →';
    }
}

async function handleHeartbeatTest(e) {
    e.preventDefault();
    const btn = document.getElementById('hb-btn');
    const resBox = document.getElementById('hb-result');
    const deviceId = document.getElementById('hb-device-id').value.trim();
    const token = document.getElementById('hb-token').value.trim();

    btn.disabled = true;
    resBox.style.display = 'block';
    resBox.textContent = 'Sending ping...';

    try {
        const res = await fetch('/api/v1/sensors/heartbeat', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-Sensor-Id': deviceId
            },
            body: JSON.stringify({ deviceId, sensorVersion: '1.0.0' })
        });
        const json = await res.json();
        resBox.textContent = `HTTP ${res.status}\n` + JSON.stringify(json, null, 2);
        if (res.ok) window.showToast('Heartbeat ping OK!', 'success');
        else window.showToast('Heartbeat rejected', 'error');
    } catch (err) {
        resBox.textContent = 'Error: ' + err.message;
    } finally {
        btn.disabled = false;
    }
}

async function handleVersionCheckTest(e) {
    e.preventDefault();
    const btn = document.getElementById('vc-btn');
    const resBox = document.getElementById('vc-result');
    const deviceId = document.getElementById('vc-device-id').value.trim();
    const version = document.getElementById('vc-version').value.trim();
    const token = document.getElementById('vc-token').value.trim();

    btn.disabled = true;
    resBox.style.display = 'block';
    resBox.textContent = 'Checking version...';

    try {
        const res = await fetch('/api/v1/sensors/version-check', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`,
                'X-Sensor-Id': deviceId
            },
            body: JSON.stringify({ deviceId, currentVersion: version })
        });
        const json = await res.json();
        resBox.textContent = `HTTP ${res.status}\n` + JSON.stringify(json, null, 2);
        if (res.ok) window.showToast(`Version Result: ${json.result}`, 'info');
    } catch (err) {
        resBox.textContent = 'Error: ' + err.message;
    } finally {
        btn.disabled = false;
    }
}

function prefillTestForms(deviceId, token) {
    if (document.getElementById('hb-device-id')) document.getElementById('hb-device-id').value = deviceId;
    if (document.getElementById('hb-token')) document.getElementById('hb-token').value = token;
    if (document.getElementById('vc-device-id')) document.getElementById('vc-device-id').value = deviceId;
    if (document.getElementById('vc-token')) document.getElementById('vc-token').value = token;
    if (window.showToast) window.showToast('Test forms auto-filled with ' + deviceId, 'info');
    const target = document.getElementById('hb-device-id');
    if (target) target.scrollIntoView({ behavior: 'smooth', block: 'center' });
}
</script>
