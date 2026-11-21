#include "MyFile.h"
#include <LittleFS.h>

static bool _fsMounted = false;

bool fsBegin() {
  if (LittleFS.begin(true)) {
    _fsMounted = true;
    Serial.println("[FS] LittleFS mounted successfully.");
    return true;
  }
  _fsMounted = false;
  Serial.println("[FS] Error: Failed to mount LittleFS!");
  return false;
}

bool fsIsMounted() {
  return _fsMounted;
}

bool fsExists(const char* path) {
  if (!_fsMounted || path == nullptr) return false;
  return LittleFS.exists(path);
}

size_t fsGetSize(const char* path) {
  if (!_fsMounted || path == nullptr) return 0;
  File file = LittleFS.open(path, "r");
  if (!file) return 0;
  size_t s = file.size();
  file.close();
  return s;
}

void fsList(const char* path) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  const char* targetPath = (path == nullptr || strlen(path) == 0) ? "/" : path;
  File dir = LittleFS.open(targetPath);
  if (!dir || !dir.isDirectory()) {
    Serial.printf("[FS] Error: Directory '%s' not found or invalid.\n", targetPath);
    return;
  }

  Serial.printf("[FS] Directory listing for: %s\n", targetPath);
  Serial.println("Type    Size (B)   Name");
  Serial.println("----------------------------------------");

  File file = dir.openNextFile();
  int count = 0;
  while (file) {
    if (file.isDirectory()) {
      Serial.printf("DIR     %-10s %s\n", "-", file.name());
    } else {
      Serial.printf("FILE    %-10u %s\n", (unsigned int)file.size(), file.name());
    }
    count++;
    file = dir.openNextFile();
  }
  Serial.println("----------------------------------------");
  Serial.printf("Total entries: %d\n", count);
}

void fsCat(const char* path) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  File file = LittleFS.open(path, "r");
  if (!file || file.isDirectory()) {
    Serial.printf("[FS] Error: Cannot open file '%s'.\n", path);
    return;
  }

  Serial.printf("--- Content of %s (%u bytes) ---\n", path, (unsigned int)file.size());
  while (file.available()) {
    Serial.write(file.read());
  }
  Serial.println("\n--- EOF ---");
  file.close();
}

void fsWrite(const char* path, const char* text, bool append) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  const char* mode = append ? "a" : "w";
  File file = LittleFS.open(path, mode);
  if (!file) {
    Serial.printf("[FS] Error: Failed to open '%s' for writing.\n", path);
    return;
  }

  size_t bytesWritten = file.print(text ? text : "");
  file.close();
  Serial.printf("[FS] Successfully wrote %u bytes to '%s' (%s mode).\n", 
                (unsigned int)bytesWritten, path, append ? "append" : "overwrite");
}

bool fsWriteBytes(const char* path, const uint8_t* data, size_t len) {
  if (!_fsMounted || path == nullptr || data == nullptr) return false;
  File file = LittleFS.open(path, "w");
  if (!file) return false;
  size_t w = file.write(data, len);
  file.close();
  return (w == len);
}

void fsRemove(const char* path) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  if (LittleFS.remove(path)) {
    Serial.printf("[FS] File '%s' removed.\n", path);
  } else {
    Serial.printf("[FS] Error: Failed to remove file '%s'.\n", path);
  }
}

void fsMkdir(const char* path) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  if (LittleFS.mkdir(path)) {
    Serial.printf("[FS] Directory '%s' created.\n", path);
  } else {
    Serial.printf("[FS] Error: Failed to create directory '%s'.\n", path);
  }
}

void fsRmdir(const char* path) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  if (LittleFS.rmdir(path)) {
    Serial.printf("[FS] Directory '%s' removed.\n", path);
  } else {
    Serial.printf("[FS] Error: Failed to remove directory '%s' (must be empty).\n", path);
  }
}

void fsRename(const char* from, const char* to) {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  if (LittleFS.rename(from, to)) {
    Serial.printf("[FS] Renamed '%s' to '%s'.\n", from, to);
  } else {
    Serial.printf("[FS] Error: Failed to rename '%s' to '%s'.\n", from, to);
  }
}

void fsInfo() {
  if (!_fsMounted) {
    Serial.println("[FS] Error: File system not mounted.");
    return;
  }

  size_t totalBytes = LittleFS.totalBytes();
  size_t usedBytes  = LittleFS.usedBytes();
  size_t freeBytes  = (totalBytes > usedBytes) ? (totalBytes - usedBytes) : 0;

  Serial.println("====== Flash File System (LittleFS) Info ======");
  Serial.printf("Total Space: %10u bytes (%.2f KB / %.2f MB)\n", 
                (unsigned int)totalBytes, totalBytes / 1024.0, totalBytes / (1024.0 * 1024.0));
  Serial.printf("Used Space:  %10u bytes (%.2f KB)\n", 
                (unsigned int)usedBytes, usedBytes / 1024.0);
  Serial.printf("Free Space:  %10u bytes (%.2f KB)\n", 
                (unsigned int)freeBytes, freeBytes / 1024.0);
  Serial.printf("Usage:       %.1f%%\n", (totalBytes > 0) ? (usedBytes * 100.0 / totalBytes) : 0.0);
  Serial.println("===============================================");
}

void fsFormat() {
  Serial.println("[FS] Formatting LittleFS flash partition... Please wait.");
  LittleFS.end();
  if (LittleFS.format()) {
    Serial.println("[FS] Format successful. Remounting...");
    fsBegin();
  } else {
    Serial.println("[FS] Error: Format failed!");
  }
}
