@echo off
title Store Automeation - Local Preview
color 0A

set "PHP_BIN=C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64\php.exe"

if not exist "%PHP_BIN%" (
    echo [ERROR] PHP not found at %PHP_BIN%
    pause
    exit /b
)

echo ========================================================
echo   Store Automeation - Local Development Server
echo ========================================================
echo [1/3] Ensuring Database and Default Accounts...
"%PHP_BIN%" "%~dp0backend\setup-local-db.php"

echo.
echo [2/3] Checking PHP syntax across files...
"%PHP_BIN%" -l "%~dp0backend\index.php"
"%PHP_BIN%" -l "%~dp0backend\profile.php"
"%PHP_BIN%" -l "%~dp0backend\history.php"
"%PHP_BIN%" -l "%~dp0backend\catalog.php"
"%PHP_BIN%" -l "%~dp0backend\receive.php"
"%PHP_BIN%" -l "%~dp0backend\issue.php"
"%PHP_BIN%" -l "%~dp0backend\users.php"

echo.
echo [3/3] Starting Local Server at http://localhost:8000
echo.
echo ========================================================
echo   Accounts for Testing:
echo   - Admin  : admin  / admin1234
echo   - Staff  : staff  / staff1234
echo   - Viewer : viewer / viewer1234
echo ========================================================
echo.

start "" "http://localhost:8000"
"%PHP_BIN%" -S localhost:8000 -t "%~dp0backend"
pause
