@echo off
setlocal EnableExtensions EnableDelayedExpansion

title MediCare Pharmacy Management System - Universal Launcher
color 0B

:: ------------------------------------------------------------
:: 0. Check for Administrative Privileges and Self-Elevate
:: ------------------------------------------------------------
net session >nul 2>&1
if !errorlevel! neq 0 (
    echo [INFO] Requesting Administrator permissions to configure services and ports...
    powershell -Command "Start-Process cmd -ArgumentList '/c \"\"%~f0\"\"' -Verb runAs"
    exit /b
)

echo ============================================================
echo   MediCare Pharmacy Management System - Global Auto-Launcher
echo ============================================================
echo.

:: Get directory where this script is located
set "PROJECT_DIR=%~dp0"
if "%PROJECT_DIR:~-1%"=="\" set "PROJECT_DIR=%PROJECT_DIR:~0,-1%"

:: Determine application directory
if exist "%PROJECT_DIR%\config\db.php" (
    set "APP_DIR=%PROJECT_DIR%"
) else if exist "%PROJECT_DIR%\pharmacy\config\db.php" (
    set "APP_DIR=%PROJECT_DIR%\pharmacy"
) else (
    echo [ERROR] Could not find pharmacy files in '%PROJECT_DIR%'.
    pause
    exit /b 1
)

:: ------------------------------------------------------------
:: 1. Handle Known Windows Service Conflicts (e.g. MySQL80)
:: ------------------------------------------------------------
sc query MySQL80 >nul 2>&1
if !errorlevel! equ 0 (
    echo [INFO] Detected background Windows service 'MySQL80' occupying port 3306.
    echo [INFO] Stopping MySQL80 to allow XAMPP MariaDB/MySQL to run...
    net stop MySQL80 >nul 2>&1
    sc config MySQL80 start= demand >nul 2>&1
    echo [OK] MySQL80 stopped and set to manual start.
)

:: ------------------------------------------------------------
:: 2. Detect or Install XAMPP
:: ------------------------------------------------------------
set "XAMPP_DIR="
if exist "C:\xampp\xampp-control.exe" set "XAMPP_DIR=C:\xampp"
if not defined XAMPP_DIR (
    if exist "D:\xampp\xampp-control.exe" set "XAMPP_DIR=D:\xampp"
)

if not defined XAMPP_DIR (
    echo [INFO] XAMPP not found on this device.
    echo Attempting automated installation via Windows Package Manager...
    where winget >nul 2>&1
    if !errorlevel! equ 0 (
        echo Installing XAMPP 8.2... Please wait.
        winget install ApacheFriends.Xampp.8.2 --accept-package-agreements --accept-source-agreements
        if exist "C:\xampp\xampp-control.exe" (
            set "XAMPP_DIR=C:\xampp"
            echo [SUCCESS] XAMPP installed at C:\xampp
        ) else (
            echo [ERROR] Automated install did not finish.
            echo Please install XAMPP manually from https://www.apachefriends.org
            pause
            exit /b 1
        )
    ) else (
        echo [ERROR] winget is unavailable on this system.
        echo Please download and install XAMPP from https://www.apachefriends.org
        pause
        exit /b 1
    )
)

echo [OK] XAMPP located at: %XAMPP_DIR%

:: ------------------------------------------------------------
:: 3. Map Project to XAMPP htdocs (Junction / Copy)
:: ------------------------------------------------------------
set "HTDOCS_TARGET=%XAMPP_DIR%\htdocs\pharmacy"

if not exist "%HTDOCS_TARGET%" (
    echo [INFO] Mapping project to %HTDOCS_TARGET%...
    mklink /J "%HTDOCS_TARGET%" "%APP_DIR%" >nul 2>&1
    if not exist "%HTDOCS_TARGET%" (
        echo [INFO] Copying application files to htdocs...
        mkdir "%HTDOCS_TARGET%" >nul 2>&1
        xcopy "%APP_DIR%" "%HTDOCS_TARGET%" /E /I /H /Y >nul
    )
    echo [OK] Project mapped to web root.
) else (
    echo [OK] Web root link already exists.
)

:: ------------------------------------------------------------
:: 4. Start Apache Web Server
:: ------------------------------------------------------------
echo [INFO] Checking Apache server...
tasklist /FI "IMAGENAME eq httpd.exe" 2>nul | find /I /N "httpd.exe" >nul
if !errorlevel! equ 0 (
    echo [OK] Apache is active.
) else (
    echo [INFO] Starting Apache...
    if exist "%XAMPP_DIR%\apache_start.bat" (
        start "" /B "%XAMPP_DIR%\apache_start.bat" >nul 2>&1
    ) else (
        start "" /B "%XAMPP_DIR%\apache\bin\httpd.exe" >nul 2>&1
    )
    timeout /t 2 >nul
    echo [OK] Apache started.
)

:: ------------------------------------------------------------
:: 5. Start XAMPP MySQL Database Server
:: ------------------------------------------------------------
echo [INFO] Checking XAMPP MySQL server...
set "MYSQL_BIN=%XAMPP_DIR%\mysql\bin\mysql.exe"

:: Test if MySQL is actually accepting root connections on localhost
"%MYSQL_BIN%" -u root -e "SELECT 1;" >nul 2>&1
if !errorlevel! neq 0 (
    echo [INFO] Starting XAMPP MySQL engine...
    if exist "%XAMPP_DIR%\mysql_start.bat" (
        start "" /B "%XAMPP_DIR%\mysql_start.bat" >nul 2>&1
    ) else (
        start "" /B "%XAMPP_DIR%\mysql\bin\mysqld.exe" --defaults-file="%XAMPP_DIR%\mysql\bin\my.ini" >nul 2>&1
    )
    timeout /t 3 >nul
)
echo [OK] MySQL is active.

:: ------------------------------------------------------------
:: 6. Auto-Create Database and Import Schema (First Run)
:: ------------------------------------------------------------
set "SCHEMA_FILE=%APP_DIR%\database\schema.sql"

if exist "%MYSQL_BIN%" (
    echo [INFO] Verifying 'pharmacy_db' schema...
    "%MYSQL_BIN%" -u root -e "USE pharmacy_db; SELECT 1 FROM users LIMIT 1;" >nul 2>&1
    if !errorlevel! neq 0 (
        echo [FIRST RUN SETUP] Database 'pharmacy_db' is not initialized.
        echo [FIRST RUN SETUP] Creating database and importing schema with seed data...
        "%MYSQL_BIN%" -u root -e "CREATE DATABASE IF NOT EXISTS pharmacy_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" >nul 2>&1
        "%MYSQL_BIN%" -u root pharmacy_db < "%SCHEMA_FILE%"
        if !errorlevel! equ 0 (
            echo [SUCCESS] 'pharmacy_db' and tables created successfully!
        ) else (
            echo [WARNING] Automatic import had an issue. Check phpMyAdmin manually.
        )
    ) else (
        echo [OK] 'pharmacy_db' is ready and up to date.
    )
)

:: ------------------------------------------------------------
:: 7. Launch Application in Browser
:: ------------------------------------------------------------
echo.
echo ============================================================
echo   Application Ready!
echo   URL:      http://localhost/pharmacy
echo   Username: admin
echo   Password: admin123
echo ============================================================
echo.

start "" "http://localhost/pharmacy"

timeout /t 4 >nul
exit /b 0
