@echo off
echo Running TPLearn Overdue Payments Update...
cd /d "C:\xampp\htdocs\TPLearn"
php cron\update-overdue-payments.php
echo.
echo Overdue payments update completed.
pause