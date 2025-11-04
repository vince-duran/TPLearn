@echo off
REM =============================
REM TPLearn Deployment Package Creator
REM =============================

echo Creating TPLearn deployment package...
echo.

REM Set variables
set SOURCE_DIR=C:\xampp\htdocs\TPLearn
set DEPLOY_DIR=%SOURCE_DIR%\deployment_package
set ZIP_FILE=%SOURCE_DIR%\tplearn_deployment_%date:~-4,4%%date:~-10,2%%date:~-7,2%.zip

REM Create deployment directory
if exist "%DEPLOY_DIR%" rmdir /s /q "%DEPLOY_DIR%"
mkdir "%DEPLOY_DIR%"

echo Step 1: Copying core files...
REM Copy all PHP files
xcopy "%SOURCE_DIR%\*.php" "%DEPLOY_DIR%\" /Y
xcopy "%SOURCE_DIR%\api" "%DEPLOY_DIR%\api\" /E /Y
xcopy "%SOURCE_DIR%\dashboards" "%DEPLOY_DIR%\dashboards\" /E /Y
xcopy "%SOURCE_DIR%\includes" "%DEPLOY_DIR%\includes\" /E /Y
xcopy "%SOURCE_DIR%\config" "%DEPLOY_DIR%\config\" /E /Y

echo Step 2: Copying assets and static files...
xcopy "%SOURCE_DIR%\assets" "%DEPLOY_DIR%\assets\" /E /Y
xcopy "%SOURCE_DIR%\css" "%DEPLOY_DIR%\css\" /E /Y
xcopy "%SOURCE_DIR%\js" "%DEPLOY_DIR%\js\" /E /Y
xcopy "%SOURCE_DIR%\images" "%DEPLOY_DIR%\images\" /E /Y

echo Step 3: Copying database and SQL files...
xcopy "%SOURCE_DIR%\database.sql" "%DEPLOY_DIR%\" /Y
xcopy "%SOURCE_DIR%\*.sql" "%DEPLOY_DIR%\" /Y
if exist "%SOURCE_DIR%\sql" xcopy "%SOURCE_DIR%\sql" "%DEPLOY_DIR%\sql\" /E /Y
if exist "%SOURCE_DIR%\database" xcopy "%SOURCE_DIR%\database" "%DEPLOY_DIR%\database\" /E /Y

echo Step 4: Creating empty directories...
mkdir "%DEPLOY_DIR%\uploads"
mkdir "%DEPLOY_DIR%\uploads\payment_receipts"
mkdir "%DEPLOY_DIR%\uploads\assignments" 
mkdir "%DEPLOY_DIR%\uploads\program_materials"
mkdir "%DEPLOY_DIR%\uploads\program_covers"
mkdir "%DEPLOY_DIR%\logs"
mkdir "%DEPLOY_DIR%\cache"

echo Step 5: Copying configuration files...
xcopy "%SOURCE_DIR%\.htaccess" "%DEPLOY_DIR%\" /Y
xcopy "%SOURCE_DIR%\index.html" "%DEPLOY_DIR%\" /Y

echo Step 6: Creating deployment notes...
echo TPLearn Deployment Package > "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo Generated: %date% %time% >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo. >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo DEPLOYMENT STEPS: >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 1. Upload all files to public_html/ directory >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 2. Create MySQL database in hosting control panel >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 3. Import database.sql via phpMyAdmin >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 4. Update includes/db.php with production database credentials >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 5. Set permissions: uploads/ and logs/ to 755 >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 6. Test connection with check_db_connection.php >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo 7. Delete check_db_connection.php after testing >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo. >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo EXCLUDED FROM PACKAGE: >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo - .git/ directory >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo - node_modules/ directory >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo - Development documentation >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"
echo - Test files >> "%DEPLOY_DIR%\DEPLOYMENT_NOTES.txt"

echo.
echo =============================
echo Deployment package created!
echo =============================
echo.
echo Location: %DEPLOY_DIR%
echo.
echo Ready for upload to your PHP hosting provider.
echo.
echo Next steps:
echo 1. Purchase PHP hosting (recommended: Hostinger)
echo 2. Upload contents of deployment_package/ to public_html/
echo 3. Create MySQL database and import database.sql
echo 4. Update includes/db.php with production credentials
echo 5. Test with check_db_connection.php
echo.
pause