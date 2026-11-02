@echo off
title Ayam Bang Jungko - CCTV Analytics System
cd /d "%~dp0"

echo ======================================================================
echo   Ayam Bang Jungko - Visitor Counting & Crowd Analytics
echo ======================================================================
echo   Memulai layanan sistem...

:: 1. Hentikan instance lama jika masih berjalan
taskkill /f /im php.exe >nul 2>&1

:: 2. Jalankan PHP Built-in Server (Port 8000) dengan multi-worker di background
set PHP_CLI_SERVER_WORKERS=4
start /b "" php -S 127.0.0.1:8000 > nul 2>&1
echo   [OK] Web Dashboard & API aktif di http://127.0.0.1:8000

:: 3. Jalankan AI Vision Worker di background
powershell -WindowStyle Hidden -Command "Start-Process 'python' -ArgumentList 'vision/worker_php.py' -WorkingDirectory '%~dp0' -WindowStyle Hidden"
echo   [OK] AI Vision Worker & Live Streamer aktif di port 8080

:: 4. Buka Dashboard di browser
timeout /t 2 /nobreak > nul
start http://127.0.0.1:8000/index.php

echo ======================================================================
echo   Sistem siap digunakan! Jendela ini dapat Anda tutup.
echo ======================================================================
timeout /t 3 /nobreak > nul
exit
