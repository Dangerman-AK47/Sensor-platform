# HostyCare ₹239/Month Business Shared Hosting — Complete Deployment Guide

This guide provides step-by-step instructions for deploying the **ESP32 Sensor Platform & Telemetry Dashboard** onto HostyCare's ₹239/month Business Shared Hosting plan with cPanel and LiteSpeed Web Server.

---

## 1. Prerequisites & Account Verification

Before starting, verify you have the following from HostyCare:
1. **cPanel Login URL:** Usually `https://yourdomain.com:2083` or the server IP provided in your welcome email (e.g., `https://serverXX.hostycare.com:2083`).
2. **cPanel Username & Password:** (Example: username `aditya` or `hosty_user`).
3. **Domain Status:** Ensure your custom domain's nameservers point to HostyCare's nameservers (typically `ns1.hostycare.com` & `ns2.hostycare.com`) or have an `A Record` pointing to your server's shared IP.
4. **PHP Version:** PHP 8.1 or 8.2 is recommended (MultiPHP Selector is built into cPanel).

---

## 2. Prepare the Deployment Archive

The production bundle has already been packaged on your machine at:
```
F:\Aditya\Programs\Sensor-platform\dist\sensor_platform_hostycare_bundle.zip
```
*(You can regenerate this package at any time by running `node scripts/build-production-bundle.js`)*.

The zip file contains:
- `sensor_app/` — Private backend modules (`config/`, `src/`, `views/`, `database/`, `storage/`, `.env.production.template`).
- `public_html/` — Public web root (`index.php`, `.htaccess`, `assets/`, `hostycare_check.php`).
- `DEPLOYMENT_README.txt`

---

## 3. Step-by-Step cPanel Deployment

### Step 3.1: Log in to cPanel
1. Open your browser and navigate to your HostyCare cPanel URL.
2. Enter your cPanel username and password.

---

### Step 3.2: Verify PHP Version & Extensions
1. In cPanel, search for **Select PHP Version** or **MultiPHP Manager**.
2. Set the PHP version for your domain to **PHP 8.1** or **PHP 8.2**.
3. Under the **Extensions** tab, ensure the following core extensions are ticked (standard on HostyCare):
   - `pdo` & `pdo_mysql`
   - `json`
   - `mbstring`
   - `openssl`
   - `fileinfo`
   - `curl`

---

### Step 3.3: Create MySQL Database & User
HostyCare prefixes all databases and users with your cPanel username (e.g., `cpuser_`).

1. In cPanel, open the **MySQL® Database Wizard** (or **MySQL® Databases**).
2. **Step 1: Create a Database**
   - Enter name: `sensordb`
   - Resulting database name: `cpuser_sensordb` (take note of this exact name).
   - Click **Next Step**.
3. **Step 2: Create Database User**
   - Username: `sensoruser` (Resulting username: `cpuser_sensoruser`).
   - Password: Click **Password Generator** to create a strong password (e.g., `K9#m$L2!vP8xQ4wZ`).
   - **Important:** Copy and save this password immediately.
   - Click **Create User**.
4. **Step 3: Add User to Database**
   - Check the box **ALL PRIVILEGES**.
   - Click **Make Changes**.

---

### Step 3.4: Import Database Schema & Initial Data
1. In cPanel, open **phpMyAdmin**.
2. In the left sidebar, click on your newly created database (`cpuser_sensordb`).
3. Click the **Import** tab at the top.
4. Click **Choose File** and select:
   ```
   F:\Aditya\Programs\Sensor-platform\dist\hostycare_bundle\sensor_app\database\production_install.sql
   ```
   *(or the identical file in `database/production_install.sql`)*.
5. Leave all settings at default (`utf8mb4` encoding, SQL format).
6. Scroll down and click **Import** (or **Go**).
7. You will see a success message: *Import has been successfully finished, 11 queries executed.*
8. Verify that 11 tables prefixed with `fv_` are listed in the database.

---

### Step 3.5: Upload Application Files

We support two layout options. **Option A is recommended** because it places sensitive code and storage outside the public web root.

#### Option A: Recommended Dual-Directory Deployment (Most Secure)
1. In cPanel, open **File Manager**.
2. Click **Upload** in the top toolbar.
3. Upload `sensor_platform_hostycare_bundle.zip` to your cPanel root directory (`/home/cpuser/`).
4. Select the uploaded zip file and click **Extract**.
5. This creates two folders in `/home/cpuser/`:
   - `/home/cpuser/sensor_app/`
   - `/home/cpuser/public_html/` (its contents will extract directly into `public_html/`).
6. Navigate into `/home/cpuser/sensor_app/`:
   - Rename `.env.production.template` to `.env`.
   - Right-click `.env` and select **Edit**.
   - Fill in your actual credentials:
     ```ini
     APP_ENV=production
     APP_URL=https://yourdomain.com

     DB_CONNECTION=mysql
     DB_HOST=localhost
     DB_PORT=3306
     DB_NAME=cpuser_sensordb
     DB_USER=cpuser_sensoruser
     DB_PASS="YourActualGeneratedPassword"
     DB_CHARSET=utf8mb4

     SESSION_SECRET="GenerateAnyRandom64CharHexStringHere"
     MAX_FILE_SIZE_MB=100
     ```
   - Click **Save Changes**.

#### Option B: Single `public_html` Upload (If Preferred)
If you prefer keeping everything inside `public_html`:
1. Extract `sensor_platform_hostycare_bundle.zip` inside `public_html/`.
2. Move the contents of `public_html` into the root of `public_html`.
3. Rename `sensor_app/.env.production.template` to `sensor_app/.env` and update credentials.
4. *Security guarantee:* The built-in `.htaccess` files inside `sensor_app/storage/`, `sensor_app/config/`, and `sensor_app/database/` automatically reject any direct web requests.

---

### Step 3.6: Verify File Permissions
In cPanel File Manager:
- Directories should be permissions `0755`.
- Files should be permissions `0644`.
- The storage directories `/home/cpuser/sensor_app/storage/uploads` and `/home/cpuser/sensor_app/storage/configs` must be writable by PHP (`0755` is sufficient under CloudLinux CageFS / suPHP / LSPHP).

---

### Step 3.7: Enforce Free HTTPS / SSL
HostyCare provides free AutoSSL (cPanel / Let's Encrypt):
1. In cPanel, search for **SSL/TLS Status**.
2. Check that your domain has a valid green lock certificate. If not, click **Run AutoSSL** and wait 2–5 minutes.
3. Once SSL is active, enforce HTTPS redirection:
   - In cPanel File Manager, edit `/home/cpuser/public_html/.htaccess`.
   - Uncomment the HTTPS redirection block:
     ```apache
     RewriteCond %{HTTPS} off
     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```
   - Save the file.

---

## 4. Post-Deployment Verification

### 4.1 Server Environment Health Check
1. Open your browser and visit:
   ```
   https://yourdomain.com/hostycare_check.php
   ```
2. You will see a JSON response:
   ```json
   {
       "status": "READY_FOR_SENSOR_PLATFORM",
       "checks": {
           "php_version": "8.1.x",
           "pdo_mysql": true,
           "json": true,
           "mbstring": true,
           "openssl": true
       }
   }
   ```
3. **Important:** After verifying, delete `public_html/hostycare_check.php` via cPanel File Manager so public users cannot inspect server configuration.

---

### 4.2 Web Interface & Admin Verification
1. Visit `https://yourdomain.com` — You will see the **Sensor Platform Telemetry Portal & Testing Console**.
2. Visit `https://yourdomain.com/admin/login`
   - Log in with default credentials:
     - **Username:** `admin`
     - **Password:** `admin123`
3. You will be redirected to `https://yourdomain.com/admin/dashboard`.
4. Inspect the dashboard:
   - Verify that the 9 StatCards load without errors.
   - Verify that the Sensors list, Configuration settings, Registration history, Files, and Audit logs load properly.

---

### 4.3 ESP32 Device Connection Verification
Update your ESP32 firmware configuration (`firmware/sensor_platform_client.ino`):
```cpp
const char* serverBaseUrl = "https://yourdomain.com";
const char* deviceId      = "ESP32-PROD-001";
```
1. Flash the ESP32 or test using the Home Page registration console.
2. The device will send `POST https://yourdomain.com/api/v1/sensors/register`.
3. It will immediately receive `HTTP 201 AUTO_APPROVED` with its 64-character Bearer token.
4. The device will begin streaming heartbeats and telemetry uploads directly to your HostyCare MySQL database and NVMe SSD storage!

---

## 5. Maintenance, Backups & Rollback Procedure

### 5.1 Automated Backups via cPanel
HostyCare includes the **Backup Wizard** in cPanel:
- **Database Backup:** Go to cPanel &rarr; **Backup** &rarr; **Download a MySQL Database Backup** &rarr; Click `cpuser_sensordb`. This downloads a clean `.sql.gz` dump.
- **Full Home Directory Backup:** Download a full home directory archive once a month or before performing major updates.

### 5.2 Safe Rollback Procedure
If you ever need to roll back:
1. Re-upload the previous `sensor_platform_hostycare_bundle.zip`.
2. In phpMyAdmin, click **Drop Tables** and re-import your previous database backup `.sql` file.
3. Clear your browser cache and refresh.

---

## Summary Checklist

| Step | Task | Status |
|---|---|---|
| 1 | Target HostyCare ₹239 plan confirmed (cPanel, LiteSpeed LSPHP, MySQL) | Verified |
| 2 | Production bundle created (`dist/sensor_platform_hostycare_bundle.zip`) | Ready |
| 3 | MySQL Database & user created in cPanel | Manual (Follow Section 3.3) |
| 4 | `production_install.sql` imported via phpMyAdmin | Manual (Follow Section 3.4) |
| 5 | Files uploaded to cPanel and `.env` configured | Manual (Follow Section 3.5) |
| 6 | Free AutoSSL provisioned & HTTPS redirect active | Manual (Follow Section 3.7) |
| 7 | Health check verified (`/hostycare_check.php`) | Ready to verify |
| 8 | Super Admin login tested (`admin` / `admin123`) | Ready to test |
| 9 | ESP32 pointed to `https://yourdomain.com` | Ready |
