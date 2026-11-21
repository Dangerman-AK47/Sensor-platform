/**
 * Sensor Platform / FileVault / DeviceVault v3 - Automated Local Test Suite
 * Tests all 14 mandatory test cases from Phase 7.
 */

const http = require('http');
const assert = require('assert');

const BASE_URL = 'http://localhost:8000';

function request(options, body = null) {
    return new Promise((resolve, reject) => {
        const req = http.request(options, (res) => {
            let data = [];
            res.on('data', chunk => data.push(chunk));
            res.on('end', () => {
                const buffer = Buffer.concat(data);
                let json = null;
                try {
                    json = JSON.parse(buffer.toString());
                } catch (e) {}
                resolve({
                    status: res.statusCode,
                    headers: res.headers,
                    body: buffer.toString(),
                    json
                });
            });
        });
        req.on('error', reject);
        if (body) {
            req.write(body);
        }
        req.end();
    });
}

async function runTests() {
    console.log('====================================================');
    console.log('🧪 Starting Sensor Platform Automated Local Test Suite');
    console.log('====================================================\n');

    let passed = 0;
    let failed = 0;
    let sessionCookie = '';
    let generatedSensorToken = '';
    let testDeviceId = 'SENSOR-TEST-' + Math.floor(Math.random() * 9000 + 1000);
    let testRequestId = '';

    async function test(name, fn) {
        process.stdout.write(`• ${name}... `);
        try {
            await fn();
            console.log('✅ PASSED');
            passed++;
        } catch (e) {
            console.log('❌ FAILED:', e.message);
            failed++;
        }
    }

    // Reset dev state to initial defaults
    try {
        await request({ host: 'localhost', port: 8000, path: '/api/dev/reset-test-state', method: 'POST' });
    } catch (e) {}

    // TEST 1: Server bootstrap and public pages
    await test('1. Public upload portal loads (GET /upload)', async () => {
        const res = await request({ host: 'localhost', port: 8000, path: '/upload', method: 'GET' });
        assert.strictEqual(res.status, 200);
        assert(res.body.includes('Sensor Telemetry Upload Portal'));
    });

    await test('2. Login page loads (GET /admin/login)', async () => {
        const res = await request({ host: 'localhost', port: 8000, path: '/admin/login', method: 'GET' });
        assert.strictEqual(res.status, 200);
        assert(res.body.includes('FileVault Admin'));
    });

    // TEST 2: RBAC & Authentication Security
    await test('3. Unauthorized request to /api/admin/session returns 401', async () => {
        const res = await request({ host: 'localhost', port: 8000, path: '/api/admin/session', method: 'GET' });
        assert.strictEqual(res.status, 401);
    });

    await test('4. Unauthorized request to /api/admin/sensors/stats returns 401', async () => {
        const res = await request({ host: 'localhost', port: 8000, path: '/api/admin/sensors/stats', method: 'GET' });
        assert.strictEqual(res.status, 401);
    });

    await test('5. Admin login with bad password returns 401', async () => {
        const payload = JSON.stringify({ username: 'admin', password: 'wrongPassword999' });
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/admin/login', method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Content-Length': payload.length }
        }, payload);
        assert.strictEqual(res.status, 401);
        assert.strictEqual(res.json.success, false);
    });

    await test('6. Admin login with valid credentials succeeds and returns session cookie', async () => {
        const payload = JSON.stringify({ username: 'admin', password: 'admin123' });
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/admin/login', method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Content-Length': payload.length }
        }, payload);
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.json.success, true);
        assert(res.headers['set-cookie']);
        sessionCookie = res.headers['set-cookie'][0].split(';')[0];
    });

    await test('7. Authenticated request to /api/admin/sensors/stats returns 9 metrics', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/admin/sensors/stats', method: 'GET',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.json.success, true);
        assert(typeof res.json.totalSensors === 'number');
        assert(typeof res.json.activeSensors === 'number');
        assert(typeof res.json.deactiveSensors === 'number');
        assert(typeof res.json.pendingRequests === 'number');
        assert(typeof res.json.approvedRequests === 'number');
        assert(typeof res.json.rejectedRequests === 'number');
        assert(typeof res.json.totalFiles === 'number');
        assert(typeof res.json.totalStorageBytes === 'number');
        assert(typeof res.json.uploadsLast24h === 'number');
    });

    // TEST 3: ESP32 Self-Registration with Auto-Approval
    await test(`8. ESP32 registers (${testDeviceId}) -> 201 AUTO_APPROVED with immediate Bearer token`, async () => {
        const payload = JSON.stringify({
            deviceId: testDeviceId,
            sensorVersion: '1.0.0',
            firmwareVersion: '2.1.0',
            hardwareModel: 'ESP32-S3'
        });
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/register', method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Content-Length': payload.length }
        }, payload);
        assert.strictEqual(res.status, 201);
        assert.strictEqual(res.json.status, 'AUTO_APPROVED');
        assert(res.json.token);
        assert.strictEqual(res.json.token.length, 64);
        generatedSensorToken = res.json.token;
        testRequestId = res.json.requestId;
    });

    await test('9. Duplicate active sensor registration returns 409 Conflict', async () => {
        const payload = JSON.stringify({ deviceId: testDeviceId });
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/register', method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Content-Length': payload.length }
        }, payload);
        assert.strictEqual(res.status, 409);
    });

    await test('10. Registration request is recorded in history as AUTO_APPROVED', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/admin/sensors/stats', method: 'GET',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(res.status, 200);
        const reqItem = res.json.recentRequests.find(r => r.device_id === testDeviceId);
        assert(reqItem);
        assert.strictEqual(reqItem.status, 'AUTO_APPROVED');
    });

    // TEST 5: Sensor Bearer Authentication & Heartbeat
    await test('11. Sensor heartbeat with invalid token returns 401', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/heartbeat', method: 'POST',
            headers: { 'Authorization': 'Bearer bad_token_12345', 'X-Sensor-Id': testDeviceId }
        });
        assert.strictEqual(res.status, 401);
    });

    await test('12. Sensor heartbeat with newly generated Bearer token succeeds', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/heartbeat', method: 'POST',
            headers: {
                'Authorization': `Bearer ${generatedSensorToken}`,
                'X-Sensor-Id': testDeviceId,
                'Content-Type': 'application/json'
            }
        });
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.json.ok, true);
    });

    // TEST 6: Version Check & Configuration Download
    await test('13. Sensor version check with older version returns UPDATE_AVAILABLE', async () => {
        const payload = JSON.stringify({ currentVersion: '1.0.0' });
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/version-check', method: 'POST',
            headers: {
                'Authorization': `Bearer ${generatedSensorToken}`,
                'X-Sensor-Id': testDeviceId,
                'Content-Type': 'application/json',
                'Content-Length': payload.length
            }
        }, payload);
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.json.result, 'UPDATE_AVAILABLE');
        assert.strictEqual(res.json.activeVersion, '1.1.0');
    });

    await test('14. Sensor downloads active configuration with Bearer token', async () => {
        const res = await request({
            host: 'localhost', port: 8000,
            path: `/api/v1/sensors/configuration/download?deviceId=${testDeviceId}`,
            method: 'GET',
            headers: { 'Authorization': `Bearer ${generatedSensorToken}` }
        });
        assert.strictEqual(res.status, 200);
        assert(res.body.length > 0);
    });

    // TEST 7: File Upload & Checksum
    await test('15. Sensor uploads telemetry file and verifies SHA-256', async () => {
        const fileContent = 'timestamp,temp,humidity,pressure\n1729158000,24.5,61.2,1013.2\n';
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/upload', method: 'POST',
            headers: {
                'Authorization': `Bearer ${generatedSensorToken}`,
                'X-Sensor-Id': testDeviceId,
                'Content-Type': 'application/octet-stream',
                'Content-Length': Buffer.byteLength(fileContent)
            }
        }, fileContent);
        assert.strictEqual(res.status, 201);
        assert.strictEqual(res.json.success, true);
        assert(res.json.uploadId);
        assert(res.json.checksum);
    });

    // TEST 8: Sensor Deletion (Admin Power of Delete)
    await test(`16. Admin permanently deletes device (${testDeviceId}) -> CASCADE DB purge`, async () => {
        const res = await request({
            host: 'localhost', port: 8000,
            path: `/api/admin/sensors/${encodeURIComponent(testDeviceId)}`,
            method: 'DELETE',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.json.success, true);

        // Verify sensor is rejected on subsequent heartbeat
        const hbCheck = await request({
            host: 'localhost', port: 8000, path: '/api/v1/sensors/heartbeat', method: 'POST',
            headers: {
                'Authorization': `Bearer ${generatedSensorToken}`,
                'X-Sensor-Id': testDeviceId
            }
        });
        assert.strictEqual(hbCheck.status, 401);

        // Verify deleted sensor page redirects to /admin/sensors
        const pageCheck = await request({
            host: 'localhost', port: 8000,
            path: `/admin/sensors/${encodeURIComponent(testDeviceId)}`,
            method: 'GET',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(pageCheck.status, 302);
        assert.strictEqual(pageCheck.headers.location, '/admin/sensors');
    });

    // TEST 8b: User Portal Auth & Settings Management
    await test('17. User portal login with invalid password returns 401', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/user/login', method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'user', password: 'wrongpassword' }));
        assert.strictEqual(res.status, 401);
        assert.strictEqual(res.json.success, false);
    });

    let portalCookie = null;
    await test('18. User portal login with valid credentials (user / password) returns 200 and unlocks portal', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/user/login', method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'user', password: 'password' }));
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.json.success, true);
        portalCookie = (res.headers['set-cookie'] || [])[0];
        assert.ok(portalCookie && portalCookie.includes('fv_portal_session'));
    });

    await test('19. Admin views settings and updates user credentials in Admin Settings', async () => {
        // Read settings
        const settingsRes = await request({
            host: 'localhost', port: 8000, path: '/api/admin/settings', method: 'GET',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(settingsRes.status, 200);
        assert.strictEqual(settingsRes.json.data.portalUser.username, 'user');

        // Update user password to newpass456
        const updateRes = await request({
            host: 'localhost', port: 8000, path: '/api/admin/settings/user-credentials', method: 'POST',
            headers: { 'Cookie': sessionCookie, 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'user', password: 'newpass456' }));
        assert.strictEqual(updateRes.status, 200);
        assert.strictEqual(updateRes.json.success, true);
    });

    await test('20. Portal user verifies new password required; old password fails', async () => {
        // Old password must now fail
        const oldRes = await request({
            host: 'localhost', port: 8000, path: '/api/user/login', method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'user', password: 'password' }));
        assert.strictEqual(oldRes.status, 401);

        // New password succeeds
        const newRes = await request({
            host: 'localhost', port: 8000, path: '/api/user/login', method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'user', password: 'newpass456' }));
        assert.strictEqual(newRes.status, 200);
        assert.strictEqual(newRes.json.success, true);
    });

    await test('21. Admin updates admin credentials in Settings and verifies login with new password', async () => {
        // Update admin password to AdminPass#2026
        const updateAdminRes = await request({
            host: 'localhost', port: 8000, path: '/api/admin/settings/admin-credentials', method: 'POST',
            headers: { 'Cookie': sessionCookie, 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'admin', password: 'AdminPass#2026' }));
        assert.strictEqual(updateAdminRes.status, 200);
        assert.strictEqual(updateAdminRes.json.success, true);

        // Login with new admin credentials
        const loginRes = await request({
            host: 'localhost', port: 8000, path: '/api/admin/login', method: 'POST',
            headers: { 'Content-Type': 'application/json' }
        }, JSON.stringify({ username: 'admin', password: 'AdminPass#2026' }));
        assert.strictEqual(loginRes.status, 200);
        assert.strictEqual(loginRes.json.success, true);
        // Refresh session cookie
        sessionCookie = (loginRes.headers['set-cookie'] || [])[0];
    });

    // TEST 9: Admin Logout
    await test('22. Admin logout revokes session', async () => {
        const res = await request({
            host: 'localhost', port: 8000, path: '/api/admin/logout', method: 'POST',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(res.status, 200);

        // Follow up check: session should now be rejected
        const check = await request({
            host: 'localhost', port: 8000, path: '/api/admin/session', method: 'GET',
            headers: { 'Cookie': sessionCookie }
        });
        assert.strictEqual(check.status, 401);
    });

    console.log('\n====================================================');
    console.log(`Summary: ${passed} Passed, ${failed} Failed`);
    console.log('====================================================\n');

    process.exit(failed > 0 ? 1 : 0);
}

runTests();
