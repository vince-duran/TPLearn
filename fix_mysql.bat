@echo off
echo ====================================
echo XAMPP MySQL Troubleshooting Tool
echo ====================================
echo.

echo Step 1: Checking if port 3306 is in use...
netstat -ano | findstr :3306
if %ERRORLEVEL% EQU 0 (
    echo [WARNING] Port 3306 is being used by another process!
    echo You need to stop that process first.
) else (
    echo [OK] Port 3306 is available.
)
echo.

echo Step 2: Checking MySQL service status...
sc query MySQL
if %ERRORLEVEL% EQU 0 (
    echo [INFO] MySQL service is installed.
    echo Attempting to stop it...
    net stop MySQL
) else (
    echo [OK] No MySQL service found (this is normal for XAMPP).
)
echo.

echo Step 3: Checking for error logs...
if exist "C:\xampp\mysql\data\*.err" (
    echo [FOUND] Error log exists. Last 20 lines:
    echo ----------------------------------------
    for /f %%i in ('dir /b /od "C:\xampp\mysql\data\*.err" 2^>nul') do set ERRLOG=%%i
    if defined ERRLOG (
        powershell -Command "Get-Content 'C:\xampp\mysql\data\%ERRLOG%' -Tail 20"
    )
) else (
    echo [INFO] No error log found.
)
echo.

echo Step 4: Recommended Solutions
echo ====================================
echo.
echo SOLUTION 1 (Try First): Restart MySQL in XAMPP
echo - Click Stop on MySQL (if running)
echo - Wait 5 seconds
echo - Click Start on MySQL
echo.
echo SOLUTION 2: Backup and Reset MySQL Data
echo - Stop XAMPP
echo - Rename C:\xampp\mysql\data to data_old
echo - Copy C:\xampp\mysql\backup to C:\xampp\mysql\data
echo - Copy your database folders from data_old to data
echo - Start XAMPP
echo.
echo SOLUTION 3: Check if another MySQL/MariaDB is running
echo - Open Task Manager
echo - Look for "mysqld.exe" or "mariadb.exe"
echo - End those processes
echo - Try starting MySQL in XAMPP again
echo.
echo ====================================
pause
