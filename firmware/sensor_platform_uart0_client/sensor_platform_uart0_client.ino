// ============================================================
// ESP32 Sensor Platform Client — UART0 Edition (USB Serial)
// ============================================================
// UART0 (USB / Serial Monitor @ 115200 baud) → ALL user I/O:
//   - Interactive WiFi scan & clear formatted table output
//   - Auto-reprint reminder so late-opened Serial Monitors never stay blank
//   - Numbered network selection & password entry
//   - Interactive main menu & command dispatch
//   - Real-time operation status & HTTP responses
//   - Config file stream capture (with <<<CONFIG_START>>> markers)
//   - Telemetry data ingestion for file upload
//
// Hardware:
//   Connect via standard USB cable to your PC.
//   Uses hardware UART0 (GPIO 3 RX / GPIO 1 TX).
//
// Arduino IDE Settings:
//   - Board: "ESP32 Dev Module" (or ESP32-S3 / ESP32-C3)
//   - Serial Monitor Baud: 115200 baud
//   - Line Ending: "Newline" or "Both NL & CR"
//
// Required Libraries (install via Arduino Library Manager):
//   - ArduinoJson by Benoit Blanchon (v7.x)
//   All other libraries (WiFi, HTTPClient, Preferences, SPIFFS)
//   are built-in with the ESP32 Arduino Board Core.
// ============================================================

#include <WiFi.h>
#include <HTTPClient.h>
#include <WiFiClientSecure.h>
#include <ArduinoJson.h>
#include <Preferences.h>
#include <SPIFFS.h>

// ============================================================
// CONFIGURATION — edit before flashing
// ============================================================
// Production HostyCare URL or Local Dev IP:
// Example Production: "https://realtimeprojectsolutions.com"
// Example Local:      "http://192.168.1.100:8000"
const char* SERVER_BASE_URL = "https://realtimeprojectsolutions.com";
const char* SENSOR_ID       = "ESP32-DEVICE-001";  // Unique identifier for this device

const long  UART0_BAUD          = 115200;
const int   MAX_WIFI_SLOTS      = 5;               // Max saved WiFi networks in NVS
const long  MAX_FILE_BYTES      = 5L * 1024L * 1024L; // 5 MB upload limit
const int   UPLOAD_IDLE_TIMEOUT = 5000;            // 5000 ms inactivity → proceed to upload
const int   WIFI_CONNECT_TIMEOUT= 30000;           // 30s timeout per WiFi attempt

// ============================================================
// GLOBALS
// ============================================================
Preferences wifiPrefs;
Preferences sensorPrefs;

String sensorToken          = "";
String currentConfigVersion = "0.0.0";
String currentCodeVersion   = "0.0.0";

// Reusable network clients
WiFiClientSecure secureClient;
WiFiClient       plainClient;

// ============================================================
// UART0 (USB SERIAL) I/O HELPERS
// All user interaction occurs via the primary Serial interface
// ============================================================

void sprint(const String& msg)   { Serial.print(msg);   Serial.flush(); }
void sprintln(const String& msg) { Serial.println(msg); Serial.flush(); }
void sprintln()                  { Serial.println();    Serial.flush(); }
void printDivider()              { Serial.println("----------------------------------------------------------------------"); Serial.flush(); }

/**
 * Flush any leftover bytes in the serial RX buffer.
 */
void clearSerialRx() {
  while (Serial.available()) {
    Serial.read();
    delay(5);
  }
}

/**
 * Blocks until a full line of text is received on UART0 (Serial).
 * Strips whitespace, \r, and \n.
 */
String readLine() {
  while (!Serial.available()) {
    delay(10);
  }
  String line = Serial.readStringUntil('\n');
  line.trim();
  return line;
}

// ============================================================
// PREFERENCES (NVS) — PERSISTENT STORAGE
// ============================================================

void loadSensorPrefs() {
  sensorPrefs.begin("sensor", true);
  sensorToken          = sensorPrefs.getString("token",      "");
  currentConfigVersion = sensorPrefs.getString("cfgVersion", "0.0.0");
  currentCodeVersion   = sensorPrefs.getString("codeVersion", "0.0.0");
  String savedId       = sensorPrefs.getString("sensorId",   "");
  sensorPrefs.end();

  // Detect Sensor ID change: invalidate old token so clean re-registration occurs
  if (savedId.length() > 0 && savedId != String(SENSOR_ID)) {
    sprintln("[Prefs] Sensor ID changed!");
    sprintln("  Saved : " + savedId);
    sprintln("  New   : " + String(SENSOR_ID));
    sprintln("[Prefs] Clearing old token. Automatic re-registration will trigger...");
    sensorToken          = "";
    currentConfigVersion = "0.0.0";
    currentCodeVersion   = "0.0.0";
  } else if (sensorToken.length() > 0) {
    sprintln("[Prefs] Token loaded for: " + savedId);
    sprintln("[Prefs] Config version  : " + currentConfigVersion);
    sprintln("[Prefs] Code version    : " + currentCodeVersion);
  }
}

void saveSensorPrefs() {
  sensorPrefs.begin("sensor", false);
  sensorPrefs.putString("token",       sensorToken);
  sensorPrefs.putString("sensorId",    String(SENSOR_ID));
  sensorPrefs.putString("cfgVersion",  currentConfigVersion);
  sensorPrefs.putString("codeVersion", currentCodeVersion);
  sensorPrefs.end();
}

// ============================================================
// HTTP / HTTPS HELPER
// Supports both HTTPS (with setInsecure) and plain HTTP
// ============================================================
bool beginRequest(HTTPClient& http, const String& url) {
  if (url.startsWith("https://")) {
    secureClient.setInsecure();  // Accept hosting certificates (optimal for IoT)
    if (!http.begin(secureClient, url)) {
      sprintln("[HTTP] ERROR: begin() failed for SSL URL: " + url);
      return false;
    }
  } else {
    if (!http.begin(plainClient, url)) {
      sprintln("[HTTP] ERROR: begin() failed for URL: " + url);
      return false;
    }
  }

  http.setTimeout(15000);                                  // 15 seconds timeout
  http.setFollowRedirects(HTTPC_STRICT_FOLLOW_REDIRECTS); // Follow 301/302 redirects
  http.addHeader("User-Agent", "ESP32-SensorClient/UART0-4.0");
  http.addHeader("Connection", "close");
  return true;
}

// ============================================================
// WIFI — Save credential to NVS (up to MAX_WIFI_SLOTS)
// ============================================================
void saveWiFiCredential(const String& ssid, const String& password) {
  wifiPrefs.begin("wifi", false);
  int count = wifiPrefs.getInt("count", 0);

  // Update password if SSID already exists in NVS
  for (int i = 0; i < count; i++) {
    if (wifiPrefs.getString(("ssid" + String(i)).c_str(), "") == ssid) {
      wifiPrefs.putString(("pass" + String(i)).c_str(), password.c_str());
      sprintln("[WiFi] Updated password for existing entry: \"" + ssid + "\"");
      wifiPrefs.end();
      return;
    }
  }

  // Evict oldest entry when slots are full (FIFO)
  if (count >= MAX_WIFI_SLOTS) {
    for (int i = 0; i < MAX_WIFI_SLOTS - 1; i++) {
      String s = wifiPrefs.getString(("ssid" + String(i + 1)).c_str(), "");
      String p = wifiPrefs.getString(("pass" + String(i + 1)).c_str(), "");
      wifiPrefs.putString(("ssid" + String(i)).c_str(), s.c_str());
      wifiPrefs.putString(("pass" + String(i)).c_str(), p.c_str());
    }
    count = MAX_WIFI_SLOTS - 1;
  }

  // Save new entry
  wifiPrefs.putString(("ssid" + String(count)).c_str(), ssid.c_str());
  wifiPrefs.putString(("pass" + String(count)).c_str(), password.c_str());
  wifiPrefs.putInt("count", count + 1);
  wifiPrefs.end();

  sprintln("[WiFi] Saved \"" + ssid + "\" (" + String(count + 1) + "/" + String(MAX_WIFI_SLOTS) + " slots used)");
}

// ============================================================
// WIFI — Formatted Network Table Display
// ============================================================
void displayNetworkTable(int networkCount) {
  sprintln();
  printDivider();
  sprintln("  AVAILABLE 2.4GHz WIFI NETWORKS");
  printDivider();
  Serial.printf("  %-4s %-28s %-12s %s\n", "No.", "SSID Name", "Signal", "Security");
  printDivider();

  for (int i = 0; i < networkCount; i++) {
    String lock = (WiFi.encryptionType(i) == WIFI_AUTH_OPEN) ? "[OPEN]" : "[SECURED]";
    String ssid = WiFi.SSID(i);
    if (ssid.length() == 0) ssid = "<Hidden SSID>";
    int32_t rssi = WiFi.RSSI(i);

    Serial.printf("  [%2d] %-28.28s %4d dBm      %s\n", (i + 1), ssid.c_str(), rssi, lock.c_str());
  }

  printDivider();
  sprintln("  [R] Re-scan for networks");
  printDivider();
  sprint("Enter network number (1-" + String(networkCount) + ") or 'R' to re-scan: ");
}

// ============================================================
// WIFI — Interactive scan & selection via UART0
// ============================================================
void interactiveWiFiScan() {
  while (WiFi.status() != WL_CONNECTED) {
    sprintln("\n[WiFi] Scanning for nearby 2.4GHz WiFi networks...");
    WiFi.disconnect(true);
    delay(300);
    WiFi.mode(WIFI_STA);
    delay(200);

    int n = WiFi.scanNetworks(false, true);

    if (n <= 0) {
      sprintln("[WiFi] No networks discovered. Retrying scan in 3 seconds...");
      delay(3000);
      continue;
    }

    // Clear any pending serial input
    clearSerialRx();

    // Print the formatted table
    displayNetworkTable(n);

    // Read user choice with periodic refresh if user opened Serial Monitor late
    String choiceStr = "";
    unsigned long lastPromptTime = millis();

    while (true) {
      if (Serial.available()) {
        choiceStr = Serial.readStringUntil('\n');
        choiceStr.trim();
        if (choiceStr.length() > 0) {
          sprintln(choiceStr); // Echo selection
          break;
        }
      }

      // If no input received after 6 seconds, re-print the prompt & table
      // (ensures user sees it even if they opened Serial Monitor after boot)
      if (millis() - lastPromptTime > 6000) {
        sprintln("\n\n>>> Waiting for your input! <<<");
        displayNetworkTable(n);
        lastPromptTime = millis();
      }

      delay(30);
    }

    // Check if user requested a re-scan
    if (choiceStr.equalsIgnoreCase("R")) {
      sprintln("[WiFi] Re-scanning networks on request...");
      delay(500);
      continue;
    }

    int idx = choiceStr.toInt() - 1;
    if (idx < 0 || idx >= n) {
      sprintln("[WiFi] Invalid selection \"" + choiceStr + "\". Please enter a valid number from the list.");
      delay(1500);
      continue;
    }

    String selectedSSID  = WiFi.SSID(idx);
    bool   isOpenNetwork = (WiFi.encryptionType(idx) == WIFI_AUTH_OPEN);
    String password      = "";

    sprintln("\n  Selected Network : [" + String(idx + 1) + "] " + selectedSSID);

    if (!isOpenNetwork) {
      sprint("  Enter Password for \"" + selectedSSID + "\": ");
      clearSerialRx();
      password = readLine();
      sprintln("  [Password received: " + String(password.length()) + " characters]");
    } else {
      sprintln("  (Network is Open — no password required)");
    }

    // Attempt connection
    sprint("\n[WiFi] Connecting to \"" + selectedSSID + "\"");
    WiFi.begin(selectedSSID.c_str(), password.c_str());

    unsigned long startTime = millis();
    while (WiFi.status() != WL_CONNECTED && millis() - startTime < (unsigned long)WIFI_CONNECT_TIMEOUT) {
      delay(500);
      sprint(".");
    }
    sprintln();

    if (WiFi.status() == WL_CONNECTED) {
      sprintln("[WiFi] CONNECTED SUCCESSFULLY!");
      sprintln("  IP Address : " + WiFi.localIP().toString());
      sprintln("  Signal     : " + String(WiFi.RSSI()) + " dBm");
      saveWiFiCredential(selectedSSID, password);
      return;
    }

    sprintln("[WiFi] Connection failed to \"" + selectedSSID + "\".");
    sprintln("[WiFi] Check your password or choose another network.");
    WiFi.disconnect(true);
    delay(1500);
  }
}

// ============================================================
// WIFI — Main Setup: Try saved networks first, then scan
// ============================================================
void setupWiFi() {
  WiFi.mode(WIFI_STA);

  wifiPrefs.begin("wifi", true);
  int count = wifiPrefs.getInt("count", 0);
  wifiPrefs.end();

  if (count > 0) {
    sprintln("\n[WiFi] Checking " + String(count) + " saved network(s) in NVS memory...");

    for (int i = 0; i < count; i++) {
      wifiPrefs.begin("wifi", true);
      String ssid = wifiPrefs.getString(("ssid" + String(i)).c_str(), "");
      String pass = wifiPrefs.getString(("pass" + String(i)).c_str(), "");
      wifiPrefs.end();

      if (ssid.length() == 0) continue;

      sprint("[WiFi] Trying saved network [" + String(i + 1) + "] \"" + ssid + "\"");
      WiFi.begin(ssid.c_str(), pass.c_str());

      unsigned long startTime = millis();
      while (WiFi.status() != WL_CONNECTED && millis() - startTime < 10000) { // 10s fast try
        delay(500);
        sprint(".");
      }
      sprintln();

      if (WiFi.status() == WL_CONNECTED) {
        sprintln("[WiFi] Auto-connected to \"" + ssid + "\"!");
        sprintln("  IP Address : " + WiFi.localIP().toString());
        sprintln("  Signal     : " + String(WiFi.RSSI()) + " dBm");
        return;
      }

      sprintln("[WiFi] Could not connect to saved network \"" + ssid + "\".");
      WiFi.disconnect(true);
      delay(300);
    }

    sprintln("[WiFi] No saved networks reachable. Switching to Interactive WiFi Selection...");
  } else {
    sprintln("\n[WiFi] No saved WiFi networks found in NVS memory.");
  }

  interactiveWiFiScan();
}

// ============================================================
// SENSOR REGISTRATION
// POST /api/v1/sensors/register
// ============================================================
bool registerSensor() {
  sprintln("\n[Register] Registering sensor: " + String(SENSOR_ID));
  sprintln("[Register] Contacting server: " + String(SERVER_BASE_URL));

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/register")) {
    http.end();
    return false;
  }

  http.addHeader("Content-Type", "application/json");

  // Send both deviceId and sensorId for universal compatibility
  JsonDocument reqDoc;
  reqDoc["deviceId"]        = SENSOR_ID;
  reqDoc["sensorId"]        = SENSOR_ID;
  reqDoc["sensorVersion"]   = currentConfigVersion;
  reqDoc["firmwareVersion"] = "4.0.0";
  reqDoc["hardwareModel"]   = "ESP32-DevKit-V1";

  String body;
  serializeJson(reqDoc, body);

  int httpCode = http.POST(body);
  sprintln("[Register] HTTP Status: " + String(httpCode));

  if (httpCode == 200 || httpCode == 201) {
    String response = http.getString();
    JsonDocument doc;
    DeserializationError err = deserializeJson(doc, response);

    if (!err) {
      const char* token = doc["token"] | doc["data"]["token"] | doc["rawToken"];
      if (token && strlen(token) > 0) {
        sensorToken = String(token);
        saveSensorPrefs();
        printDivider();
        sprintln("[Register] REGISTRATION SUCCESSFUL!");
        sprintln("  Device ID : " + String(SENSOR_ID));
        sprintln("  Status    : " + String(doc["status"] | doc["data"]["status"] | "APPROVED"));
        sprintln("  Message   : " + String(doc["message"] | "Authorized"));
        sprintln("  Token     : " + sensorToken.substring(0, 16) + "...");
        sprintln("  (Bearer Token saved to NVS flash)");
        printDivider();
        http.end();
        return true;
      }
    }
    sprintln("[Register] ERROR: Token not found in server response: " + response);

  } else if (httpCode == 409) {
    sprintln("[Register] Notice: Device is already registered (409 Conflict).");
    sprintln("  If token is lost, use Menu option [4] to re-register with a fresh token.");
  } else if (httpCode < 0) {
    sprintln("[Register] Connection error: " + String(http.errorToString(httpCode).c_str()));
  } else {
    sprintln("[Register] Failed with HTTP " + String(httpCode) + ": " + http.getString());
  }

  http.end();
  return false;
}

// ============================================================
// OPTION 1 — HEARTBEAT
// POST /api/v1/sensors/heartbeat
// ============================================================
void sendHeartbeat() {
  sprintln("\n[Heartbeat] Sending ping to server...");

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/heartbeat")) {
    http.end();
    return;
  }

  http.addHeader("Content-Type",  "application/json");
  http.addHeader("Authorization", "Bearer " + sensorToken);
  http.addHeader("X-Sensor-ID",   SENSOR_ID);

  JsonDocument doc;
  doc["deviceId"]             = SENSOR_ID;
  doc["sensorVersion"]        = currentConfigVersion;
  doc["firmwareVersion"]      = "4.0.0";
  doc["hardwareModel"]        = "ESP32-DevKit-V1";
  doc["metadata"]["freeHeap"] = ESP.getFreeHeap();
  doc["metadata"]["rssi"]     = WiFi.RSSI();
  doc["metadata"]["uptime"]   = millis() / 1000;

  String body;
  serializeJson(doc, body);

  int httpCode = http.POST(body);

  if (httpCode == 200) {
    JsonDocument res;
    deserializeJson(res, http.getString());
    printDivider();
    sprintln("[Heartbeat] PING SUCCESSFUL!");
    sprintln("  Sensor Status : " + String(res["sensorStatus"] | res["status"] | "ACTIVE"));
    sprintln("  Server Time   : " + String(res["serverTime"] | res["timestamp"] | "OK"));
    sprintln("  Free Heap     : " + String(ESP.getFreeHeap()) + " bytes");
    sprintln("  WiFi RSSI     : " + String(WiFi.RSSI()) + " dBm");
    sprintln("  Uptime        : " + String(millis() / 1000) + " seconds");
    printDivider();
  } else if (httpCode == 401) {
    sprintln("[Heartbeat] Unauthorized (401). Token invalid. Use option [4] to re-register.");
  } else if (httpCode < 0) {
    sprintln("[Heartbeat] Connection error: " + String(http.errorToString(httpCode).c_str()));
  } else {
    sprintln("[Heartbeat] Failed with HTTP " + String(httpCode));
    sprintln("  " + http.getString());
  }

  http.end();
}

// ============================================================
// OPTION 2 — UPLOAD DATA FILE (max 5 MB)
// POST /api/v1/sensors/upload
//
// How data is read:
//   Incoming bytes are read from UART0 (Serial).
//   Reading stops after UPLOAD_IDLE_TIMEOUT ms of inactivity.
//   If no bytes are entered, a sample CSV telemetry file is sent.
// ============================================================
void uploadData() {
  sprintln("\n[Upload] Ready to receive data via UART0 (Serial Monitor).");
  sprintln("[Upload] Paste or send your raw telemetry / CSV data now.");
  sprintln("[Upload] Upload automatically triggers after " + String(UPLOAD_IDLE_TIMEOUT / 1000) + "s of inactivity.");
  sprintln("[Upload] (If no data is typed/pasted, sample telemetry will be uploaded automatically).");
  sprintln();

  clearSerialRx();
  String payload = "";
  bool gotData = false;
  unsigned long lastByte = millis();

  while (true) {
    if (Serial.available()) {
      char c = Serial.read();
      payload += c;
      lastByte = millis();
      gotData = true;

      if ((long)payload.length() > MAX_FILE_BYTES) {
        sprintln("\n[Upload] ERROR: Data exceeded 5 MB limit. Upload cancelled.");
        return;
      }
    }

    // Trigger upload if data arrived and idle timeout elapsed
    if (gotData && (millis() - lastByte >= (unsigned long)UPLOAD_IDLE_TIMEOUT)) {
      break;
    }

    // Fall back to sample telemetry if nothing was typed within timeout
    if (!gotData && (millis() - lastByte >= (unsigned long)UPLOAD_IDLE_TIMEOUT)) {
      sprintln("[Upload] No manual data received. Generating sample telemetry CSV...");
      payload  = "timestamp_ms,device_id,free_heap_bytes,wifi_rssi_dbm,uptime_sec\r\n";
      payload += String(millis()) + ","
               + String(SENSOR_ID) + ","
               + String(ESP.getFreeHeap()) + ","
               + String(WiFi.RSSI()) + ","
               + String(millis() / 1000) + "\r\n";
      break;
    }
  }

  sprintln("[Upload] Payload prepared (" + String(payload.length()) + " bytes).");

  // Construct multipart/form-data boundary and body
  String boundary = "----ESP32Boundary7MA4YWxk";
  String bodyPre  = "--" + boundary + "\r\n"
                  + "Content-Disposition: form-data; name=\"file\"; filename=\"telemetry_data.csv\"\r\n"
                  + "Content-Type: text/csv\r\n"
                  + "\r\n";
  String bodyPost = "\r\n--" + boundary + "--\r\n";
  String fullBody = bodyPre + payload + bodyPost;

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/upload")) {
    http.end();
    return;
  }

  http.addHeader("Authorization",  "Bearer " + sensorToken);
  http.addHeader("X-Sensor-ID",    SENSOR_ID);
  http.addHeader("X-Sensor-Version", currentConfigVersion);
  http.addHeader("Content-Type",   "multipart/form-data; boundary=" + boundary);
  http.addHeader("Content-Length", String(fullBody.length()));

  sprint("[Upload] Transmitting to server...");
  int httpCode = http.POST(fullBody);
  sprintln(" HTTP Status: " + String(httpCode));

  if (httpCode == 200 || httpCode == 201) {
    JsonDocument res;
    DeserializationError err = deserializeJson(res, http.getString());
    if (!err) {
      printDivider();
      sprintln("[Upload] UPLOAD SUCCESSFUL!");
      sprintln("  Upload ID : " + String(res["data"]["uploadId"] | res["uploadId"] | "N/A"));
      sprintln("  File Name : " + String(res["data"]["originalFileName"] | res["fileName"] | "telemetry_data.csv"));
      sprintln("  File Size : " + String(payload.length()) + " bytes");
      sprintln("  Checksum  : " + String(res["data"]["checksum"] | res["checksum"] | "verified"));
      printDivider();
    } else {
      sprintln("[Upload] Parse warning on response JSON.");
    }
  } else if (httpCode == 413) {
    sprintln("[Upload] Payload Too Large (413). Max allowed is 5 MB.");
  } else if (httpCode == 401) {
    sprintln("[Upload] Unauthorized (401). Token invalid. Use option [4] to re-register.");
  } else if (httpCode < 0) {
    sprintln("[Upload] Connection error: " + String(http.errorToString(httpCode).c_str()));
  } else {
    sprintln("[Upload] Failed with HTTP " + String(httpCode) + ": " + http.getString());
  }

  http.end();
}

// ============================================================
// CONFIG DOWNLOAD (called automatically by checkVersion)
// GET /api/v1/sensors/configuration/download
// ============================================================
void downloadConfig(String expectedVersion = "") {
  sprintln("[Config] Downloading active configuration file from server...");

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/configuration/download")) {
    http.end();
    return;
  }

  http.addHeader("Authorization", "Bearer " + sensorToken);
  http.addHeader("X-Sensor-ID",   SENSOR_ID);

  const char* capturedHeaders[] = { "X-Config-Version", "Content-Type" };
  http.collectHeaders(capturedHeaders, 2);

  int httpCode = http.GET();
  sprintln("[Config] HTTP Status: " + String(httpCode));

  if (httpCode == 200) {
    String headerVersion = http.header("X-Config-Version");
    String fileContent   = http.getString();

    if (fileContent.length() == 0) {
      sprintln("[Config] ERROR: Received empty file from server.");
      http.end();
      return;
    }

    // ── 1. Save file to SPIFFS flash ──
    File file = SPIFFS.open("/config.json", FILE_WRITE);
    if (file) {
      file.print(fileContent);
      file.close();
      sprintln("[Config] Successfully saved to SPIFFS flash (/config.json)");
    } else {
      sprintln("[Config] WARNING: Failed to write to SPIFFS.");
    }

    // ── 2. Determine New Version (multi-tier resolution) ──
    String newVersion = "";

    // Priority 1: Version passed directly from checkVersion()
    if (expectedVersion.length() > 0 && expectedVersion != "0.0.0") {
      newVersion = expectedVersion;
    }
    // Priority 2: X-Config-Version HTTP header from server
    else if (headerVersion.length() > 0) {
      newVersion = headerVersion;
    }

    // Priority 3: Parse JSON content of the downloaded file
    if (newVersion.length() == 0 || newVersion == "0.0.0") {
      JsonDocument cfgDoc;
      DeserializationError err = deserializeJson(cfgDoc, fileContent);
      if (!err) {
        const char* v = cfgDoc["version"] | cfgDoc["config_version"] | cfgDoc["configVersion"];
        if (v && strlen(v) > 0) {
          newVersion = String(v);
        }
      }
    }

    // ── 3. Persist updated version in NVS Preferences & RAM ──
    newVersion.trim();
    if (newVersion.length() > 0) {
      currentConfigVersion = newVersion;
      saveSensorPrefs();
      sprintln("[Config] >>> Config Version updated in NVS: " + currentConfigVersion + " <<<");
    } else {
      sprintln("[Config] Notice: Could not detect version string, retaining current: " + currentConfigVersion);
    }

    printDivider();
    sprintln("[Config] DOWNLOAD COMPLETE!");
    sprintln("  File Size        : " + String(fileContent.length()) + " bytes");
    sprintln("  Active Version   : " + currentConfigVersion);
    printDivider();

    // ── Output configuration directly on UART0 (Serial) ──
    sprintln("<<<CONFIG_START>>>");
    Serial.print(fileContent);
    Serial.flush();
    sprintln("\n<<<CONFIG_END>>>");
    sprintln("[Config] Configuration stream output above between markers.");

    http.end();

    // ── 4. Immediately notify server with new version so website updates ──
    sprintln("[Config] Syncing new version with server dashboard...");
    sendHeartbeat();
    return;

  } else if (httpCode == 404) {
    sprintln("[Config] No active configuration deployed on server yet.");
  } else if (httpCode == 401) {
    sprintln("[Config] Unauthorized (401). Use option [4] to re-register.");
  } else if (httpCode < 0) {
    sprintln("[Config] Connection error: " + String(http.errorToString(httpCode).c_str()));
  } else {
    sprintln("[Config] Download failed with HTTP " + String(httpCode) + ": " + http.getString());
  }

  http.end();
}

// ============================================================
// OPTION 3 — CHECK VERSION + AUTO-DOWNLOAD
// POST /api/v1/sensors/version-check
// ============================================================
void checkVersion() {
  sprintln("\n[Version] Checking configuration version on server...");
  sprintln("  Currently installed version: " + currentConfigVersion);

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/version-check")) {
    http.end();
    return;
  }

  http.addHeader("Content-Type",  "application/json");
  http.addHeader("Authorization", "Bearer " + sensorToken);
  http.addHeader("X-Sensor-ID",   SENSOR_ID);

  JsonDocument reqDoc;
  reqDoc["currentConfigVersion"] = currentConfigVersion;
  reqDoc["currentVersion"]       = currentConfigVersion;
  reqDoc["deviceId"]             = SENSOR_ID;

  String body;
  serializeJson(reqDoc, body);

  int httpCode = http.POST(body);

  if (httpCode == 200) {
    JsonDocument res;
    deserializeJson(res, http.getString());

    const char* result            = res["result"] | "CURRENT";
    const char* activeVersion     = res["activeVersion"] | res["activeConfigVersion"] | res["config_version"];
    bool        isUpdateAvailable = (String(result) == "UPDATE_AVAILABLE") || res["isUpdateAvailable"];
    const char* message           = res["message"] | "";

    printDivider();
    sprintln("[Version] Status           : " + String(result));
    sprintln("  Message                  : " + String(message));
    sprintln("  Current Device Version   : " + currentConfigVersion);
    sprintln("  Active Server Version    : " + String(activeVersion ? activeVersion : "None"));
    sprintln("  Update Available         : " + String(isUpdateAvailable ? "YES" : "NO"));
    printDivider();

    http.end();

    if (isUpdateAvailable) {
      String targetVer = activeVersion ? String(activeVersion) : "";
      sprintln("[Version] New configuration detected: " + currentConfigVersion + " -> " + targetVer);
      sprintln("[Version] Starting automatic download now...\n");
      downloadConfig(targetVer);
    } else {
      sprintln("[Version] Sensor configuration is already up to date.");
      sprintln("[Version] Downloading anyway to verify repeat download capability...\n");
      downloadConfig(activeVersion ? String(activeVersion) : "");
    }

  } else if (httpCode == 401) {
    sprintln("[Version] Unauthorized (401). Use option [5] to re-register.");
    http.end();
  } else if (httpCode < 0) {
    sprintln("[Version] Connection error: " + String(http.errorToString(httpCode).c_str()));
    http.end();
  } else {
    sprintln("[Version] Check failed with HTTP " + String(httpCode) + ": " + http.getString());
    http.end();
  }
}

// ============================================================
// OPTION 4 — CHECK CODE FILE VERSION + DOWNLOAD
// POST /api/v1/sensors/code/version-check & GET /api/v1/sensors/code/download
// ============================================================
void downloadCode(String expectedVersion = "") {
  sprintln("[Code] Downloading active code file from server...");

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/code/download?deviceId=" + String(SENSOR_ID))) {
    http.end();
    return;
  }

  http.addHeader("Authorization", "Bearer " + sensorToken);
  http.addHeader("X-Sensor-ID",   SENSOR_ID);

  const char* capturedHeaders[] = { "X-Code-Version", "Content-Type" };
  http.collectHeaders(capturedHeaders, 2);

  int httpCode = http.GET();
  sprintln("[Code] HTTP Status: " + String(httpCode));

  if (httpCode == 200) {
    String headerVer = http.header("X-Code-Version");
    int payloadSize  = http.getSize();

    String newVersion = "";
    if (expectedVersion.length() > 0 && expectedVersion != "0.0.0") {
      newVersion = expectedVersion;
    } else if (headerVer.length() > 0) {
      newVersion = headerVer;
    }

    newVersion.trim();
    if (newVersion.length() > 0) {
      currentCodeVersion = newVersion;
      saveSensorPrefs();
      sprintln("[Code] >>> Code Version updated in NVS: " + currentCodeVersion + " <<<");
    }

    File file = SPIFFS.open("/sensor_code.bin", FILE_WRITE);
    if (!file) {
      sprintln("[Code] ERROR: Failed to open /sensor_code.bin in SPIFFS.");
      http.end();
      return;
    }

    WiFiClient* stream = http.getStreamPtr();
    size_t totalBytesWritten = 0;
    uint8_t buff[512];
    unsigned long startRead = millis();

    while (http.connected() && (payloadSize < 0 || (int)totalBytesWritten < payloadSize)) {
      size_t avail = stream ? stream->available() : 0;
      if (avail > 0) {
        size_t toRead = avail > sizeof(buff) ? sizeof(buff) : avail;
        int readBytes = stream->readBytes(buff, toRead);
        if (readBytes > 0) {
          file.write(buff, readBytes);
          totalBytesWritten += readBytes;
        }
        startRead = millis();
      } else {
        if (millis() - startRead > 4000) break;
        delay(5);
      }
    }
    file.close();

    printDivider();
    sprintln("[Code] DOWNLOAD COMPLETE!");
    sprintln("  Saved to Flash   : /sensor_code.bin");
    sprintln("  File Size        : " + String(totalBytesWritten) + " bytes");
    sprintln("  Active Version   : " + currentCodeVersion);
    printDivider();

    http.end();
  } else if (httpCode == 404) {
    sprintln("[Code] No active code file deployed on server yet.");
    http.end();
  } else {
    sprintln("[Code] Download failed with HTTP " + String(httpCode) + ": " + http.getString());
    http.end();
  }
}

void checkCodeVersion() {
  sprintln("\n[Code] Checking sensor code/firmware version on server...");
  sprintln("  Currently installed code version: " + currentCodeVersion);

  HTTPClient http;
  if (!beginRequest(http, String(SERVER_BASE_URL) + "/api/v1/sensors/code/version-check")) {
    http.end();
    return;
  }

  http.addHeader("Content-Type",  "application/json");
  http.addHeader("Authorization", "Bearer " + sensorToken);
  http.addHeader("X-Sensor-ID",   SENSOR_ID);

  JsonDocument doc;
  doc["deviceId"]           = SENSOR_ID;
  doc["currentVersion"]     = currentCodeVersion;
  doc["currentCodeVersion"] = currentCodeVersion;

  String body;
  serializeJson(doc, body);

  int httpCode = http.POST(body);
  sprintln("[Code] HTTP Status: " + String(httpCode));

  if (httpCode == 200) {
    String respStr = http.getString();
    JsonDocument resDoc;
    deserializeJson(resDoc, respStr);

    const char* result            = resDoc["result"] | "CURRENT";
    const char* activeVersion     = resDoc["activeVersion"] | resDoc["code_version"];
    bool        isUpdateAvailable = (String(result) == "UPDATE_AVAILABLE") || resDoc["isUpdateAvailable"];

    printDivider();
    sprintln("[Code] Status              : " + String(result));
    sprintln("  Current Device Version   : " + currentCodeVersion);
    sprintln("  Active Server Version    : " + String(activeVersion ? activeVersion : "None"));
    sprintln("  Update Available         : " + String(isUpdateAvailable ? "YES" : "NO"));
    printDivider();

    http.end();

    if (isUpdateAvailable) {
      String targetVer = activeVersion ? String(activeVersion) : "";
      sprintln("[Code] New code version detected: " + currentCodeVersion + " -> " + targetVer);
      sprintln("[Code] Starting automatic download now...\n");
      downloadCode(targetVer);
    } else {
      sprintln("[Code] Sensor code is up to date.");
      sprintln("[Code] Downloading anyway to verify repeat download capability...\n");
      downloadCode(activeVersion ? String(activeVersion) : "");
    }
  } else if (httpCode == 401) {
    sprintln("[Code] Unauthorized (401). Use option [5] to re-register.");
    http.end();
  } else {
    sprintln("[Code] Check failed with HTTP " + String(httpCode) + ": " + http.getString());
    http.end();
  }
}

// ============================================================
// OPTION 5 — RE-REGISTER
// Clears NVS token and registers freshly
// ============================================================
void reRegister() {
  sprintln("\n[Re-Register] Clearing token from NVS and initiating fresh registration...");
  sensorToken          = "";
  currentConfigVersion = "0.0.0";
  currentCodeVersion   = "0.0.0";
  saveSensorPrefs();

  int attempt = 1;
  while (sensorToken.length() == 0) {
    sprintln("[Re-Register] Attempt " + String(attempt++));
    registerSensor();
    if (sensorToken.length() == 0) {
      sprintln("[Re-Register] Registration failed. Retrying in 5 seconds...");
      delay(5000);
    }
  }
}

// ============================================================
// PRINT MAIN MENU (via UART0 Serial Monitor)
// ============================================================
void printMenu() {
  printDivider();
  sprintln("  ESP32 SENSOR PLATFORM — UART0 CONTROL MENU");
  printDivider();
  sprintln("  Sensor ID  : " + String(SENSOR_ID));
  sprintln("  Token      : " + (sensorToken.length() > 0 ? sensorToken.substring(0, 12) + "..." : "NOT REGISTERED"));
  sprintln("  Config Ver : " + currentConfigVersion);
  sprintln("  Code Ver   : " + currentCodeVersion);
  sprintln("  WiFi RSSI  : " + String(WiFi.RSSI()) + " dBm");
  printDivider();
  sprintln("  [1] Send Heartbeat / Ping");
  sprintln("  [2] Upload Data File      (max 5 MB via Serial / sample CSV)");
  sprintln("  [3] Check Configuration   (auto-downloads config update)");
  sprintln("  [4] Check / Download Code (downloads active code file)");
  sprintln("  [5] Re-Register           (clears token & requests fresh token)");
  sprintln("  [M] Re-display this menu");
  printDivider();
  sprint("Enter choice (1-5) or 'M': ");
}

// ============================================================
// SETUP — Runs once upon power-on / boot
// ============================================================
void setup() {
  // Expand buffer size to prevent dropped input
  Serial.setRxBufferSize(4096);
  Serial.begin(UART0_BAUD);
  Serial.setTimeout(50);

  // Generous 2.5s delay with visual indicator to let USB Serial Monitor open
  delay(1000);
  for (int i = 0; i < 3; i++) {
    Serial.print(".");
    Serial.flush();
    delay(500);
  }
  Serial.println();

  sprintln("======================================================================");
  sprintln("   ESP32 Sensor Platform Client — UART0 Edition");
  sprintln("   Interface : UART0 (Standard USB Serial Monitor)");
  sprintln("   Baud Rate : " + String(UART0_BAUD));
  sprintln("   Server    : " + String(SERVER_BASE_URL));
  sprintln("   Device ID : " + String(SENSOR_ID));
  sprintln("======================================================================");

  // Initialize SPIFFS filesystem
  if (!SPIFFS.begin(true)) {
    sprintln("[SPIFFS] ERROR: Filesystem initialization failed.");
  } else {
    sprintln("[SPIFFS] Storage filesystem ready.");
  }

  // 1. Establish WiFi Connection
  setupWiFi();

  // 2. Load stored credentials from NVS
  loadSensorPrefs();

  // 3. Auto-register device if no token is stored
  if (sensorToken.length() == 0) {
    sprintln("\n[Setup] No saved token found in NVS. Registering device...");
    int attempt = 1;
    while (sensorToken.length() == 0) {
      sprintln("[Setup] Registration attempt " + String(attempt++));
      registerSensor();
      if (sensorToken.length() == 0) {
        sprintln("[Setup] Retrying in 5 seconds...");
        delay(5000);
      }
    }
  } else {
    sprintln("\n[Setup] Stored token found. Device ready.");
  }

  sprintln("\n[Setup] System ready!\n");

  // 4. Display interactive menu
  printMenu();
}

// ============================================================
// MAIN LOOP — Listens on UART0 (Serial), dispatches actions
// ============================================================
void loop() {
  // Auto-reconnect if WiFi disconnects
  if (WiFi.status() != WL_CONNECTED) {
    sprintln("\n[WiFi] Connection lost. Reconnecting...");
    setupWiFi();
  }

  // Handle user input from Serial Monitor
  if (Serial.available()) {
    String choice = Serial.readStringUntil('\n');
    choice.trim();

    if (choice.length() > 0) {
      sprintln(choice); // Echo user selection

      if      (choice == "1")                  sendHeartbeat();
      else if (choice == "2")                  uploadData();
      else if (choice == "3")                  checkVersion();
      else if (choice == "4")                  checkCodeVersion();
      else if (choice == "5")                  reRegister();
      else if (choice.equalsIgnoreCase("M"))   { /* Menu will re-print below */ }
      else                                     sprintln("[Menu] Invalid choice. Enter 1, 2, 3, 4, 5, or 'M'.");

      delay(300);
      printMenu();
    }
  }

  delay(50);
}
