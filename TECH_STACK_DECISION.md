# Technology Stack Decision

## 1. Options Evaluation Matrix

Each option is scored from 1 (poor / high risk) to 10 (ideal / zero risk) against the HostyCare ₹239/month Business shared hosting plan and the project requirements:

| Evaluation Criterion (Weight) | Option A: Native Vanilla PHP + MySQL | Option B: Modular Architecture PHP + MySQL (Selected) | Option C: Static SPA (React/Vue) + PHP API | Option D: Node.js Full-Stack (Next.js 14) |
|---|---|---|---|---|
| **Confirmed Hosting Compatibility** | 10/10 | 10/10 | 9/10 | 3/10 |
| **Ease of Local Dev (Windows)** | 9/10 | 10/10 | 8/10 | 8/10 |
| **Ease of Deployment via cPanel** | 10/10 | 10/10 | 8/10 | 3/10 |
| **Database Compatibility (cPanel MySQL)** | 10/10 | 10/10 | 10/10 | 4/10 (Prisma native binary issues) |
| **ESP32 API Integration** | 10/10 | 10/10 | 10/10 | 7/10 |
| **Admin Dashboard Requirements** | 9/10 | 10/10 | 10/10 | 9/10 |
| **Security (Bcrypt, Sessions, SQLi Prevention)** | 10/10 | 10/10 | 10/10 | 9/10 |
| **Performance & Resource Consumption** | 10/10 | 10/10 | 9/10 | 3/10 (exceeds shared RAM/EP) |
| **Dependency Complexity** | 10/10 | 10/10 | 6/10 | 2/10 (thousands of node_modules) |
| **Maintenance & Long-term Stability** | 9/10 | 10/10 | 8/10 | 4/10 |
| **Risk of Deployment Failure** (10 = Lowest Risk) | 10/10 | 10/10 | 8/10 | 2/10 (Extremely High Risk) |
| **TOTAL SCORE (out of 110)** | **107/110** | **110/110** | **96/110** | **47/110** |

---

## 2. Deep Trade-off Analysis

### Why Option D (Node.js / Next.js) Was Rejected
1. **CloudLinux LVE Process Killing:** Next.js 14 requires a persistent Node.js background process daemon. In HostyCare's ₹239 shared hosting environment, CloudLinux automatically terminates idle or long-running processes that hit entry-process or memory thresholds.
2. **Prisma Query Engine Incompatibilities:** Prisma generates platform-dependent native binary engines (`query-engine-rhel-openssl...`). Shared hosting with CageFS security often blocks execution of binary shared objects located in application folders or `/tmp`, throwing `EACCES` or GLIBC version mismatches.
3. **Database Protocol Overhead:** The reference architecture required external PostgreSQL / Supabase poolers. HostyCare natively provides high-speed, local MySQL/MariaDB with zero external network latency and zero recurring subscription cost.

### Why Option B (Modular PHP + MySQL + Responsive Modern UI) Was Selected
1. **100% Native to HostyCare:** LiteSpeed Web Server and LSPHP are natively configured in HostyCare cPanel. PHP scripts execute with near-zero latency and consume 0 MB of RAM when idle.
2. **Built-in cPanel Database:** Directly leverages HostyCare's MySQL/MariaDB engine with standard PDO prepared statements, connection pooling, and utf8mb4 character set.
3. **Rock-Solid Security:**
   - Standard bcrypt (`password_hash` with cost 12) for admin passwords and sensor tokens.
   - HTTP-only, SameSite=Lax, Secure session cookies.
   - Secure directory structure: Application logic, uploads, and secrets reside outside the public `public_html` directory.
4. **Seamless ESP32 Support:** ESP32 devices connect via standard HTTP/HTTPS REST endpoints. Multipart firmware and telemetry uploads stream efficiently to disk without memory buffering bottlenecks.
5. **Faithful UI Preservation:** Implements the complete design system from `UI Design Guide` (Navy sidebar `#0F172A`, slate cards, semantic badges, responsive mobile drawer, Lucide icons, and 30s auto-refresh).
6. **Dual-Runner Local Windows Workflow:** The project includes both standard PHP built-in server support AND a zero-dependency local Node.js test runner for instant, effortless testing on any Windows PC.
