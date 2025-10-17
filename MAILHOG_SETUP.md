# MailHog Setup for TPLearn Email Testing

## What is MailHog?
MailHog is a local email testing tool that catches emails sent from your application and displays them in a web interface. Perfect for development!

## Installation Options

### Option 1: Download Executable (Easiest)
1. Download MailHog from: https://github.com/mailhog/MailHog/releases
2. Download the Windows executable: `MailHog_windows_amd64.exe`
3. Rename it to `mailhog.exe`
4. Place it in a folder like `C:\tools\mailhog\`
5. Add that folder to your Windows PATH

### Option 2: Using Go (if you have Go installed)
```bash
go get github.com/mailhog/MailHog
```

### Option 3: Using Docker
```bash
docker run -d -p 1025:1025 -p 8025:8025 mailhog/mailhog
```

## Running MailHog

### Start MailHog
Open Command Prompt or PowerShell and run:
```bash
mailhog
```

You should see output like:
```
[HTTP] Binding to address: 0.0.0.0:8025
[SMTP] Binding to address: 0.0.0.0:1025
```

### Access MailHog Web Interface
Open your browser and go to: http://localhost:8025

## Configure TPLearn to Use MailHog

1. Edit `config/email.php`
2. Change the provider to 'local':
```php
'provider' => 'local',
```

3. MailHog settings are already configured:
```php
'local' => [
    'host' => 'localhost',
    'port' => 1025,
    'security' => false,
    'auth' => false,
],
```

## Testing Email

1. Start MailHog: `mailhog`
2. Set TPLearn to use 'local' provider
3. Register a new account on TPLearn
4. Check http://localhost:8025 to see the verification email
5. Click the verification link in MailHog interface

## Troubleshooting

### Port 1025 or 8025 Already in Use
```bash
# Check what's using the ports
netstat -ano | findstr :1025
netstat -ano | findstr :8025

# Kill the process if needed (replace PID with actual process ID)
taskkill /PID <PID> /F
```

### MailHog Not Starting
- Make sure it's in your PATH
- Try running with full path: `C:\tools\mailhog\mailhog.exe`
- Check Windows Defender/Antivirus isn't blocking it

### Can't Access Web Interface
- Make sure MailHog is running
- Try http://127.0.0.1:8025 instead of localhost
- Check Windows Firewall settings

## Alternative: MailDev (Node.js)

If you prefer Node.js, you can use MailDev instead:

```bash
# Install globally
npm install -g maildev

# Run
maildev

# Web interface: http://localhost:1080
# SMTP port: 1025 (same as MailHog)
```

For MailDev, you'd need to update the config:
```php
'local' => [
    'host' => 'localhost',
    'port' => 1025,
    'security' => false,
    'auth' => false,
],
```

The web interface would be at http://localhost:1080 instead of 8025.