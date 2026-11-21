# Sensor Platform / FileVault / DeviceVault v3

Full-stack, HostyCare cPanel-compatible platform for ESP32 IoT sensor identity, automatic registration, 30-second heartbeat tracking, global OTA configuration distribution, and secure telemetry file ingestion with an administrative dashboard.

---

## 1. Local Setup on Windows (Dual-Runner Support)

This project supports **two local development workflows**:
- **Option 1 (Instant, 0-Friction):** Built-in Node.js local server runner (`node local-server.js` or double-click `run-local.bat`). Node v24 is already installed on your PC. No PHP or MySQL installation is required to test immediately.
- **Option 2 (Full PHP/XAMPP Stack):** Standard PHP 8.x + MySQL/MariaDB stack (`php -S localhost:8000 -t public`), which mirrors production cPanel execution.

### Prerequisites
- **Node.js:** v18, v20, or v24 (Confirmed installed: `v24.21.0`)
- **Web Browser:** Chrome, Edge, Firefox, or Safari
- **(Optional for PHP runner):** PHP 8.1+ with `pdo_mysql` and MySQL 5.7+/8.0+/MariaDB (e.g., via XAMPP or Laragon)

---

## 2. Project Folder Structure

```
Sensor-platform/
├── config/
│   ├── config.php                 <-- Environment and application settings loader
│   └── database.php               <-- PDO connection provider (MySQL/MariaDB)
├── database/
│   ├── schema.sql                 <-- MySQL / MariaDB DDL schema (11 fv_* tables)
│   └── seed.sql                   <-- Development test dataset & admin seed
├── firmware/
│   └── sensor_platform_client.ino <-- Production ESP32 Arduino/C++ firmware
├── public/                        <-- Public Web Root (maps to public_html on cPanel)
│   ├── index.php                  <-- Front controller & request router
│   ├── .htaccess                  <-- LiteSpeed / Apache rewrite & security headers
│   └── assets/
│       ├── css/
│       │   └── styles.css         <-- Design tokens, cards, badges, responsive layout
│       └── js/
│           └── app.js             <-- 30s auto-refresh, modals, copy tokens, toasts
├── scripts/
│   └── esp32_simulator.js         <-- CLI ESP32 device simulator
├── src/                           <-- Private Application Core (Above web root)
│   ├── Controllers/               <-- Sensor API, Admin API, View Controllers
│   ├── Services/                  <-- AuthService, DeviceService, ConfigService, StorageService, AuditService
│   └── Utils/                     <-- Response, Semver, DeviceIdValidator, View
├── storage/                       <-- Private persistent storage
│   ├── uploads/                   <-- Uploaded telemetry binary files
│   ├── configs/                   <-- Stored global sensor configuration files
│   └── logs/                      <-- Application activity and error logs
├── tests/
│   └── test-suite.js              <-- 16-point automated integration test runner
├── .env.example                   <-- Configuration template
├── local-server.js                <-- Built-in standalone local dev server
├── run-local.bat                  <-- Windows 1-click batch launcher
├── run-local.ps1                  <-- Windows PowerShell launcher
└── README.md
```

---

## 3. Quick Start Instructions (Instant Node Runner)

### Step 1: Start the Local Development Server
Open PowerShell in `F:\Aditya\Programs\Sensor-platform` and run:
```powershell
node local-server.js
```
*Or simply double-click `run-local.bat` in Windows Explorer.*

The server will bind to `http://localhost:8000` (and `0.0.0.0:8000` for LAN devices).

### Step 2: Open in Browser
- **Home & Manual Testing Console:** [http://localhost:8000](http://localhost:8000)
  - Features an interactive **Manual ESP32 Registration Testing Tool** where you can submit device registration requests (`POST /api/v1/sensors/register`) right in your browser with live response preview.
  - Also includes quick interactive Heartbeat and Version Check API testers.
- **Admin Dashboard:** [http://localhost:8000/admin/dashboard](http://localhost:8000/admin/dashboard)
- **Admin Login:** [http://localhost:8000/admin/login](http://localhost:8000/admin/login)
  - **Username:** `admin`
  - **Password:** `admin123`
- **Public Sensor Upload Portal:** [http://localhost:8000/upload](http://localhost:8000/upload)

### Step 3: Run the Automated Test Suite
In a second terminal window, run:
```powershell
node tests/test-suite.js
```
All 16 integration tests will execute and verify page loading, session authentication, 401 unauthorized protection, sensor registration, admin approval, token generation, heartbeats, version checks, configuration downloads, and file uploads.

---

## 4. Full PHP / MySQL Stack Setup (XAMPP / Production Mirror)

If you wish to run the PHP stack locally with MySQL:

1. **Copy configuration:**
   ```powershell
   Copy-Item .env.example .env
   ```
2. **Create Database in MySQL:**
   Open MySQL command line or phpMyAdmin (`http://localhost/phpmyadmin`):
   ```sql
   CREATE DATABASE sensordb CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```
3. **Import Schema & Seed Data:**
   ```powershell
   mysql -u root -p sensordb < database\schema.sql
   mysql -u root -p sensordb < database\seed.sql
   ```
4. **Update `.env`:**
   Set `DB_NAME=sensordb`, `DB_USER=root`, `DB_PASS=your_mysql_password`.
5. **Start PHP Built-in Server:**
   ```powershell
   php -S localhost:8000 -t public
   ```

---

## 5. ESP32 Testing: Localhost vs. Computer LAN IP

### Crucial Networking Rule
- Microcontrollers running on your local Wi-Fi **cannot connect to `http://localhost:8000`**, because on the microcontroller, `localhost` refers to the microcontroller itself.
- Instead, configure your ESP32 with your **Windows PC's Local Area Network (LAN) IPv4 address**:
  1. Find your PC's IP by running in PowerShell:
     ```powershell
     ipconfig
     ```
     Look for `IPv4 Address`, e.g. `192.168.1.100`.
  2. In `firmware/sensor_platform_client.ino`, set:
     ```cpp
     const char* SERVER_BASE_URL = "http://192.168.1.100:8000";
     ```
  3. Ensure Windows Defender Firewall allows inbound traffic on port 8000 for your private network.

### Testing with the CLI Simulator (No Hardware Required)
You can test the entire ESP32 communication lifecycle immediately using the built-in simulator:
```powershell
node scripts/esp32_simulator.js SENSOR-001 a8f4b2c1d3e567890123456789abcdefa8f4b2c1d3e567890123456789abcdef
```
The simulator sends heartbeats every 30 seconds, runs version checks, and uploads simulated telemetry readings every 60 seconds.

---

## 6. How to Reset Development Database

- **Instant Runner:** Delete `storage/dev_db.json`. When restarted, the server automatically repopulates fresh default seed data.
- **MySQL/PHP Runner:** Re-run `mysql -u root -p sensordb < database\schema.sql` followed by `mysql -u root -p sensordb < database\seed.sql`.
