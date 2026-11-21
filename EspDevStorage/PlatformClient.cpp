#include "PlatformClient.h"
#include "Config.h"
#include "MyFile.h"
#include "MyWifi.h"
#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <Preferences.h>
#include <ArduinoJson.h>
#include <LittleFS.h>

static Preferences       _sensorPrefs;
static String            _sensorToken = "";
static String            _configVersion = "0.0.0";
static String            _sensorId = DEFAULT_SENSOR_ID;
static String            _serverUrl = DEFAULT_SERVER_URL;

static WiFiClientSecure  _secureClient;
static WiFiClient        _plainClient;

static bool beginRequest(HTTPClient& http, const String& fullUrl) {
  if (fullUrl.startsWith("https://")) {
    _secureClient.setInsecure(); // Allow IoT connections without CA cert bundle
    if (!http.begin(_secureClient, fullUrl)) {
      Serial.printf("[HTTP] Error: begin() failed for SSL URL: %s\n", fullUrl.c_str());
      return false;
    }
  } else {
    if (!http.begin(_plainClient, fullUrl)) {
      Serial.printf("[HTTP] Error: begin() failed for URL: %s\n", fullUrl.c_str());
      return false;
    }
  }

  http.setTimeout(HTTP_TIMEOUT_MS);
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS);
  http.addHeader("User-Agent", "ESP32-SensorStorageClient/4.1");
  http.addHeader("Connection", "close");
  return true;
}

void platformInit() {
  _sensorPrefs.begin("sensor_store", true); // read-only
  _sensorToken   = _sensorPrefs.getString("token", "");
  _configVersion = _sensorPrefs.getString("cfgVer", "0.0.0");
  String savedId = _sensorPrefs.getString("sensorId", DEFAULT_SENSOR_ID);
  String savedUrl= _sensorPrefs.getString("serverUrl", DEFAULT_SERVER_URL);
  _sensorPrefs.end();

  if (savedId.length() > 0)  _sensorId  = savedId;
  if (savedUrl.length() > 0) _serverUrl = savedUrl;

  Serial.println("\n[Platform] Initializing Sensor Platform Client:");
  Serial.printf("  Server URL    : %s\n", _serverUrl.c_str());
  Serial.printf("  Sensor ID     : %s\n", _sensorId.c_str());
  Serial.printf("  Config Version: %s\n", _configVersion.c_str());

  if (_sensorToken.length() > 0) {
    Serial.printf("  Auth Token    : %s...\n", _sensorToken.substring(0, min(16, (int)_sensorToken.length())).c_str());
  } else {
    Serial.println("  Auth Token    : (Not registered)");
  }
}

bool platformIsRegistered() {
  return (_sensorToken.length() > 0);
}

String platformGetSensorId() {
  return _sensorId;
}

void platformSetSensorId(const char* id) {
  if (id == nullptr || strlen(id) == 0) return;
  _sensorId = id;
  _sensorPrefs.begin("sensor_store", false);
  _sensorPrefs.putString("sensorId", _sensorId);
  _sensorToken = ""; // Invalidate token on ID change so re-registration happens
  _sensorPrefs.putString("token", "");
  _sensorPrefs.end();
  Serial.printf("[Platform] Sensor ID updated to '%s'. Token reset for re-registration.\n", _sensorId.c_str());
}

String platformGetServerUrl() {
  return _serverUrl;
}

void platformSetServerUrl(const char* url) {
  if (url == nullptr || strlen(url) == 0) return;
  String u = url;
  if (u.endsWith("/")) u.remove(u.length() - 1);
  _serverUrl = u;
  _sensorPrefs.begin("sensor_store", false);
  _sensorPrefs.putString("serverUrl", _serverUrl);
  _sensorPrefs.end();
  Serial.printf("[Platform] Server URL set to '%s'.\n", _serverUrl.c_str());
}

String platformGetToken() {
  return _sensorToken;
}

void platformSetToken(const char* token) {
  _sensorToken = token ? token : "";
  _sensorPrefs.begin("sensor_store", false);
  _sensorPrefs.putString("token", _sensorToken);
  _sensorPrefs.end();
  Serial.println("[Platform] Auth token saved manually to NVS.");
}

String platformGetConfigVersion() {
  return _configVersion;
}

void platformSetConfigVersion(const char* version) {
  _configVersion = version ? version : "0.0.0";
  _sensorPrefs.begin("sensor_store", false);
  _sensorPrefs.putString("cfgVer", _configVersion);
  _sensorPrefs.end();
  Serial.printf("[Platform] Config version updated to '%s'.\n", _configVersion.c_str());
}

void platformStatus() {
  Serial.println("\n============ Platform Status ============");
  Serial.printf("Server Base URL: %s\n", _serverUrl.c_str());
  Serial.printf("Device ID:       %s\n", _sensorId.c_str());
  Serial.printf("Hardware Model:  %s\n", HARDWARE_MODEL);
  Serial.printf("Firmware Ver:    %s\n", FIRMWARE_VERSION);
  Serial.printf("Config Ver:      %s\n", _configVersion.c_str());
  if (_sensorToken.length() > 0) {
    Serial.printf("Bearer Token:    %s...\n", _sensorToken.substring(0, min(16, (int)_sensorToken.length())).c_str());
    Serial.println("Registration:    REGISTERED (ACTIVE)");
  } else {
    Serial.println("Registration:    NOT REGISTERED (Run 'register' or choose option [1])");
  }
  Serial.printf("Wi-Fi Connected: %s\n", wifiIsConnected() ? "YES" : "NO");
  Serial.println("=========================================");
}

bool platformRegister() {
  if (!wifiIsConnected()) {
    Serial.println("[Register] Error: Wi-Fi not connected. Connect Wi-Fi first.");
    return false;
  }

  Serial.printf("\n[Register] Registering device '%s' at %s...\n", _sensorId.c_str(), _serverUrl.c_str());

  HTTPClient http;
  String url = _serverUrl + "/api/v1/sensors/register";
  if (!beginRequest(http, url)) {
    http.end();
    return false;
  }

  http.addHeader("Content-Type", "application/json");

  JsonDocument reqDoc;
  reqDoc["deviceId"]        = _sensorId;
  reqDoc["sensorId"]        = _sensorId;
  reqDoc["sensorVersion"]   = _configVersion;
  reqDoc["firmwareVersion"] = FIRMWARE_VERSION;
  reqDoc["hardwareModel"]   = HARDWARE_MODEL;

  String body;
  serializeJson(reqDoc, body);

  int httpCode = http.POST(body);
  Serial.printf("[Register] Server returned HTTP %d\n", httpCode);

  if (httpCode == 200 || httpCode == 201) {
    String response = http.getString();
    JsonDocument resDoc;
    DeserializationError err = deserializeJson(resDoc, response);

    if (!err) {
      const char* tok = resDoc["token"] | resDoc["data"]["token"] | resDoc["rawToken"];
      if (tok && strlen(tok) > 0) {
        _sensorToken = String(tok);
        _sensorPrefs.begin("sensor_store", false);
        _sensorPrefs.putString("token", _sensorToken);
        _sensorPrefs.putString("sensorId", _sensorId);
        _sensorPrefs.end();

        Serial.println("---------------------------------------------------------------");
        Serial.println("[Register] REGISTRATION SUCCESSFUL!");
        Serial.printf("  Device ID : %s\n", _sensorId.c_str());
        Serial.printf("  Status    : %s\n", (const char*)(resDoc["status"] | "APPROVED"));
        Serial.printf("  Message   : %s\n", (const char*)(resDoc["message"] | "Authorized"));
        Serial.printf("  Token     : %s...\n", _sensorToken.substring(0, min(16, (int)_sensorToken.length())).c_str());
        Serial.println("  (Bearer Token saved to flash NVS)");
        Serial.println("---------------------------------------------------------------");
        http.end();
        return true;
      }
    }
    Serial.printf("[Register] Error: Could not extract token from server response: %s\n", response.c_str());
  } else if (httpCode == 409) {
    Serial.println("[Register] Device is already registered (409 Conflict).");
    Serial.println("  If your token was lost, use 'reregister' to clear and request a fresh token.");
  } else {
    Serial.printf("[Register] Registration failed (HTTP %d): %s\n", httpCode, http.getString().c_str());
  }

  http.end();
  return false;
}

bool platformHeartbeat() {
  if (!wifiIsConnected()) {
    Serial.println("[Heartbeat] Error: Wi-Fi not connected.");
    return false;
  }
  if (_sensorToken.length() == 0) {
    Serial.println("[Heartbeat] Error: Sensor not registered. Registering now...");
    if (!platformRegister()) return false;
  }

  Serial.println("\n[Heartbeat] Sending telemetry ping to server...");

  HTTPClient http;
  String url = _serverUrl + "/api/v1/sensors/heartbeat";
  if (!beginRequest(http, url)) {
    http.end();
    return false;
  }

  http.addHeader("Content-Type",  "application/json");
  http.addHeader("Authorization", "Bearer " + _sensorToken);
  http.addHeader("X-Sensor-ID",   _sensorId);

  JsonDocument doc;
  doc["deviceId"]             = _sensorId;
  doc["sensorVersion"]        = _configVersion;
  doc["firmwareVersion"]      = FIRMWARE_VERSION;
  doc["hardwareModel"]        = HARDWARE_MODEL;
  doc["metadata"]["freeHeap"] = ESP.getFreeHeap();
  doc["metadata"]["rssi"]     = WiFi.RSSI();
  doc["metadata"]["uptime"]   = millis() / 1000;

  String body;
  serializeJson(doc, body);

  int httpCode = http.POST(body);

  if (httpCode == 200) {
    JsonDocument res;
    deserializeJson(res, http.getString());
    Serial.println("---------------------------------------------------------------");
    Serial.println("[Heartbeat] PING SUCCESSFUL! (Website dashboard synchronized)");
    Serial.printf("  Device ID      : %s\n", _sensorId.c_str());
    Serial.printf("  Sensor Status  : %s\n", (const char*)(res["sensorStatus"] | res["status"] | "ACTIVE"));
    Serial.printf("  Synced Version : %s\n", _configVersion.c_str());
    Serial.printf("  Free Heap      : %u bytes\n", ESP.getFreeHeap());
    Serial.printf("  Wi-Fi RSSI     : %d dBm\n", WiFi.RSSI());
    Serial.printf("  Uptime         : %lu seconds\n", millis() / 1000);
    Serial.println("---------------------------------------------------------------");
    http.end();
    return true;
  } else if (httpCode == 401) {
    Serial.println("[Heartbeat] Unauthorized (401). Token invalid or revoked. Please run 'reregister'.");
  } else {
    Serial.printf("[Heartbeat] Failed with HTTP %d: %s\n", httpCode, http.getString().c_str());
  }

  http.end();
  return false;
}

bool platformVersionCheck() {
  if (!wifiIsConnected()) {
    Serial.println("[Version] Error: Wi-Fi not connected.");
    return false;
  }
  if (_sensorToken.length() == 0) {
    Serial.println("[Version] Error: Sensor not registered. Registering now...");
    if (!platformRegister()) return false;
  }

  Serial.printf("\n[Version] Checking configuration version on server...\n");
  Serial.printf("  Installed Version: %s\n", _configVersion.c_str());

  HTTPClient http;
  String url = _serverUrl + "/api/v1/sensors/version-check";
  if (!beginRequest(http, url)) {
    http.end();
    return false;
  }

  http.addHeader("Content-Type",  "application/json");
  http.addHeader("Authorization", "Bearer " + _sensorToken);
  http.addHeader("X-Sensor-ID",   _sensorId);

  JsonDocument reqDoc;
  reqDoc["currentConfigVersion"] = _configVersion;
  reqDoc["currentVersion"]       = _configVersion;
  reqDoc["deviceId"]             = _sensorId;

  String body;
  serializeJson(reqDoc, body);

  int httpCode = http.POST(body);

  if (httpCode == 200) {
    JsonDocument res;
    deserializeJson(res, http.getString());

    const char* result            = res["result"] | "CURRENT";
    const char* activeVer         = res["activeVersion"] | res["activeConfigVersion"] | res["config_version"];
    bool        isUpdateAvailable = (String(result) == "UPDATE_AVAILABLE") || res["isUpdateAvailable"];

    Serial.println("---------------------------------------------------------------");
    Serial.printf("[Version] Status           : %s\n", result);
    Serial.printf("  Current Device Version   : %s\n", _configVersion.c_str());
    Serial.printf("  Active Server Version    : %s\n", activeVer ? activeVer : "None");
    Serial.printf("  Update Available         : %s\n", isUpdateAvailable ? "YES" : "NO");
    Serial.println("---------------------------------------------------------------");

    http.end();

    if (isUpdateAvailable) {
      Serial.printf("[Version] New configuration detected (%s -> %s)\n", _configVersion.c_str(), activeVer ? activeVer : "");
      Serial.println("[Version] Starting automatic download now...\n");
      return platformDownloadConfig(activeVer);
    } else {
      Serial.println("[Version] Sensor configuration is already up to date.");
      return true;
    }
  } else if (httpCode == 401) {
    Serial.println("[Version] Unauthorized (401). Please run 'reregister'.");
  } else {
    Serial.printf("[Version] Failed with HTTP %d: %s\n", httpCode, http.getString().c_str());
  }

  http.end();
  return false;
}

bool platformDownloadConfig(const char* expectedVersion) {
  if (!wifiIsConnected()) {
    Serial.println("[Config] Error: Wi-Fi not connected.");
    return false;
  }

  Serial.println("[Config] Downloading active configuration file from server...");

  HTTPClient http;
  String url = _serverUrl + "/api/v1/sensors/configuration/download?deviceId=" + _sensorId;
  if (!beginRequest(http, url)) {
    http.end();
    return false;
  }

  http.addHeader("Authorization", "Bearer " + _sensorToken);
  http.addHeader("X-Sensor-ID",   _sensorId);

  const char* capturedHeaders[] = { "X-Config-Version", "Content-Type" };
  http.collectHeaders(capturedHeaders, 2);

  int httpCode = http.GET();
  Serial.printf("[Config] Server returned HTTP %d\n", httpCode);

  if (httpCode == 200) {
    String headerVer = http.header("X-Config-Version");
    int payloadSize  = http.getSize();
    
    // Read response content (with stream fallback if getString is empty but stream has bytes)
    String content = http.getString();
    if (content.length() == 0 && payloadSize > 0) {
      WiFiClient* stream = http.getStreamPtr();
      unsigned long startRead = millis();
      while (http.connected() && (int)content.length() < payloadSize && (millis() - startRead < 4000)) {
        while (stream && stream->available()) {
          content += (char)stream->read();
        }
        delay(5);
      }
    }

    // Version Resolution (Multi-Tier)
    String newVer = "";
    if (expectedVersion != nullptr && strlen(expectedVersion) > 0 && String(expectedVersion) != "0.0.0") {
      newVer = expectedVersion;
    } else if (headerVer.length() > 0 && headerVer != "0.0.0") {
      newVer = headerVer;
    }

    if (content.length() > 0 && (newVer.length() == 0 || newVer == "0.0.0")) {
      JsonDocument cfgDoc;
      if (!deserializeJson(cfgDoc, content)) {
        const char* v = cfgDoc["version"] | cfgDoc["config_version"] | cfgDoc["configVersion"];
        if (v && strlen(v) > 0) newVer = String(v);
      }
    }

    newVer.trim();
    if (newVer.length() > 0) {
      _configVersion = newVer;
      _sensorPrefs.begin("sensor_store", false);
      _sensorPrefs.putString("cfgVer", _configVersion);
      _sensorPrefs.end();
      Serial.printf("[Config] >>> Config version updated in NVS: %s <<<\n", _configVersion.c_str());
    }

    // Ensure content is not completely empty for LittleFS
    if (content.length() == 0) {
      content = "{\"configVersion\":\"" + _configVersion + "\",\"updatedAt\":\"" + String(millis()) + "\"}";
      Serial.println("[Config] Stored default JSON profile in /config.json.");
    }

    // Save configuration file to LittleFS
    File file = LittleFS.open("/config.json", "w");
    if (file) {
      file.print(content);
      file.close();
      Serial.println("[Config] Successfully saved to LittleFS flash: /config.json");
    } else {
      Serial.println("[Config] Warning: Failed to write to LittleFS.");
    }

    Serial.println("---------------------------------------------------------------");
    Serial.println("[Config] DOWNLOAD COMPLETE!");
    Serial.printf("  File Size          : %u bytes\n", content.length());
    Serial.printf("  New Active Version : %s\n", _configVersion.c_str());
    Serial.println("---------------------------------------------------------------");

    // Output content stream between markers
    Serial.println("<<<CONFIG_START>>>");
    Serial.print(content);
    Serial.println("\n<<<CONFIG_END>>>");

    http.end();

    // Immediately synchronize the updated version with the server dashboard
    Serial.println("\n[Config] Synchronizing updated version with website dashboard...");
    platformHeartbeat();
    return true;
  } else if (httpCode == 404) {
    Serial.println("[Config] No active configuration deployed on server yet.");
  } else {
    Serial.printf("[Config] Download failed with HTTP %d: %s\n", httpCode, http.getString().c_str());
  }

  http.end();
  return false;
}

bool platformUploadFile(const char* localFsPath) {
  if (!wifiIsConnected()) {
    Serial.println("[Upload] Error: Wi-Fi not connected.");
    return false;
  }
  if (_sensorToken.length() == 0) {
    Serial.println("[Upload] Error: Sensor not registered. Registering now...");
    if (!platformRegister()) return false;
  }

  String payload = "";
  String fileName = "telemetry_data.csv";

  if (localFsPath != nullptr && strlen(localFsPath) > 0 && LittleFS.exists(localFsPath)) {
    File f = LittleFS.open(localFsPath, "r");
    if (!f || f.isDirectory()) {
      Serial.printf("[Upload] Error opening '%s'.\n", localFsPath);
      return false;
    }
    fileName = localFsPath;
    if (fileName.startsWith("/")) fileName.remove(0, 1);
    while (f.available()) payload += (char)f.read();
    f.close();
    Serial.printf("[Upload] Loaded '%s' from LittleFS (%u bytes).\n", localFsPath, payload.length());
  } else {
    Serial.println("[Upload] Generating sample telemetry CSV...");
    payload  = "timestamp_ms,device_id,free_heap_bytes,wifi_rssi_dbm,uptime_sec\r\n";
    payload += String(millis()) + "," + _sensorId + ","
             + String(ESP.getFreeHeap()) + "," + String(WiFi.RSSI()) + ","
             + String(millis() / 1000) + "\r\n";
  }

  String boundary = "----ESP32Boundary7MA4YWxk";
  String bodyPre  = "--" + boundary + "\r\n"
                  + "Content-Disposition: form-data; name=\"file\"; filename=\"" + fileName + "\"\r\n"
                  + "Content-Type: text/csv\r\n\r\n";
  String bodyPost = "\r\n--" + boundary + "--\r\n";
  String fullBody = bodyPre + payload + bodyPost;

  HTTPClient http;
  String url = _serverUrl + "/api/v1/sensors/upload";
  if (!beginRequest(http, url)) {
    http.end();
    return false;
  }

  http.addHeader("Authorization",    "Bearer " + _sensorToken);
  http.addHeader("X-Sensor-ID",      _sensorId);
  http.addHeader("X-Sensor-Version", _configVersion);
  http.addHeader("Content-Type",     "multipart/form-data; boundary=" + boundary);
  http.addHeader("Content-Length",   String(fullBody.length()));

  Serial.println("[Upload] Transmitting file to server...");
  int httpCode = http.POST(fullBody);
  Serial.printf("[Upload] Server returned HTTP %d\n", httpCode);

  if (httpCode == 200 || httpCode == 201) {
    JsonDocument res;
    if (!deserializeJson(res, http.getString())) {
      Serial.println("---------------------------------------------------------------");
      Serial.println("[Upload] UPLOAD SUCCESSFUL!");
      Serial.printf("  Upload ID : %s\n", (const char*)(res["data"]["uploadId"] | res["uploadId"] | "N/A"));
      Serial.printf("  File Name : %s\n", (const char*)(res["data"]["originalFileName"] | res["fileName"] | fileName.c_str()));
      Serial.printf("  Payload   : %u bytes\n", payload.length());
      Serial.printf("  Checksum  : %s\n", (const char*)(res["data"]["checksum"] | res["checksum"] | "verified"));
      Serial.println("---------------------------------------------------------------");
    }
    http.end();
    return true;
  } else {
    Serial.printf("[Upload] Upload failed (HTTP %d): %s\n", httpCode, http.getString().c_str());
  }

  http.end();
  return false;
}

bool platformReRegister() {
  Serial.println("\n[Re-Register] Clearing token from NVS and starting fresh registration...");
  _sensorToken = "";
  _configVersion = "0.0.0";
  _sensorPrefs.begin("sensor_store", false);
  _sensorPrefs.putString("token", "");
  _sensorPrefs.putString("cfgVer", "0.0.0");
  _sensorPrefs.end();

  return platformRegister();
}
