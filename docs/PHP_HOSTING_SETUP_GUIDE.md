# 🌐 TPLearn PHP Hosting Setup Guide

## 🏆 **RECOMMENDED: Hostinger Premium Plan**

### Why Hostinger?
- ✅ **Affordable:** $1.99/month (with 48-month plan)
- ✅ **Performance:** NVMe SSD, LiteSpeed, PHP 8.2+
- ✅ **Features:** Free SSL, MySQL databases, email accounts
- ✅ **Support:** 24/7 live chat
- ✅ **Perfect for TPLearn:** Handles PHP/MySQL applications excellently

### **Purchase Steps:**
1. Go to: https://www.hostinger.com
2. Choose "Premium Web Hosting" plan
3. Register domain or connect existing `tplearn.tech`
4. Complete payment

---

## 🔧 **Step-by-Step Deployment Process**

### **Phase 1: Hosting Account Setup (15 minutes)**

#### 1. **Create Hosting Account**
```
✅ Purchase Hostinger Premium
✅ Access hPanel (Hostinger control panel)
✅ Note down:
   - Server IP address
   - MySQL database details
   - FTP/cPanel credentials
```

#### 2. **Domain Configuration**
```
Update your .TECH DNS records:
- Remove GitHub Pages A records
- Add new A record pointing to Hostinger IP
- Update CNAME if needed
```

#### 3. **SSL Certificate Setup**
```
✅ Enable SSL in hPanel
✅ Force HTTPS redirect
✅ Verify certificate installation
```

### **Phase 2: Database Setup (10 minutes)**

#### 1. **Create MySQL Database**
```sql
Database Name: tplearn_prod
Username: tplearn_user
Password: [SECURE_PASSWORD]
Host: localhost
```

#### 2. **Import Database Structure**
```bash
# Upload database.sql via phpMyAdmin
# Or use command line:
mysql -u tplearn_user -p tplearn_prod < database.sql
```

### **Phase 3: File Upload and Configuration (20 minutes)**

#### 1. **Upload Files via FTP/File Manager**
```
Source: C:\xampp\htdocs\TPLearn\
Destination: public_html/ (or httpdocs/)

Upload ALL files except:
- .git/
- node_modules/
- uploads/ (create empty on server)
- logs/ (create empty on server)
```

#### 2. **Update Configuration Files**
```php
// includes/db.php - Update with hosting database details
$host = "localhost";
$user = "tplearn_user";
$pass = "YOUR_SECURE_PASSWORD";
$dbname = "tplearn_prod";
```

#### 3. **Set File Permissions**
```
uploads/ → 755 or 777
logs/ → 755 or 777
cache/ → 755 or 777
includes/db.php → 644
config/ → 644
```

### **Phase 4: Testing and Verification (10 minutes)**

#### 1. **Test Database Connection**
```
Visit: https://tplearn.tech/check_db_connection.php
Expected: "Database connected successfully"
```

#### 2. **Test Core Functionality**
```
✅ Homepage loads
✅ Login system works
✅ Student/Tutor/Admin dashboards accessible
✅ File uploads working
✅ Email notifications working
```

---

## 🔗 **Alternative Hosting Options**

### **Option 2: Namecheap Shared Hosting**
- **Price:** $2.88/month
- **Pros:** Easy cPanel, great support, reliable
- **Best for:** Beginners who want simplicity

### **Option 3: SiteGround StartUp**
- **Price:** $3.99/month
- **Pros:** Excellent support, fast servers, staging
- **Best for:** Professional deployment

### **Option 4: Free Options (Testing Only)**
- **InfinityFree:** Good for testing
- **000webhost:** Basic but functional
- **⚠️ Note:** Free hosting has limitations

---

## 📁 **Deployment Checklist**

### **Pre-Deployment:**
- [ ] Backup current local database
- [ ] Test application locally one final time
- [ ] Prepare production database credentials
- [ ] Create deployment package (exclude dev files)

### **During Deployment:**
- [ ] Purchase hosting account
- [ ] Configure domain DNS
- [ ] Create production database
- [ ] Upload files via FTP/File Manager
- [ ] Update configuration files
- [ ] Set proper file permissions
- [ ] Configure SSL certificate

### **Post-Deployment:**
- [ ] Test all functionality
- [ ] Verify SSL is working
- [ ] Check email functionality
- [ ] Test file uploads
- [ ] Verify payment system (if applicable)
- [ ] Set up monitoring/backups

---

## 🚀 **Quick Start: Hostinger Deployment**

### **1. Get Hosting (5 minutes)**
```
1. Visit hostinger.com
2. Choose "Premium Web Hosting"
3. Select 48-month plan for best price
4. Use existing domain: tplearn.tech
5. Complete checkout
```

### **2. Access Control Panel (2 minutes)**
```
1. Check email for login details
2. Access hPanel
3. Note server IP address
4. Access File Manager
```

### **3. Quick DNS Update (5 minutes)**
```
In your .TECH domain panel:
- Delete GitHub Pages A records
- Add new A record: @ → [Hostinger IP]
- Keep www CNAME → tplearn.tech
```

### **4. Upload TPLearn (15 minutes)**
```
1. Zip your TPLearn folder (exclude .git)
2. Upload via File Manager
3. Extract to public_html/
4. Update includes/db.php with new credentials
```

### **5. Database Setup (10 minutes)**
```
1. Create MySQL database in hPanel
2. Import database.sql via phpMyAdmin
3. Test connection
```

### **6. Final Testing (5 minutes)**
```
Visit: https://tplearn.tech
✅ Should show your full TPLearn application
```

---

## 💡 **Pro Tips**

### **Performance Optimization:**
- Enable caching in hosting control panel
- Optimize images before upload
- Use CDN if traffic grows
- Monitor resource usage

### **Security:**
- Use strong database passwords
- Keep PHP version updated
- Regular backups
- Monitor for suspicious activity

### **Maintenance:**
- Weekly database backups
- Monthly security updates
- Monitor disk space usage
- Check error logs regularly

---

## 🆘 **Troubleshooting Common Issues**

### **"Database Connection Error"**
```
✅ Check database credentials in includes/db.php
✅ Verify database exists in hosting panel
✅ Check database user permissions
```

### **"File Upload Errors"**
```
✅ Set uploads/ folder to 755 or 777 permissions
✅ Check PHP upload_max_filesize setting
✅ Verify disk space available
```

### **"SSL Certificate Issues"**
```
✅ Wait 24 hours for SSL provisioning
✅ Force HTTPS in hosting panel
✅ Clear browser cache
```

### **"Email Not Working"**
```
✅ Configure SMTP settings in hosting panel
✅ Use hosting provider's mail servers
✅ Check spam folders
```

---

## 📞 **Support Resources**

- **Hostinger Support:** 24/7 live chat
- **Documentation:** help.hostinger.com
- **TPLearn Issues:** Check GitHub repository
- **Email:** Contact hosting support for server issues

---

## 🎉 **Expected Results**

After successful deployment:
- ✅ **https://tplearn.tech** → Full TPLearn application
- ✅ **All features working:** Login, dashboards, uploads
- ✅ **SSL enabled:** Secure HTTPS connection
- ✅ **Professional hosting:** Fast, reliable performance
- ✅ **Scalable:** Can handle growing user base

**Total setup time: ~60 minutes**
**Cost: ~$2-4/month**
**Result: Professional, production-ready TPLearn system**