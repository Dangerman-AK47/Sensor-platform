#ifndef PLATFORM_CLIENT_H
#define PLATFORM_CLIENT_H

#include <Arduino.h>

// Platform Lifecycle & Persistence
void platformInit();
bool platformIsRegistered();
void platformStatus();

// Getters & Setters
String platformGetSensorId();
void   platformSetSensorId(const char* id);
String platformGetServerUrl();
void   platformSetServerUrl(const char* url);
String platformGetToken();
void   platformSetToken(const char* token);
String platformGetConfigVersion();
void   platformSetConfigVersion(const char* version);

// Platform Operations
bool platformRegister();
bool platformHeartbeat();
bool platformVersionCheck();
bool platformDownloadConfig(const char* expectedVersion = nullptr);
bool platformUploadFile(const char* localFsPath = nullptr);
bool platformReRegister();

#endif // PLATFORM_CLIENT_H
