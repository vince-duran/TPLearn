# 🌐 TPLearn Domain Setup Guide
## Setting up tplearn.tech with your TPLearn System

### 📋 Prerequisites
- ✅ Domain registered: `tplearn.tech`
- ✅ TPLearn system installed and working locally
- ✅ Web hosting server or VPS
- ✅ SSL certificate (Let's Encrypt recommended)

### 🔧 Step 1: DNS Configuration

In your .TECH domain control panel, add these DNS records:

#### A Records
```
@ (root)          →  Your server IP address
www               →  Your server IP address
app               →  Your server IP address
api               →  Your server IP address
```

#### CNAME Records (alternative to multiple A records)
```
www               →  tplearn.tech
app               →  tplearn.tech
api               →  tplearn.tech
```

#### MX Records (for email)
```
@                 →  mail.tplearn.tech (Priority: 10)
```

### 🔐 Step 2: SSL Certificate Setup

#### Option A: Let's Encrypt (Free)
```bash
# Install Certbot
sudo apt-get install certbot python3-certbot-apache

# Get SSL certificate
sudo certbot --apache -d tplearn.tech -d www.tplearn.tech -d app.tplearn.tech -d api.tplearn.tech
```

#### Option B: Commercial SSL
1. Purchase SSL certificate from provider
2. Generate CSR (Certificate Signing Request)
3. Install certificate files in Apache configuration

### ⚙️ Step 3: Apache Configuration

1. **Enable required modules:**
```bash
sudo a2enmod rewrite
sudo a2enmod ssl
sudo a2enmod headers
sudo a2enmod deflate
sudo a2enmod expires
```

2. **Copy virtual host configuration:**
```bash
cp /path/to/TPLearn/config/apache-vhost.conf /etc/apache2/sites-available/tplearn.conf
sudo a2ensite tplearn.conf
```

3. **Restart Apache:**
```bash
sudo systemctl restart apache2
```

### 🗄️ Step 4: Database Setup

#### Create Production Database
```sql
CREATE DATABASE tplearn_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'tplearn_user'@'localhost' IDENTIFIED BY 'secure_password_here';
GRANT ALL PRIVILEGES ON tplearn_prod.* TO 'tplearn_user'@'localhost';
FLUSH PRIVILEGES;
```

#### Import Database
```bash
mysql -u tplearn_user -p tplearn_prod < /path/to/TPLearn/database.sql
```

### 📁 Step 5: File Upload and Permissions

#### Set proper permissions
```bash
sudo chown -R www-data:www-data /var/www/tplearn.tech/
sudo chmod -R 755 /var/www/tplearn.tech/
sudo chmod -R 777 /var/www/tplearn.tech/uploads/
sudo chmod -R 777 /var/www/tplearn.tech/logs/
sudo chmod -R 777 /var/www/tplearn.tech/cache/
```

#### Secure sensitive directories
```bash
sudo chmod 600 /var/www/tplearn.tech/config/domain-config.php
sudo chmod 600 /var/www/tplearn.tech/includes/db-domain.php
```

### 🔧 Step 6: Configuration Updates

#### Update `config/domain-config.php`:
```php
// Update these values
define('DB_HOST', 'localhost');
define('DB_USER', 'tplearn_user');
define('DB_PASS', 'your_secure_password');
define('DB_NAME', 'tplearn_prod');

define('SMTP_USERNAME', 'noreply@tplearn.tech');
define('SMTP_PASSWORD', 'your_email_password');
```

#### Update main application files to use new database connection:
```php
// Replace in all PHP files:
require_once 'includes/db.php';
// With:
require_once 'includes/db-domain.php';
```

### 🧪 Step 7: Testing

#### Local Testing (before DNS propagation)
Add to `C:\Windows\System32\drivers\etc\hosts` (Windows) or `/etc/hosts` (Linux):
```
your.server.ip.address tplearn.tech
your.server.ip.address www.tplearn.tech
your.server.ip.address app.tplearn.tech
your.server.ip.address api.tplearn.tech
```

#### Test URLs
- Main site: `https://tplearn.tech`
- Student portal: `https://app.tplearn.tech/student`
- Tutor portal: `https://app.tplearn.tech/tutor`
- Admin portal: `https://app.tplearn.tech/admin`
- API endpoint: `https://api.tplearn.tech/students`

### 📧 Step 8: Email Configuration

#### Gmail SMTP (for development)
```php
define('SMTP_HOST', 'smtp.gmail.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'your-email@gmail.com');
define('SMTP_PASSWORD', 'your-app-password');
```

#### Professional Email (recommended)
```php
define('SMTP_HOST', 'mail.tplearn.tech');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'noreply@tplearn.tech');
define('SMTP_PASSWORD', 'email_password');
```

### 🚀 Step 9: Performance Optimization

#### Enable OPcache
```ini
; In php.ini
opcache.enable=1
opcache.enable_cli=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=12
opcache.max_accelerated_files=4000
opcache.revalidate_freq=60
```

#### Enable Redis (optional)
```bash
sudo apt-get install redis-server php-redis
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### 🔒 Step 10: Security Hardening

#### Hide server information
```apache
# In .htaccess or Apache config
ServerTokens Prod
ServerSignature Off
```

#### Update PHP security settings
```ini
; In php.ini
expose_php = Off
display_errors = Off
log_errors = On
error_log = /var/log/php_errors.log
```

#### Set up fail2ban (optional)
```bash
sudo apt-get install fail2ban
# Configure jail for Apache
```

### 📊 Step 11: Monitoring Setup

#### Error Logging
```php
// In domain-config.php
define('LOG_PATH', '/var/www/tplearn.tech/logs/');
define('LOG_LEVEL', 'ERROR');
```

#### Google Analytics (optional)
Add tracking code to `includes/header.php`

#### Uptime Monitoring
Set up monitoring with services like:
- UptimeRobot
- Pingdom
- StatusCake

### 🔄 Step 12: Backup Strategy

#### Automated backups
```bash
#!/bin/bash
# Daily backup script
DATE=$(date +%Y%m%d)
mysqldump -u tplearn_user -p tplearn_prod > /backups/db_backup_$DATE.sql
tar -czf /backups/files_backup_$DATE.tar.gz /var/www/tplearn.tech/
```

#### Setup cron job
```bash
# Run daily at 2 AM
0 2 * * * /path/to/backup-script.sh
```

### ✅ Step 13: Go Live Checklist

- [ ] DNS records configured and propagated
- [ ] SSL certificates installed and working
- [ ] Database migrated and tested
- [ ] File permissions set correctly
- [ ] Email functionality tested
- [ ] All URLs redirecting properly
- [ ] Performance optimizations applied
- [ ] Security measures implemented
- [ ] Monitoring and logging configured
- [ ] Backup strategy in place
- [ ] Error pages customized
- [ ] SEO meta tags updated

### 🆘 Troubleshooting

#### Common Issues

**DNS not propagating:**
- Wait 24-48 hours for full propagation
- Check with DNS checker tools
- Clear browser DNS cache

**SSL certificate errors:**
- Verify certificate installation
- Check certificate expiration
- Ensure all subdomains are covered

**Database connection errors:**
- Verify credentials in domain-config.php
- Check MySQL user permissions
- Ensure database exists

**Permission errors:**
- Check file/folder permissions
- Verify web server user ownership
- Ensure upload directories are writable

### 📞 Support

For additional help:
1. Check error logs in `/logs/` directory
2. Review Apache error logs
3. Test with curl commands
4. Use browser developer tools
5. Contact hosting provider if server issues

### 🎉 Success!

Once completed, your TPLearn system will be accessible at:
- **Main Site:** https://tplearn.tech
- **Student Portal:** https://app.tplearn.tech/student
- **Tutor Portal:** https://app.tplearn.tech/tutor  
- **Admin Portal:** https://app.tplearn.tech/admin
- **API Endpoints:** https://api.tplearn.tech/*

Your professional Learning Management System is now live! 🚀