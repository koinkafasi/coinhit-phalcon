# CoinHit Installation Guide

## 📋 Requirements

### Server Requirements
- **PHP**: 8.1 or higher
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Memory**: Minimum 256MB PHP memory limit

### Required PHP Extensions
- phalcon (5.0+)
- pdo
- pdo_mysql
- mbstring
- json
- openssl
- curl (optional, for API integrations)

### Optional
- Redis (for caching)
- Composer (for manual installation)

---

## 🚀 Quick Installation (Recommended)

### Method 1: Auto-Installer (Easiest)

1. **Upload Files**
   - Upload all files to your web server
   - Extract the ZIP file to your `public_html` or `www` directory

2. **Run Installation Wizard**
   - Navigate to: `http://yourdomain.com/install.php`
   - Follow the on-screen instructions
   - The installer will:
     - Check server requirements
     - Setup database
     - Create admin account
     - Configure the application

3. **Delete Installer**
   - After installation, delete `install.php` for security

4. **Login**
   - Go to: `http://yourdomain.com/admin`
   - Use your admin credentials

---

## 🛠️ Manual Installation

### Step 1: Upload Files
```bash
# Upload all files via FTP/SFTP
# Or use cPanel File Manager
```

### Step 2: Set Permissions
```bash
chmod 755 backend/cache
chmod 755 backend/logs
chmod 644 backend/.env
```

### Step 3: Create Database
```sql
CREATE DATABASE coinhit_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'coinhit_user'@'localhost' IDENTIFIED BY 'your_password';
GRANT ALL PRIVILEGES ON coinhit_db.* TO 'coinhit_user'@'localhost';
FLUSH PRIVILEGES;
```

### Step 4: Import Database
```bash
mysql -u coinhit_user -p coinhit_db < backend/database.sql
```

### Step 5: Configure Environment
```bash
cp backend/.env.example backend/.env
nano backend/.env
```

Edit `.env` with your database credentials:
```env
DB_HOST=localhost
DB_PORT=3306
DB_NAME=coinhit_db
DB_USER=coinhit_user
DB_PASS=your_password
```

### Step 6: Install Dependencies
```bash
cd backend
composer install --no-dev --optimize-autoloader
```

### Step 7: Configure Web Server

#### Apache (.htaccess already included)
Make sure mod_rewrite is enabled:
```bash
sudo a2enmod rewrite
sudo systemctl restart apache2
```

#### Nginx
Add this to your nginx config:
```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /path/to/backend/public;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?_url=$uri&$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

---

## 🎛️ cPanel Installation

### Step 1: Upload via File Manager
1. Login to cPanel
2. Go to **File Manager**
3. Navigate to `public_html`
4. Upload the ZIP file
5. Extract it

### Step 2: Create Database
1. Go to **MySQL Databases**
2. Create new database: `coinhit_db`
3. Create new user: `coinhit_user`
4. Add user to database with ALL PRIVILEGES

### Step 3: Run Auto-Installer
1. Visit: `http://yourdomain.com/install.php`
2. Follow wizard steps
3. Enter database credentials
4. Create admin account

### Step 4: Cleanup
1. Delete `install.php` from File Manager
2. Your site is ready!

---

## 🐳 CloudPanel Installation

### Step 1: Create Site
1. Login to CloudPanel
2. Click **Sites** → **Add Site**
3. Choose PHP 8.1+
4. Domain: `yourdomain.com`

### Step 2: Upload Files
```bash
# Via SFTP or CloudPanel File Manager
# Upload to: /home/[username]/htdocs/yourdomain.com/
```

### Step 3: Create Database
1. Go to **Databases**
2. Click **Add Database**
3. Name: `coinhit_db`
4. Create user and password

### Step 4: Set Document Root
1. In Site Settings
2. Set Document Root to: `/backend/public`

### Step 5: Run Installer
- Visit: `http://yourdomain.com/install.php`

---

## 🔧 Post-Installation

### Security Steps
1. Delete `install.php`
2. Change default passwords
3. Update `.env` with strong JWT_SECRET
4. Set proper file permissions:
   ```bash
   find . -type f -exec chmod 644 {} \;
   find . -type d -exec chmod 755 {} \;
   chmod 600 .env
   ```

### Configure Site
1. Login to admin panel
2. Go to **Settings**
3. Update:
   - Site name
   - Site URL
   - Email settings
   - Payment gateways (if needed)

### Optional Optimizations
1. Enable OPcache in php.ini
2. Setup Redis for caching
3. Configure CDN
4. Setup SSL certificate (Let's Encrypt)

---

## 🆘 Troubleshooting

### Error: "Phalcon extension not found"
**Solution**: Install Phalcon extension
```bash
# Via PECL
pecl install phalcon

# Or compile from source
git clone https://github.com/phalcon/cphalcon
cd cphalcon/build
sudo ./install
```

### Error: "Database connection failed"
**Solution**: Check credentials in `.env` file

### Error: "500 Internal Server Error"
**Solution**: 
1. Check Apache/Nginx error logs
2. Ensure mod_rewrite is enabled
3. Check file permissions

### Blank Page
**Solution**:
1. Enable error reporting in `public/index.php`
2. Check PHP error log
3. Ensure all dependencies installed

---

## 📞 Support

- Documentation: https://docs.coinhit.net
- Support Email: support@coinhit.net
- GitHub: https://github.com/koinkafasi/coinhit-phalcon

---

## 📄 License

Licensed for use on ThemeForest. See LICENSE file.
