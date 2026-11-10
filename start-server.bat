@echo off
setlocal

set "PHP_EXE=C:\xampp\php\php.exe"
if not exist "%PHP_EXE%" (
    where php >nul 2>nul
    if errorlevel 1 (
        echo PHP was not found. Install XAMPP or add PHP to PATH.
        pause
        exit /b 1
    )
    set "PHP_EXE=php"
)

echo Starting the store at http://127.0.0.1:8000
"%PHP_EXE%" -S 127.0.0.1:8000 -t "%~dp0"
if errorlevel 1 (
    echo.
    echo The PHP server stopped. Check whether port 8000 is already in use.
    pause
)
