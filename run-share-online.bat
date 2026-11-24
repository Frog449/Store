@echo off
title Store Automeation - Online Share (Cloudflare Tunnel)
color 0A

set "PHP_BIN=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"
if not exist "%PHP_BIN%" (
    where php >nul 2>nul
    if errorlevel 1 (
        echo [ERROR] PHP not found at %PHP_BIN%
        pause
        exit /b 1
    )
    set "PHP_BIN=php"
)

set "CLOUDFLARED_DIR=C:\laragon\bin\cloudflared"
set "CLOUDFLARED_BIN=%CLOUDFLARED_DIR%\cloudflared.exe"

echo ========================================================
echo   Store Automeation - Online Share Mode
echo   (Close this window anytime to stop sharing)
echo ========================================================
echo.

if not exist "%CLOUDFLARED_BIN%" (
    echo [0/3] Downloading Cloudflare Tunnel ^(first time only^)...
    if not exist "%CLOUDFLARED_DIR%" mkdir "%CLOUDFLARED_DIR%"
    powershell -NoProfile -ExecutionPolicy Bypass -Command "[Net.ServicePointManager]::SecurityProtocol = [Net.SecurityProtocolType]::Tls12; Invoke-WebRequest -Uri 'https://github.com/cloudflare/cloudflared/releases/latest/download/cloudflared-windows-amd64.exe' -OutFile '%CLOUDFLARED_BIN%' -UseBasicParsing"
    if not exist "%CLOUDFLARED_BIN%" (
        echo [ERROR] Failed to download cloudflared.exe
        pause
        exit /b 1
    )
)

echo [1/3] Ensuring Database and Default Accounts...
"%PHP_BIN%" "%~dp0backend\setup-local-db.php"

echo.
echo [2/3] Starting Local PHP Server on port 8000...
start /B "" "%PHP_BIN%" -S 0.0.0.0:8000 -t "%~dp0backend" >nul 2>&1
echo       - This PC (Offline OK)   : http://localhost:8000
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
    for /f "tokens=1" %%b in ("%%a") do echo       - Same Wi-Fi ^(Offline OK^): http://%%b:8000
)

echo [3/3] Starting Cloudflare Tunnel ^(please wait 5-8 seconds^)...
echo.
echo ========================================================
echo   Accounts for Testing:
echo   - Admin  : admin  / admin1234
echo   - Staff  : staff  / staff1234
echo   - Viewer : viewer / viewer1234
echo ========================================================

powershell -NoProfile -ExecutionPolicy Bypass -Command "$u = $null; $opened = $false; & '%CLOUDFLARED_BIN%' tunnel --url http://127.0.0.1:8000 --no-autoupdate 2>&1 | ForEach-Object { $s = $_.ToString(); if (-not $u -and $s -match '(https://[a-zA-Z0-9-]+\.trycloudflare\.com)') { $u = $Matches[1]; try { Set-Clipboard -Value $u } catch {}; Write-Host ''; Write-Host '========================================================' -ForegroundColor Green; Write-Host '  ONLINE LINK READY! (Auto-copied to Clipboard)' -ForegroundColor Green; Write-Host ''; Write-Host ('  ' + $u) -ForegroundColor Yellow; Write-Host ''; Write-Host '  Press Ctrl+V to paste and send this link to others.' -ForegroundColor Cyan; Write-Host '  Close this window [X] when you want to stop sharing.' -ForegroundColor Magenta; Write-Host '========================================================' -ForegroundColor Green; Write-Host '' }; if ($u -and -not $opened -and $s -match 'Registered tunnel connection') { $opened = $true; for ($i=0; $i -lt 10; $i++) { Start-Sleep -Seconds 1; if (Resolve-DnsName ($u -replace 'https://','') -ErrorAction SilentlyContinue) { break } }; Start-Sleep -Seconds 2; try { Start-Process $u } catch {} } }"

pause