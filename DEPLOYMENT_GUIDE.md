# Deployment Guide - SchoolLink Africa to Live Server

## Live Server Details
- **URL**: http://169.239.251.102:442/~splendour.kalu/
- **Deployment Date**: <?php echo date('Y-m-d H:i:s'); ?>

---

## CRITICAL: Pre-Deployment Checklist

### 1. Configuration Files to Update

#### A. Database Configuration (`config/database.php`)
```php
// UPDATE THESE FOR YOUR LIVE SERVER:
private $host = "localhost";           // Or your live DB host
private $db_name = "your_live_db_name"; // Your live database name
private $username = "your_live_db_user"; // Your live database username
private $password = "your_live_db_password"; // Your live database password
```

#### B. Paystack Configuration (`config/paystack.php`)
```php
// IMPORTANT: Add your LIVE Paystack keys
private const LIVE_PUBLIC_KEY = 'pk_live_YOUR_ACTUAL_LIVE_KEY';
private const LIVE_SECRET_KEY = 'sk_live_YOUR_ACTUAL_LIVE_SECRET';

// Set to TRUE for production
private const USE_LIVE_MODE = true; // ← CHANGE THIS TO true
```

#### C. Update Base URL (if needed)
Search for any hardcoded "localhost" references and update to:
`http://169.239.251.102:442/~splendour.kalu/`

---

## 2. Files & Folders to Upload

### Required Files (Upload ALL):
```
├── admin/
├── assets/
├── classes/
├── config/
├── database/
├── includes/
├── school-admin/
├── uploads/
├── user/
├── *.php (all PHP files in root)
└── README.md
```

### Files to EXCLUDE (Do NOT upload):
```
- ECOMMERCE_IMPLEMENTATION.md
- FEATURE_COMPLETION_REPORT.md
- PAYMENT_SETUP.md
- QUICK_START.md
- bug-analysis.md
- *-test.php files
- *-debug.php files
```

---

## 3. Database Setup on Live Server

### Step 1: Create Database
1. Login to your live server's phpMyAdmin or cPanel
2. Create a new database (e.g., `schoollink_africa`)
3. Create a database user with all privileges

### Step 2: Import Database
1. Go to phpMyAdmin on live server
2. Select your new database
3. Click "Import" tab
4. Upload: `database/schoollink_africa.sql`
5. Click "Go" to import

### Step 3: Update Database Config
Edit `config/database.php` with your live database credentials

---

## 4. File Permissions (Important!)

Set these permissions on live server:
```
Directories (755):
- uploads/
- logs/ (create this folder)

Files (644):
- All .php files
- All .css, .js files

Writable (777 or 755 depending on server):
- uploads/ (must be writable)
```

---

## 5. Security Hardening

### A. Create .htaccess for uploads folder
Create `uploads/.htaccess`:
```apache
# Prevent PHP execution in uploads
<Files *.php>
    deny from all
</Files>
```

### B. Hide sensitive files
Create root `.htaccess`:
```apache
# Deny access to sensitive files
<FilesMatch "^(DEPLOYMENT|PAYMENT_SETUP|FEATURE_COMPLETION|QUICK_START|bug-analysis)">
    Order allow,deny
    Deny from all
</FilesMatch>
```

### C. Enable HTTPS (Recommended)
If your server supports SSL, update all URLs to use `https://`

---

## 6. Deployment Methods

### Option A: FTP/SFTP Upload (Recommended)
1. Use FileZilla or WinSCP
2. Connect to: `169.239.251.102` (port 22 for SFTP or 21 for FTP)
3. Navigate to your web directory: `/home/splendour.kalu/public_html/` or similar
4. Upload all files and folders

### Option B: cPanel File Manager
1. Login to cPanel
2. Open File Manager
3. Navigate to public_html or www directory
4. Upload a ZIP of your project
5. Extract the ZIP file

### Option C: Git Deployment (If available)
```bash
# On your local machine, initialize git if not done
git init
git add .
git commit -m "Initial deployment - SchoolLink Africa"

# On live server (via SSH)
cd /home/splendour.kalu/public_html/
git clone <your-repository-url>
```

---

## 7. Post-Deployment Testing

### Test Checklist:
- [ ] Visit: http://169.239.251.102:442/~splendour.kalu/
- [ ] Test login (admin, school admin, student)
- [ ] Test product browsing
- [ ] Test add to cart
- [ ] Test checkout (use Paystack TEST mode first!)
- [ ] Test payment with test card
- [ ] Verify order creation
- [ ] Check my_orders.php
- [ ] Test admin panel features
- [ ] Check all navigation links
- [ ] Test search functionality
- [ ] Verify image uploads work

---

## 8. Paystack Callback URL Configuration

### Update Paystack Dashboard:
1. Login to https://dashboard.paystack.com
2. Go to Settings → API Keys & Webhooks
3. Add callback URL:
   ```
   http://169.239.251.102:442/~splendour.kalu/payment_callback.php
   ```

---

## 9. Common Issues & Solutions

### Issue: Database connection failed
**Solution**: Check `config/database.php` credentials match your live server

### Issue: Images not showing
**Solution**: 
- Check `uploads/` folder exists and is writable (chmod 755 or 777)
- Verify image paths don't have hardcoded localhost

### Issue: Payment not working
**Solution**:
- Verify Paystack keys in `config/paystack.php`
- Check `USE_LIVE_MODE` is set correctly
- Verify callback URL in Paystack dashboard

### Issue: Session errors
**Solution**:
- Check PHP session configuration on server
- Ensure `session_start()` is at top of files

### Issue: 404 errors
**Solution**:
- Check .htaccess file exists
- Verify mod_rewrite is enabled on server
- Check file paths are correct

---

## 10. Maintenance Mode (Optional)

Create `maintenance.php` to show during deployment:
```php
<?php
// Redirect all traffic during maintenance
if (!isset($_GET['bypass']) || $_GET['bypass'] !== 'admin123') {
    ?>
    <!DOCTYPE html>
    <html>
    <head><title>Under Maintenance</title></head>
    <body>
        <h1>Site Under Maintenance</h1>
        <p>We'll be back shortly!</p>
    </body>
    </html>
    <?php
    exit;
}
?>
```

---

## 11. Backup Strategy

### Before Deployment:
- Backup current live site (if exists)
- Export live database (if exists)

### After Deployment:
- Schedule regular database backups
- Use cPanel backup tools or cron jobs

---

## Quick Deployment Commands (If using FTP)

Using WinSCP or FileZilla:
```
Host: 169.239.251.102
Port: 22 (SFTP) or 21 (FTP)
Username: splendour.kalu
Password: <your-password>

Remote Directory: /public_html/ or /www/
```

---

## Support Contacts

- **Server Issues**: Contact your hosting provider
- **Paystack Issues**: support@paystack.com
- **Database Issues**: Check server error logs

---

**REMEMBER**: 
1. ✅ Update database credentials in `config/database.php`
2. ✅ Update Paystack keys in `config/paystack.php`
3. ✅ Set `USE_LIVE_MODE = true` when ready for real payments
4. ✅ Import database SQL file
5. ✅ Test thoroughly before going live!

---

**Good luck with your deployment! 🚀**
