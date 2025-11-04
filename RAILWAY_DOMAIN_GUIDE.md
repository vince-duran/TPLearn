# Adding Custom Domain to Railway TPLearn Deployment

## 🚀 Step 1: Configure Custom Domain in Railway Dashboard

### 1.1 Access Railway Dashboard
1. Go to [railway.app](https://railway.app) and log in
2. Navigate to your **TPLearn** project
3. Click on your service/deployment

### 1.2 Add Custom Domain
1. In your service dashboard, click **Settings**
2. Scroll down to **Domains** section
3. Click **+ Add Domain**
4. Enter your domain (e.g., `tplearn.site` or `app.yoursite.com`)
5. Click **Add Domain**

### 1.3 Get DNS Configuration
Railway will provide you with:
- **Type**: `CNAME` or `A` record
- **Name**: `@` or your subdomain
- **Value**: Your Railway app URL (e.g., `your-app.railway.app`)

## 🌐 Step 2: Configure DNS Records

### Option A: Using Your Domain Registrar
1. Log in to your domain registrar (GoDaddy, Namecheap, etc.)
2. Go to DNS Management
3. Add the record provided by Railway:
   ```
   Type: CNAME
   Name: @ (for root domain) or app (for subdomain)
   Value: your-app.railway.app
   TTL: 3600 (or default)
   ```

### Option B: Using Cloudflare (Recommended)
1. Add your domain to Cloudflare
2. Update nameservers at your registrar
3. In Cloudflare DNS:
   ```
   Type: CNAME
   Name: @ or your subdomain
   Target: your-app.railway.app
   Proxy: Orange cloud (enabled)
   ```

## 🔧 Step 3: Update Environment Variables in Railway

### 3.1 Set Environment Variables
In Railway dashboard → Settings → Variables, add:

```bash
# Domain Settings
APP_URL=https://yourdomain.com
RAILWAY_PUBLIC_DOMAIN=yourdomain.com

# Database (if using Railway PostgreSQL)
# These are usually auto-populated by Railway

# Email Settings
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=tplearnph@gmail.com
MAIL_PASSWORD=wctjiwaulqkljyek
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=tplearnph@gmail.com
MAIL_FROM_NAME="TPLearn"

# Application Settings
APP_NAME=TPLearn
APP_ENV=production
APP_DEBUG=false
```

## 📋 Step 4: Verify Configuration Files

The following files have been updated for Railway compatibility:

### ✅ `railway.toml` - Railway deployment configuration
### ✅ `health-check.php` - Health check endpoint
### ✅ `config/railway-db.php` - Auto-detecting database configuration
### ✅ `config/domain-config.php` - Auto-detecting domain settings
### ✅ `config/email.php` - Dynamic base URL configuration

## 🚀 Step 5: Deploy and Test

### 5.1 Commit and Deploy
```bash
git add .
git commit -m "Add Railway domain configuration and deployment files"
git push origin main
```

### 5.2 Verify Deployment
1. Railway will automatically redeploy
2. Check the **Deployments** tab for status
3. Visit your custom domain to test

### 5.3 Test Key Features
- [ ] Homepage loads correctly
- [ ] Login/registration works
- [ ] Database connections work
- [ ] Email sending works
- [ ] File uploads work (if applicable)

## 🔍 Troubleshooting

### Domain Not Working?
1. **Check DNS Propagation**: Use [whatsmydns.net](https://whatsmydns.net)
2. **Verify Railway Domain**: Ensure it's added in Railway dashboard
3. **Check SSL**: Railway automatically provides SSL certificates

### Database Issues?
1. **Check Railway Logs**: Railway dashboard → Deployments → View Logs
2. **Verify Environment Variables**: Ensure DATABASE_URL is set
3. **Import Database**: You may need to import your MySQL data to Railway PostgreSQL

### Email Not Working?
1. **Check Gmail Settings**: Ensure app password is correct
2. **Verify Environment Variables**: Check MAIL_* variables in Railway
3. **Test SMTP**: Use Railway logs to debug email sending

## 📊 Monitoring

### Railway Dashboard
- Monitor deployment status
- Check application logs
- View usage metrics
- Monitor database performance

### Custom Domain Health
- Set up uptime monitoring (UptimeRobot, Pingdom)
- Monitor SSL certificate renewal
- Track performance metrics

## 🎯 Next Steps

1. **Custom Domain SSL**: Railway automatically provides SSL
2. **CDN Setup**: Consider using Cloudflare for better performance
3. **Monitoring**: Set up error tracking (Sentry, LogRocket)
4. **Backup Strategy**: Regular database backups
5. **Staging Environment**: Create separate Railway service for testing

## 🆘 Support

- **Railway Documentation**: [docs.railway.app](https://docs.railway.app)
- **Railway Discord**: [railway.app/discord](https://railway.app/discord)
- **TPLearn Issues**: Check application logs in Railway dashboard

---

**Note**: This configuration allows your TPLearn application to work both locally (XAMPP) and on Railway with custom domains automatically.