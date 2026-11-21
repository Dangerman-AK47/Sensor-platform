<?php
declare(strict_types=1);
?>

<div class="card">
    <div class="card-header">
        <div>
            <h2 class="card-title" style="font-size: 18px;">Sensor Telemetry Upload Portal</h2>
            <p class="card-desc">Ingest sensor readings and log archives using Bearer authentication</p>
        </div>
    </div>

    <div class="card-content" style="padding: 24px;">
        <form id="public-upload-form" onsubmit="handlePublicUpload(event)">
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="device_id">Device ID (e.g. SENSOR-001)</label>
                    <input id="device_id" type="text" required placeholder="SENSOR-001" class="input font-mono" style="text-transform: uppercase;">
                </div>
                <div class="form-group" style="margin-bottom: 0;">
                    <label class="form-label" for="bearer_token">Sensor Bearer Token</label>
                    <input id="bearer_token" type="password" required placeholder="Paste 64-char hex Bearer token" class="input font-mono">
                </div>
            </div>

            <!-- Drag & Drop Zone -->
            <div id="drop-zone" class="dropzone" onclick="document.getElementById('file-input').click()">
                <svg width="40" height="40" viewBox="0 0 24 24" fill="none" stroke="#3563E9" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="margin: 0 auto 12px auto; display: block;">
                    <path d="M4 14.899A7 7 0 1 1 15.71 8h1.79a4.5 4.5 0 0 1 2.5 8.242"/><path d="M12 12v9"/><path d="m16 16-4-4-4 4"/>
                </svg>
                <div style="font-weight: 600; color: var(--slate-900); font-size: 15px;">Click to select file, or drag and drop here</div>
                <div style="font-size: 12.5px; color: var(--slate-500); margin-top: 4px;">CSV, JSON, Binary telemetry, or sensor logs (Max 100MB)</div>
                <input id="file-input" type="file" required style="display: none;" onchange="handleFileSelect(this)">
                <div id="selected-file-label" style="display: none; margin-top: 14px; font-weight: 600; color: var(--accent-500); font-size: 13.5px;"></div>
            </div>

            <!-- Progress Bar -->
            <div id="upload-progress-container" style="display: none; margin-top: 20px;">
                <div style="display: flex; justify-content: space-between; font-size: 12px; margin-bottom: 6px;">
                    <span id="upload-progress-status">Uploading...</span>
                    <span id="upload-progress-percent">0%</span>
                </div>
                <div style="height: 8px; background: var(--slate-100); border-radius: var(--radius-full); overflow: hidden;">
                    <div id="upload-progress-bar" style="width: 0%; height: 100%; background: var(--accent-500); transition: width 0.2s ease;"></div>
                </div>
            </div>

            <div style="margin-top: 24px; display: flex; justify-content: flex-end;">
                <button id="upload-submit-btn" type="submit" class="btn btn-primary btn-lg">
                    Submit Telemetry File
                </button>
            </div>
        </form>

        <!-- Success Receipt Panel -->
        <div id="upload-receipt-panel" style="display: none; margin-top: 24px; padding: 20px; background: var(--success-50); border: 1px solid var(--success-500); border-radius: var(--radius-lg);">
            <div style="display: flex; align-items: center; gap: 10px; margin-bottom: 12px;">
                <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="#16A34A" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg>
                <h4 style="font-size: 15px; font-weight: 600; color: var(--success-700);">Upload Ingested Successfully!</h4>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; font-size: 13px;">
                <div><span style="color: var(--slate-500);">Upload ID:</span> <strong id="rcpt-id" class="font-mono"></strong></div>
                <div><span style="color: var(--slate-500);">Filename:</span> <strong id="rcpt-name"></strong></div>
                <div><span style="color: var(--slate-500);">Size:</span> <strong id="rcpt-size"></strong></div>
                <div><span style="color: var(--slate-500);">Ingested At:</span> <strong id="rcpt-time"></strong></div>
                <div style="grid-column: 1 / -1;"><span style="color: var(--slate-500);">SHA-256 Hash:</span> <strong id="rcpt-hash" class="font-mono" style="font-size: 11px; word-break: break-all;"></strong></div>
            </div>
        </div>
    </div>
</div>

<script>
const dropZone = document.getElementById('drop-zone');
const fileInput = document.getElementById('file-input');
const selectedLabel = document.getElementById('selected-file-label');

['dragenter', 'dragover'].forEach(name => {
    dropZone.addEventListener(name, (e) => { e.preventDefault(); dropZone.classList.add('dragover'); });
});
['dragleave', 'drop'].forEach(name => {
    dropZone.addEventListener(name, (e) => { e.preventDefault(); dropZone.classList.remove('dragover'); });
});
dropZone.addEventListener('drop', (e) => {
    if (e.dataTransfer.files.length) {
        fileInput.files = e.dataTransfer.files;
        handleFileSelect(fileInput);
    }
});

function handleFileSelect(input) {
    if (input.files.length) {
        selectedLabel.textContent = `Selected: ${input.files[0].name} (${(input.files[0].size / 1024).toFixed(1)} KB)`;
        selectedLabel.style.display = 'block';
    }
}

async function handlePublicUpload(e) {
    e.preventDefault();
    const btn = document.getElementById('upload-submit-btn');
    const deviceId = document.getElementById('device_id').value.trim().toUpperCase();
    const token = document.getElementById('bearer_token').value.trim();
    const file = fileInput.files[0];

    if (!file) {
        window.showToast('Please select a file to upload', 'error');
        return;
    }

    btn.disabled = true;
    document.getElementById('upload-progress-container').style.display = 'block';
    document.getElementById('upload-progress-bar').style.width = '20%';
    document.getElementById('upload-progress-percent').textContent = '20%';

    const formData = new FormData();
    formData.append('file', file);
    formData.append('deviceId', deviceId);

    try {
        const xhr = new XMLHttpRequest();
        xhr.open('POST', '/api/v1/sensors/upload', true);
        xhr.setRequestHeader('Authorization', `Bearer ${token}`);
        xhr.setRequestHeader('X-Sensor-Id', deviceId);

        xhr.upload.onprogress = (evt) => {
            if (evt.lengthComputable) {
                const percent = Math.round((evt.loaded / evt.total) * 100);
                document.getElementById('upload-progress-bar').style.width = `${percent}%`;
                document.getElementById('upload-progress-percent').textContent = `${percent}%`;
            }
        };

        xhr.onload = () => {
            btn.disabled = false;
            try {
                const json = JSON.parse(xhr.responseText);
                if (xhr.status === 201 && json.success) {
                    document.getElementById('rcpt-id').textContent = json.uploadId;
                    document.getElementById('rcpt-name').textContent = json.fileName;
                    document.getElementById('rcpt-size').textContent = (json.fileSize / 1024).toFixed(1) + ' KB';
                    document.getElementById('rcpt-time').textContent = new Date().toLocaleString();
                    document.getElementById('rcpt-hash').textContent = json.checksum;
                    document.getElementById('upload-receipt-panel').style.display = 'block';
                    document.getElementById('upload-progress-container').style.display = 'none';
                    window.showToast('File successfully uploaded!', 'success');
                } else {
                    window.showToast(json.error || 'Upload rejected by server', 'error');
                    document.getElementById('upload-progress-container').style.display = 'none';
                }
            } catch (err) {
                window.showToast('Invalid server response', 'error');
                document.getElementById('upload-progress-container').style.display = 'none';
            }
        };

        xhr.onerror = () => {
            btn.disabled = false;
            window.showToast('Network error during upload', 'error');
            document.getElementById('upload-progress-container').style.display = 'none';
        };

        xhr.send(formData);
    } catch (err) {
        btn.disabled = false;
        window.showToast('Upload error', 'error');
        document.getElementById('upload-progress-container').style.display = 'none';
    }
}
</script>
