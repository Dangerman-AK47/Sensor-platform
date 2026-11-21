# HostyCare ₹239/month Business Plan Deployment Bundle

This bundle contains the production files for your ESP32 Sensor Platform.

## Option A: Recommended Split Deployment (Highest Security)
1. In cPanel File Manager:
   - Upload the `sensor_app` folder to your cPanel HOME directory:
     /home/YOUR_CPANEL_USER/sensor_app
   - Rename `sensor_app/.env.production.template` to `sensor_app/.env` and enter your cPanel MySQL details.
2. Upload the contents of `public_html` directly into:
   - /home/YOUR_CPANEL_USER/public_html/
3. Import `sensor_app/database/production_install.sql` into your database via phpMyAdmin.

## Option B: Single public_html Deployment (Simplest)
If you prefer everything in public_html:
1. Upload both `sensor_app` and the contents of `public_html` into:
   /home/YOUR_CPANEL_USER/public_html/
2. The index.php automatically detects and binds to `sensor_app`!
3. The .htaccess files inside `sensor_app/storage`, `sensor_app/config`, and `sensor_app/database` automatically block unauthorized web access.

Visit https://yourdomain.com/hostycare_check.php to verify server environment.
Delete `hostycare_check.php` after verification.
