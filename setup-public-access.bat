@echo off
echo ====================================
echo  TPLearn Public Access Setup Script
echo ====================================
echo.

echo Checking if ngrok is installed...
where ngrok >nul 2>&1
if %errorlevel% neq 0 (
    echo ❌ ngrok is not installed or not in PATH
    echo.
    echo Please install ngrok:
    echo 1. Go to https://ngrok.com/download
    echo 2. Download ngrok for Windows
    echo 3. Extract to a folder in your PATH
    echo 4. Run: ngrok authtoken YOUR_TOKEN
    echo.
    pause
    exit /b 1
)

echo ✅ ngrok found
echo.

echo Starting ngrok tunnel for port 8080...
echo This will create a public HTTPS URL for your video conferencing system
echo.
echo ⚠️  WARNING: This exposes your local server to the internet
echo    Only use for testing with trusted users
echo.
echo Starting tunnel...
start /B ngrok http 8080 --log=stdout

echo.
echo ✅ Tunnel started!
echo.
echo 📋 Next Steps:
echo 1. Check the ngrok terminal window for your public URL
echo 2. Look for a line like: "Forwarding https://abc123.ngrok.io -> http://localhost:8080"
echo 3. Use the HTTPS URL (not HTTP) for video conferencing
echo 4. Share the public URL with remote users
echo.
echo 🎯 Example URLs:
echo Tutor: https://YOUR_NGROK_URL.ngrok.io/dashboards/tutor/tutor-programs.php
echo Student: https://YOUR_NGROK_URL.ngrok.io/video-conference/student-join.php?session=SESSION_ID&program_id=1
echo.
echo ⚠️  Security Notes:
echo - Never share your ngrok URLs publicly
echo - Only use with trusted users
echo - Consider adding password protection
echo - Monitor ngrok dashboard for activity
echo.
pause