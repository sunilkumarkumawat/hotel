@echo off
rem Keeps a queue worker running for WhatsApp/email sends. This is the piece
rem SendQueuedWhatsApp / SendQueuedGuestMail were always written for — it was
rem just never running, because QUEUE_CONNECTION was "sync" until now.
rem
rem It restarts itself every so often (see --max-time below) on purpose: a
rem running worker keeps the app's code loaded in memory from when it
rem started, so it will NOT see a code change Claude deploys later until it
rem restarts. This loop does that automatically, instead of you having to
rem remember to close and reopen this window after every update.

cd /d "%~dp0"

:loop
echo [%date% %time%] Starting queue worker...
php artisan queue:work --tries=3 --timeout=60 --sleep=3 --max-time=1800

echo [%date% %time%] Queue worker stopped - restarting in 3 seconds...
timeout /t 3 /nobreak >nul
goto loop
