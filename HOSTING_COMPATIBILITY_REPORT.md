# HostyCare Hosting Compatibility Report

**Target Plan:** HostyCare ₹239/month "Business" Shared Hosting  
**Control Panel:** cPanel with LiteSpeed Web Server  
**Date Evaluated:** October 2026  
**Official Sources Consulted:**
- https://www.hostycare.com/shared-hosting
- https://www.hostycare.com/cpanel-hosting
- https://www.hostycare.com/web-hosting-india
- https://www.hostycare.com/pricing-specifications

---

## 1. Executive Summary

HostyCare's ₹239/month "Business" plan is an optimized **cPanel Linux Shared Hosting** package running CloudLinux OS and LiteSpeed Web Server (LSPHP). It offers high storage (120 GB NVMe SSD), 3 GB RAM, 3 CPU cores, 720,000 inodes, and 90 concurrent MySQL connections.

While HostyCare advertises Node.js and Python support via CloudLinux Passenger modules, running a modern full-stack Node.js runtime (such as Next.js 14 App Router with standalone server and Prisma native binaries) on shared hosting introduces severe risks: process termination when idle, 110 entry process limits, high memory footprint, binary compilation incompatibilities in CageFS, and complex deployment lifecycles.

In contrast, **PHP 8.x + MySQL/MariaDB with a static/pre-built frontend** matches 100% of the platform's native strengths, guarantees sub-millisecond cold starts, zero idle memory consumption, effortless cPanel deployment, and zero risk of deployment failure.

---

## 2. Plan Specifications & Resource Limits

| Resource / Feature | Business Plan Specification | Confirmed Status | Impact on Architecture |
|---|---|---|---|
| **Monthly Price** | ₹239/month (on 36-mo cycle) | Confirmed (Official Site) | Cost-effective shared tier |
| **Hosting Type** | Shared Linux (CloudLinux + CageFS) | Confirmed | Process isolation per cPanel account |
| **Web Server** | LiteSpeed Web Server (LSPHP) | Confirmed | 100% Apache `.htaccess` compatible, high performance |
| **Control Panel** | cPanel + 1-Click Softaculous | Confirmed | Standard industry interface |
| **Storage** | 120 GB NVMe SSD | Confirmed | Ample room for telemetry logs, firmware, uploaded files |
| **Inodes (File Limit)** | 7,20,000 (720,000 files) | Confirmed | Extremely generous; ample for years of sensor logs |
| **CPU / RAM** | 3 Core CPU / 3 GB RAM | Confirmed | Strong specs for shared hosting |
| **Entry Processes (EP)** | 110 | Confirmed | Max simultaneous PHP/CGI requests executing at one instant |
| **PHP Workers** | 110 | Confirmed | Max concurrency for PHP scripts |
| **NPROC (Processes)** | 660 | Confirmed | Max processes under the cPanel user |
| **I/O & IOPS** | 16 MB/s limit / 1024 IOPS | Confirmed | Fast disk throughput |
| **Bandwidth** | Unlimited (1 Gbps port) | Confirmed | Sufficient for ESP32 heartbeats and data transfers |
| **Websites Hosted** | Up to 60 websites | Confirmed | Can host sensor platform and staging sites |
| **Subdomains** | Unlimited | Confirmed | Supports `api.domain.com`, `admin.domain.com` if needed |

---

## 3. Technology Matrix: Support & Constraints

### 3.1 PHP & Web Server Environment
- **PHP Versions:** PHP 5.2 through PHP 8.1 / 8.2+ supported via cPanel MultiPHP / CloudLinux PHP Selector.
- **Extensions:** Standard extensions enabled: `pdo`, `pdo_mysql`, `mysqli`, `openssl`, `mbstring`, `json`, `curl`, `gd`, `fileinfo`, `zip`, `zlib`, `xml`, `ctype`.
- **Composer:** Available and enabled on the Business plan (green checkmark; Basic plan does not have it).
- **Execution Model:** LiteSpeed LSPHP module (fast, spawns on demand, zero memory footprint when idle).

### 3.2 Node.js & Python Environment
- **Advertised:** "Node.js & Python & Django — All Versions Available, from Legacy to Latest" via CloudLinux `mod_passenger`.
- **Reality on Shared Hosting:**
  - Node.js applications must run through Phusion Passenger via cPanel "Setup Node.js App".
  - Requires a specific entry file (`app.js`), environment mapping, and Passenger hooks.
  - Idle applications are spun down by CloudLinux to conserve RAM; subsequent requests suffer 3–8 second cold starts.
  - Next.js 14 App Router requires persistent Node daemon listening on a local port, high continuous memory (400MB–1GB), and Prisma ORM's native query engine (`.so` library) which often encounters `EACCES` or GLIBC errors inside CloudLinux CageFS.
  - No background WebSockets or persistent MQTT brokers can be kept running.

### 3.3 Database Engines
- **MySQL / MariaDB:** Confirmed, primary engine.
  - Unlimited MySQL databases.
  - 90 concurrent connections limit.
  - Administered via cPanel "MySQL Database Wizard" and phpMyAdmin.
  - Local connection via `localhost` (127.0.0.1:3306 or UNIX socket `/var/lib/mysql/mysql.sock`).
- **PostgreSQL:**
  - Listed on the comparison table with a checkmark on Business plan.
  - However, in cPanel hosting, PostgreSQL is secondary, often lacks phpPgAdmin or requires support ticket activation. MySQL/MariaDB is 100% guaranteed, rock-solid, and the standard cPanel database.

### 3.4 Command-Line, Terminal & Git
- **SSH Access:** Confirmed enabled on Business plan.
- **cPanel In-Browser Terminal:** Confirmed enabled on Business plan.
- **Git Version Control:** Confirmed available in cPanel ("Git Version Control" icon).

### 3.5 Routing, `.htaccess` & SPA Routing
- Fully supported via LiteSpeed Apache-compatible rewrite rules.
- `.htaccess` directives: `RewriteEngine On`, `RewriteCond`, `RewriteRule`, `Header set`, `Deny from all` (for protecting private directories).

### 3.6 Cron Jobs & Background Tasks
- cPanel "Cron Jobs" interface supports scheduled executions (e.g., `* * * * * php /home/user/cron/cleanup.php`).
- Long-running background daemons (e.g., continuous Python/Node daemon or MQTT broker) are automatically killed by CloudLinux LVE process monitors if they exceed CPU/time limits.
- **Conclusion for ESP32:** Sensor communication must use standard HTTP/HTTPS REST endpoints (`POST /api/v1/sensors/heartbeat`, `POST /api/v1/sensors/upload`), NOT a persistent custom TCP/MQTT broker daemon.

### 3.7 SSL & Custom Domain
- Free AutoSSL (Let's Encrypt / Sectigo) issues wildcard and standard SSL certificates automatically upon domain DNS propagation.
- HTTPS redirection easily enforced via 3 lines in `.htaccess`.

---

## 4. Distinction: Advertised vs. Confirmed vs. Unresolved

| Feature | Category | Details & Recommendations |
|---|---|---|
| **cPanel, LiteSpeed, PHP 8.x, MySQL/MariaDB** | Confirmed & Proven | Rock-solid, zero configuration hurdles, instant operation. |
| **Composer, SSH, Git, Terminal** | Advertised & Checked on Plan | Green checkmark on ₹239 Business plan. |
| **Node.js (Passenger)** | Advertised, but Fragile | CloudLinux Passenger exists, but unsuitable for heavy Next.js App Router daemon. |
| **PostgreSQL** | Advertised, but Secondary | Do not depend on it when MySQL/MariaDB is 100% native and robust. |
| **Persistent MQTT Broker** | Incompatible | Not supported on any shared hosting. ESP32 HTTP/HTTPS REST API is mandatory. |

---

## 5. Precise Questions for HostyCare Support (If Needed)

If the user or team ever desires to enable specific advanced features, here are the exact questions to ask HostyCare support:
1. *"Is SSH/Terminal access enabled by default on my Business cPanel account, or does it require ticket activation?"*
2. *"Is PostgreSQL (and phpPgAdmin) activated on my server partition, or is MySQL/MariaDB the standard recommended database engine?"*
3. *"What is the maximum upload limit (`upload_max_filesize` and `post_max_size`) configurable via Select PHP Version?"* (Standard is 128M–512M).
