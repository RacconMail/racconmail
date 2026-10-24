@echo off
title Raccon Mail Desktop v2.0
cd /d "%~dp0"
echo Starting Raccon Mail Desktop Client v2.0...
where npx >nul 2>nul
if %ERRORLEVEL% EQU 0 (
  npx electron .
) else (
  start "" "https://racconmail.eu/inbox"
)
