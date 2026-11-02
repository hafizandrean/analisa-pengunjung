@echo off
title Ayam Bang Jungko - Hentikan Sistem
cd /d "%~dp0"

echo Menghentikan seluruh proses CCTV Analytics...
taskkill /f /im php.exe >nul 2>&1
taskkill /f /im python.exe >nul 2>&1

echo [OK] Web server dan AI Worker berhasil dihentikan.
timeout /t 2 /nobreak > nul
exit
