# Project Audit: Sensor Platform / FileVault / DeviceVault v3

## 1. Context & Architectural Root Cause Analysis

The project specification describes **Sensor Platform / FileVault / DeviceVault v3**, a centralized platform managing ESP32 IoT sensors, secure file uploads, over-the-air global configuration updates, and an administrative telemetry dashboard.

### Why the Previous Architecture Fails on Shared Hosting
The reference architecture was originally designed around **Next.js 14 App Router, Node.js ≥ 18, Prisma ORM, and PostgreSQL**:
1. **Daemon Requirement:** Next.js App Router server requires a continuous running Node.js process daemon. Shared hosting environments (such as HostyCare's CloudLinux LVE) enforce process killing on idle, thread limits (660 NPROC), and entry process limits (110 EP).
2. **Prisma Binary Failure:** Prisma relies on compiling/downloading precompiled query engines (`libquery_engine-debian-openssl`). In shared cPanel environments running CloudLinux CageFS, native shared objects often fail to execute due to `/tmp` noexec mount policies, missing GLIBC symbols, or restricted system calls.
3. **Database Mismatch:** The reference suggests PostgreSQL via an external connection pooler (e.g. Supabase pgBouncer on port 6543). HostyCare provides **local MySQL 5.7/8.0 / MariaDB** directly inside cPanel. Using an external database creates unwanted network roundtrips, connection timeouts, latency spikes, and vendor lock-in, violating the prompt's mandate to use the hosting account's provided database.
4. **Maintenance Overhead:** Running Node.js via cPanel's Phusion Passenger requires manual restarting from the cPanel interface whenever files or environment variables change, leading to fragile operational workflows.

---

## 2. Resource & Requirement Audit

### Core Domains & Boundaries
1. **Public / Sensor Edge:**
   - Registration endpoint (`/api/v1/sensors/register`): Open endpoint for new devices.
   - Heartbeat endpoint (`/api/v1/sensors/heartbeat`): 30s periodic alive ping.
   - Version Check endpoint (`/api/v1/sensors/version-check`): Strict semver check against active config.
   - Config Download endpoint (`/api/v1/sensors/configuration/download`): Stream active configuration file with anti-cache headers.
   - Upload endpoint (`/api/v1/sensors/upload`): Multipart form file upload with SHA-256 integrity verification.
   - Public Upload Portal (`/upload`): Interactive drag-and-drop web portal for sensor manual uploads.

2. **Admin Portal (`/admin/*`):**
   - Session authentication with secure HTTP-only cookies.
   - Dashboard with 9 StatCards, status distribution bar, 7-day upload activity chart, and recent events.
   - Sensors list & detail management (Activate/Deactivate, token generation).
   - Global configuration management (semver forward-only atomic activation).
   - Registration request review (Approve -> generate CSPRNG token; Reject with reason).
   - Uploaded file records with secure download and deletion.
   - Version check metrics aggregation.
   - Tamper-evident admin audit logging.

---

## 3. Retain vs. Fix vs. Implement Matrix

| Component / Layer | Status | Action Required |
|---|---|---|
| **Database Schema** | Well-designed relational schema | Adapt from PostgreSQL/Prisma to clean MySQL 5.7/8.0 & MariaDB schema (`fv_*` tables) with appropriate indexing, foreign keys, and collation (`utf8mb4_unicode_ci`). |
| **API Contracts** | Fully specified JSON REST format | Retain exact paths, request payloads, response schemas, and HTTP status codes (200, 201, 400, 401, 403, 404, 409, 413, 500). |
| **Admin Authentication** | JWT in HTTP-only cookie | Implement native secure HTTP-only session cookie backed by secure session store and password verification using `password_hash()` (bcrypt cost 12). |
| **Sensor Authentication** | CSPRNG Bearer tokens | Retain exact cryptographic design: 32 random bytes hex token, stored as bcrypt hash (`$2y$12$...`), verified via `Authorization: Bearer <token>` and `X-Sensor-Id`. |
| **File Storage** | Storage abstraction | Implement local filesystem storage with database metadata fallback, completely isolated outside public web root (`storage/uploads/` and `storage/configs/`). |
| **Frontend UI** | Fully specified in UI Design Guide | Implement full responsive UI matching all design tokens, Navy/Slate/Accent palette, Lucide icons, 30s auto-refresh, and keyboard accessibility. |
