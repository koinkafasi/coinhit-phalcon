#!/bin/bash

# CoinHit Deployment Script
# Usage: ./deploy.sh [environment]
# Environment: production (default), staging

ENVIRONMENT=${1:-production}
SERVER="194.163.159.82"
USER="root"
DOMAIN="coinhit.net"
PROJECT_PATH="/var/www/$DOMAIN"

echo "=== CoinHit Deployment ==="
echo "Environment: $ENVIRONMENT"
echo "Server: $SERVER"
echo "Domain: $DOMAIN"
echo ""

# Check requirements
if ! command -v sshpass &> /dev/null; then
    echo "Error: sshpass is required. Install with: sudo apt-get install sshpass"
    exit 1
fi

# Confirm deployment
read -p "Deploy to $DOMAIN? (y/N) " -n 1 -r
echo
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "Deployment cancelled."
    exit 0
fi

read -s -p "Enter SSH password: " SSH_PASS
echo ""

# Step 1: Transfer files
echo ""
echo "[1/5] Transferring files..."
sshpass -p "$SSH_PASS" rsync -avz --delete \
    -e "ssh -o StrictHostKeyChecking=no" \
    --exclude='vendor/' \
    --exclude='.git/' \
    --exclude='cache/' \
    --exclude='logs/' \
    --exclude='.env' \
    ./backend/ $USER@$SERVER:$PROJECT_PATH/

# Step 2: Server setup
echo ""
echo "[2/5] Installing dependencies..."
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $USER@$SERVER << ENDSSH
cd $PROJECT_PATH
composer install --no-dev --optimize-autoloader
ENDSSH

# Step 3: Database setup
echo ""
echo "[3/5] Setting up database..."
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $USER@$SERVER << 'ENDSSH'
DB_NAME="coinhit_db"
DB_USER="coinhit_user"
DB_PASS="$(openssl rand -base64 24)"

mysql -u root << EOSQL
CREATE DATABASE IF NOT EXISTS $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';
GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';
FLUSH PRIVILEGES;
EOSQL

echo "DB_NAME=$DB_NAME" > /tmp/db_credentials
echo "DB_USER=$DB_USER" >> /tmp/db_credentials
echo "DB_PASS=$DB_PASS" >> /tmp/db_credentials
ENDSSH

# Step 4: Configuration
echo ""
echo "[4/5] Configuring environment..."
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $USER@$SERVER << 'ENDSSH'
PROJECT_PATH="/var/www/coinhit.net"
source /tmp/db_credentials

cat > $PROJECT_PATH/.env << EOENV
APP_ENV=production
APP_DEBUG=false
APP_URL=https://coinhit.net

DB_HOST=localhost
DB_PORT=3306
DB_NAME=$DB_NAME
DB_USER=$DB_USER
DB_PASS=$DB_PASS

JWT_SECRET=$(openssl rand -base64 64)
JWT_EXPIRATION=86400

CACHE_DRIVER=file
CACHE_LIFETIME=3600
EOENV

# Create nginx config
cat > /etc/nginx/sites-available/coinhit.net << 'EONGINX'
server {
    listen 80;
    server_name coinhit.net www.coinhit.net;
    root /var/www/coinhit.net/public;
    index index.php;

    access_log /var/log/nginx/coinhit.net-access.log;
    error_log /var/log/nginx/coinhit.net-error.log;

    location / {
        try_files $uri $uri/ /index.php?_url=$uri&$args;
    }

    location ~ \.php$ {
        try_files $uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass unix:/var/run/php/php-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.ht {
        deny all;
    }

    location ~* \.(jpg|jpeg|png|gif|ico|css|js|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
    }
}
EONGINX

ln -sf /etc/nginx/sites-available/coinhit.net /etc/nginx/sites-enabled/

# Set permissions
mkdir -p $PROJECT_PATH/cache $PROJECT_PATH/logs
chown -R www-data:www-data $PROJECT_PATH
chmod -R 755 $PROJECT_PATH
chmod -R 775 $PROJECT_PATH/cache $PROJECT_PATH/logs
chmod 600 $PROJECT_PATH/.env

# Restart services
nginx -t && systemctl restart nginx
systemctl restart php*-fpm

cat /tmp/db_credentials
rm /tmp/db_credentials
ENDSSH

# Step 5: Run migrations
echo ""
echo "[5/5] Running migrations..."
sshpass -p "$SSH_PASS" ssh -o StrictHostKeyChecking=no $USER@$SERVER << ENDSSH
cd $PROJECT_PATH
if [ -f vendor/bin/phinx ]; then
    vendor/bin/phinx migrate
fi
ENDSSH

echo ""
echo "=== Deployment Complete ==="
echo "Site: http://$DOMAIN"
echo ""
echo "Database credentials have been saved on the server."
echo "Check server logs for details."
