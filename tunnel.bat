@echo off
rem Started by start-pms.bat in its own window, the same way serve.bat and
rem queue-worker.bat are. Keeps a free Cloudflare Quick Tunnel open so
rem WhatsApp (Chatway) can fetch the guest PDF link over the internet —
rem without this window open and connected, WhatsApp messages that need a
rem PDF attached quietly fall back to text-only instead (see
rem app/Support/WhatsApp.php). Every time this tunnel connects it gets a
rem brand new address; php artisan pms:tunnel-watch (routes/console.php)
rem notices and updates the .env file's PMS_PUBLIC_URL automatically, so
rem nothing here needs to be typed or copied by hand.
rem
rem Restarts itself if it ever stops, the same way queue-worker.bat does —
rem so closing this window is the only way to actually stop it.

cd /d "%~dp0"

:loop
php artisan pms:tunnel-watch

echo [%date% %time%] Tunnel watcher stopped - restarting in 3 seconds...
timeout /t 3 /nobreak >nul
goto loop
