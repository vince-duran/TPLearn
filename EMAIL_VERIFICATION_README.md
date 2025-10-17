# Email Verification System - TPLearn

## Overview
This email verification system ensures that users verify their email addresses before they can log in to their accounts. This improves security and ensures valid email addresses for communication.

## Features
- ✅ Email verification required for new registrations
- ✅ Secure token-based verification (64-character tokens)
- ✅ Token expiration (24 hours)
- ✅ Resend verification functionality
- ✅ Email verification status checking
- ✅ Clean up of expired tokens
- ✅ User-friendly verification pages
- ✅ Integration with existing login system

## Files Added/Modified

### New Files
1. **`database/email_verification_setup.sql`** - Database schema
2. **`includes/email-verification.php`** - Core email verification functions
3. **`verify-email.php`** - Email verification page
4. **`resend-verification.php`** - Resend verification email page
5. **`config/email.php`** - Email configuration
6. **`test-email-verification.php`** - Testing script
7. **`setup_email_verification.php`** - Database setup script

### Modified Files
1. **`register.php`** - Updated to send verification emails
2. **`login.php`** - Added email verification check
3. **`composer.json`** - Added PHPMailer dependency

## Database Changes

### New Table: `email_verifications`
```sql
CREATE TABLE email_verifications (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    email VARCHAR(255) NOT NULL,
    token VARCHAR(64) NOT NULL UNIQUE,
    expires_at DATETIME NOT NULL,
    verified_at DATETIME NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
```

### Modified Table: `users`
- Added `email_verified` TINYINT(1) DEFAULT 0

## Configuration

### Email Settings (`config/email.php`)
```php
return [
    'provider' => 'local', // 'smtp' for production, 'local' for development
    'smtp' => [
        'host' => 'smtp.gmail.com',
        'username' => 'your-email@gmail.com',
        'password' => 'your-app-password',
        // ... other SMTP settings
    ],
    'local' => [
        'host' => 'localhost',
        'port' => 1025, // MailHog default
        // ... other local settings
    ]
];
```

## Usage Flow

### 1. Registration Process
1. User fills out registration form
2. Account is created with `email_verified = 0`
3. Verification email is sent with unique token
4. Success modal shows email verification required message

### 2. Email Verification
1. User clicks link in email: `verify-email.php?token=ABC123...`
2. System validates token (not expired, exists, not already used)
3. If valid: sets `email_verified = 1` and `verified_at = NOW()`
4. User is redirected to login with success message

### 3. Login Process
1. User enters credentials
2. System validates username/password
3. **NEW**: Checks if `email_verified = 1`
4. If not verified: shows error with resend link
5. If verified: proceeds with normal login

### 4. Resend Verification
1. User visits `resend-verification.php`
2. Enters email address
3. System generates new token and sends email
4. Old tokens for that user are deleted

## Email Configuration for Production

### Gmail SMTP
1. Enable 2-factor authentication on your Google account
2. Generate an App Password for your application
3. Update `config/email.php`:
```php
'provider' => 'smtp',
'smtp' => [
    'host' => 'smtp.gmail.com',
    'username' => 'your-email@gmail.com',
    'password' => 'your-16-character-app-password',
    'port' => 587,
    'security' => 'tls'
]
```

### Other Email Providers
- **SendGrid**: Use their SMTP settings
- **Mailgun**: Use their SMTP settings
- **AWS SES**: Configure with AWS credentials

## Development Setup

### Using MailHog (Recommended)
1. Install MailHog: `go get github.com/mailhog/MailHog`
2. Run: `MailHog`
3. Set config to `'provider' => 'local'`
4. View emails at: `http://localhost:8025`

### Using MailDev (Alternative)
1. Install: `npm install -g maildev`
2. Run: `maildev`
3. View emails at: `http://localhost:1080`

## Testing

### Run the Test Script
Visit `http://localhost/TPLearn/test-email-verification.php` to:
- Check database setup
- Test token generation
- Send test emails
- Verify configuration

### Manual Testing
1. Register a new account
2. Check that verification email is sent
3. Click verification link
4. Try logging in before and after verification
5. Test resend functionality

## Security Features

### Token Security
- 64-character random tokens (256-bit entropy)
- Tokens expire after 24 hours
- One-time use tokens (marked as used after verification)
- Tokens are deleted after use

### Rate Limiting
- Login attempts are rate-limited (existing feature)
- Email sending could be rate-limited (future enhancement)

### Data Protection
- User emails are validated
- SQL injection protection with prepared statements
- XSS protection with htmlspecialchars()

## Maintenance

### Cleanup Expired Tokens
Run periodically (e.g., daily cron job):
```php
require_once 'includes/email-verification.php';
$deleted = cleanupExpiredTokens();
echo "Deleted $deleted expired tokens\n";
```

### Monitor Email Sending
- Check email provider logs
- Monitor bounce rates
- Track verification completion rates

## Error Handling

### Common Issues
1. **Emails not sending**: Check SMTP settings and credentials
2. **Links not working**: Verify base_url in config
3. **Database errors**: Ensure tables are created properly
4. **Permission errors**: Check file permissions

### Error Messages
- Invalid/expired tokens: User-friendly error with resend option
- Email sending failures: Graceful fallback with manual contact info
- Database errors: Generic error message, detailed logs

## Future Enhancements

### Possible Improvements
1. **Email templates**: HTML email template system
2. **Rate limiting**: Prevent email spam
3. **Analytics**: Track verification rates
4. **Multi-language**: Support multiple languages
5. **SMS verification**: Alternative verification method
6. **Social login**: OAuth integration with email verification

## Support

### Troubleshooting
1. Check `test-email-verification.php` for system status
2. Verify email configuration
3. Check server error logs
4. Test with different email providers

### Contact
For issues with this system, check:
1. PHP error logs
2. Email provider logs
3. Database connection
4. SMTP authentication

---

**Note**: Always test the email verification system thoroughly before deploying to production, especially the email sending functionality with your chosen email provider.