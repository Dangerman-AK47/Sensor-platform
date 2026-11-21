# ESP32 Sensor Platform & Flash Storage Controller (v4.1)

Universal modular Arduino firmware supporting both the **Classic ESP32 Dev Board (30-pin/38-pin CP2102 Dual-Core Xtensa)** and the **ESP32-C3 SuperMini (RISC-V)**.

This firmware integrates LittleFS flash storage, an interactive Wi-Fi manager, and full sensor synchronization with the **FileVault / Sensor Platform** cloud portal (`https://realtimeprojectsolutions.com`).

---

## 📁 Modular File Structure

| File | Description |
| :--- | :--- |
| **`Config.h`** | Central configuration: server URL, default device ID, hardware pinouts, baud rates, and automatic board detection macros. |
| **`MyWifi.h` / `.cpp`** | **Wi-Fi Manager (Runs First):** NVS flash persistence (`wifi_store`), auto-connect on boot, formatted ASCII table scanner, and interactive SSID/password wizard. |
| **`PlatformClient.h` / `.cpp`** | **Platform Client (Runs Second):** REST API integration with `realtimeprojectsolutions.com` (Registration, Bearer Token NVS storage, Heartbeat/Telemetry, Semver Version Checks, Config Download, and Multipart File Upload). |
| **`MyFile.h` / `.cpp`** | **Flash Storage Manager:** LittleFS wrapper with directory listing (`ls`), file view (`cat`), write, append, rename, delete (`rm`), disk usage (`df`), and formatting. |
| **`EspDevStorage.ino`** | Main entry point, LED status blinker, dual-serial bridge, and interactive Serial CLI menu / command parser. |

---

## 🔌 Hardware Pinout & Wiring

The firmware automatically senses the target processor board at compile time:

### 1. Classic ESP32 Dev Board (CP2102 / CH340 Dual-Core)
* **USB CLI / Debug (UART0)**: GPIO 1 (TX0) / GPIO 3 (RX0) connected to onboard USB @ **115200 baud**.
* **External Sensor Interface (Hardware UART2)**:
  * **RX2**: **GPIO 16** (silkscreen `RX2`)
  * **TX2**: **GPIO 17** (silkscreen `TX2`)
  * **Baud Rate**: `9600 baud` (configurable in `Config.h`)
* **Status LED**: **GPIO 2** (Onboard Blue LED, Active HIGH).

### 2. ESP32-C3 SuperMini (Single-Core RISC-V)
* **USB CLI / Debug**: Native USB-CDC @ **115200 baud**.
* **External Sensor Interface (`Serial1`)**:
  * **RX1**: **GPIO 20**
  * **TX1**: **GPIO 21**
  * **Baud Rate**: `9600 baud`
* **Status LED**: **GPIO 8** (Onboard Blue LED, Active LOW).

---

## 🚀 Boot Sequence & Execution Flow

1. **Hardware & Serial Setup**:
   - Primary CLI initialized on USB Serial at `115200 baud`.
   - External Sensor serial initialized on GPIO 16/17 (or 20/21 on C3) at `9600 baud`.
   - LittleFS flash partition mounted (auto-formatted if fresh chip).
2. **Step 1 — Wi-Fi First**:
   - Reads stored network credentials from flash NVS (`wifi_store`).
   - If found: Connects within 15 seconds.
   - If not found or failed: Automatically triggers the **Interactive Wi-Fi Scanner**, displays available SSIDs in a formatted ASCII table, and prompts you in the Serial Monitor for network selection and password.
3. **Step 2 — Website Platform Registration & Synchronization**:
   - Once Wi-Fi connects, checks NVS (`sensor_store`) for a registered Bearer token.
   - **If not registered**: Automatically sends POST `/api/v1/sensors/register`, captures the 64-character bearer token, and saves it in flash NVS.
   - **If registered**: Automatically fires a POST `/api/v1/sensors/heartbeat` ping containing RSSI, heap, uptime, and installed configuration version so the web dashboard turns **Active** with the right version.
4. **Step 3 — Interactive CLI**:
   - Prints the main control menu and waits for quick single-digit numbers (`1`–`6`) or typed commands.

---

## 💻 Arduino IDE Setup Instructions

### 1. Required Libraries
Install via **Arduino IDE Library Manager** (`Ctrl+Shift+I`):
* **`ArduinoJson`** by Benoit Blanchon (version **7.x**, e.g., 7.4.x).
*(All other libraries: `WiFi`, `HTTPClient`, `WiFiClientSecure`, `Preferences`, and `LittleFS` are built directly into the ESP32 Arduino Core).*

### 2. Board & Compilation Settings
* **For Classic ESP32 Dev Board:**
  * **Board**: `ESP32 Dev Module` or `DOIT ESP32 DEVKIT V1`
  * **Upload Speed**: `921600` (or `115200`)
  * **Flash Size**: `4MB (32Mb)`
  * **Partition Scheme**: `Default 4MB with spiffs (1.2MB APP / 1.5MB SPIFFS)`
* **For ESP32-C3 SuperMini:**
  * **Board**: `ESP32C3 Dev Module`
  * **USB CDC On Boot**: `Enabled`
  * **Flash Size**: `4MB`
  * **Partition Scheme**: `Default 4MB with spiffs`

---

## ⌨️ Serial CLI Reference (115200 Baud, New Line)

### Quick Actions (Single Number + Enter)
* `1` — **Send Heartbeat / Ping**: Synchronizes telemetry and config version with the dashboard.
* `2` — **Upload Telemetry File**: Transmits a CSV file to the FileVault storage portal (`/api/v1/sensors/upload`).
* `3` — **Check Configuration Update**: Queries `/api/v1/sensors/version-check`, downloads new config into LittleFS `/config.json`, and updates dashboard.
* `4` — **Re-Register Sensor**: Clears saved token from NVS and registers freshly.
* `5` — **Scan & Connect Wi-Fi**: Launches the interactive network scanner.
* `6` — **Flash Storage Info**: Runs `df` and `ls` on LittleFS.
* `M` — **Menu**: Re-prints the main quick status menu.
* `H` — **Help**: Displays the full command list.

### Full Command Reference

#### Wi-Fi Commands
* `wifi scan` — Run interactive scan & connection wizard
* `wifi select <num> [password]` — Connect to scanned network by index
* `wifi status` — Show current connection state, SSID, IP, RSSI
* `wifi connect [ssid] [password]` — Connect using saved (or specified) credentials
* `wifi set <ssid> <password>` — Manually store Wi-Fi credentials in flash NVS
* `wifi clear` — Wipe stored Wi-Fi credentials
* `wifi disconnect` — Disconnect from active Wi-Fi

#### Sensor Platform & Cloud Commands
* `register` — Register device at `https://realtimeprojectsolutions.com`
* `heartbeat` or `ping` — Send live telemetry ping
* `version` — Check configuration version on server
* `download [version]` — Force download of active server configuration
* `upload [path]` — Upload a file from LittleFS (or sample telemetry data)
* `reregister` — Clear bearer token and register freshly
* `status` — Show complete platform, registration, token, and Wi-Fi status
* `device set <id>` — Change Device ID (e.g., `device set ESP32-DEVICE-2002`)
* `server set <url>` — Update server URL

#### Flash File System (LittleFS) Commands
* `ls [path]` — List files in directory
* `cat <file>` — Display file contents on serial
* `write <file> <text>` — Overwrite file with text
* `append <file> <text>` — Append text to file
* `rm <file>` — Delete a file
* `mkdir <dir>` — Create a directory
* `rmdir <dir>` — Delete an empty directory
* `mv <from> <to>` — Move / rename a file
* `df` — Display flash storage usage and free bytes
* `format yes` — Format the LittleFS partition

#### External Sensor Serial (GPIO 16/17 @ 9600 Baud)
* `sensor send <text>` — Transmit string to external sensor
* `sensor flush` — Clear sensor receive buffer

#### System Commands
* `reboot` — Soft-reset the ESP32
