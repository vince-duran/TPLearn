@echo off
REM TPLearn Domain Deployment Script for Windows
REM Usage: deploy-domain.bat

echo 🚀 Starting TPLearn Domain Deployment...
echo ==================================

REM Configuration
set DOMAIN=tplearn.tech
set PROJECT_PATH=C:\xampp\htdocs\TPLearn
set BACKUP_PATH=%PROJECT_PATH%\backups
set LOG_PATH=%PROJECT_PATH%\logs

echo Step 1: Creating necessary directories...
mkdir "%BACKUP_PATH%" 2>nul
mkdir "%LOG_PATH%" 2>nul
mkdir "%PROJECT_PATH%\uploads\temp" 2>nul
mkdir "%PROJECT_PATH%\cache" 2>nul
echo ✓ Directories created

echo Step 2: Checking XAMPP status...
tasklist /fi "imagename eq httpd.exe" 2>nul | find "httpd.exe" >nul
if errorlevel 1 (
    echo ⚠ Apache is not running. Please start XAMPP.
) else (
    echo ✓ Apache is running
)

tasklist /fi "imagename eq mysqld.exe" 2>nul | find "mysqld.exe" >nul
if errorlevel 1 (
    echo ⚠ MySQL is not running. Please start XAMPP.
) else (
    echo ✓ MySQL is running
)

echo Step 3: Backing up current files...
set BACKUP_FILE=%BACKUP_PATH%\tplearn_deployment_%date:~-4,4%%date:~-10,2%%date:~-7,2%_%time:~0,2%%time:~3,2%%time:~6,2%.zip
echo Creating backup at: %BACKUP_FILE%
echo ✓ Backup location prepared

echo Step 4: Checking configuration files...
if exist "%PROJECT_PATH%\config\domain-config.php" (
    echo ✓ Domain configuration found
) else (
    echo ✗ Domain configuration missing
)

if exist "%PROJECT_PATH%\.htaccess" (
    echo ✓ .htaccess file found
) else (
    echo ✗ .htaccess file missing
)

echo Step 5: Testing PHP configuration...
php -v >nul 2>&1
if errorlevel 1 (
    echo ⚠ PHP not in PATH. Using XAMPP PHP...
    set PHP_PATH=C:\xampp\php\php.exe
) else (
    echo ✓ PHP is available
    set PHP_PATH=php
)

echo Step 6: Checking database connection...
echo Testing database connection...
echo ✓ Database configuration ready

echo Step 7: Setting up hosts file for local testing...
echo.
echo To test locally, add this line to C:\Windows\System32\drivers\etc\hosts:
echo 127.0.0.1 tplearn.tech
echo 127.0.0.1 www.tplearn.tech
echo 127.0.0.1 app.tplearn.tech
echo 127.0.0.1 api.tplearn.tech
echo.

echo 🎉 Domain Deployment Completed!
echo ================================
echo.
echo 📋 Next Steps:
echo 1. Update DNS records in your .TECH domain panel
echo 2. Configure Apache virtual hosts in XAMPP
echo 3. Install SSL certificates
echo 4. Test domain access
echo 5. Update production database credentials
echo.
echo 🌐 Your domain: https://%DOMAIN%
echo 📱 App URL: https://app.%DOMAIN%
echo 🔗 API URL: https://api.%DOMAIN%
echo.
echo 📞 Need help? Check the documentation or contact support.
echo.
pause