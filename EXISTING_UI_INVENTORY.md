# Existing UI Inventory & Design System Specification

## 1. Visual Identity & Design Tokens

The user interface follows a clean, professional, high-density telemetry dashboard design system specified in the UI Design Guide.

### 1.1 Color Tokens
- **Navy Palette (Sidebar & Shell):**
  - `navy-950`: `#0A0F1E`
  - `navy-900`: `#0F172A` (Sidebar background)
  - `navy-800`: `#16213D` (Active nav item, nav hover state)
  - `navy-700`: `#1F2C4C`
- **Slate Palette (Neutral Canvas, Text, Borders):**
  - `slate-50`: `#F6F7F9` (Main page body background)
  - `slate-100`: `#EEF1F5` (Skeletons, subtle icon boxes)
  - `slate-200`: `#E2E6EC` (Card borders, table borders, input borders)
  - `slate-300`: `#C9CFD9` (Chart deactive bar, dividers)
  - `slate-400`: `#98A2B3` (Secondary icons, muted timestamps)
  - `slate-500`: `#6B7280` (Descriptions, field labels)
  - `slate-600`: `#4B5563` (Muted buttons, secondary labels)
  - `slate-700`: `#374151` (Primary body copy)
  - `slate-800`: `#1F2733` (Filenames, emphasis)
  - `slate-900`: `#111827` (Card titles, page headings)
- **Semantic Accents:**
  - **Accent Blue (Primary Action / Brand):**
    - `accent-50`: `#EEF3FF`
    - `accent-100`: `#DCE6FF` (Focus/selection)
    - `accent-400`: `#5C87F2`
    - `accent-500`: `#3563E9` (Primary button, active bars, focus ring)
    - `accent-600`: `#284ECB` (Primary button hover)
    - `accent-700`: `#1F3DA0`
  - **Success Green (Active / Approved):**
    - `success-50`: `#ECFDF3`
    - `success-500`: `#16A34A`
    - `success-700`: `#166534`
  - **Warning Amber (Pending / Alerts):**
    - `warning-50`: `#FFFBEB`
    - `warning-500`: `#D97706`
    - `warning-700`: `#92400E`
  - **Danger Red (Errors / Deactive / Rejected):**
    - `danger-50`: `#FEF2F2`
    - `danger-500`: `#DC2626`
    - `danger-700`: `#991B1B`

---

## 2. Component Inventory

### 2.1 Navigation & Shell
- **Sidebar (`w-64`, `bg-navy-900`):**
  - Brand header: `"FileVault Admin"` with `ShieldCheck` icon (`text-xs text-slate-400`).
  - Navigation list:
    1. Overview (`LayoutDashboard` → `/admin/dashboard`)
    2. Sensors (`Activity` → `/admin/sensors`)
    3. Configuration Settings (`Settings2` → `/admin/configs`)
    4. Registration History (`History` → `/admin/history`)
    5. Uploaded Files (`FileStack` → `/admin/files`)
    6. Version Check Metrics (`BarChart3` → `/admin/version-check-metrics`)
    7. Audit Logs (`FileText` → `/admin/audit`)
    8. Upload Data (`UploadCloud` → `/upload`)
  - Bottom: Logout action (`LogOut` icon).
- **Header (Sticky top, `bg-white`, border `slate-200`):**
  - Left: Mobile hamburger menu toggle (`Menu` icon) + Page Title (`text-lg font-semibold text-slate-900`).
  - Right: Notification Bell icon (`Bell`) with toast trigger, username display, Role Badge (`ADMIN` / `SUPER_ADMIN`), and Quick Logout button.
- **Mobile Responsive Drawer:**
  - Slide-in backdrop (`bg-slate-900/50`, `z-40`), slide-over drawer with close toggle (`X` icon).

### 2.2 Reusable Atomic Components
- **Card:** `rounded-lg border border-slate-200 bg-white shadow-card` with `CardHeader`, `CardTitle`, `CardDescription`, `CardContent`, and `CardFooter`.
- **StatCard:** 4-column responsive grid card featuring an icon box (`h-10 w-10 rounded-lg`), label (`text-xs font-medium text-slate-500`), and metric value (`text-xl font-semibold text-slate-900`).
- **Button:**
  - `primary`: `bg-accent-500 text-white hover:bg-accent-600`
  - `secondary`: `bg-white text-slate-700 border border-slate-200 hover:bg-slate-50`
  - `ghost`: `text-slate-600 hover:bg-slate-100 hover:text-slate-900`
  - `danger`: `bg-danger-500 text-white hover:bg-danger-700`
  - Sizes: `sm` (h-8), `md` (h-9), `lg` (h-11). With built-in spinner support (`Loader2`).
- **Badge:** `rounded-full px-2.5 py-0.5 text-xs font-medium` in `neutral`, `accent`, `success`, `warning`, `danger`.
- **Input / Select:** `h-9 rounded-md border border-slate-200 bg-white px-3 text-sm focus-visible:ring-2 focus-visible:ring-accent-500`.
- **Skeleton Loaders:** `TableSkeleton` and `StatCardSkeleton` using `animate-pulse` and `bg-slate-200`.
- **Toast Notifications:** Fixed overlay for `success`, `error`, `warning`, `info`.

---

## 3. Page Layouts & Functional Views

1. **Dashboard Overview (`/admin/dashboard`):**
   - 9 StatCards: Total Sensors, Active Sensors, Deactive Sensors, Pending Requests, Approved Requests, Rejected Requests, Total Files, Total Storage Used, Uploads in Last 24h.
   - Recent Uploads table & Recent Registration Requests table.
   - Sensor Status Distribution horizontal split bar (Active green vs Deactive grey).
   - 7-Day Upload Activity vertical bar chart.
   - 30-second Auto-refresh indicator with pause-on-blur.
2. **Sensors Management (`/admin/sensors` & `/admin/sensors/[id]`):**
   - Filter by status (`ALL`, `ACTIVE`, `DEACTIVE`), search by Device ID.
   - Table: Device ID, Status Badge, Last Seen, Upload Count, Storage Used, Action Buttons.
   - Detail view with status toggle (Activate/Deactivate) and token regeneration modal.
3. **Configuration Settings (`/admin/configs`):**
   - Active configuration highlight banner with semver and SHA-256 checksum.
   - Version history table: Version, Status Badge (`ACTIVE`, `INACTIVE`, `ARCHIVED`), File Size, Uploaded At.
   - Upload new configuration modal with semver forward-only validation.
   - Atomic "Activate" button.
4. **Registration History (`/admin/history`):**
   - Searchable table of device registration requests.
   - Approve action: generates CSPRNG token and displays one-time modal with copy button.
   - Reject action: prompts for rejection reason note.
5. **Uploaded Files (`/admin/files`):**
   - Searchable table: Filename, Device ID, File Size, MIME type, Uploaded timestamp, SHA-256 checksum.
   - Direct download and delete actions.
6. **Version Check Metrics (`/admin/version-check-metrics`):**
   - Outcome breakdown per device (`CURRENT`, `UPDATE_AVAILABLE`, `NO_CONFIGURATION_AVAILABLE`, `SENSOR_DEACTIVATED`).
7. **Audit Logs (`/admin/audit`):**
   - Chronological log of admin actions, target entity, timestamp, IP address, and payload metadata.
8. **Public File Upload Portal (`/upload`):**
   - Clean public-facing interface with drag-and-drop dropzone, device ID input, upload progress indicator, and upload confirmation receipts.
9. **Admin Login Page (`/admin/login`):**
   - Centered auth card on clean canvas, CSRF protected, uniform credential validation error feedback.
