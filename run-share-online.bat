@echo off
setlocal EnableExtensions
title Store Automeation - Online Share (XAMPP + Cloudflare Tunnel)
color 0A

:: 1. Locate PHP (Prefer XAMPP, then PATH, then Laragon)
set "PHP_BIN="
if exist "C:\xampp\php\php.exe" (
    set "PHP_BIN=C:\xampp\php\php.exe"
) else (
    for /f "delims=" %%p in ('where php 2^>nul') do (
        if not defined PHP_BIN set "PHP_BIN=%%p"
    )
)
if not defined PHP_BIN (
    if exist "C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe" (
        set "PHP_BIN=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"
    )
)
if not defined PHP_BIN (
    echo [ERROR] PHP not found. Please install XAMPP ^(C:\xampp\php\php.exe^) or add php to PATH.
    pause
    exit /b 1
)

:: 2. Locate cloudflared (Prefer XAMPP folder, then existing Laragon folder, then PATH)
set "CLOUDFLARED_BIN="
if exist "C:\xampp\cloudflared\cloudflared.exe" (
    set "CLOUDFLARED_BIN=C:\xampp\cloudflared\cloudflared.exe"
) else if exist "C:\laragon\bin\cloudflared\cloudflared.exe" (
    set "CLOUDFLARED_BIN=C:\laragon\bin\cloudflared\cloudflared.exe"
) else (
    for /f "delims=" %%c in ('where cloudflared 2^>nul') do (
        if not defined CLOUDFLARED_BIN set "CLOUDFLARED_BIN=%%c"
    )
)

echo ========================================================
echo   Store Automeation - Online Share Mode (XAMPP)
echo   (Close this window anytime to stop sharing)
echo ========================================================
echo   PHP: %PHP_BIN%
echo.

if not defined CLOUDFLARED_BIN (
    set "CLOUDFLARED_DIR=C:\xampp\cloudflared"
    if not exist "C:\xampp" set "CLOUDFLARED_DIR=%LOCALAPPDATA%\cloudflared"
    set "CLOUDFLARED_BIN=%CLOUDFLARED_DIR%\cloudflared.exe"
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
if errorlevel 1 (
    echo.
    echo [ERROR] Cannot connect to MySQL. Please make sure MySQL is started in XAMPP Control Panel.
    pause
    exit /b 1
)

echo.
echo [2/3] Starting Local PHP Server on port 8000...
:: Stop any old Store PHP built-in server on port 8000 so XAMPP PHP serves cleanly
powershell -NoProfile -ExecutionPolicy Bypass -Command "Get-CimInstance Win32_Process -Filter \"Name='php.exe'\" -ErrorAction SilentlyContinue | Where-Object { $_.CommandLine -match '0\.0\.0\.0:8000' -and $_.CommandLine -match 'Store' } | ForEach-Object { Stop-Process -Id $_.ProcessId -Force -ErrorAction SilentlyContinue }"
start /B "" "%PHP_BIN%" -S 0.0.0.0:8000 -t "%~dp0backend" >nul 2>&1
ping -n 2 127.0.0.1 >nul
echo       - This PC (Offline OK)   : http://localhost:8000
echo       - XAMPP Apache (Offline) : http://localhost/Store/
for /f "tokens=2 delims=:" %%a in ('ipconfig ^| findstr /c:"IPv4"') do (
    for /f "tokens=1" %%b in ("%%a") do echo       - Same Wi-Fi ^(Offline OK^): http://%%b:8000
)

echo.
echo [3/3] Starting Cloudflare Tunnel ^(please wait 5-10 seconds^)...
echo.
echo ========================================================
echo   Accounts for Testing:
echo   - Admin  : admin  / admin1234
echo   - Staff  : staff  / staff1234
echo   - Viewer : viewer / viewer1234
echo ========================================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command "$ErrorActionPreference = 'Continue'; $cf = '%CLOUDFLARED_BIN%'; $maxTry = 5; for ($attempt = 1; $attempt -le $maxTry; $attempt++) { $protoArgs = if ($attempt -ge 3) { @('--protocol', 'http2') } else { @() }; $modeLabel = if ($attempt -ge 3) { 'HTTP/2' } else { 'Auto/QUIC' }; Write-Host ('[Cloudflare] Connecting (attempt ' + $attempt + '/' + $maxTry + ', ' + $modeLabel + ')...') -ForegroundColor Cyan; $u = $null; $opened = $false; $lastErrors = New-Object System.Collections.Generic.List[string]; & $cf tunnel --url http://127.0.0.1:8000 --no-autoupdate @protoArgs 2>&1 | ForEach-Object { $s = $_.ToString(); if (-not $u -and $s -match '(https://[a-zA-Z0-9-]+\.trycloudflare\.com)') { $u = $Matches[1]; try { Set-Clipboard -Value $u } catch {}; Write-Host ''; Write-Host '========================================================' -ForegroundColor Green; Write-Host '  ONLINE LINK READY! (Auto-copied to Clipboard)' -ForegroundColor Green; Write-Host ''; Write-Host ('  ' + $u) -ForegroundColor Yellow; Write-Host ''; Write-Host '  Press Ctrl+V to paste and send this link to others.' -ForegroundColor Cyan; Write-Host '  Keep this window OPEN while using online / Excel Live.' -ForegroundColor Magenta; Write-Host '========================================================' -ForegroundColor Green; Write-Host ''; }; if ($u -and -not $opened -and $s -match 'Registered tunnel connection') { $opened = $true; Write-Host '[Cloudflare] Tunnel registered and online!' -ForegroundColor Green; for ($i = 0; $i -lt 10; $i++) { Start-Sleep -Seconds 1; if (Resolve-DnsName ($u -replace 'https://','') -ErrorAction SilentlyContinue) { break } }; Start-Sleep -Seconds 2; try { Start-Process $u } catch {}; }; if ($s -match '\b(ERR|FTL)\b|failed to|429|error') { $lastErrors.Add($s); if ($lastErrors.Count -gt 6) { $lastErrors.RemoveAt(0) }; if (-not $u) { Write-Host ('  ' + $s) -ForegroundColor DarkYellow }; }; }; if ($u) { Write-Host '[Cloudflare] Tunnel disconnected.' -ForegroundColor Yellow; break; }; Write-Host ('[Cloudflare] Failed to obtain tunnel URL on attempt ' + $attempt + '.') -ForegroundColor Red; foreach ($errLine in $lastErrors) { Write-Host ('  ' + $errLine) -ForegroundColor Red }; if ($attempt -lt $maxTry) { Write-Host '  Retrying in 4 seconds...' -ForegroundColor Yellow; Start-Sleep -Seconds 4; } else { Write-Host ''; Write-Host ('[ERROR] Could not start Cloudflare Tunnel after ' + $maxTry + ' attempts.') -ForegroundColor Red; Write-Host '        You can still use the local site at http://localhost:8000 or http://localhost/Store/' -ForegroundColor Cyan; }; }"

pause
