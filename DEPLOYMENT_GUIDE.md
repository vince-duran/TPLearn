# TPLearn Deployment Guide for tplearn.site

## Files to Upload to Hostinger

### 1. Website Files
**File:** `TPLearn-website-backup.zip`
- **Size:** ~40 MB
- **Contains:** All your TPLearn PHP files, assets, and configuration

### 2. Database Backup
**File:** `TPLearn-database-backup.sql`
- **Size:** ~300 KB
- **Contains:** Your complete TPLearn database with all data

## Deployment Steps

### Step 1: Upload Files to Hostinger
1. Upload `TPLearn-website-backup.zip` to the Hostinger migration tool
2. Extract the files to your domain's public_html folder

### Step 2: Database Setup
1. Create a new MySQL database in Hostinger cPanel
2. Import `TPLearn-database-backup.sql` into the new database
3. Note down your database credentials:
   - Database name
   - Database username
   - Database password
   - Database host

### Step 3: Configuration Updates
Update these files after upload:

#### A. Database Configuration (`config/domain-config.php`)
```php
// Update production database settings
define('DB_HOST', 'localhost'); // Usually localhost
define('DB_USER', 'your_db_username'); // From Hostinger
define('DB_PASS', 'your_db_password'); // From Hostinger
define('DB_NAME', 'your_db_name'); // From Hostinger
```

#### B. Email Configuration
**Keep your existing Gmail SMTP setup!** Just update the base URL:

1. **In `config/email.php`** - Update base_url:
```php
'base_url' => 'https://tplearn.site',
```

2. **Your Gmail settings are already perfect:**
   - Using: `tplearnph@gmail.com`
   - SMTP: `smtp.gmail.com:587`
   - App Password: Already configured
   - ✅ No changes needed to Gmail settings!

**Note:** Your Gmail SMTP is more reliable than hosting email, so keep using it!

### Step 4: File Permissions
Set these folder permissions in cPanel File Manager:
- `uploads/` folder: 755 or 777
- `config/` folder: 755
- All PHP files: 644

### Step 5: SSL Certificate
- Hostinger should auto-install SSL for tplearn.site
- Verify HTTPS is working
- Your system is already configured for SSL

### Step 6: Testing
1. Visit `https://tplearn.site`
2. Test login functionality
3. Test file uploads
4. Test email functionality
5. Check all dashboard features

## Important Notes

✅ **Domain Already Configured:** Your system is ready for tplearn.site
✅ **SSL Ready:** HTTPS is already configured
✅ **Email Ready:** Email templates use new domain
✅ **File Structure:** All paths are relative and will work

## Troubleshooting

If you encounter issues:
1. Check file permissions
2. Verify database connection
3. Check error logs in cPanel
4. Ensure all required PHP extensions are enabled

## Contact
For deployment assistance, contact your hosting provider or refer to Hostinger documentation.