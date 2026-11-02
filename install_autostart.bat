@echo off
title Ayam Bang Jungko - Pasang Auto-Start Windows
cd /d "%~dp0"

set "STARTUP_FOLDER=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "TARGET_BAT=%~dp0start_system.bat"
set "SHORTCUT_BAT=%STARTUP_FOLDER%\AyamBangJungko_Analytics.bat"

echo ======================================================================
echo   Memasang Auto-Start Ayam Bang Jungko Analytics pada Windows...
echo ======================================================================

:: Tulis launcher ke folder Startup Windows
(
    echo @echo off
    echo cd /d "%~dp0"
    echo call "%TARGET_BAT%"
) > "%SHORTCUT_BAT%"

if exist "%SHORTCUT_BAT%" (
    echo.
    echo   [BERHASIL] Auto-Start berhasil dipasang!
    echo   Setiap kali laptop kasir dinyalakan, web server dan AI Worker
    echo   akan otomatis berjalan sendiri di latar belakang tanpa repot.
    echo.
) else (
    echo.
    echo   [GAGAL] Tidak dapat menulis ke folder Startup.
    echo.
)

pause
