@echo off
title Raccon Mail Desktop
cd /d "%~dp0"
echo Starting Raccon Mail Desktop Client...
where npx >nul 2>nul
if %ERRORLEVEL% EQU 0 (
  npx electron .
) else (
  start "" "https://racconmail.eu/inbox"
)
