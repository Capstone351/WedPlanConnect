@echo off
rem Starts WedPlanConnect and opens it in your browser.
rem  - MySQL must be running in the XAMPP Control Panel.
rem  - QR codes use PUBLIC_URL from .env when it is set (ngrok tunnel), so phones on ANY network can scan them.
rem    Otherwise they use this PC's current Wi-Fi address, and phones must be on the same Wi-Fi.
rem  - If Windows Firewall asks about "php", click "Allow" so phones can connect.
cd /d "%~dp0wedplanconnect"
php artisan config:clear >nul

rem Read PUBLIC_URL from .env and start the ngrok tunnel in its own window when it is set.
set "PUBLIC_URL="
for /f "tokens=1,* delims==" %%a in ('findstr /b /c:"PUBLIC_URL=" .env') do set "PUBLIC_URL=%%b"
if defined PUBLIC_URL (
    if exist "%LOCALAPPDATA%\ngrok\ngrok.exe" (
        echo Starting public tunnel: %PUBLIC_URL%
        start "ngrok tunnel - keep open for QR codes" /min "%LOCALAPPDATA%\ngrok\ngrok.exe" http 8000 --url=%PUBLIC_URL%
    ) else (
        echo PUBLIC_URL is set but ngrok was not found in %LOCALAPPDATA%\ngrok. QR codes will not open outside this Wi-Fi.
    )
)

rem Open the browser a few seconds after the server starts.
start "" /b powershell -NoProfile -WindowStyle Hidden -Command "Start-Sleep -Seconds 3; Start-Process 'http://127.0.0.1:8000'"

php artisan serve --host=0.0.0.0 --port=8000
pause
