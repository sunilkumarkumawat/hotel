@echo off
rem Run this file ONCE (double-click it) to make Windows start the PMS
rem automatically every time you log in - the same three windows that
rem start-pms.bat already opens will just appear on their own, without
rem anyone needing to double-click anything by hand. You do not need to
rem run this again after doing it once.
rem
rem This does not make the software itself run any slower - it only saves
rem the one click at the start of the day. The website, the background
rem WhatsApp/email worker and the WhatsApp-PDF tunnel all behave exactly
rem the same either way. The window this file itself opens starts
rem minimised, out of the way, so only the familiar "PMS - Web Server",
rem "PMS - Queue Worker" and "PMS - Cloudflare Tunnel" windows show up.

setlocal

set "TARGET=%~dp0start-pms.bat"
set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "SHORTCUT=%STARTUP%\PMS - Start Everything.lnk"
set "VBS=%TEMP%\pms-make-shortcut.vbs"

> "%VBS%" echo Set oWS = WScript.CreateObject("WScript.Shell")
>> "%VBS%" echo sLinkFile = "%SHORTCUT%"
>> "%VBS%" echo Set oLink = oWS.CreateShortcut(sLinkFile)
>> "%VBS%" echo oLink.TargetPath = "%TARGET%"
>> "%VBS%" echo oLink.WorkingDirectory = "%~dp0"
>> "%VBS%" echo oLink.WindowStyle = 7
>> "%VBS%" echo oLink.Description = "Starts the hotel PMS server and its background WhatsApp/email worker"
>> "%VBS%" echo oLink.Save

cscript //nologo "%VBS%"
del "%VBS%"

echo.
echo Done. PMS will now start automatically every time you log in to
echo Windows - you will see the same three windows appear on their own,
echo usually within a few seconds of logging in.
echo.
echo You do not need to run this file again. To undo this later, delete
echo "PMS - Start Everything" from the Windows Startup folder
echo (press Win+R, type shell:startup, press Enter).
echo.
pause
