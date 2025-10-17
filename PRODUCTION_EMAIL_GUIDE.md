# Production Email Setup Guide

## Why Not Gmail for Production?

While Gmail works for development and small projects, production applications should use dedicated email services:

- **Higher sending limits**
- **Better deliverability**
- **Professional features** (analytics, templates, etc.)
- **No personal account dependency**
- **Better support**

## Recommended Services

### 1. SendGrid (Recommended)

**Why SendGrid?**
- Excellent deliverability
- Generous free tier (100 emails/day free)
- Great documentation
- Easy integration

**Setup:**
1. Sign up at [SendGrid](https://sendgrid.com)
2. Verify your domain or single sender
3. Get your API key
4. Configure TPLearn:

```php
'provider' => 'sendgrid',
'sendgrid' => [
    'username' => 'apikey',
    'password' => 'SG.your-api-key-here',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'TPLearn',
],
```

### 2. Mailgun

**Why Mailgun?**
- Powerful API
- Good free tier (5,000 emails/month)
- Excellent for developers

**Setup:**
1. Sign up at [Mailgun](https://mailgun.com)
2. Add and verify your domain
3. Get SMTP credentials
4. Configure TPLearn:

```php
'provider' => 'mailgun',
'mailgun' => [
    'username' => 'postmaster@mg.yourdomain.com',
    'password' => 'your-mailgun-password',
    'from_email' => 'noreply@yourdomain.com',
    'from_name' => 'TPLearn',
],
```

### 3. AWS SES (Advanced)

**Why AWS SES?**
- Extremely cost-effective (high volume)
- Integrated with AWS ecosystem
- Professional grade

**Setup:** (More complex, requires AWS account)
1. Set up AWS SES
2. Verify domain/email
3. Request production access
4. Configure SMTP credentials

## Domain Setup (Important!)

### 1. Domain Verification
Most services require domain verification to improve deliverability:

1. Add your domain to the email service
2. Add DNS records (TXT, CNAME)
3. Wait for verification

### 2. SPF Record
Add to your domain's DNS:
```
v=spf1 include:sendgrid.net ~all
```
(Replace with your provider's SPF record)

### 3. DKIM
Your email provider will give you DKIM records to add to DNS.

### 4. DMARC (Optional but recommended)
```
v=DMARC1; p=none; rua=mailto:dmarc@yourdomain.com
```

## Environment Variables (Security)

**Never hard-code credentials!** Use environment variables:

### 1. Create .env file
```env
EMAIL_PROVIDER=sendgrid
SENDGRID_API_KEY=SG.your-key-here
FROM_EMAIL=noreply@yourdomain.com
FROM_NAME=TPLearn
BASE_URL=https://yourdomain.com
```

### 2. Update config/email.php
```php
// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

return [
    'provider' => $_ENV['EMAIL_PROVIDER'] ?? 'simulate',
    'sendgrid' => [
        'username' => 'apikey',
        'password' => $_ENV['SENDGRID_API_KEY'] ?? '',
        'from_email' => $_ENV['FROM_EMAIL'] ?? '',
        'from_name' => $_ENV['FROM_NAME'] ?? 'TPLearn',
    ],
    'templates' => [
        'base_url' => $_ENV['BASE_URL'] ?? 'http://localhost',
    ],
];
```

### 3. Install vlucas/phpdotenv
```bash
composer require vlucas/phpdotenv
```

## Production Checklist

### Email Service Setup
- [ ] Sign up for production email service
- [ ] Verify domain/sender email
- [ ] Configure DNS records (SPF, DKIM)
- [ ] Get API keys/SMTP credentials
- [ ] Test email sending

### TPLearn Configuration
- [ ] Update config/email.php with production settings
- [ ] Use environment variables for credentials
- [ ] Set correct base_url for production
- [ ] Disable debug mode
- [ ] Test registration flow

### Security
- [ ] Use environment variables for all credentials
- [ ] Add .env to .gitignore
- [ ] Ensure HTTPS for production
- [ ] Set up proper error logging

### Monitoring
- [ ] Set up email delivery monitoring
- [ ] Monitor bounce rates
- [ ] Track verification rates
- [ ] Set up alerts for failures

## SendGrid Detailed Setup

### 1. Account Setup
1. Go to [SendGrid](https://sendgrid.com)
2. Sign up for free account
3. Complete email verification

### 2. Sender Authentication
1. Go to Settings > Sender Authentication
2. Choose "Single Sender Verification" (easiest) or "Domain Authentication" (better)
3. For single sender: enter your from email and verify
4. For domain: follow DNS setup instructions

### 3. API Key
1. Go to Settings > API Keys
2. Create API Key with "Mail Send" permissions
3. Copy the key (starts with "SG.")

### 4. Configure TPLearn
Use the email setup tool at `http://yourdomain.com/email-setup.php`

## Deliverability Best Practices

### 1. Authentication
- Set up SPF, DKIM, and DMARC records
- Use verified domains/senders

### 2. Content
- Avoid spam trigger words
- Include unsubscribe links (for newsletters)
- Use clear, professional language

### 3. Monitoring
- Monitor bounce and complaint rates
- Keep lists clean
- Remove invalid emails

### 4. Volume
- Start with low volumes
- Gradually increase sending
- Warm up your domain/IP

## Troubleshooting

### Emails Not Delivered
1. Check spam folder
2. Verify DNS records
3. Check service logs/analytics
4. Verify sender authentication

### High Bounce Rate
1. Clean email lists
2. Use double opt-in
3. Remove invalid emails promptly

### Low Open Rates
1. Improve subject lines
2. Check sender reputation
3. Verify authentication setup

## Cost Considerations

### SendGrid Pricing
- Free: 100 emails/day
- Essentials: $14.95/month (40,000 emails)
- Pro: $89.95/month (100,000 emails)

### Mailgun Pricing
- Free: 5,000 emails/month for 3 months
- Pay-as-you-go: $0.80/1,000 emails
- Monthly plans available

### AWS SES Pricing
- $0.10 per 1,000 emails
- Extremely cost-effective for high volume

## Migration from Development

### 1. Test Environment
Set up staging environment with production email service

### 2. Gradual Rollout
1. Test with small group
2. Monitor delivery rates
3. Full rollout after verification

### 3. Backup Plan
Keep development configuration as fallback

## Support

### Getting Help
- SendGrid: Excellent documentation and support
- Mailgun: Good documentation, community support
- AWS SES: AWS support (paid tiers)

### Monitoring Tools
- Email service dashboards
- Third-party monitoring (like Litmus)
- Custom logging and alerts