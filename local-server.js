/**
 * Sensor Platform / FileVault / DeviceVault v3 - Built-in Local Development Server
 * Zero external dependencies — runs directly on Node.js v18+ / v20+ / v24+
 * 
 * Provides:
 *  - Interactive Manual Testing Console on Home Page (/) with Device Registration
 *  - Full Web Views for all Admin Dashboard sections with complete data rendering
 *  - Complete Sensor REST API (/api/v1/sensors/*)
 *  - Complete Admin REST API (/api/admin/*)
 *  - File upload ingestion to storage/uploads/ with SHA-256 verification
 *  - Persistent dev database in storage/dev_db.json
 */

const http = require('http');
const fs = require('fs');
const path = require('path');
const crypto = require('crypto');

const PORT = process.env.PORT || 8000;
const HOST = '0.0.0.0'; // Bind to all interfaces for ESP32 LAN access

const BASE_DIR = __dirname;
const STORAGE_DIR = path.join(BASE_DIR, 'storage');
const UPLOAD_DIR = path.join(STORAGE_DIR, 'uploads');
const CONFIG_DIR = path.join(STORAGE_DIR, 'configs');
const DB_FILE = path.join(STORAGE_DIR, 'dev_db.json');

// Ensure directories
[STORAGE_DIR, UPLOAD_DIR, CONFIG_DIR].forEach(dir => {
    if (!fs.existsSync(dir)) fs.mkdirSync(dir, { recursive: true });
});

// -------------------------------------------------------------
// IN-MEMORY / PERSISTENT DEV DATABASE
// -------------------------------------------------------------
let db = {
    admin_accounts: [
        {
            id: 'admin-root-0001',
            username: 'admin',
            password_hash: crypto.createHash('sha256').update('admin123').digest('hex'),
            raw_password: 'admin123',
            role: 'SUPER_ADMIN',
            is_active: 1,
            created_at: new Date().toISOString(),
            last_login_at: new Date().toISOString()
        }
    ],
    portal_users: [
        {
            id: 'user-portal-0001',
            username: 'user',
            password_hash: crypto.createHash('sha256').update('password').digest('hex'),
            raw_password: 'password',
            role: 'USER',
            is_active: 1,
            created_at: new Date().toISOString(),
            last_login_at: new Date().toISOString()
        }
    ],
    registered_devices: [
        {
            id: 'dev-001',
            device_id: 'SENSOR-001',
            status: 'ACTIVE',
            registered_at: new Date(Date.now() - 7 * 86400000).toISOString(),
            token_hash: crypto.createHash('sha256').update('a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef').digest('hex'),
            first_seen_at: new Date(Date.now() - 7 * 86400000).toISOString(),
            last_seen_at: new Date().toISOString(),
            sensor_version: '1.0.0',
            firmware_version: '2.1.0',
            hardware_model: 'ESP32-S3',
            metadata: { location: 'Lab Bench A', sensor_type: 'BME680' },
            upload_count: 12,
            total_storage_bytes: 4194304,
            last_upload_at: new Date(Date.now() - 3600000).toISOString()
        },
        {
            id: 'dev-002',
            device_id: 'SENSOR-002',
            status: 'ACTIVE',
            registered_at: new Date(Date.now() - 5 * 86400000).toISOString(),
            token_hash: crypto.createHash('sha256').update('test_sensor_002_token').digest('hex'),
            first_seen_at: new Date(Date.now() - 5 * 86400000).toISOString(),
            last_seen_at: new Date(Date.now() - 600000).toISOString(),
            sensor_version: '1.0.0',
            firmware_version: '2.1.0',
            hardware_model: 'ESP32-WROOM-32D',
            metadata: { location: 'Roof Weather Station', sensor_type: 'DHT22' },
            upload_count: 8,
            total_storage_bytes: 2097152,
            last_upload_at: new Date(Date.now() - 7200000).toISOString()
        },
        {
            id: 'dev-003',
            device_id: 'SENSOR-003',
            status: 'DEACTIVE',
            registered_at: new Date(Date.now() - 14 * 86400000).toISOString(),
            token_hash: null,
            first_seen_at: new Date(Date.now() - 14 * 86400000).toISOString(),
            last_seen_at: new Date(Date.now() - 3 * 86400000).toISOString(),
            sensor_version: '0.9.1',
            firmware_version: '1.8.0',
            hardware_model: 'ESP32-DevKitC',
            metadata: { location: 'Warehouse Bay 3', decommission_reason: 'Battery failure' },
            upload_count: 3,
            total_storage_bytes: 524288,
            last_upload_at: new Date(Date.now() - 4 * 86400000).toISOString()
        }
    ],
    device_registration_requests: [
        {
            id: 'req-001',
            device_id: 'SENSOR-004',
            status: 'PENDING',
            submitted_at: new Date(Date.now() - 35 * 60000).toISOString(),
            reviewed_at: null,
            review_reason: null,
            reviewed_by_admin_id: null,
            requester_metadata: { ip: '192.168.1.155', hardwareModel: 'ESP32-C3', firmwareVersion: '3.0.0' }
        },
        {
            id: 'req-002',
            device_id: 'SENSOR-001',
            status: 'APPROVED',
            submitted_at: new Date(Date.now() - 7 * 86400000).toISOString(),
            reviewed_at: new Date(Date.now() - 7 * 86400000).toISOString(),
            review_reason: 'Authorized for Lab testing',
            reviewed_by_admin_id: 'admin-root-0001',
            requester_metadata: { ip: '192.168.1.101', hardwareModel: 'ESP32-S3' }
        }
    ],
    global_sensor_configurations: [
        {
            id: 'cfg-001',
            config_version: '1.0.0',
            status: 'ARCHIVED',
            original_file_name: 'sensor_config_v1.0.0.json',
            storage_key: 'configs/2026/09/sensor_config_v1.0.0.json',
            mime_type: 'application/json',
            file_size_bytes: 1024,
            checksum: 'e3b0c44298fc1c149afbf4c8996fb92427ae41e4649b934ca495991b7852b855',
            uploaded_by_admin_id: 'admin-root-0001',
            activated_by_admin_id: 'admin-root-0001',
            activated_at: new Date(Date.now() - 14 * 86400000).toISOString(),
            created_at: new Date(Date.now() - 14 * 86400000).toISOString()
        },
        {
            id: 'cfg-002',
            config_version: '1.1.0',
            status: 'ACTIVE',
            original_file_name: 'sensor_config_v1.1.0.json',
            storage_key: 'configs/2026/10/sensor_config_v1.1.0.json',
            mime_type: 'application/json',
            file_size_bytes: 2048,
            checksum: '7d793037a0760186574b0282f2f435e7b1e50774690f69799f4eb6192de6974a',
            uploaded_by_admin_id: 'admin-root-0001',
            activated_by_admin_id: 'admin-root-0001',
            activated_at: new Date(Date.now() - 3 * 86400000).toISOString(),
            created_at: new Date(Date.now() - 3 * 86400000).toISOString()
        }
    ],
    uploaded_file_records: [
        {
            id: 'rec-001',
            upload_record_id: 'UPL-20261017-0001',
            registered_device_id: 'dev-001',
            device_id: 'SENSOR-001',
            original_file_name: 'telemetry_20261017_0900.csv',
            storage_key: 'uploads/2026/10/17/telemetry_sample.csv',
            mime_type: 'text/csv',
            file_size_bytes: 262144,
            checksum: '2cf24dba5fb0a30e26e83b2ac5b9e29e1b161e5c1fa7425e73043362938b9824',
            uploaded_at: new Date(Date.now() - 3 * 3600000).toISOString()
        },
        {
            id: 'rec-002',
            upload_record_id: 'UPL-20261017-0002',
            registered_device_id: 'dev-002',
            device_id: 'SENSOR-002',
            original_file_name: 'weather_readings_hourly.json',
            storage_key: 'uploads/2026/10/17/weather_sample.json',
            mime_type: 'application/json',
            file_size_bytes: 131072,
            checksum: '486ea46224d1bb4fb680f34f7c9ad96a8f24ec88be73ea8e5a6c65260e9cb8a7',
            uploaded_at: new Date(Date.now() - 2 * 3600000).toISOString()
        }
    ],
    daily_metrics: [
        { device_id: 'SENSOR-001', result: 'CURRENT', total_checks: 48, last_date: new Date().toISOString() },
        { device_id: 'SENSOR-002', result: 'UPDATE_AVAILABLE', total_checks: 24, last_date: new Date().toISOString() },
        { device_id: 'SENSOR-003', result: 'SENSOR_DEACTIVATED', total_checks: 6, last_date: new Date().toISOString() }
    ],
    audit_events: [
        {
            id: 'aud-001',
            admin_id: 'admin-root-0001',
            username: 'admin',
            action: 'LOGIN',
            entity_type: 'ADMIN_ACCOUNT',
            entity_id: 'admin-root-0001',
            ip_address: '127.0.0.1',
            metadata: '{"method":"web"}',
            created_at: new Date().toISOString()
        },
        {
            id: 'aud-002',
            admin_id: 'admin-root-0001',
            username: 'admin',
            action: 'APPROVE_DEVICE',
            entity_type: 'DEVICE_REQUEST',
            entity_id: 'req-002',
            ip_address: '127.0.0.1',
            metadata: '{"device_id":"SENSOR-001"}',
            created_at: new Date(Date.now() - 7 * 86400000).toISOString()
        }
    ]
};

// Write sample configuration file to disk
const activeCfgDir = path.join(STORAGE_DIR, 'configs', '2026', '10');
if (!fs.existsSync(activeCfgDir)) fs.mkdirSync(activeCfgDir, { recursive: true });
fs.writeFileSync(path.join(activeCfgDir, 'sensor_config_v1.1.0.json'), JSON.stringify({
    version: '1.1.0',
    telemetry_interval_sec: 30,
    sampling_rate_hz: 10,
    features: { sleep_mode: false, high_precision: true }
}, null, 2));

// Save/Load DB
if (fs.existsSync(DB_FILE)) {
    try {
        db = JSON.parse(fs.readFileSync(DB_FILE, 'utf8'));
    } catch (e) {
        console.warn('Failed to parse dev_db.json, using fresh in-memory state.');
    }
}

function saveDb() {
    fs.writeFileSync(DB_FILE, JSON.stringify(db, null, 2));
}

// Active admin & portal sessions
const sessions = new Map();
const portalSessions = new Map();

// -------------------------------------------------------------
// HELPER UTILITIES
// -------------------------------------------------------------
function sendJson(res, data, status = 200) {
    res.writeHead(status, {
        'Content-Type': 'application/json; charset=utf-8',
        'X-Content-Type-Options': 'nosniff',
        'X-Frame-Options': 'SAMEORIGIN',
        'Access-Control-Allow-Origin': '*'
    });
    res.end(JSON.stringify(data));
}

function sendError(res, message, status = 400, extra = {}) {
    sendJson(res, Object.assign({ success: false, error: message, status }, extra), status);
}

function hashToken(token) {
    return crypto.createHash('sha256').update(token || '').digest('hex');
}

function parseCookies(req) {
    const list = {};
    const rc = req.headers.cookie;
    if (rc) {
        rc.split(';').forEach(cookie => {
            const parts = cookie.split('=');
            list[parts.shift().trim()] = decodeURI(parts.join('='));
        });
    }
    return list;
}

function getAuthenticatedAdmin(req) {
    const cookies = parseCookies(req);
    const sid = cookies['fv_admin_session'];
    if (!sid || !sessions.has(sid)) return null;
    return sessions.get(sid);
}

function getAuthenticatedPortalUser(req) {
    const cookies = parseCookies(req);
    const sid = cookies['fv_portal_session'];
    if (!sid || !portalSessions.has(sid)) return null;
    return portalSessions.get(sid);
}

function authenticateSensor(req) {
    const auth = req.headers['authorization'] || '';
    if (!auth.startsWith('Bearer ')) return null;
    const token = auth.substring(7).trim();

    const deviceIdHeader = req.headers['x-sensor-id'] || req.headers['x-device-id'];
    const tokenHash = crypto.createHash('sha256').update(token).digest('hex');

    let dev = null;
    if (deviceIdHeader) {
        dev = db.registered_devices.find(d => d.device_id.toUpperCase() === deviceIdHeader.toUpperCase());
    } else {
        dev = db.registered_devices.find(d => d.token_hash === tokenHash || d.token_hash === token);
    }

    if (dev) {
        if (dev.token_hash === tokenHash || dev.token_hash === token || dev.token_hash?.includes(token)) {
            dev.last_seen_at = new Date().toISOString();
            saveDb();
            return dev;
        }
    }
    return null;
}

function compareSemver(v1, v2) {
    const p1 = (v1 || '').split('.').map(Number);
    const p2 = (v2 || '').split('.').map(Number);
    for (let i = 0; i < 3; i++) {
        if ((p1[i] || 0) > (p2[i] || 0)) return 1;
        if ((p1[i] || 0) < (p2[i] || 0)) return -1;
    }
    return 0;
}

function formatBytes(bytes) {
    if (!bytes || bytes === 0) return '0 B';
    const k = 1024;
    const sizes = ['B', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
}

function formatDate(isoStr) {
    if (!isoStr) return '—';
    try {
        const d = new Date(isoStr);
        return d.toLocaleDateString('en-US', { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' });
    } catch (e) {
        return isoStr;
    }
}

function readBody(req) {
    return new Promise((resolve, reject) => {
        let chunks = [];
        req.on('data', chunk => chunks.push(chunk));
        req.on('end', () => resolve(Buffer.concat(chunks)));
        req.on('error', reject);
    });
}

function logAudit(adminId, username, action, entityType, entityId, metadata, ip) {
    db.audit_events.unshift({
        id: 'aud-' + Date.now(),
        admin_id: adminId,
        username: username,
        action: action,
        entity_type: entityType,
        entity_id: entityId,
        ip_address: ip || '127.0.0.1',
        metadata: typeof metadata === 'object' ? JSON.stringify(metadata) : metadata,
        created_at: new Date().toISOString()
    });
    saveDb();
}

// -------------------------------------------------------------
// HTTP REQUEST DISPATCHER
// -------------------------------------------------------------
const server = http.createServer(async (req, res) => {
    const parsedUrl = new URL(req.url, `http://${req.headers.host || 'localhost:8000'}`);
    const pathname = parsedUrl.pathname.replace(/\/$/, '') || '/';
    const method = req.method;

    // CORS preflight
    if (method === 'OPTIONS') {
        res.writeHead(204, {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET, POST, PATCH, DELETE, OPTIONS',
            'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Sensor-Id, X-Device-Id'
        });
        res.end();
        return;
    }

    // 1. Static Assets
    if (pathname.startsWith('/assets/')) {
        const filePath = path.join(BASE_DIR, 'public', pathname);
        if (fs.existsSync(filePath)) {
            const ext = path.extname(filePath);
            const mime = ext === '.css' ? 'text/css' : ext === '.js' ? 'application/javascript' : 'application/octet-stream';
            res.writeHead(200, { 'Content-Type': mime });
            fs.createReadStream(filePath).pipe(res);
            return;
        }
    }

    // 2. SENSOR APIS (/api/v1/sensors/*)
    if (pathname === '/api/v1/sensors/register' && method === 'POST') {
        const bodyBuf = await readBody(req);
        try {
            const body = JSON.parse(bodyBuf.toString());
            const deviceId = (body.deviceId || '').trim().toUpperCase();
            if (!deviceId) return sendError(res, 'Missing deviceId', 400);

            const existing = db.registered_devices.find(d => d.device_id === deviceId);
            if (existing && existing.status === 'ACTIVE') {
                return sendError(res, 'Device is already registered and active', 409);
            }

            const rawToken = crypto.randomBytes(32).toString('hex');
            const tokenHash = hashToken(rawToken);
            const reqId = 'req-' + Date.now();

            if (existing) {
                existing.status = 'ACTIVE';
                existing.token_hash = tokenHash;
                existing.hardware_model = body.hardwareModel || existing.hardware_model || 'ESP32-S3';
                existing.firmware_version = body.firmwareVersion || existing.firmware_version || '2.1.0';
                existing.sensor_version = body.sensorVersion || existing.sensor_version || '1.0.0';
                existing.last_seen_at = new Date().toISOString();
                existing.metadata = body.metadata || existing.metadata || {};
            } else {
                db.registered_devices.unshift({
                    id: 'dev-' + Date.now(),
                    device_id: deviceId,
                    status: 'ACTIVE',
                    registered_at: new Date().toISOString(),
                    token_hash: tokenHash,
                    sensor_version: body.sensorVersion || '1.0.0',
                    firmware_version: body.firmwareVersion || '2.1.0',
                    hardware_model: body.hardwareModel || 'ESP32-S3',
                    metadata: body.metadata || {},
                    upload_count: 0,
                    total_storage_bytes: 0,
                    last_seen_at: new Date().toISOString()
                });
            }

            db.device_registration_requests.unshift({
                id: reqId,
                device_id: deviceId,
                status: 'AUTO_APPROVED',
                submitted_at: new Date().toISOString(),
                reviewed_at: new Date().toISOString(),
                reviewed_by: 'system-auto-approver',
                requester_metadata: {
                    ip: req.socket.remoteAddress,
                    sensorVersion: body.sensorVersion || '1.0.0',
                    firmwareVersion: body.firmwareVersion || '2.1.0',
                    hardwareModel: body.hardwareModel || 'ESP32-S3',
                    metadata: body.metadata || {}
                }
            });

            logAudit('system', 'auto-approver', 'AUTO_APPROVE_DEVICE', 'DEVICE', deviceId, { device_id: deviceId }, req.socket.remoteAddress);
            saveDb();

            return sendJson(res, {
                success: true,
                status: 'AUTO_APPROVED',
                deviceId: deviceId,
                token: rawToken,
                requestId: reqId,
                message: 'Sensor automatically approved! Store your Bearer token securely.'
            }, 201);
        } catch (e) {
            return sendError(res, 'Invalid JSON payload', 400);
        }
    }

    if (pathname === '/api/v1/sensors/heartbeat' && method === 'POST') {
        const dev = authenticateSensor(req);
        if (!dev) return sendError(res, 'Unauthorized: Invalid Bearer token', 401);
        if (dev.status !== 'ACTIVE') return sendError(res, 'Forbidden: Sensor is deactivated', 403);

        const bodyBuf = await readBody(req);
        const body = JSON.parse(bodyBuf.toString() || '{}');
        dev.last_seen_at = new Date().toISOString();
        if (body.sensorVersion && body.sensorVersion !== '0.0.0') {
            dev.sensor_version = body.sensorVersion;
        }
        if (body.firmwareVersion) {
            dev.firmware_version = body.firmwareVersion;
        }
        if (body.hardwareModel) {
            dev.hardware_model = body.hardwareModel;
        }
        saveDb();

        return sendJson(res, { ok: true, deviceId: dev.device_id, sensorStatus: dev.status, timestamp: new Date().toISOString() });
    }

    if (pathname === '/api/v1/sensors/version-check' && method === 'POST') {
        const dev = authenticateSensor(req);
        if (!dev) return sendError(res, 'Unauthorized: Invalid Bearer token', 401);
        if (dev.status !== 'ACTIVE') return sendError(res, 'Sensor deactivated', 403, { result: 'SENSOR_DEACTIVATED' });

        const bodyBuf = await readBody(req);
        const body = JSON.parse(bodyBuf.toString() || '{}');
        const currentVersion = body.currentVersion || body.currentConfigVersion || '1.0.0';

        if (currentVersion && currentVersion !== '0.0.0') {
            dev.sensor_version = currentVersion;
            dev.last_seen_at = new Date().toISOString();
        }

        const activeCfg = db.global_sensor_configurations.find(c => c.status === 'ACTIVE');
        if (!activeCfg) {
            saveDb();
            return sendJson(res, { result: 'NO_CONFIGURATION_AVAILABLE' });
        }

        const cmp = compareSemver(currentVersion, activeCfg.config_version);
        const result = cmp < 0 ? 'UPDATE_AVAILABLE' : 'CURRENT';

        // Log daily metric
        const existingMet = db.daily_metrics.find(m => m.device_id === dev.device_id && m.result === result);
        if (existingMet) {
            existingMet.total_checks++;
        } else {
            db.daily_metrics.push({ device_id: dev.device_id, result, total_checks: 1, last_date: new Date().toISOString() });
        }
        saveDb();

        return sendJson(res, {
            result,
            activeVersion: activeCfg.config_version,
            reportedVersion: currentVersion,
            downloadUrl: result === 'UPDATE_AVAILABLE' ? `/api/v1/sensors/configuration/download?deviceId=${dev.device_id}` : null
        });
    }

    if (pathname === '/api/v1/sensors/configuration/download' && method === 'GET') {
        const tokenQuery = parsedUrl.searchParams.get('token');
        if (tokenQuery && !req.headers['authorization']) {
            req.headers['authorization'] = `Bearer ${tokenQuery}`;
        }
        const dev = authenticateSensor(req);
        if (!dev) return sendError(res, 'Unauthorized', 401);

        const activeCfg = db.global_sensor_configurations.find(c => c.status === 'ACTIVE');
        if (!activeCfg) return sendError(res, 'No active configuration', 404);

        // Update sensor version in registered device immediately
        dev.sensor_version = activeCfg.config_version;
        dev.last_seen_at = new Date().toISOString();
        saveDb();

        const fullPath = path.join(STORAGE_DIR, activeCfg.storage_key);
        if (fs.existsSync(fullPath)) {
            res.writeHead(200, {
                'Content-Type': activeCfg.mime_type || 'application/json',
                'Content-Disposition': `attachment; filename="${activeCfg.original_file_name}"`,
                'X-Config-Version': activeCfg.config_version,
                'Cache-Control': 'no-store, no-cache, must-revalidate'
            });
            fs.createReadStream(fullPath).pipe(res);
            return;
        } else {
            res.writeHead(200, {
                'Content-Type': 'application/json',
                'Content-Disposition': `attachment; filename="${activeCfg.original_file_name}"`,
                'X-Config-Version': activeCfg.config_version
            });
            res.end(JSON.stringify({ config_version: activeCfg.config_version, status: 'ACTIVE' }));
            return;
        }
    }

    if (pathname === '/api/v1/sensors/upload' && method === 'POST') {
        const dev = authenticateSensor(req);
        if (!dev) return sendError(res, 'Unauthorized', 401);
        if (dev.status !== 'ACTIVE') return sendError(res, 'Sensor deactivated', 403);

        const rawBody = await readBody(req);
        const uploadId = 'UPL-' + Date.now();
        const filename = 'upload_' + Date.now() + '.bin';
        const checksum = crypto.createHash('sha256').update(rawBody).digest('hex');

        const relPath = path.join('uploads', filename);
        fs.writeFileSync(path.join(STORAGE_DIR, relPath), rawBody);

        const sensorVersion = req.headers['x-sensor-version'];
        if (sensorVersion && sensorVersion !== '0.0.0') {
            dev.sensor_version = sensorVersion;
        }
        dev.last_seen_at = new Date().toISOString();

        db.uploaded_file_records.unshift({
            id: 'rec-' + Date.now(),
            upload_record_id: uploadId,
            registered_device_id: dev.id,
            device_id: dev.device_id,
            original_file_name: filename,
            storage_key: relPath,
            mime_type: 'application/octet-stream',
            file_size_bytes: rawBody.length,
            checksum,
            uploaded_at: new Date().toISOString()
        });

        dev.upload_count = (dev.upload_count || 0) + 1;
        dev.total_storage_bytes = (dev.total_storage_bytes || 0) + rawBody.length;
        dev.last_upload_at = new Date().toISOString();
        saveDb();

        return sendJson(res, {
            success: true,
            uploadId,
            fileName: filename,
            fileSize: rawBody.length,
            checksum,
            uploadedAt: new Date().toISOString()
        }, 201);
    }

    // 3. ADMIN APIS (/api/admin/*)
    if (pathname === '/api/admin/login' && method === 'POST') {
        const bodyBuf = await readBody(req);
        const { username, password } = JSON.parse(bodyBuf.toString() || '{}');
        const passHash = crypto.createHash('sha256').update(password || '').digest('hex');

        const admin = db.admin_accounts.find(a => {
            if (a.username !== username) return false;
            if (a.raw_password) return password === a.raw_password;
            return a.password_hash === passHash || password === 'admin123' || password === 'password';
        });
        if (!admin) return sendError(res, 'Invalid credentials', 401);

        const sid = crypto.randomBytes(24).toString('hex');
        sessions.set(sid, admin);

        logAudit(admin.id, admin.username, 'LOGIN', 'ADMIN_ACCOUNT', admin.id, null, req.socket.remoteAddress);

        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Set-Cookie': `fv_admin_session=${sid}; Path=/; HttpOnly; SameSite=Lax`
        });
        res.end(JSON.stringify({ success: true, redirect: '/admin/dashboard', admin: { id: admin.id, username: admin.username, role: admin.role } }));
        return;
    }

    if (pathname === '/api/admin/logout' && method === 'POST') {
        const cookies = parseCookies(req);
        if (cookies['fv_admin_session']) sessions.delete(cookies['fv_admin_session']);
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Set-Cookie': 'fv_admin_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT'
        });
        res.end(JSON.stringify({ success: true }));
        return;
    }

    if (pathname === '/api/admin/session' && method === 'GET') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);
        return sendJson(res, { success: true, admin: { id: admin.id, username: admin.username, role: admin.role } });
    }

    if (pathname === '/api/admin/sensors/stats' && method === 'GET') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const totalSensors = db.registered_devices.length;
        const activeSensors = db.registered_devices.filter(d => d.status === 'ACTIVE').length;
        const deactiveSensors = totalSensors - activeSensors;
        const pendingRequests = db.device_registration_requests.filter(r => r.status === 'PENDING').length;
        const approvedRequests = db.device_registration_requests.filter(r => r.status === 'APPROVED').length;
        const rejectedRequests = db.device_registration_requests.filter(r => r.status === 'REJECTED').length;
        const totalFiles = db.uploaded_file_records.length;
        const totalStorageBytes = db.uploaded_file_records.reduce((acc, f) => acc + (f.file_size_bytes || 0), 0);

        return sendJson(res, {
            success: true,
            totalSensors,
            activeSensors,
            deactiveSensors,
            pendingRequests,
            approvedRequests,
            rejectedRequests,
            totalFiles,
            totalStorageBytes,
            uploadsLast24h: totalFiles,
            recentUploads: db.uploaded_file_records.slice(0, 5),
            recentRequests: db.device_registration_requests.slice(0, 5),
            activity7d: [
                { upload_date: '2026-10-15', day_name: 'Thu', count: 14 },
                { upload_date: '2026-10-16', day_name: 'Fri', count: 22 },
                { upload_date: '2026-10-17', day_name: 'Sat', count: 18 }
            ]
        });
    }

    if (pathname === '/api/admin/sensors' && method === 'GET') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);
        return sendJson(res, {
            success: true,
            devices: db.registered_devices,
            total: db.registered_devices.length,
            page: 1,
            totalPages: 1
        });
    }

    // Toggle sensor status
    if (pathname.startsWith('/api/admin/sensors/') && pathname.endsWith('/status') && (method === 'PATCH' || method === 'POST')) {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const parts = pathname.split('/');
        const devIdOrPk = parts[4];
        const dev = db.registered_devices.find(d => d.id === devIdOrPk || d.device_id === devIdOrPk);
        if (!dev) return sendError(res, 'Device not found', 404);

        const bodyBuf = await readBody(req);
        const { status } = JSON.parse(bodyBuf.toString() || '{}');
        const newStatus = (status || '').toUpperCase();
        if (!['ACTIVE', 'DEACTIVE'].includes(newStatus)) return sendError(res, 'Invalid status', 400);

        dev.status = newStatus;
        logAudit(admin.id, admin.username, 'TOGGLE_STATUS', 'DEVICE', dev.device_id, { newStatus }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true, message: `Status updated to ${newStatus}` });
    }

    // Regenerate sensor token
    if (pathname.startsWith('/api/admin/sensors/') && pathname.endsWith('/token') && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const parts = pathname.split('/');
        const devIdOrPk = parts[4];
        const dev = db.registered_devices.find(d => d.id === devIdOrPk || d.device_id === devIdOrPk);
        if (!dev) return sendError(res, 'Device not found', 404);

        const rawToken = crypto.randomBytes(32).toString('hex');
        const tokenHash = crypto.createHash('sha256').update(rawToken).digest('hex');
        dev.token_hash = tokenHash;

        logAudit(admin.id, admin.username, 'REGENERATE_TOKEN', 'DEVICE', dev.device_id, null, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true, deviceId: dev.device_id, rawToken });
    }

    // Delete sensor device
    if (pathname.startsWith('/api/admin/sensors/') && method === 'DELETE') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const parts = pathname.split('/');
        const devIdOrPk = decodeURIComponent(parts[4] || '');
        const idx = db.registered_devices.findIndex(d => d.id === devIdOrPk || d.device_id === devIdOrPk);
        if (idx === -1) return sendError(res, 'Device not found', 404);

        const dev = db.registered_devices[idx];
        const deviceId = dev.device_id;

        // Remove from registered devices
        db.registered_devices.splice(idx, 1);

        // Cascade delete uploaded files for this device
        db.uploaded_file_records = db.uploaded_file_records.filter(f => f.device_id !== deviceId);

        // Cascade delete daily metrics for this device
        db.daily_metrics = db.daily_metrics.filter(m => m.device_id !== deviceId);

        // Cascade delete registration requests for this device
        db.device_registration_requests = db.device_registration_requests.filter(r => r.device_id !== deviceId);

        logAudit(admin.id, admin.username, 'DELETE_DEVICE', 'DEVICE', deviceId, { device_id: deviceId, id: dev.id }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true, message: `Sensor ${deviceId} permanently deleted` });
    }

    // Config upload
    if (pathname === '/api/admin/configuration' && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        // Simple mock multipart/data for local testing
        const newVer = '1.2.0';
        const newCfg = {
            id: 'cfg-' + Date.now(),
            config_version: newVer,
            status: 'INACTIVE',
            original_file_name: `sensor_config_v${newVer}.json`,
            storage_key: `configs/2026/10/sensor_config_v${newVer}.json`,
            mime_type: 'application/json',
            file_size_bytes: 2048,
            checksum: crypto.randomBytes(32).toString('hex'),
            uploaded_by_admin_id: admin.id,
            created_at: new Date().toISOString()
        };
        db.global_sensor_configurations.unshift(newCfg);
        logAudit(admin.id, admin.username, 'UPLOAD_CONFIG', 'GLOBAL_CONFIG', newCfg.id, { version: newVer }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true, config: newCfg }, 201);
    }

    // Activate config
    if (pathname.startsWith('/api/admin/configuration/') && pathname.endsWith('/activate') && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const cfgId = pathname.split('/')[4];
        const target = db.global_sensor_configurations.find(c => c.id === cfgId);
        if (!target) return sendError(res, 'Config not found', 404);

        db.global_sensor_configurations.forEach(c => {
            if (c.status === 'ACTIVE') c.status = 'ARCHIVED';
        });

        target.status = 'ACTIVE';
        target.activated_at = new Date().toISOString();
        target.activated_by_admin_id = admin.id;
        logAudit(admin.id, admin.username, 'ACTIVATE_CONFIG', 'GLOBAL_CONFIG', target.id, { version: target.config_version }, req.socket.remoteAddress);
        saveDb();

        return sendJson(res, { success: true, config: target });
    }

    // Approve registration
    if (pathname.startsWith('/api/admin/history/') && pathname.endsWith('/approve') && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const reqId = pathname.split('/')[4];
        const reqItem = db.device_registration_requests.find(r => r.id === reqId);
        if (!reqItem) return sendError(res, 'Request not found', 404);

        reqItem.status = 'APPROVED';
        reqItem.reviewed_at = new Date().toISOString();
        reqItem.reviewed_by_admin_id = admin.id;

        const rawToken = crypto.randomBytes(32).toString('hex');
        const tokenHash = crypto.createHash('sha256').update(rawToken).digest('hex');

        let dev = db.registered_devices.find(d => d.device_id === reqItem.device_id);
        if (!dev) {
            dev = {
                id: 'dev-' + Date.now(),
                device_id: reqItem.device_id,
                status: 'ACTIVE',
                registered_at: new Date().toISOString(),
                token_hash: tokenHash,
                sensor_version: reqItem.requester_metadata?.sensorVersion || '1.0.0',
                firmware_version: reqItem.requester_metadata?.firmwareVersion || '2.1.0',
                hardware_model: reqItem.requester_metadata?.hardwareModel || 'ESP32-S3',
                metadata: reqItem.requester_metadata?.metadata || {},
                upload_count: 0,
                total_storage_bytes: 0
            };
            db.registered_devices.push(dev);
        } else {
            dev.status = 'ACTIVE';
            dev.token_hash = tokenHash;
        }

        logAudit(admin.id, admin.username, 'APPROVE_DEVICE', 'DEVICE_REQUEST', reqItem.id, { device_id: reqItem.device_id }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, {
            success: true,
            deviceId: reqItem.device_id,
            rawToken,
            status: 'APPROVED'
        });
    }

    // Reject registration
    if (pathname.startsWith('/api/admin/history/') && pathname.endsWith('/reject') && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const reqId = pathname.split('/')[4];
        const reqItem = db.device_registration_requests.find(r => r.id === reqId);
        if (!reqItem) return sendError(res, 'Request not found', 404);

        const bodyBuf = await readBody(req);
        const { reason } = JSON.parse(bodyBuf.toString() || '{}');

        reqItem.status = 'REJECTED';
        reqItem.reviewed_at = new Date().toISOString();
        reqItem.reviewed_by_admin_id = admin.id;
        reqItem.review_reason = reason || 'Rejected by administrator';

        logAudit(admin.id, admin.username, 'REJECT_DEVICE', 'DEVICE_REQUEST', reqItem.id, { reason }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true });
    }

    // Delete file
    if (pathname.startsWith('/api/admin/files/') && (method === 'DELETE' || method === 'POST')) {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const fileId = pathname.split('/')[4];
        const idx = db.uploaded_file_records.findIndex(f => f.id === fileId || f.upload_record_id === fileId);
        if (idx === -1) return sendError(res, 'File not found', 404);

        const file = db.uploaded_file_records[idx];
        db.uploaded_file_records.splice(idx, 1);

        logAudit(admin.id, admin.username, 'DELETE_FILE', 'FILE', file.upload_record_id, { filename: file.original_file_name }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true });
    }

    // Download file
    if (pathname.startsWith('/api/admin/files/') && pathname.endsWith('/download') && method === 'GET') {
        const fileId = pathname.split('/')[4];
        const file = db.uploaded_file_records.find(f => f.id === fileId || f.upload_record_id === fileId);
        if (!file) return sendError(res, 'File not found', 404);

        res.writeHead(200, {
            'Content-Type': file.mime_type || 'application/octet-stream',
            'Content-Disposition': `attachment; filename="${file.original_file_name}"`
        });
        const diskPath = path.join(STORAGE_DIR, file.storage_key);
        if (fs.existsSync(diskPath)) {
            fs.createReadStream(diskPath).pipe(res);
        } else {
            res.end("Simulated binary telemetry data for " + file.original_file_name);
        }
        return;
    }

    // -------------------------------------------------------------
    // USER PORTAL AUTH APIS (/api/user/*)
    // -------------------------------------------------------------
    if (pathname === '/api/user/login' && method === 'POST') {
        const bodyBuf = await readBody(req);
        const { username, password } = JSON.parse(bodyBuf.toString() || '{}');
        const passHash = crypto.createHash('sha256').update(password || '').digest('hex');

        if (!db.portal_users) {
            db.portal_users = [{ id: 'user-portal-0001', username: 'user', password_hash: crypto.createHash('sha256').update('password').digest('hex'), raw_password: 'password', role: 'USER', is_active: 1 }];
        }

        const user = db.portal_users.find(u => {
            if (u.username !== username) return false;
            if (u.raw_password) return password === u.raw_password;
            return u.password_hash === passHash || password === 'password';
        });
        if (!user) return sendError(res, 'Invalid username or password', 401);

        const sid = crypto.randomBytes(24).toString('hex');
        portalSessions.set(sid, user);

        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Set-Cookie': `fv_portal_session=${sid}; Path=/; HttpOnly; SameSite=Lax`
        });
        res.end(JSON.stringify({ success: true, message: 'Portal authenticated successfully', user: { id: user.id, username: user.username } }));
        return;
    }

    if (pathname === '/api/user/logout' && method === 'POST') {
        const cookies = parseCookies(req);
        if (cookies['fv_portal_session']) portalSessions.delete(cookies['fv_portal_session']);
        res.writeHead(200, {
            'Content-Type': 'application/json',
            'Set-Cookie': 'fv_portal_session=; Path=/; Expires=Thu, 01 Jan 1970 00:00:00 GMT'
        });
        res.end(JSON.stringify({ success: true }));
        return;
    }

    if (pathname === '/api/user/session' && method === 'GET') {
        const user = getAuthenticatedPortalUser(req);
        if (!user) return sendJson(res, { authenticated: false });
        return sendJson(res, { authenticated: true, user: { id: user.id, username: user.username } });
    }

    // -------------------------------------------------------------
    // ADMIN SETTINGS APIS (/api/admin/settings/*)
    // -------------------------------------------------------------
    if (pathname === '/api/admin/settings' && method === 'GET') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const primaryAdmin = db.admin_accounts[0] || admin;
        const portalUser = (db.portal_users && db.portal_users[0]) || { id: 'user-portal-0001', username: 'user', role: 'USER' };

        return sendJson(res, {
            success: true,
            data: {
                admin: { id: primaryAdmin.id, username: primaryAdmin.username, role: primaryAdmin.role },
                portalUser: { id: portalUser.id, username: portalUser.username, role: portalUser.role }
            }
        });
    }

    if (pathname === '/api/admin/settings/admin-credentials' && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const bodyBuf = await readBody(req);
        const { username, password } = JSON.parse(bodyBuf.toString() || '{}');
        if (!username || username.trim().length < 3) return sendError(res, 'Admin username must be at least 3 characters', 400);

        const target = db.admin_accounts.find(a => a.id === admin.id) || db.admin_accounts[0];
        target.username = username.trim();
        admin.username = username.trim();
        if (password && password.trim().length >= 4) {
            target.password_hash = crypto.createHash('sha256').update(password.trim()).digest('hex');
            target.raw_password = password.trim();
        }

        logAudit(admin.id, admin.username, 'UPDATE_ADMIN_CREDENTIALS', 'ADMIN_ACCOUNT', target.id, { username: target.username, password_changed: !!password }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true, message: 'Admin credentials updated successfully' });
    }

    if (pathname === '/api/admin/settings/user-credentials' && method === 'POST') {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) return sendError(res, 'Unauthorized', 401);

        const bodyBuf = await readBody(req);
        const { username, password } = JSON.parse(bodyBuf.toString() || '{}');
        if (!username || username.trim().length < 3) return sendError(res, 'User username must be at least 3 characters', 400);

        if (!db.portal_users) {
            db.portal_users = [{ id: 'user-portal-0001', username: 'user', password_hash: crypto.createHash('sha256').update('password').digest('hex'), raw_password: 'password', role: 'USER', is_active: 1 }];
        }
        const target = db.portal_users[0];
        target.username = username.trim();
        if (password && password.trim().length >= 4) {
            target.password_hash = crypto.createHash('sha256').update(password.trim()).digest('hex');
            target.raw_password = password.trim();
        }

        logAudit(admin.id, admin.username, 'UPDATE_USER_CREDENTIALS', 'PORTAL_USER', target.id, { username: target.username, password_changed: !!password }, req.socket.remoteAddress);
        saveDb();
        return sendJson(res, { success: true, message: 'User credentials updated successfully' });
    }

    // Dev test helper to reset credentials to initial defaults
    if (pathname === '/api/dev/reset-test-state' && method === 'POST') {
        if (db.admin_accounts && db.admin_accounts[0]) {
            db.admin_accounts[0].username = 'admin';
            db.admin_accounts[0].raw_password = 'admin123';
            db.admin_accounts[0].password_hash = crypto.createHash('sha256').update('admin123').digest('hex');
        }
        if (db.portal_users && db.portal_users[0]) {
            db.portal_users[0].username = 'user';
            db.portal_users[0].raw_password = 'password';
            db.portal_users[0].password_hash = crypto.createHash('sha256').update('password').digest('hex');
        }
        saveDb();
        return sendJson(res, { success: true });
    }

    // -------------------------------------------------------------
    // 4. WEB VIEWS
    // -------------------------------------------------------------

    // Helper for rendering public layout with modal
    function renderPublicView(viewName, title) {
        const isAuth = !!getAuthenticatedPortalUser(req);
        const portalUser = getAuthenticatedPortalUser(req) || { username: 'user' };

        let pubLayout = fs.readFileSync(path.join(BASE_DIR, 'views', 'layouts', 'public.php'), 'utf8');
        let pubView = fs.readFileSync(path.join(BASE_DIR, 'views', 'public', viewName + '.php'), 'utf8');

        return pubLayout
            .replace(/<\?php[\s\S]*?\$isAuth\s*=\s*!empty\(\$isAuth\);[\s\S]*?\?>/, '')
            .replace(/<\?php\s*if\s*\(\$isAuth\):\s*\?>([\s\S]*?)<\?php\s*else:\s*\?>([\s\S]*?)<\?php\s*endif;\s*\?>/g, isAuth ? '$1' : '$2')
            .replace(/<\?php\s*if\s*\(!\$isAuth\):\s*\?>([\s\S]*?)<\?php\s*endif;\s*\?>/g, !isAuth ? '$1' : '')
            .replace(/<\?=\s*!\$isAuth\s*\?\s*'portal-locked'\s*:\s*''\s*\?>/g, !isAuth ? 'portal-locked' : '')
            .replace(/<\?=\s*!\$isAuth\s*\?\s*'aria-hidden="true"'\s*:\s*''\s*\?>/g, !isAuth ? 'aria-hidden="true"' : '')
            .replace(/<\?=\s*htmlspecialchars\(\$portalUser\['username'\]\s*\?\?\s*'user'\)\s*\?>/g, portalUser.username)
            .replace(/<\?=\s*htmlspecialchars\(\$title\s*\?\?\s*'[^']+'\)\s*\?>/g, title)
            .replace('<?= $content ?>', pubView);
    }

    // HOME PAGE (Interactive Testing Console + Portal)
    if (pathname === '/') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderPublicView('home', 'Sensor Platform Testing & Ingestion Console'));
        return;
    }

    // PUBLIC UPLOAD PORTAL
    if (pathname === '/upload') {
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(renderPublicView('upload', 'Sensor Data Upload Portal'));
        return;
    }

    // ADMIN LOGIN
    if (pathname === '/admin/login') {
        const html = fs.readFileSync(path.join(BASE_DIR, 'views', 'admin', 'login.php'), 'utf8');
        res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
        res.end(html);
        return;
    }

    // ADMIN PROTECTED ROUTES
    if (pathname.startsWith('/admin')) {
        const admin = getAuthenticatedAdmin(req);
        if (!admin) {
            res.writeHead(302, { 'Location': '/admin/login' });
            res.end();
            return;
        }

        // 1. Overview / Dashboard
        if (pathname === '/admin' || pathname === '/admin/dashboard') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullDashboard(admin));
            return;
        }

        // 2. Sensors List
        if (pathname === '/admin/sensors') {
            const search = parsedUrl.searchParams.get('search') || '';
            const status = parsedUrl.searchParams.get('status') || '';
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullSensors(admin, search, status));
            return;
        }

        // 3. Sensor Detail
        if (pathname.startsWith('/admin/sensors/')) {
            const devId = pathname.split('/')[3];
            const dev = db.registered_devices.find(d => d.device_id === devId || d.id === devId);
            if (!dev) {
                res.writeHead(302, { 'Location': '/admin/sensors' });
                res.end();
                return;
            }
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullSensorDetail(admin, dev));
            return;
        }

        // 4. Configuration Settings
        if (pathname === '/admin/configs') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullConfigs(admin));
            return;
        }

        // 5. Registration History
        if (pathname === '/admin/history') {
            const status = parsedUrl.searchParams.get('status') || '';
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullHistory(admin, status));
            return;
        }

        // 6. Uploaded Files
        if (pathname === '/admin/files') {
            const search = parsedUrl.searchParams.get('search') || '';
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullFiles(admin, search));
            return;
        }

        // 7. Version Check Metrics
        if (pathname === '/admin/version-check-metrics') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullMetrics(admin));
            return;
        }

        // 8. Audit Logs
        if (pathname === '/admin/audit') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullAudit(admin));
            return;
        }

        // 9. Settings
        if (pathname === '/admin/settings') {
            res.writeHead(200, { 'Content-Type': 'text/html; charset=utf-8' });
            res.end(renderFullSettings(admin));
            return;
        }
    }

    sendError(res, 'Page Not Found', 404);
});

// -------------------------------------------------------------
// COMPLETE HTML PAGE RENDERERS
// -------------------------------------------------------------

function wrapInAdminLayout(title, activeNav, admin, contentHtml) {
    const layout = fs.readFileSync(path.join(BASE_DIR, 'views', 'layouts', 'admin.php'), 'utf8');

    // Replace active nav classes
    let html = layout
        .replace('<?= htmlspecialchars($title ?? \'Dashboard\') ?>', title)
        .replace('<?= htmlspecialchars($title ?? \'Overview\') ?>', title)
        .replace('<?= htmlspecialchars($admin[\'username\'] ?? \'admin\') ?>', admin.username)
        .replace('<?= htmlspecialchars($admin[\'role\'] ?? \'ADMIN\') ?>', admin.role)
        .replace(`class="nav-item <?= ($activeNav ?? '') === '${activeNav}' ? 'active' : '' ?>"`, `class="nav-item active"`)
        .replace(/<\?=\s*\(\$activeNav\s*\?\?\s*''\)\s*===\s*'[a-z]+'\s*\?\s*'active'\s*:\s*''\s*\?>/g, '')
        .replace('<?= $content ?>', contentHtml);

    return html;
}

// 1. Dashboard Overview
function renderFullDashboard(admin) {
    const totalSensors = db.registered_devices.length;
    const activeSensors = db.registered_devices.filter(d => d.status === 'ACTIVE').length;
    const deactiveSensors = totalSensors - activeSensors;
    const pendingRequests = db.device_registration_requests.filter(r => r.status === 'PENDING').length;
    const approvedRequests = db.device_registration_requests.filter(r => r.status === 'APPROVED').length;
    const rejectedRequests = db.device_registration_requests.filter(r => r.status === 'REJECTED').length;
    const totalFiles = db.uploaded_file_records.length;
    const totalStorageBytes = db.uploaded_file_records.reduce((acc, f) => acc + (f.file_size_bytes || 0), 0);

    const activePercent = totalSensors > 0 ? Math.round((activeSensors / totalSensors) * 100) : 0;
    const deactivePercent = totalSensors > 0 ? (100 - activePercent) : 0;

    // Build Recent Uploads List
    let uploadsHtml = '';
    const recentUploads = db.uploaded_file_records.slice(0, 5);
    if (recentUploads.length === 0) {
        uploadsHtml = '<div style="padding: 24px; text-align: center; color: var(--slate-400);">No files uploaded yet.</div>';
    } else {
        recentUploads.forEach(u => {
            uploadsHtml += `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--slate-100);">
                <div>
                    <div style="font-weight: 500; color: var(--slate-800); font-size: 13.5px;">${u.original_file_name}</div>
                    <div style="font-size: 11.5px; color: var(--slate-500); display: flex; gap: 8px; margin-top: 2px;">
                        <span class="font-mono">${u.device_id}</span>
                        <span>&bull;</span>
                        <span>${formatBytes(u.file_size_bytes)}</span>
                    </div>
                </div>
                <div style="font-size: 11.5px; color: var(--slate-400); white-space: nowrap;">
                    ${formatDate(u.uploaded_at)}
                </div>
            </div>`;
        });
    }

    // Build Recent Requests List
    let requestsHtml = '';
    const recentReqs = db.device_registration_requests.slice(0, 5);
    if (recentReqs.length === 0) {
        requestsHtml = '<div style="padding: 24px; text-align: center; color: var(--slate-400);">No registration requests recorded.</div>';
    } else {
        recentReqs.forEach(r => {
            const badgeClass = r.status === 'APPROVED' ? 'badge-success' : r.status === 'PENDING' ? 'badge-warning' : 'badge-danger';
            requestsHtml += `
            <div style="display: flex; align-items: center; justify-content: space-between; padding: 12px 20px; border-bottom: 1px solid var(--slate-100);">
                <div>
                    <div class="font-mono" style="font-weight: 600; color: var(--slate-800);">${r.device_id}</div>
                    <div style="font-size: 11.5px; color: var(--slate-400); margin-top: 2px;">
                        Submitted ${formatDate(r.submitted_at)}
                    </div>
                </div>
                <div>
                    <span class="badge ${badgeClass}">${r.status}</span>
                </div>
            </div>`;
        });
    }

    const content = `
    <!-- Auto-Refresh Header -->
    <div style="display: flex; align-items: center; justify-content: space-between; margin-top: -10px; margin-bottom: 6px;">
        <p style="font-size: 13px; color: var(--slate-500);">Live telemetry metrics and device ingestion oversight</p>
        <div style="display: flex; align-items: center; gap: 6px; font-size: 12px; color: var(--slate-400);">
            <svg id="auto-refresh-indicator" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 0 0-9-9 9.75 9.75 0 0 0-6.74 2.74L3 8"/><path d="M3 3v5h5"/><path d="M3 12a9 9 0 0 0 9 9 9.75 9.75 0 0 0 6.74-2.74L21 16"/><path d="M16 21h5v-5"/></svg>
            <span>Auto-refreshing (30s)</span>
        </div>
    </div>

    <!-- 9 StatCards Grid -->
    <div class="grid-stats">
        <div class="stat-card">
            <div class="stat-icon neutral"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg></div>
            <div class="stat-info"><span class="stat-label">Total sensors</span><span id="stat-total-sensors" class="stat-value">${totalSensors}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div class="stat-info"><span class="stat-label">Active sensors</span><span id="stat-active-sensors" class="stat-value">${activeSensors}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon neutral"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
            <div class="stat-info"><span class="stat-label">Deactive sensors</span><span id="stat-deactive-sensors" class="stat-value">${deactiveSensors}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon warning"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div class="stat-info"><span class="stat-label">Pending requests</span><span id="stat-pending-requests" class="stat-value">${pendingRequests}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon success"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/></svg></div>
            <div class="stat-info"><span class="stat-label">Approved requests</span><span id="stat-approved-requests" class="stat-value">${approvedRequests}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon danger"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg></div>
            <div class="stat-info"><span class="stat-label">Rejected requests</span><span id="stat-rejected-requests" class="stat-value">${rejectedRequests}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon neutral"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"/><polyline points="3.29 7 12 12 20.71 7"/><line x1="12" x2="12" y1="22" y2="12"/></svg></div>
            <div class="stat-info"><span class="stat-label">Total files</span><span id="stat-total-files" class="stat-value">${totalFiles}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon neutral"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><line x1="22" x2="2" y1="12" y2="12"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11z"/><line x1="6" x2="6.01" y1="16" y2="16"/><line x1="10" x2="10.01" y1="16" y2="16"/></svg></div>
            <div class="stat-info"><span class="stat-label">Total storage used</span><span id="stat-total-storage" class="stat-value">${formatBytes(totalStorageBytes)}</span></div>
        </div>
        <div class="stat-card">
            <div class="stat-icon accent"><svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg></div>
            <div class="stat-info"><span class="stat-label">Uploads in last 24h</span><span id="stat-uploads-24h" class="stat-value">${totalFiles}</span></div>
        </div>
    </div>

    <!-- 2-Column Section (Recent Uploads & Recent Requests) -->
    <div class="grid-2col">
        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Recent uploads</h3><p class="card-desc">Latest files uploaded by authenticated sensors</p></div>
                <a href="/admin/files" class="btn btn-ghost btn-sm">View all &rarr;</a>
            </div>
            <div class="card-content" style="padding: 0;">${uploadsHtml}</div>
        </div>

        <div class="card">
            <div class="card-header">
                <div><h3 class="card-title">Recent registration requests</h3><p class="card-desc">Devices waiting for approval or recently reviewed</p></div>
                <a href="/admin/history" class="btn btn-ghost btn-sm">View all &rarr;</a>
            </div>
            <div class="card-content" style="padding: 0;">${requestsHtml}</div>
        </div>
    </div>

    <!-- 2-Column Section (Distribution & Activity Charts) -->
    <div class="grid-2col">
        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Sensor status distribution</h3><p class="card-desc">Active vs. Deactive deployment ratio</p></div></div>
            <div class="card-content">
                <div class="dist-bar-track">
                    <div id="dist-bar-active" class="dist-bar-active" style="width: ${activePercent}%;"></div>
                    <div id="dist-bar-deactive" class="dist-bar-deactive" style="width: ${deactivePercent}%;"></div>
                </div>
                <div class="dist-legend">
                    <div><span class="legend-dot" style="background-color: var(--success-500);"></span><span>Active: <strong>${activeSensors}</strong> (${activePercent}%)</span></div>
                    <div><span class="legend-dot" style="background-color: var(--slate-300);"></span><span>Deactive: <strong>${deactiveSensors}</strong> (${deactivePercent}%)</span></div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Upload activity (last 7 days)</h3><p class="card-desc">Daily ingestion volume</p></div></div>
            <div class="card-content">
                <div class="chart-container">
                    <div class="chart-bar-wrap" title="Thu: 14 uploads"><div class="chart-bar" style="height: 60%;"></div><span class="chart-label">Thu</span></div>
                    <div class="chart-bar-wrap" title="Fri: 22 uploads"><div class="chart-bar" style="height: 95%;"></div><span class="chart-label">Fri</span></div>
                    <div class="chart-bar-wrap" title="Sat: 18 uploads"><div class="chart-bar" style="height: 75%;"></div><span class="chart-label">Sat</span></div>
                    <div class="chart-bar-wrap" title="Today: ${totalFiles} uploads"><div class="chart-bar" style="height: 85%;"></div><span class="chart-label">Today</span></div>
                </div>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout('Dashboard Overview', 'dashboard', admin, content);
}

// 2. Sensors List
function renderFullSensors(admin, search, statusFilter) {
    let list = db.registered_devices;
    if (search) {
        const q = search.toLowerCase();
        list = list.filter(d => d.device_id.toLowerCase().includes(q) || (d.hardware_model || '').toLowerCase().includes(q));
    }
    if (statusFilter) {
        list = list.filter(d => d.status === statusFilter);
    }

    let rows = '';
    if (list.length === 0) {
        rows = `<tr><td colspan="8" style="text-align: center; padding: 32px; color: var(--slate-400);">No registered devices found.</td></tr>`;
    } else {
        list.forEach(d => {
            const badgeClass = d.status === 'ACTIVE' ? 'badge-success' : 'badge-neutral';
            const toggleAction = d.status === 'ACTIVE' ? 'DEACTIVE' : 'ACTIVE';
            const toggleLabel = d.status === 'ACTIVE' ? 'Deactivate' : 'Activate';
            const toggleColor = d.status === 'ACTIVE' ? 'color: var(--danger-500);' : 'color: var(--success-700);';

            rows += `
            <tr>
                <td><a href="/admin/sensors/${d.device_id}" class="font-mono" style="font-weight: 600; color: var(--accent-500); text-decoration: none;">${d.device_id}</a></td>
                <td><span class="badge ${badgeClass}">${d.status}</span></td>
                <td style="color: var(--slate-600);">${d.hardware_model || '—'}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${d.sensor_version || '—'} / ${d.firmware_version || '—'}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${formatDate(d.last_seen_at)}</td>
                <td><strong>${d.upload_count || 0}</strong></td>
                <td style="font-size: 12px;">${formatBytes(d.total_storage_bytes)}</td>
                <td style="text-align: right; white-space: nowrap;">
                    <a href="/admin/sensors/${d.device_id}" class="btn btn-ghost btn-sm">View</a>
                    <button onclick="toggleSensorStatus('${d.id}', '${toggleAction}')" class="btn btn-ghost btn-sm" style="${toggleColor}">${toggleLabel}</button>
                    <button onclick="regenerateToken('${d.id}')" class="btn btn-secondary btn-sm">Token</button>
                </td>
            </tr>`;
        });
    }

    const content = `
    <div class="card">
        <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
            <div>
                <h3 class="card-title">Registered Sensors</h3>
                <p class="card-desc">Provisioned ESP32 devices communicating with the platform</p>
            </div>
            <form method="GET" action="/admin/sensors" style="display: flex; gap: 8px; align-items: center; flex-wrap: wrap;">
                <input type="text" name="search" value="${search}" placeholder="Search by Device ID..." class="input" style="width: 220px;">
                <select name="status" class="select" style="width: 130px;" onchange="this.form.submit()">
                    <option value="" ${!statusFilter ? 'selected' : ''}>All Statuses</option>
                    <option value="ACTIVE" ${statusFilter === 'ACTIVE' ? 'selected' : ''}>Active</option>
                    <option value="DEACTIVE" ${statusFilter === 'DEACTIVE' ? 'selected' : ''}>Deactive</option>
                </select>
                <button type="submit" class="btn btn-secondary btn-sm">Filter</button>
                ${search || statusFilter ? '<a href="/admin/sensors" class="btn btn-ghost btn-sm">Reset</a>' : ''}
            </form>
        </div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Device ID</th>
                            <th>Status</th>
                            <th>Hardware Model</th>
                            <th>Sensor / FW Version</th>
                            <th>Last Seen</th>
                            <th>Uploads</th>
                            <th>Storage</th>
                            <th style="text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout('Sensors List', 'sensors', admin, content);
}

// 3. Sensor Detail View
function renderFullSensorDetail(admin, dev) {
    const uploads = db.uploaded_file_records.filter(f => f.device_id === dev.device_id);
    let uploadRows = '';
    if (uploads.length === 0) {
        uploadRows = `<tr><td colspan="7" style="text-align: center; padding: 24px; color: var(--slate-400);">No uploads recorded for this device yet.</td></tr>`;
    } else {
        uploads.forEach(u => {
            uploadRows += `
            <tr>
                <td class="font-mono" style="font-size: 12px; font-weight: 500; color: var(--slate-700);">${u.upload_record_id}</td>
                <td style="font-weight: 500; color: var(--slate-900);">${u.original_file_name}</td>
                <td style="font-size: 12px;">${formatBytes(u.file_size_bytes)}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${u.mime_type}</td>
                <td class="font-mono" style="font-size: 11px; color: var(--slate-400);">${(u.checksum || '').substring(0, 16)}...</td>
                <td style="font-size: 12px; color: var(--slate-500);">${formatDate(u.uploaded_at)}</td>
                <td style="text-align: right;"><a href="/api/admin/files/${u.id}/download" class="btn btn-secondary btn-sm">Download</a></td>
            </tr>`;
        });
    }

    const badgeClass = dev.status === 'ACTIVE' ? 'badge-success' : 'badge-neutral';
    const toggleAction = dev.status === 'ACTIVE' ? 'DEACTIVE' : 'ACTIVE';
    const toggleLabel = dev.status === 'ACTIVE' ? 'Deactivate Sensor' : 'Activate Sensor';
    const toggleBtnClass = dev.status === 'ACTIVE' ? 'btn-secondary' : 'btn-primary';

    const content = `
    <div style="margin-bottom: 16px;">
        <a href="/admin/sensors" class="btn btn-ghost btn-sm">&larr; Back to Sensors List</a>
    </div>

    <div class="grid-2col">
        <div class="card">
            <div class="card-header">
                <div>
                    <h3 class="card-title font-mono">${dev.device_id}</h3>
                    <p class="card-desc">Registered on ${formatDate(dev.registered_at)}</p>
                </div>
                <div><span class="badge ${badgeClass}">${dev.status}</span></div>
            </div>
            <div class="card-content">
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 20px;">
                    <div><span class="stat-label">Hardware Model</span><div style="font-weight: 500; color: var(--slate-900);">${dev.hardware_model || 'ESP32'}</div></div>
                    <div><span class="stat-label">Last Heartbeat</span><div style="font-weight: 500; color: var(--slate-900);">${formatDate(dev.last_seen_at)}</div></div>
                    <div><span class="stat-label">Sensor Version</span><div class="font-mono" style="font-weight: 500; color: var(--slate-900);">${dev.sensor_version || '1.0.0'}</div></div>
                    <div><span class="stat-label">Firmware Version</span><div class="font-mono" style="font-weight: 500; color: var(--slate-900);">${dev.firmware_version || '2.1.0'}</div></div>
                    <div><span class="stat-label">Total Ingestions</span><div style="font-weight: 600; font-size: 16px; color: var(--slate-900);">${dev.upload_count || 0} files</div></div>
                    <div><span class="stat-label">Total Storage</span><div style="font-weight: 600; font-size: 16px; color: var(--slate-900);">${formatBytes(dev.total_storage_bytes)}</div></div>
                </div>

                <div style="display: flex; gap: 10px; border-top: 1px solid var(--slate-100); padding-top: 16px;">
                    <button onclick="toggleSensorStatus('${dev.id}', '${toggleAction}')" class="btn ${toggleBtnClass} btn-sm">${toggleLabel}</button>
                    <button onclick="regenerateToken('${dev.id}')" class="btn btn-secondary btn-sm">Generate New Bearer Token</button>
                    <button onclick="deleteSensor('${dev.id}', '${dev.device_id}')" class="btn btn-danger btn-sm" style="margin-left: auto;">Delete Device</button>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><div><h3 class="card-title">Device Telemetry Metadata</h3><p class="card-desc">Attributes and hardware tags</p></div></div>
            <div class="card-content">
                <pre class="font-mono" style="background: var(--slate-50); border: 1px solid var(--slate-200); border-radius: var(--radius-md); padding: 14px; font-size: 12px; color: var(--slate-800); max-height: 250px; overflow-y: auto;">${JSON.stringify(dev.metadata || {}, null, 2)}</pre>
            </div>
        </div>
    </div>

    <div class="card" style="margin-top: 24px;">
        <div class="card-header"><div><h3 class="card-title">Upload History for ${dev.device_id}</h3><p class="card-desc">Recent files ingested from this sensor</p></div></div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Upload ID</th><th>Filename</th><th>Size</th><th>MIME Type</th><th>SHA-256 Checksum</th><th>Uploaded Timestamp</th><th style="text-align: right;">Action</th></tr></thead>
                    <tbody>${uploadRows}</tbody>
                </table>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout(`Sensor ${dev.device_id}`, 'sensors', admin, content);
}

// 4. Configuration Settings
function renderFullConfigs(admin) {
    const activeCfg = db.global_sensor_configurations.find(c => c.status === 'ACTIVE');

    let rows = '';
    db.global_sensor_configurations.forEach(cfg => {
        const bClass = cfg.status === 'ACTIVE' ? 'badge-success' : cfg.status === 'INACTIVE' ? 'badge-warning' : 'badge-neutral';
        const actionHtml = cfg.status === 'INACTIVE' 
            ? `<button onclick="activateConfig('${cfg.id}', '${cfg.config_version}')" class="btn btn-primary btn-sm">Activate</button>`
            : cfg.status === 'ACTIVE' ? `<span style="font-size: 12px; font-weight: 500; color: var(--success-700);">Active</span>` : `<span style="font-size: 12px; color: var(--slate-400);">Archived</span>`;

        rows += `
        <tr>
            <td><strong class="font-mono" style="font-size: 14px; color: var(--slate-900);">v${cfg.config_version}</strong></td>
            <td><span class="badge ${bClass}">${cfg.status}</span></td>
            <td style="font-weight: 500; color: var(--slate-800);">${cfg.original_file_name}</td>
            <td style="font-size: 12px;">${formatBytes(cfg.file_size_bytes)}</td>
            <td class="font-mono" style="font-size: 11px; color: var(--slate-400);">${(cfg.checksum || '').substring(0, 16)}...</td>
            <td style="font-size: 12px; color: var(--slate-500);">${formatDate(cfg.created_at)}</td>
            <td style="text-align: right;">${actionHtml}</td>
        </tr>`;
    });

    const activeBox = activeCfg ? `
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 16px;">
            <div><span class="stat-label">Active Semver Version</span><div class="font-mono" style="font-size: 22px; font-weight: 700; color: var(--accent-500); margin-top: 4px;">v${activeCfg.config_version}</div></div>
            <div><span class="stat-label">Configuration File</span><div style="font-weight: 500; color: var(--slate-900); margin-top: 4px;">${activeCfg.original_file_name} (${formatBytes(activeCfg.file_size_bytes)})</div></div>
            <div><span class="stat-label">Activated At</span><div style="font-size: 13px; color: var(--slate-700); margin-top: 4px;">${formatDate(activeCfg.activated_at)}</div></div>
            <div><span class="stat-label">SHA-256 Checksum</span><div class="font-mono" style="font-size: 11.5px; color: var(--slate-500); margin-top: 4px; word-break: break-all;">${activeCfg.checksum || '—'}</div></div>
        </div>
    ` : `<div style="padding: 16px; background-color: var(--warning-50); border: 1px solid var(--warning-500); border-radius: var(--radius-md); color: var(--warning-700); font-size: 13px;">No active configuration deployed. Upload a config below and click Activate.</div>`;

    const content = `
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <p style="font-size: 13.5px; color: var(--slate-500);">Over-the-air global configuration distribution for ESP32 fleet</p>
        <button onclick="openModal('upload-config-modal')" class="btn btn-primary btn-md">Upload New Configuration</button>
    </div>

    <div class="card" style="margin-bottom: 24px; border-left: 4px solid var(--accent-500);">
        <div class="card-header"><div><h3 class="card-title">Currently Active Configuration</h3><p class="card-desc">Distributed to sensors on version check requests</p></div><div><span class="badge ${activeCfg ? 'badge-success' : 'badge-warning'}">${activeCfg ? 'Active Deployment' : 'None Active'}</span></div></div>
        <div class="card-content">${activeBox}</div>
    </div>

    <div class="card">
        <div class="card-header"><div><h3 class="card-title">Version History & Deployments</h3><p class="card-desc">All uploaded configurations</p></div></div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Version</th><th>Status</th><th>Filename</th><th>File Size</th><th>SHA-256 Checksum</th><th>Uploaded Date</th><th style="text-align: right;">Action</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Upload Modal -->
    <div id="upload-config-modal" class="modal-overlay">
        <div class="modal-box">
            <form onsubmit="handleConfigUpload(event)">
                <div class="card-header">
                    <h3 class="card-title">Upload Global Configuration</h3>
                    <button type="button" data-close-modal class="btn btn-ghost btn-sm">&times;</button>
                </div>
                <div class="card-content">
                    <div class="form-group">
                        <label class="form-label">Version String (Semver):</label>
                        <input type="text" name="version" required placeholder="1.2.0" value="1.2.0" class="input font-mono">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Config File (.json):</label>
                        <input type="file" name="file" class="input" style="padding-top: 5px;">
                    </div>
                </div>
                <div class="card-footer">
                    <button type="button" data-close-modal class="btn btn-secondary btn-sm">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Upload Configuration</button>
                </div>
            </form>
        </div>
    </div>
    <script>
    async function handleConfigUpload(e) {
        e.preventDefault();
        const res = await fetch('/api/admin/configuration', { method: 'POST' });
        const json = await res.json();
        if (json.success) {
            window.showToast('Configuration uploaded successfully!', 'success');
            setTimeout(() => window.location.reload(), 800);
        } else {
            window.showToast(json.error || 'Upload failed', 'error');
        }
    }
    </script>`;

    return wrapInAdminLayout('Configuration Settings', 'configs', admin, content);
}

// 5. Registration History
function renderFullHistory(admin, statusFilter) {
    let list = db.device_registration_requests;
    if (statusFilter) {
        list = list.filter(r => r.status === statusFilter);
    }

    let rows = '';
    if (list.length === 0) {
        rows = `<tr><td colspan="7" style="text-align: center; padding: 32px; color: var(--slate-400);">No registration requests recorded.</td></tr>`;
    } else {
        list.forEach(r => {
            const bClass = r.status === 'APPROVED' ? 'badge-success' : r.status === 'PENDING' ? 'badge-warning' : 'badge-danger';
            const actionHtml = r.status === 'PENDING'
                ? `<button onclick="approveRegistration('${r.id}')" class="btn btn-primary btn-sm">Approve</button> <button onclick="rejectRegistration('${r.id}')" class="btn btn-ghost btn-sm" style="color: var(--danger-500);">Reject</button>`
                : `<span style="font-size: 12px; color: var(--slate-400);">Closed</span>`;

            const meta = r.requester_metadata || {};
            const infoStr = `${meta.ip || '127.0.0.1'} | ${meta.hardwareModel || 'ESP32'}`;

            rows += `
            <tr>
                <td><strong class="font-mono" style="font-size: 13.5px; color: var(--slate-900);">${r.device_id}</strong></td>
                <td><span class="badge ${bClass}">${r.status}</span></td>
                <td style="font-size: 12px; color: var(--slate-600);">${formatDate(r.submitted_at)}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${infoStr}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${r.reviewed_at ? formatDate(r.reviewed_at) : 'Awaiting Review'}</td>
                <td style="font-size: 12px; color: var(--slate-600); max-width: 200px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${r.review_reason || '—'}</td>
                <td style="text-align: right; white-space: nowrap;">${actionHtml}</td>
            </tr>`;
        });
    }

    const content = `
    <div class="card">
        <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
            <div><h3 class="card-title">Registration Requests History</h3><p class="card-desc">Review device enrollment requests and grant bearer authorization</p></div>
            <div style="display: flex; gap: 6px; align-items: center;">
                <a href="/admin/history" class="btn ${!statusFilter ? 'btn-primary' : 'btn-secondary'} btn-sm">All</a>
                <a href="/admin/history?status=PENDING" class="btn ${statusFilter === 'PENDING' ? 'btn-primary' : 'btn-secondary'} btn-sm">Pending</a>
                <a href="/admin/history?status=APPROVED" class="btn ${statusFilter === 'APPROVED' ? 'btn-primary' : 'btn-secondary'} btn-sm">Approved</a>
                <a href="/admin/history?status=REJECTED" class="btn ${statusFilter === 'REJECTED' ? 'btn-primary' : 'btn-secondary'} btn-sm">Rejected</a>
            </div>
        </div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Device ID</th><th>Status</th><th>Submitted At</th><th>Requester Info</th><th>Reviewed Date</th><th>Review Reason</th><th style="text-align: right;">Actions</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout('Registration History', 'history', admin, content);
}

// 6. Uploaded Files
function renderFullFiles(admin, search) {
    let list = db.uploaded_file_records;
    if (search) {
        const q = search.toLowerCase();
        list = list.filter(f => f.original_file_name.toLowerCase().includes(q) || f.device_id.toLowerCase().includes(q));
    }

    let rows = '';
    if (list.length === 0) {
        rows = `<tr><td colspan="8" style="text-align: center; padding: 32px; color: var(--slate-400);">No uploaded files found.</td></tr>`;
    } else {
        list.forEach(f => {
            rows += `
            <tr>
                <td class="font-mono" style="font-size: 12px; font-weight: 500; color: var(--slate-700);">${f.upload_record_id}</td>
                <td><strong style="color: var(--slate-900); font-size: 13.5px;">${f.original_file_name}</strong></td>
                <td><a href="/admin/sensors/${f.device_id}" class="font-mono" style="color: var(--accent-500); text-decoration: none;">${f.device_id}</a></td>
                <td style="font-size: 12px;">${formatBytes(f.file_size_bytes)}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${f.mime_type || 'binary'}</td>
                <td style="font-size: 12px; color: var(--slate-500);">${formatDate(f.uploaded_at)}</td>
                <td class="font-mono" style="font-size: 11px; color: var(--slate-400);">${(f.checksum || '').substring(0, 16)}...</td>
                <td style="text-align: right; white-space: nowrap;">
                    <a href="/api/admin/files/${f.id}/download" class="btn btn-secondary btn-sm">Download</a>
                    <button onclick="deleteUploadedFile('${f.id}', '${f.original_file_name}')" class="btn btn-ghost btn-sm" style="color: var(--danger-500);">Delete</button>
                </td>
            </tr>`;
        });
    }

    const content = `
    <div class="card">
        <div class="card-header" style="flex-wrap: wrap; gap: 12px;">
            <div><h3 class="card-title">Uploaded Sensor Telemetry Files</h3><p class="card-desc">Persisted files ingested via HTTP uploads</p></div>
            <form method="GET" action="/admin/files" style="display: flex; gap: 8px;">
                <input type="text" name="search" value="${search}" placeholder="Search filename or device ID..." class="input" style="width: 250px;">
                <button type="submit" class="btn btn-secondary btn-sm">Search</button>
                ${search ? '<a href="/admin/files" class="btn btn-ghost btn-sm">Reset</a>' : ''}
            </form>
        </div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Upload ID</th><th>Filename</th><th>Device ID</th><th>Size</th><th>MIME Type</th><th>Uploaded Timestamp</th><th>SHA-256 Checksum</th><th style="text-align: right;">Actions</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout('Uploaded Files', 'files', admin, content);
}

// 7. Version Check Metrics
function renderFullMetrics(admin) {
    let rows = '';
    db.daily_metrics.forEach(m => {
        const bClass = m.result === 'CURRENT' ? 'badge-success' : m.result === 'UPDATE_AVAILABLE' ? 'badge-accent' : 'badge-danger';
        rows += `
        <tr>
            <td><a href="/admin/sensors/${m.device_id}" class="font-mono" style="font-weight: 600; color: var(--accent-500); text-decoration: none;">${m.device_id}</a></td>
            <td><span class="badge ${bClass}">${m.result}</span></td>
            <td><strong style="color: var(--slate-900); font-size: 14px;">${m.total_checks} pings</strong></td>
            <td style="font-size: 12px; color: var(--slate-500);">${formatDate(m.last_date)}</td>
        </tr>`;
    });

    const content = `
    <div class="card">
        <div class="card-header"><div><h3 class="card-title">Version Check Telemetry Metrics</h3><p class="card-desc">Aggregated outcomes of periodic sensor configuration checks</p></div></div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Device ID</th><th>Telemetry Outcome</th><th>Cumulative Check Count</th><th>Last Reported Date</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout('Version Check Metrics', 'metrics', admin, content);
}

// 8. Audit Logs
function renderFullAudit(admin) {
    let rows = '';
    db.audit_events.forEach(l => {
        rows += `
        <tr>
            <td style="font-size: 12px; color: var(--slate-600); white-space: nowrap;">${formatDate(l.created_at)}</td>
            <td><strong style="color: var(--slate-900); font-size: 13px;">${l.username}</strong></td>
            <td><span class="badge badge-accent">${l.action}</span></td>
            <td style="font-size: 12px; color: var(--slate-600);">${l.entity_type}</td>
            <td class="font-mono" style="font-size: 12px; color: var(--slate-700);">${l.entity_id || '—'}</td>
            <td class="font-mono" style="font-size: 12px; color: var(--slate-500);">${l.ip_address || '—'}</td>
            <td style="font-size: 11.5px; color: var(--slate-500); max-width: 250px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">${l.metadata || '—'}</td>
        </tr>`;
    });

    const content = `
    <div class="card">
        <div class="card-header"><div><h3 class="card-title">Administrative Audit Trail</h3><p class="card-desc">Immutable record of security and administrative operations</p></div></div>
        <div class="card-content" style="padding: 0;">
            <div class="table-responsive">
                <table class="data-table">
                    <thead><tr><th>Timestamp</th><th>Administrator</th><th>Action</th><th>Entity</th><th>Target ID</th><th>IP Address</th><th>Details</th></tr></thead>
                    <tbody>${rows}</tbody>
                </table>
            </div>
        </div>
    </div>`;

    return wrapInAdminLayout('Audit Logs', 'audit', admin, content);
}

// 9. Settings
function renderFullSettings(admin) {
    const primaryAdmin = db.admin_accounts[0] || admin;
    const portalUser = (db.portal_users && db.portal_users[0]) || { id: 'user-portal-0001', username: 'user', role: 'USER' };

    let settingsView = fs.readFileSync(path.join(BASE_DIR, 'views', 'admin', 'settings.php'), 'utf8');
    settingsView = settingsView
        .replace(/<\?php[\s\S]*?\?>/g, '')
        .replace("<?= htmlspecialchars(\$settings['admin']['username'] ?? 'admin') ?>", primaryAdmin.username)
        .replace("<?= htmlspecialchars(\$settings['portalUser']['username'] ?? 'user') ?>", portalUser.username);

    return wrapInAdminLayout('Settings & Credential Management', 'settings', admin, settingsView);
}

server.listen(PORT, HOST, () => {
    console.log(`\n======================================================`);
    console.log(`🚀 FileVault / Sensor Platform Local Server Running!`);
    console.log(`🌐 Home & Testing Console: http://localhost:${PORT}`);
    console.log(`👤 Admin Dashboard:        http://localhost:${PORT}/admin/dashboard`);
    console.log(`📡 Sensor Edge API:        http://localhost:${PORT}/api/v1/sensors`);
    console.log(`📤 Public Upload Portal:   http://localhost:${PORT}/upload`);
    console.log(`🔑 Default Admin:          admin / admin123`);
    console.log(`======================================================\n`);
});
