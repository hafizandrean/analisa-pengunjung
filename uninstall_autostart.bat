@echo off
title Ayam Bang Jungko - Hapus Auto-Start Windows
set "SHORTCUT_BAT=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\AyamBangJungko_Analytics.bat"

if exist "%SHORTCUT_BAT%" (
    del "%SHORTCUT_BAT%"
    echo [OK] Auto-Start berhasil dicabut dari Windows Startup.
) else (
    echo [INFO] Auto-Start belum pernah dipasang.
)

pause
