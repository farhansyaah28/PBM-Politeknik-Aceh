@echo off
setlocal
title Hentikan Server PMB Politeknik Aceh
cd /d "%~dp0"
powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\stop-windows.ps1" %*
set "STOP_EXIT=%ERRORLEVEL%"
if not "%STOP_EXIT%"=="0" (
    echo.
    echo Server belum dapat dihentikan. Baca pesan di atas.
    pause
    exit /b %STOP_EXIT%
)
echo.
echo Server aplikasi sudah dihentikan.
pause
exit /b 0
