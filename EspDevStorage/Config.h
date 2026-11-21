#ifndef CONFIG_H
#define CONFIG_H

#include <Arduino.h>

// ==============================================================================
// Hardware Pinout & Configuration (Auto-detected based on target architecture)
// ==============================================================================
#if defined(CONFIG_IDF_TARGET_ESP32C3)
  // --- ESP32-C3 SuperMini (RISC-V) ---
  #define BOARD_NAME          "ESP32-C3 SuperMini"
  #define PIN_LED             8      // Blue LED (Active LOW on SuperMini)
  #define LED_ACTIVE_LOW      true
  #define PIN_SENSOR_RX       20     // Sensor RX
  #define PIN_SENSOR_TX       21     // Sensor TX
  #define SENSOR_SERIAL       Serial1

#else
  // --- Classic ESP32 Dev Board (30-pin / 38-pin CP2102 Dual-Core) ---
  #define BOARD_NAME          "ESP32 DevKit (Dual-Core Xtensa)"
  #define PIN_LED             2      // Onboard Blue LED on classic ESP32 (Active HIGH)
  #define LED_ACTIVE_LOW      false
  #define PIN_SENSOR_RX       16     // Standard RX2 pin (GPIO 16)
  #define PIN_SENSOR_TX       17     // Standard TX2 pin (GPIO 17)
  #define SENSOR_SERIAL       Serial2 // Hardware UART2
#endif

// Baud Rates
#define CLI_BAUD              115200 // PC Serial Monitor (UART0 / USB CDC)
#define SENSOR_BAUD           9600   // External Sensor Serial

// ==============================================================================
// Sensor Platform Server & Device Configuration
// ==============================================================================
#define DEFAULT_SERVER_URL    "https://realtimeprojectsolutions.com"
#define DEFAULT_SENSOR_ID     "ESP32-DEVICE-2002"
#define FIRMWARE_VERSION      "4.1.0"
#define HARDWARE_MODEL        BOARD_NAME

// Upload limits & timeouts
#define MAX_FILE_BYTES        (5L * 1024L * 1024L) // 5 MB upload limit
#define HTTP_TIMEOUT_MS       15000                // 15 seconds HTTP timeout
#define WIFI_CONNECT_TIMEOUT  25000                // 25s timeout for Wi-Fi connection

#endif // CONFIG_H
