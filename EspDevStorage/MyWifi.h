#ifndef MY_WIFI_H
#define MY_WIFI_H

#include <Arduino.h>

// Wi-Fi Lifecycle & Connection
void wifiInit();
bool wifiConnect(const char* ssid = nullptr, const char* pass = nullptr);
bool wifiIsConnected();
void wifiSaveCredentials(const char* ssid, const char* pass);
void wifiClearCredentials();
void wifiStatus();
void wifiDisconnect();

// Wi-Fi Scanning & Interactive Selection
int     wifiScan();                       // Scans and prints numbered list of available SSIDs
int     wifiGetScannedCount();            // Returns number of networks found in last scan
String  wifiGetScannedSSID(int index);    // 1-based index
int32_t wifiGetScannedRSSI(int index);    // 1-based index
bool    wifiIsScannedOpen(int index);     // Returns true if network has no password

// Saved Credentials Lookup
bool    wifiHasSavedCredentials();
String  wifiGetSavedSSID();

#endif // MY_WIFI_H
