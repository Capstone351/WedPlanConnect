@echo off
rem Starts WedPlanConnect and opens it in your browser.
rem  - MySQL must be running in the XAMPP Control Panel.
rem  - QR codes automatically use this PC's current Wi-Fi address, so phones on the same Wi-Fi can scan them.
rem  - If Windows Firewall asks about "php", click "Allow" so phones can connect.
cd /d "%~dp0wedplanconnect"
php artisan config:clear >nul

rem Open the browser a few seconds after the server starts.
start "" /b powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 3; Start-Process 'http://127.0.0.1:8000'"

php artisan serve --host=0.0.0.0 --port=8000
pause
