/**
 * ESP32 Device Simulator for Sensor Platform
 * Simulates a physical ESP32 device on the local network.
 * 
 * Usage:
 *   node scripts/esp32_simulator.js [DEVICE_ID] [TOKEN]
 * 
 * Example:
 *   node scripts/esp32_simulator.js SENSOR-001 a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef
 */

const http = require('http');

const SERVER_HOST = 'localhost';
const SERVER_PORT = 8000;

const DEVICE_ID = process.argv[2] || 'SENSOR-001';
const TOKEN = process.argv[3] || 'a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef';

console.log(`\n======================================================`);
console.log(`📡 Starting ESP32 Simulator`);
console.log(`🆔 Device ID: ${DEVICE_ID}`);
console.log(`🌐 Target:    http://${SERVER_HOST}:${SERVER_PORT}`);
console.log(`======================================================\n`);

function sendHeartbeat() {
    const payload = JSON.stringify({
        deviceId: DEVICE_ID,
        sensorVersion: '1.0.0'
    });

    const req = http.request({
        host: SERVER_HOST,
        port: SERVER_PORT,
        path: '/api/v1/sensors/heartbeat',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(payload),
            'Authorization': `Bearer ${TOKEN}`,
            'X-Sensor-Id': DEVICE_ID
        }
    }, (res) => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => {
            if (res.statusCode === 200) {
                console.log(`[${new Date().toLocaleTimeString()}] 💓 Heartbeat OK (Status 200)`);
            } else {
                console.warn(`[${new Date().toLocaleTimeString()}] ⚠️ Heartbeat rejected (${res.statusCode}):`, data);
            }
        });
    });

    req.on('error', (e) => {
        console.error(`[${new Date().toLocaleTimeString()}] ❌ Network error:`, e.message);
    });

    req.write(payload);
    req.end();
}

function checkVersion() {
    const payload = JSON.stringify({
        deviceId: DEVICE_ID,
        currentVersion: '1.0.0'
    });

    const req = http.request({
        host: SERVER_HOST,
        port: SERVER_PORT,
        path: '/api/v1/sensors/version-check',
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Content-Length': Buffer.byteLength(payload),
            'Authorization': `Bearer ${TOKEN}`,
            'X-Sensor-Id': DEVICE_ID
        }
    }, (res) => {
        let data = '';
        res.on('data', chunk => data += chunk);
        res.on('end', () => {
            try {
                const json = JSON.parse(data);
                console.log(`[${new Date().toLocaleTimeString()}] 🔍 Version check: ${json.result} (Active: ${json.activeVersion || 'none'})`);
            } catch (e) {
                console.log(`[${new Date().toLocaleTimeString()}] Version check response:`, data);
            }
        });
    });

    req.write(payload);
    req.end();
}

function uploadTelemetry() {
    const data = `timestamp,device_id,temperature,humidity,battery_mv\n${Date.now()},${DEVICE_ID},${(20 + Math.random() * 10).toFixed(1)},${(50 + Math.random() * 20).toFixed(1)},${Math.floor(3200 + Math.random() * 100)}\n`;
    
    const req = http.request({
        host: SERVER_HOST,
        port: SERVER_PORT,
        path: '/api/v1/sensors/upload',
        method: 'POST',
        headers: {
            'Content-Type': 'application/octet-stream',
            'Content-Length': Buffer.byteLength(data),
            'Authorization': `Bearer ${TOKEN}`,
            'X-Sensor-Id': DEVICE_ID
        }
    }, (res) => {
        let resp = '';
        res.on('data', chunk => resp += chunk);
        res.on('end', () => {
            if (res.statusCode === 201) {
                try {
                    const json = JSON.parse(resp);
                    console.log(`[${new Date().toLocaleTimeString()}] 📤 Ingested file: ${json.uploadId} (${json.fileSize} bytes, SHA-256: ${json.checksum.substring(0, 12)}...)`);
                } catch (e) {
                    console.log(`[${new Date().toLocaleTimeString()}] Ingested file: Status 201`);
                }
            } else {
                console.warn(`[${new Date().toLocaleTimeString()}] ⚠️ Ingestion failed (${res.statusCode}):`, resp);
            }
        });
    });

    req.write(data);
    req.end();
}

// Initial checks
sendHeartbeat();
checkVersion();

// Periodic timer
setInterval(sendHeartbeat, 30000); // 30s
setInterval(uploadTelemetry, 60000); // 60s
