#include "MyWifi.h"
#include "Config.h"
#include <WiFi.h>
#include <Preferences.h>

static Preferences _wifiPrefs;
static int _lastScannedCount = 0;

void wifiInit() {
  WiFi.mode(WIFI_STA);
  _wifiPrefs.begin("wifi_store", true); // read-only
  String savedSsid = _wifiPrefs.getString("ssid", "");
  String savedPass = _wifiPrefs.getString("pass", "");
  _wifiPrefs.end();

  if (savedSsid.length() > 0) {
    Serial.printf("[WiFi] Found saved credentials for SSID: '%s'. Auto-connecting...\n", savedSsid.c_str());
    wifiConnect(savedSsid.c_str(), savedPass.c_str());
  } else {
    Serial.println("[WiFi] No saved Wi-Fi credentials in flash memory.");
  }
}

bool wifiHasSavedCredentials() {
  _wifiPrefs.begin("wifi_store", true);
  String savedSsid = _wifiPrefs.getString("ssid", "");
  _wifiPrefs.end();
  return (savedSsid.length() > 0);
}

String wifiGetSavedSSID() {
  _wifiPrefs.begin("wifi_store", true);
  String savedSsid = _wifiPrefs.getString("ssid", "");
  _wifiPrefs.end();
  return savedSsid;
}

bool wifiConnect(const char* ssid, const char* pass) {
  String targetSsid = "";
  String targetPass = "";

  if (ssid != nullptr && strlen(ssid) > 0) {
    targetSsid = ssid;
    targetPass = pass ? pass : "";
  } else {
    _wifiPrefs.begin("wifi_store", true);
    targetSsid = _wifiPrefs.getString("ssid", "");
    targetPass = _wifiPrefs.getString("pass", "");
    _wifiPrefs.end();

    if (targetSsid.length() == 0) {
      Serial.println("[WiFi] Error: No SSID specified and no saved credentials.");
      return false;
    }
  }

  Serial.printf("[WiFi] Connecting to '%s'", targetSsid.c_str());
  WiFi.disconnect(true);
  delay(200);
  WiFi.mode(WIFI_STA);
  WiFi.begin(targetSsid.c_str(), targetPass.c_str());

  unsigned long start = millis();
  while (WiFi.status() != WL_CONNECTED && millis() - start < WIFI_CONNECT_TIMEOUT) {
    delay(500);
    Serial.print(".");
  }
  Serial.println();

  if (WiFi.status() == WL_CONNECTED) {
    Serial.printf("[WiFi] CONNECTED SUCCESSFULLY!\n");
    Serial.printf("  SSID       : %s\n", WiFi.SSID().c_str());
    Serial.printf("  IP Address : %s\n", WiFi.localIP().toString().c_str());
    Serial.printf("  Signal     : %d dBm\n", WiFi.RSSI());
    return true;
  } else {
    Serial.println("[WiFi] Connection failed or timed out. Check password or signal.");
    return false;
  }
}

bool wifiIsConnected() {
  return (WiFi.status() == WL_CONNECTED);
}

void wifiSaveCredentials(const char* ssid, const char* pass) {
  if (ssid == nullptr || strlen(ssid) == 0) return;
  _wifiPrefs.begin("wifi_store", false); // read/write
  _wifiPrefs.putString("ssid", ssid);
  _wifiPrefs.putString("pass", pass ? pass : "");
  _wifiPrefs.end();
  Serial.printf("[WiFi] Saved credentials for SSID '%s' to flash NVS.\n", ssid);
}

void wifiClearCredentials() {
  _wifiPrefs.begin("wifi_store", false);
  _wifiPrefs.clear();
  _wifiPrefs.end();
  Serial.println("[WiFi] Saved Wi-Fi credentials cleared from NVS.");
}

void wifiStatus() {
  wl_status_t st = WiFi.status();
  Serial.println("============== Wi-Fi Status ==============");
  switch (st) {
    case WL_CONNECTED:
      Serial.println("Status:        CONNECTED");
      Serial.printf("SSID:          %s\n", WiFi.SSID().c_str());
      Serial.printf("IP Address:    %s\n", WiFi.localIP().toString().c_str());
      Serial.printf("Gateway:       %s\n", WiFi.gatewayIP().toString().c_str());
      Serial.printf("Subnet:        %s\n", WiFi.subnetMask().toString().c_str());
      Serial.printf("MAC Address:   %s\n", WiFi.macAddress().c_str());
      Serial.printf("Signal (RSSI): %d dBm\n", WiFi.RSSI());
      break;
    case WL_IDLE_STATUS:     Serial.println("Status:        IDLE"); break;
    case WL_NO_SSID_AVAIL:   Serial.println("Status:        NO SSID AVAILABLE"); break;
    case WL_CONNECT_FAILED:  Serial.println("Status:        CONNECT FAILED"); break;
    case WL_CONNECTION_LOST: Serial.println("Status:        CONNECTION LOST"); break;
    case WL_DISCONNECTED:    Serial.println("Status:        DISCONNECTED"); break;
    default:                 Serial.println("Status:        UNKNOWN"); break;
  }
  Serial.println("==========================================");
}

void wifiDisconnect() {
  WiFi.disconnect(true);
  Serial.println("[WiFi] Disconnected.");
}

static const char* getAuthTypeStr(wifi_auth_mode_t authType) {
  switch (authType) {
    case WIFI_AUTH_OPEN: return "[OPEN]";
    case WIFI_AUTH_WEP: return "[WEP]";
    case WIFI_AUTH_WPA_PSK: return "[WPA]";
    case WIFI_AUTH_WPA2_PSK: return "[WPA2]";
    case WIFI_AUTH_WPA_WPA2_PSK: return "[WPA/WPA2]";
    case WIFI_AUTH_WPA2_ENTERPRISE: return "[WPA2-ENT]";
    case WIFI_AUTH_WPA3_PSK: return "[WPA3]";
    case WIFI_AUTH_WPA2_WPA3_PSK: return "[WPA2/WPA3]";
    default: return "[SECURED]";
  }
}

int wifiScan() {
  Serial.println("\n[WiFi] Scanning for available 2.4GHz Wi-Fi networks...");
  WiFi.mode(WIFI_STA);
  int n = WiFi.scanNetworks(false, true);
  _lastScannedCount = (n > 0) ? n : 0;

  if (n <= 0) {
    Serial.println("[WiFi] No networks found. Try again or check antenna.");
    return 0;
  }

  Serial.println("\n---------------------------------------------------------------");
  Serial.printf("  AVAILABLE 2.4GHz WI-FI NETWORKS (Found: %d)\n", n);
  Serial.println("---------------------------------------------------------------");
  Serial.printf("  %-4s %-28s %-12s %s\n", "No.", "SSID Name", "Signal", "Security");
  Serial.println("---------------------------------------------------------------");
  for (int i = 0; i < n; ++i) {
    String ssid = WiFi.SSID(i);
    if (ssid.length() == 0) ssid = "<Hidden SSID>";
    int32_t rssi = WiFi.RSSI(i);
    const char* sec = getAuthTypeStr(WiFi.encryptionType(i));
    Serial.printf("  [%2d] %-28.28s %4d dBm      %s\n", i + 1, ssid.c_str(), rssi, sec);
  }
  Serial.println("---------------------------------------------------------------");
  return n;
}

int wifiGetScannedCount() {
  return _lastScannedCount;
}

String wifiGetScannedSSID(int index) {
  int zeroIndex = index - 1;
  if (zeroIndex >= 0 && zeroIndex < _lastScannedCount) {
    return WiFi.SSID(zeroIndex);
  }
  return "";
}

int32_t wifiGetScannedRSSI(int index) {
  int zeroIndex = index - 1;
  if (zeroIndex >= 0 && zeroIndex < _lastScannedCount) {
    return WiFi.RSSI(zeroIndex);
  }
  return 0;
}

bool wifiIsScannedOpen(int index) {
  int zeroIndex = index - 1;
  if (zeroIndex >= 0 && zeroIndex < _lastScannedCount) {
    return (WiFi.encryptionType(zeroIndex) == WIFI_AUTH_OPEN);
  }
  return false;
}
