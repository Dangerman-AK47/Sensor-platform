# Functional Requirements Specification

## 1. System Scope & Objectives

The Sensor Platform (DeviceVault / FileVault v3) provides a unified management and ingestion pipeline for ESP32 microcontroller fleets and IoT sensor devices, with real-time administration, over-the-air configuration deployment, and secure telemetry ingestion.

---

## 2. Detailed Functional Workflows

### 2.1 ESP32 Sensor Provisioning & Authentication
1. **Device ID Format:** Non-empty alphanumeric string with hyphens/underscores (e.g., `SENSOR-001`), normalized and stored in **UPPERCASE**.
2. **Self-Registration Flow:**
   - ESP32 sends `POST /api/v1/sensors/register` with `{ deviceId, sensorVersion, firmwareVersion, hardwareModel, metadata }`.
   - If device is already registered and `ACTIVE`, API returns HTTP `409 Conflict`.
   - If device is not registered, system creates a `fv_device_registration_requests` record with status `PENDING`.
   - Response returns `{ requestId, status: "PENDING", message: "Registration request submitted for admin review" }`.
3. **Admin Review & Token Provisioning:**
   - Admin views pending requests in `/admin/history`.
   - On Approve:
     1. Creates `fv_registered_devices` entry with status `ACTIVE`.
     2. Generates a 32-byte cryptographically secure pseudorandom number (CSPRNG) hex token (64 hex characters).
     3. Computes bcrypt hash (`$2y$12$...`) and stores in `tokenHash`.
     4. Marks request as `APPROVED`.
     5. Displays the raw token to the admin **EXACTLY ONCE** with a one-click copy button. Plaintext token is never stored in the database.
   - On Reject:
     - Prompts for review reason and marks request `REJECTED`.
4. **Sensor Request Authentication:**
   - Sensor passes `Authorization: Bearer <raw-token>` header and optional `X-Sensor-Id: <deviceId>` header.
   - Fast lookup: queries device by `deviceId`, compares bearer token against `tokenHash` using `password_verify()`.
   - Updates `lastSeenAt` timestamp (and `firstSeenAt` if null).
   - Validates `status === 'ACTIVE'` before permitting data uploads or configuration downloads.

### 2.2 Periodic Heartbeat Loop
- Frequency: Every 30 seconds.
- Endpoint: `POST /api/v1/sensors/heartbeat` with `{ deviceId, sensorVersion }`.
- Returns: `{ ok: true, timestamp: "2026-10-17T..." }`.

### 2.3 Configuration Management & Version Checks
1. **Semver Versioning:** Configuration versions follow strict semver `MAJOR.MINOR.PATCH` (e.g. `1.0.0`, `1.2.0`).
2. **Atomic Configuration Activation:**
   - Only ONE configuration may have status `ACTIVE` at any given time.
   - Activating a new configuration must be an atomic transaction:
     - New configuration version must be strictly greater than current active version (`semver_compare(new, active) > 0`).
     - Previous `ACTIVE` configuration is permanently changed to `ARCHIVED`.
     - New configuration becomes `ACTIVE` with `activatedAt` and `activatedByAdminId`.
     - An audit event `ACTIVATE_CONFIG` is written.
3. **Version Check API:**
   - Endpoint: `POST /api/v1/sensors/version-check` with `{ deviceId, currentVersion }`.
   - Compares reported version against active configuration:
     - If current == active: returns `CURRENT`.
     - If active > current: returns `UPDATE_AVAILABLE` with activeVersion and download instructions.
     - If no active config: returns `NO_CONFIGURATION_AVAILABLE`.
     - If device is DEACTIVE: returns `SENSOR_DEACTIVATED`.
   - Logs `fv_sensor_version_check_events` and increments `fv_daily_sensor_version_check_metrics`.
4. **Configuration Download:**
   - Endpoint: `GET /api/v1/sensors/configuration/download?deviceId=...`
   - Requires valid sensor Bearer authentication.
   - Logs immutable `fv_sensor_config_download_events`.
   - Streams file bytes with headers:
     - `Content-Type: application/octet-stream`
     - `Content-Disposition: attachment; filename="..."`
     - `Cache-Control: no-store, no-cache, must-revalidate`

### 2.4 File Upload & Telemetry Storage
1. **Upload Endpoint:** `POST /api/v1/sensors/upload` (multipart/form-data with `file` and optional `deviceId`).
2. **File Size Enforcement:** Checked against `MAX_FILE_SIZE_MB` (default 100MB; ESP32 typical uploads ≤ 5MB).
3. **Sanitization & Keying:**
   - Original filename sanitized (strips path traversals, non-alphanumeric replaced with `_`).
   - Storage key generated: `YYYY/MM/DD/[timestamp]-[random_hex].[ext]`.
4. **Integrity & Storage:**
   - Computes SHA-256 checksum during streaming.
   - Saves file to secure local disk (`storage/uploads/`) outside public web root, with fallback to database blob table (`fv_storage_blobs`) if configured.
   - In atomic transaction:
     - Inserts `fv_uploaded_file_records`.
     - Increments device `uploadCount`, updates `totalStorageBytes`, updates `lastUploadAt`.

### 2.5 Admin Authentication & RBAC
1. **Login:** `POST /api/admin/login` validating username and password.
2. **Protection:** Uniform error message ("Invalid credentials") for wrong user or password.
3. **Session:** Secure HTTP-only session cookie (`fv_admin_session`) with 8-hour TTL.
4. **Roles:** `ADMIN` and `SUPER_ADMIN`.
5. **Auto-Refresh:** Dashboard automatically polls for updates every 30 seconds; pauses when browser tab loses focus.
