# Gmail SMTP Setup Guide for TPLearn

## Step-by-Step Gmail Configuration

### 1. Enable 2-Factor Authentication
Before you can use Gmail SMTP, you **must** enable 2-factor authentication on your Google account.

1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Under "Signing in to Google", click "2-Step Verification"
3. Follow the setup process to enable 2FA

### 2. Generate an App Password
You **cannot** use your regular Gmail password for SMTP. You need an App Password.

1. Go to [Google Account Security](https://myaccount.google.com/security)
2. Under "Signing in to Google", click "App passwords"
3. Select "Mail" as the app and "Other" as the device
4. Enter "TPLearn" as the device name
5. Click "Generate"
6. **Copy the 16-character password** (it looks like: `abcd efgh ijkl mnop`)
ikbk apij sgoo vcmj
### 3. Configure TPLearn

#### Option A: Using the Email Setup Tool
1. Go to: `http://localhost/TPLearn/email-setup.php`
2. Select "Gmail SMTP" as the provider
3. Fill in the Gmail configuration:
   - **Gmail Address**: Your full Gmail address (e.g., `yourname@gmail.com`)
   - **App Password**: The 16-character password from step 2
   - **From Name**: TPLearn (or your preferred sender name)
   - **From Email**: Same as your Gmail address
4. Click "Save Configuration"
5. Test with "Send Test Email"

#### Option B: Manual Configuration
Edit `config/email.php`:

```php
return [
    'provider' => 'gmail',
    'gmail' => [
        'host' => 'smtp.gmail.com',
        'port' => 587,
        'security' => 'tls',
        'auth' => true,
        'username' => 'yourname@gmail.com',        // Your Gmail address
        'password' => 'abcd efgh ijkl mnop',       // Your 16-character App Password
        'from_name' => 'TPLearn',
        'from_email' => 'yourname@gmail.com',      // Same as username
    ],
    // ... rest of config
];
```

### 4. Test the Configuration

1. Use the email setup tool: `http://localhost/TPLearn/email-setup.php`
2. Or register a new test account
3. Check that verification emails are sent

### 5. Security Considerations

#### For Development
- Use a dedicated Gmail account for development
- Don't use your personal Gmail account

#### For Production
- Consider using a business Gmail account (Google Workspace)
- Use environment variables for credentials:

```php
'gmail' => [
    'username' => $_ENV['GMAIL_USERNAME'] ?? '',
    'password' => $_ENV['GMAIL_APP_PASSWORD'] ?? '',
    // ...
],
```

### 6. Troubleshooting

#### "Username and Password not accepted"
- Make sure you're using the App Password, not your regular password
- Verify 2-factor authentication is enabled
- Check that the email address is correct

#### "Could not authenticate"
- The App Password might be incorrect
- Try generating a new App Password
- Ensure no extra spaces in the password

#### "Connection refused" or "Could not connect"
- Check your firewall settings
- Verify port 587 is not blocked
- Try using port 465 with SSL instead:

```php
'gmail' => [
    'port' => 465,
    'security' => 'ssl',
    // ... other settings
],
```

#### Gmail Blocks the Connection
- Gmail might temporarily block connections from new locations
- Check your Gmail security notifications
- You might need to verify the login attempt

### 7. Gmail Sending Limits

Gmail has sending limits to prevent spam:

- **Free Gmail**: 500 emails per day
- **Google Workspace**: 2,000 emails per day

For high-volume sending, consider:
- SendGrid (recommended for production)
- Mailgun
- AWS SES

### 8. Alternative: OAuth2 (Advanced)

For production applications, consider using OAuth2 instead of App Passwords:

1. Create a Google Cloud Project
2. Enable Gmail API
3. Set up OAuth2 credentials
4. Use PHPMailer with OAuth2

This is more secure but more complex to set up.

### 9. Testing Commands

You can test Gmail SMTP from command line:

```bash
# Test SMTP connection
telnet smtp.gmail.com 587

# In telnet session:
EHLO gmail.com
STARTTLS
# (connection should upgrade to TLS)
```

### 10. Environment Variables (Recommended for Production)

Create a `.env` file:
```env
GMAIL_USERNAME=yourname@gmail.com
GMAIL_APP_PASSWORD=abcd efgh ijkl mnop
```

Update your config:
```php
'gmail' => [
    'username' => $_ENV['GMAIL_USERNAME'] ?? '',
    'password' => $_ENV['GMAIL_APP_PASSWORD'] ?? '',
    'from_email' => $_ENV['GMAIL_USERNAME'] ?? '',
    // ...
],
```

## Quick Setup Checklist

- [ ] Enable 2-factor authentication on Gmail
- [ ] Generate App Password
- [ ] Update `config/email.php` with Gmail settings
- [ ] Set provider to 'gmail'
- [ ] Test email sending
- [ ] Verify emails are received
- [ ] Check spam folder if emails not received

## Next Steps

Once Gmail is working:
1. Test the full registration flow
2. Set up proper logging
3. Configure for production environment
4. Consider switching to a dedicated email service for production