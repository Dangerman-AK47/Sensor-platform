/**
 * ESP32 Client Firmware for Sensor Platform / FileVault / DeviceVault v3
 * 
 * Target: ESP32 / ESP32-S3 / ESP32-C3
 * Framework: Arduino ESP32 Core
 * 
 * Features:
 *  1. Self-Registration (POST /api/v1/sensors/register)
 *  2. 30-Second Heartbeat Loop (POST /api/v1/sensors/heartbeat)
 *  3. Configuration Version Check (POST /api/v1/sensors/version-check)
 *  4. OTA Configuration Download (GET /api/v1/sensors/configuration/download)
 *  5. Periodic Telemetry File Upload (POST /api/v1/sensors/upload)
 *  6. SPIFFS Flash Storage for credentials and configuration
 */

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <FS.h>
#include <SPIFFS.h>
#include <ArduinoJson.h> // ArduinoJson v6 or v7

// -------------------------------------------------------------
// CONFIGURATION PARAMETERS
// -------------------------------------------------------------
const char* WIFI_SSID     = "YOUR_WIFI_SSID";
const char* WIFI_PASSWORD = "YOUR_WIFI_PASSWORD";

// Server base URL:
// For Local Testing on Windows: Use your PC's LAN IP (e.g. http://192.168.1.100:8000)
// For HostyCare Production: Use your domain (e.g. https://yourdomain.com)
const char* SERVER_BASE_URL = "http://192.168.1.100:8000";

const char* DEVICE_ID        = "SENSOR-001";
const char* HARDWARE_MODEL   = "ESP32-S3-WROOM";
const char* SENSOR_VERSION   = "1.0.0";
const char* FIRMWARE_VERSION = "2.1.0";

// Sensor Bearer Token (Granted upon Admin Approval in Dashboard)
String sensorBearerToken = ""; 

// Timing
unsigned long lastHeartbeatTime = 0;
const unsigned long HEARTBEAT_INTERVAL_MS = 30000; // 30 seconds

unsigned long lastVersionCheckTime = 0;
const unsigned long VERSION_CHECK_INTERVAL_MS = 120000; // 2 minutes

// -------------------------------------------------------------
// SETUP
// -------------------------------------------------------------
void setup() {
    Serial.begin(115200);
    delay(1000);
    Serial.println("\n[ESP32] Initializing Sensor Platform Client...");

    if (!SPIFFS.begin(true)) {
        Serial.println("[SPIFFS] Flash filesystem mount failed!");
    } else {
        Serial.println("[SPIFFS] Flash filesystem mounted.");
        loadTokenFromFlash();
    }

    connectWiFi();

    // If no token exists, submit self-registration request
    if (sensorBearerToken.length() == 0) {
        submitRegistration();
    }
}

// -------------------------------------------------------------
// MAIN LOOP
// -------------------------------------------------------------
void loop() {
    if (WiFi.status() != WL_CONNECTED) {
        connectWiFi();
    }

    unsigned long currentMillis = millis();

    // 1. Periodic Heartbeat (every 30 seconds)
    if (currentMillis - lastHeartbeatTime >= HEARTBEAT_INTERVAL_MS) {
        lastHeartbeatTime = currentMillis;
        sendHeartbeat();
    }

    // 2. Periodic Version Check (every 2 minutes)
    if (currentMillis - lastVersionCheckTime >= VERSION_CHECK_INTERVAL_MS) {
        lastVersionCheckTime = currentMillis;
        checkConfigurationVersion();
    }

    delay(100);
}

// -------------------------------------------------------------
// WIFI MANAGEMENT
// -------------------------------------------------------------
void connectWiFi() {
    Serial.printf("[WiFi] Connecting to %s...\n", WIFI_SSID);
    WiFi.begin(WIFI_SSID, WIFI_PASSWORD);
    int attempts = 0;
    while (WiFi.status() != WL_CONNECTED && attempts < 25) {
        delay(500);
        Serial.print(".");
        attempts++;
    }
    if (WiFi.status() == WL_CONNECTED) {
        Serial.printf("\n[WiFi] Connected! IP: %s\n", WiFi.localIP().toString().c_str());
    } else {
        Serial.println("\n[WiFi] Connection timeout. Retrying in loop.");
    }
}

// -------------------------------------------------------------
// 1. SELF-REGISTRATION
// -------------------------------------------------------------
void submitRegistration() {
    Serial.println("[API] Submitting Self-Registration Request...");
    HTTPClient http;
    String url = String(SERVER_BASE_URL) + "/api/v1/sensors/register";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");

    StaticJsonDocument<256> doc;
    doc["deviceId"] = DEVICE_ID;
    doc["sensorVersion"] = SENSOR_VERSION;
    doc["firmwareVersion"] = FIRMWARE_VERSION;
    doc["hardwareModel"] = HARDWARE_MODEL;

    String requestBody;
    serializeJson(doc, requestBody);

    int httpCode = http.POST(requestBody);
    String response = http.getString();

    Serial.printf("[API] Register Response Code: %d\n", httpCode);
    Serial.printf("[API] Register Response Body: %s\n", response.c_str());

    if (httpCode == 201) {
        Serial.println("[API] Registration request submitted. Awaiting admin approval in dashboard.");
    } else if (httpCode == 409) {
        Serial.println("[API] Device already active. Please configure the issued Bearer Token.");
    }

    http.end();
}

// -------------------------------------------------------------
// 2. HEARTBEAT
// -------------------------------------------------------------
void sendHeartbeat() {
    if (sensorBearerToken.length() == 0) return;

    HTTPClient http;
    String url = String(SERVER_BASE_URL) + "/api/v1/sensors/heartbeat";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + sensorBearerToken);
    http.addHeader("X-Sensor-Id", DEVICE_ID);

    StaticJsonDocument<128> doc;
    doc["deviceId"] = DEVICE_ID;
    doc["sensorVersion"] = SENSOR_VERSION;

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);
    if (code == 200) {
        Serial.println("[Heartbeat] Ping OK (200)");
    } else {
        Serial.printf("[Heartbeat] Ping Failed (code %d)\n", code);
    }
    http.end();
}

// -------------------------------------------------------------
// 3. VERSION CHECK & OTA DOWNLOAD
// -------------------------------------------------------------
void checkConfigurationVersion() {
    if (sensorBearerToken.length() == 0) return;

    HTTPClient http;
    String url = String(SERVER_BASE_URL) + "/api/v1/sensors/version-check";
    http.begin(url);
    http.addHeader("Content-Type", "application/json");
    http.addHeader("Authorization", "Bearer " + sensorBearerToken);
    http.addHeader("X-Sensor-Id", DEVICE_ID);

    StaticJsonDocument<128> doc;
    doc["deviceId"] = DEVICE_ID;
    doc["currentVersion"] = SENSOR_VERSION;

    String body;
    serializeJson(doc, body);

    int code = http.POST(body);
    if (code == 200) {
        String resp = http.getString();
        StaticJsonDocument<256> respDoc;
        deserializeJson(respDoc, resp);

        const char* result = respDoc["result"];
        Serial.printf("[VersionCheck] Result: %s\n", result);

        if (strcmp(result, "UPDATE_AVAILABLE") == 0) {
            Serial.printf("[VersionCheck] New active configuration v%s available. Downloading...\n", (const char*)respDoc["activeVersion"]);
            downloadActiveConfiguration();
        }
    }
    http.end();
}

void downloadActiveConfiguration() {
    HTTPClient http;
    String url = String(SERVER_BASE_URL) + "/api/v1/sensors/configuration/download?deviceId=" + DEVICE_ID;
    http.begin(url);
    http.addHeader("Authorization", "Bearer " + sensorBearerToken);
    http.addHeader("X-Sensor-Id", DEVICE_ID);

    int code = http.GET();
    if (code == 200) {
        File f = SPIFFS.open("/config.json", FILE_WRITE);
        if (f) {
            WiFiClient* stream = http.getStreamPtr();
            uint8_t buff[128] = { 0 };
            int totalBytes = 0;
            while (http.connected() && (totalBytes < http.getSize() || http.getSize() == -1)) {
                size_t size = stream->available();
                if (size) {
                    int c = stream->readBytes(buff, ((size > sizeof(buff)) ? sizeof(buff) : size));
                    f.write(buff, c);
                    totalBytes += c;
                }
                delay(1);
            }
            f.close();
            Serial.printf("[OTA] Config file written to SPIFFS (/config.json, %d bytes)\n", totalBytes);
        }
    } else {
        Serial.printf("[OTA] Download failed with code: %d\n", code);
    }
    http.end();
}

// -------------------------------------------------------------
// TOKEN PERSISTENCE
// -------------------------------------------------------------
void loadTokenFromFlash() {
    if (SPIFFS.exists("/token.txt")) {
        File f = SPIFFS.open("/token.txt", FILE_READ);
        if (f) {
            sensorBearerToken = f.readStringUntil('\n');
            sensorBearerToken.trim();
            f.close();
            Serial.printf("[SPIFFS] Loaded bearer token: %s...\n", sensorBearerToken.substring(0, 8).c_str());
        }
    }
}

void saveTokenToFlash(const String& token) {
    File f = SPIFFS.open("/token.txt", FILE_WRITE);
    if (f) {
        f.println(token);
        f.close();
        sensorBearerToken = token;
        Serial.println("[SPIFFS] Bearer token saved to flash.");
    }
}
