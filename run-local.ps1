# Sensor Platform Local Dev Server Startup Script
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host "  Starting Sensor Platform / FileVault Local Server" -ForegroundColor Cyan
Write-Host "========================================================" -ForegroundColor Cyan
Write-Host ""
Write-Host "Server URL: http://localhost:8000" -ForegroundColor Green
Write-Host "Admin Login: admin / admin123" -ForegroundColor Yellow
Write-Host ""
node local-server.js
