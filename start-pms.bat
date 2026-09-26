@echo off
rem One double-click starts everything: the website, the background worker
rem that sends WhatsApp/email, and the tunnel that lets a WhatsApp message
rem carry its PDF. Use this from now on instead of running "php artisan
rem serve" by hand — the queue worker and tunnel both have to run alongside
rem it, or guest messages will just sit unsent, or arrive on WhatsApp as
rem text only instead of with the PDF attached.

cd /d "%~dp0"

rem Guarantees .env changes (like the queue setting just switched on) are
rem actually picked up, even if this project ever had config cached before.
php artisan config:clear >nul

rem Applies any database change a software update brought with it, such as
rem today's new ID Proof photo field. Already-applied changes are skipped
rem instantly, so on a normal day this adds no real delay — it only ever
rem does something the day an update actually needs it. Left visible on
rem purpose: if this ever fails, you will see why right here instead of the
rem new feature just quietly not working.
php artisan migrate --force

rem Makes uploaded photos (item photos, ID proof photos, outlet logos)
rem actually visible on screen instead of a broken image icon. Harmless to
rem run every time — once the link exists this says so and does nothing.
php artisan storage:link

echo Starting PMS server, queue worker and tunnel in separate windows...
echo.

start "PMS - Web Server" cmd /k serve.bat
start "PMS - Queue Worker (WhatsApp / Email)" cmd /k queue-worker.bat
start "PMS - Cloudflare Tunnel (WhatsApp PDF)" cmd /k tunnel.bat

echo Done. Three windows should now be open:
echo   1. PMS - Web Server        (this runs the website)
echo   2. PMS - Queue Worker      (this sends WhatsApp/email in the background)
echo   3. PMS - Cloudflare Tunnel (this lets a WhatsApp message carry its PDF)
echo.
echo Keep all three windows open while the hotel is using the software.
echo Closing THIS window is fine - it already did its job.
echo.
pause
