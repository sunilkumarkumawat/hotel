@echo off
rem Started by start-pms.bat in its own window, the same way queue-worker.bat
rem is. Runs the site through server.php instead of "php artisan serve" —
rem see the comment at the top of server.php for why (uploaded photos were
rem invisible without this). Everything else about how the site behaves,
rem including the address it runs on, is unchanged.

cd /d "%~dp0public"
php -S 127.0.0.1:8000 ../server.php
