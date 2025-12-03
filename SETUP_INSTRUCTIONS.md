# ATIERA Financial Management System - Setup Instructions

## 🚀 Quick Start Guide

### Step 1: Environment Configuration

1. **Copy the environment template**:
   ```bash
   cp .env.example .env
   ```

2. **The .env file is already configured** with your production settings:
   - Database: `fina_financialmngmnt`
   - User: `fina_financialg10`
   - URL: `https://financial.atierahotelandrestaurant.com`

3. **Update email settings** in `.env` if you want email functionality:
   ```
   MAIL_USERNAME=your-email@gmail.com
   MAIL_PASSWORD=your-app-password
   MAIL_FROM_ADDRESS=your-email@gmail.com
   ```

### Step 2: Database Setup

1. **Import the database schema**:
   ```bash
   mysql -u fina_financialg10 -p fina_financialmngmnt < atiera_finance_master.sql
   ```
   Or use phpMyAdmin to import `atiera_finance_master.sql`

2. **Verify database connection**:
   - Access: `https://financial.atierahotelandrestaurant.com`
   - If successful, you'll see the login page

### Step 3: Login Credentials

**Default Admin Account**:
- Username: `admin`
- Password: `admin123` (CHANGE THIS IMMEDIATELY!)

**Default Staff Account**:
- Username: `staff`
- Password: `staff123` (CHANGE THIS IMMEDIATELY!)

**IMPORTANT**: Change these passwords immediately after first login!

### Step 4: File Permissions

If on Linux/Unix server, set proper permissions:
```bash
chmod 0600 .env
chmod 0644 config.php
chmod 0755 uploads/
chmod 0755 logs/
chmod 0755 backups/
```

### Step 5: Verify Installation

1. Access the system: `https://financial.atierahotelandrestaurant.com`
2. Login with admin credentials
3. Navigate to Dashboard - should load without errors
4. Test key features:
   - Create a journal entry (General Ledger)
   - Create an invoice (Accounts Receivable)
   - Create a bill (Accounts Payable)
   - Generate a report (Reports)

---

## 📋 Post-Installation Checklist

- [ ] Database imported successfully
- [ ] Login works with default credentials
- [ ] Dashboard loads without errors
- [ ] Changed admin password
- [ ] Changed staff password
- [ ] Email functionality tested (optional)
- [ ] Created backup schedule
- [ ] Verified user permissions
- [ ] Tested core financial modules

---

## 🔒 Security Recommendations

1. **Change Default Passwords**:
   - Go to User Management → Users
   - Edit admin and staff accounts
   - Use strong passwords (min 12 characters, mixed case, numbers, symbols)

2. **Enable Two-Factor Authentication (2FA)**:
   - Go to User Profile → Security Settings
   - Enable 2FA for all admin accounts

3. **Set Up Regular Backups**:
   - Configure automated database backups
   - Test backup restoration procedure
   - Store backups securely off-site

4. **Review User Permissions**:
   - Go to Roles & Permissions
   - Ensure users have appropriate access levels
   - Follow principle of least privilege

5. **Enable HTTPS** (already configured in .env):
   - Ensure SSL certificate is valid
   - All traffic should use HTTPS only

---

## 🆘 Troubleshooting

### Database Connection Error
- Verify database credentials in `.env`
- Check if MySQL service is running
- Verify database user has proper permissions

### White Screen / 500 Error
- Check `logs/app.log` for errors
- Verify file permissions
- Check PHP error logs

### Email Not Sending
- Verify SMTP settings in `.env`
- Check if Gmail "Less secure apps" is enabled (or use App Password)
- Review `logs/app.log` for email errors

### Session Expired Immediately
- Check `SESSION_LIFETIME` in `.env`
- Verify session directory is writable
- Clear browser cookies and try again

---

## 📞 Support Resources

- **Documentation**: Check `/docs` folder for detailed guides
- **API Documentation**: `https://financial.atierahotelandrestaurant.com/admin/api_docs.php`
- **Production Checklist**: See `PRODUCTION_READINESS_CHECKLIST.md`

---

## ✅ System Status

**Version**: 1.0.0
**Status**: Production Ready
**Database**: fina_financialmngmnt
**URL**: https://financial.atierahotelandrestaurant.com

**New Features in This Release**:
- ✅ Collection details modal in Accounts Payable
- ✅ Email report functionality
- ✅ Production-ready with all test files removed
- ✅ Enhanced security configuration
- ✅ Comprehensive error handling

---

**Last Updated**: December 3, 2025
**Configured For**: ATIERA Hotel & Restaurant
