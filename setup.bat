@echo off
setlocal
title Setup PMB Politeknik Aceh
cd /d "%~dp0"

echo ============================================================
echo        SETUP SISTEM PMB POLITEKNIK ACEH - WINDOWS
echo ============================================================
echo.

where powershell.exe >nul 2>nul
if errorlevel 1 (
    echo [GAGAL] Windows PowerShell tidak ditemukan.
    echo Gunakan Windows 10/11 atau pasang PowerShell terlebih dahulu.
    pause
    exit /b 1
)

powershell.exe -NoLogo -NoProfile -ExecutionPolicy Bypass -File "%~dp0scripts\setup-windows.ps1" %*
set "SETUP_EXIT=%ERRORLEVEL%"

if not "%SETUP_EXIT%"=="0" (
    echo.
    echo Setup belum selesai. Baca pesan di atas, lalu jalankan setup.bat kembali.
    pause
    exit /b %SETUP_EXIT%
)

echo.
echo Setup selesai. Jendela ini boleh ditutup.
pause
exit /b 0
