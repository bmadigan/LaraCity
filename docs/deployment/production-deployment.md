# LaraCity Production Deployment Guide

This comprehensive guide covers deploying LaraCity to production environments with focus on security, performance, and reliability.

## 🏗️ Architecture Overview

LaraCity uses a hybrid architecture combining Laravel (PHP) with Python/LangChain for AI processing:

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Load Balancer │    │   Web Servers   │    │   AI Workers    │
│                 │    │                 │    │                 │
│ • SSL/TLS       │◄──►│ • Laravel App   │◄──►│ • Python/LC     │
│ • Rate Limiting │    │ • PHP-FPM       │    │ • Queue Workers │
│ • Health Checks │    │ • Nginx/Apache  │    │ • GPU Optional  │
└─────────────────┘    └─────────────────┘    └─────────────────┘
         │                       │                       │
         ▼                       ▼                       ▼
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   CDN/Static    │    │   Database      │    │   Redis/Cache   │
│                 │    │                 │    │                 │
│ • Assets        │    │ • PostgreSQL    │    │ • Sessions      │
│ • File Storage  │    │ • pgvector      │    │ • Queue Storage │
│ • Images        │    │ • Backups       │    │ • Rate Limiting │
└─────────────────┘    └─────────────────┘    └─────────────────┘
```

## 📋 Prerequisites

### System Requirements

**Web Servers:**
- **CPU**: 2+ cores (4+ recommended for production)
- **RAM**: 4GB minimum (8GB+ recommended)
- **Storage**: 50GB+ SSD with growth capacity
- **OS**: Ubuntu 22.04 LTS, CentOS 8+, or RHEL 8+

**Database Server:**
- **CPU**: 4+ cores for database workloads
- **RAM**: 8GB minimum (16GB+ for large datasets)
- **Storage**: 100GB+ SSD with high IOPS
- **PostgreSQL**: Version 15+ with pgvector extension

**AI Processing Servers:**
- **CPU**: 4+ cores (8+ for heavy AI workloads)
- **RAM**: 8GB minimum (16GB+ recommended)
- **GPU**: Optional but recommended for large-scale processing
- **Python**: 3.8+ with scientific computing libraries

### Software Dependencies

**Web Server Stack:**
```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y nginx php8.2 php8.2-fpm php8.2-cli php8.2-pgsql \
    php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath \
    php8.2-intl php8.2-gd php8.2-redis composer

# CentOS/RHEL
sudo dnf install -y nginx php82 php82-php-fpm php82-php-pgsql \
    php82-php-mbstring php82-php-xml php82-php-curl composer
```

**Python Environment:**
```bash
# Install Python and dependencies
sudo apt install -y python3.11 python3.11-venv python3.11-dev
python3.11 -m venv /opt/laracity/python-env
source /opt/laracity/python-env/bin/activate
pip install -r requirements.txt
```

**Database Setup:**
```bash
# PostgreSQL with pgvector
sudo apt install -y postgresql-15 postgresql-15-pgvector
sudo systemctl enable postgresql
sudo systemctl start postgresql
```

## 🚀 Deployment Steps

### Step 1: Server Preparation

#### Create Application User
```bash
# Create dedicated user for LaraCity
sudo useradd -m -s /bin/bash laracity
sudo usermod -a -G www-data laracity

# Create application directory
sudo mkdir -p /opt/laracity
sudo chown laracity:laracity /opt/laracity
```

#### Security Hardening
```bash
# Configure firewall
sudo ufw allow 22    # SSH
sudo ufw allow 80    # HTTP  
sudo ufw allow 443   # HTTPS
sudo ufw enable

# Disable root SSH login
sudo sed -i 's/PermitRootLogin yes/PermitRootLogin no/' /etc/ssh/sshd_config
sudo systemctl reload ssh

# Configure fail2ban
sudo apt install fail2ban
sudo systemctl enable fail2ban
sudo systemctl start fail2ban
```

### Step 2: Application Deployment

#### Clone and Configure Application
```bash
# Switch to application user
sudo su - laracity

# Clone repository
cd /opt/laracity
git clone https://github.com/your-org/LaraCity.git app
cd app

# Install PHP dependencies
composer install --no-dev --optimize-autoloader

# Install Python dependencies  
python3 -m venv venv
source venv/bin/activate
pip install -r lacity-ai/requirements.txt

# Set permissions
sudo chown -R laracity:www-data /opt/laracity/app
sudo chmod -R 755 /opt/laracity/app
sudo chmod -R 775 storage bootstrap/cache
```

#### Environment Configuration
```bash
# Copy and configure environment
cp .env.example .env
php artisan key:generate

# Edit .env with production values
nano .env
```

**Production .env Configuration:**
```env
# Application
APP_NAME="LaraCity Production"
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com
APP_KEY=base64:generated-key-here

# Database
DB_CONNECTION=pgsql
DB_HOST=your-db-host
DB_PORT=5432
DB_DATABASE=laracity_prod
DB_USERNAME=laracity_user
DB_PASSWORD=secure-database-password

# Redis Cache
REDIS_HOST=your-redis-host
REDIS_PASSWORD=secure-redis-password
REDIS_PORT=6379

# Queue Configuration
QUEUE_CONNECTION=redis
QUEUE_FAILED_DRIVER=database-uuids

# OpenAI Configuration
OPENAI_API_KEY=your-openai-api-key
OPENAI_ORGANIZATION=your-openai-org-id
OPENAI_MODEL=gpt-4o-mini

# Slack Notifications
SLACK_WEBHOOK_URL=your-slack-webhook-url

# Security
SESSION_DRIVER=redis
SESSION_ENCRYPT=true
COOKIE_SECURE=true
SANCTUM_STATEFUL_DOMAINS=your-domain.com

# Performance
CACHE_DRIVER=redis
BROADCAST_DRIVER=redis

# Logging
LOG_CHANNEL=daily
LOG_DEPRECATIONS_CHANNEL=daily
LOG_LEVEL=warning

# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=your-smtp-host
MAIL_PORT=587
MAIL_USERNAME=your-mail-username
MAIL_PASSWORD=your-mail-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@your-domain.com
MAIL_FROM_NAME="LaraCity System"
```

#### Database Setup
```bash
# Run migrations
php artisan migrate --force

# Optimize for production
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

### Step 3: Web Server Configuration

#### Nginx Configuration

Create `/etc/nginx/sites-available/laracity`:
```nginx
server {
    listen 80;
    server_name your-domain.com www.your-domain.com;
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    server_name your-domain.com www.your-domain.com;
    root /opt/laracity/app/public;
    index index.php;

    # SSL Configuration
    ssl_certificate /path/to/ssl/certificate.crt;
    ssl_certificate_key /path/to/ssl/private.key;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;

    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Referrer-Policy "no-referrer-when-downgrade" always;
    add_header Content-Security-Policy "default-src 'self' http: https: data: blob: 'unsafe-inline'" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;

    # Rate Limiting
    limit_req_zone $binary_remote_addr zone=api:10m rate=10r/s;
    limit_req_zone $binary_remote_addr zone=login:10m rate=1r/s;

    # Gzip Compression
    gzip on;
    gzip_vary on;
    gzip_min_length 1024;
    gzip_proxied expired no-cache no-store private must-revalidate auth;
    gzip_types text/plain text/css text/xml text/javascript application/javascript application/xml+rss application/json;

    # Main location block
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # API rate limiting
    location /api/ {
        limit_req zone=api burst=20 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # Login rate limiting
    location /login {
        limit_req zone=login burst=5 nodelay;
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM configuration
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
        
        # Increase timeouts for AI processing
        fastcgi_read_timeout 300;
        fastcgi_connect_timeout 300;
        fastcgi_send_timeout 300;
    }

    # Deny access to sensitive files
    location ~ /\.(?!well-known).* {
        deny all;
    }

    # Static asset optimization
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff|woff2|ttf|eot)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }

    # Block common exploits
    location ~ ^/(wp-admin|wp-login|phpmyadmin|adminer) {
        deny all;
    }

    # Health check endpoint
    location /health {
        access_log off;
        return 200 "healthy\n";
        add_header Content-Type text/plain;
    }
}
```

Enable the site:
```bash
sudo ln -s /etc/nginx/sites-available/laracity /etc/nginx/sites-enabled/
sudo nginx -t
sudo systemctl reload nginx
```

#### PHP-FPM Configuration

Edit `/etc/php/8.2/fpm/pool.d/laracity.conf`:
```ini
[laracity]
user = laracity
group = www-data
listen = /run/php/laracity.sock
listen.owner = www-data
listen.group = www-data
listen.mode = 0660

pm = dynamic
pm.max_children = 50
pm.start_servers = 5
pm.min_spare_servers = 5
pm.max_spare_servers = 35
pm.max_requests = 1000

; Environment variables
env[PATH] = /usr/local/bin:/usr/bin:/bin
env[TMP] = /tmp
env[TMPDIR] = /tmp
env[TEMP] = /tmp

; PHP configuration overrides
php_admin_value[error_log] = /var/log/php-fpm/laracity.log
php_admin_flag[log_errors] = on
php_admin_value[memory_limit] = 256M
php_admin_value[max_execution_time] = 300
php_admin_value[upload_max_filesize] = 100M
php_admin_value[post_max_size] = 100M
```

### Step 4: Queue Workers and Background Services

#### Create Queue Worker Service

Create `/etc/systemd/system/laracity-worker.service`:
```ini
[Unit]
Description=LaraCity Queue Worker
After=network.target

[Service]
Type=simple
User=laracity
Group=laracity
WorkingDirectory=/opt/laracity/app
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --max-time=3600
Restart=always
RestartSec=5

# Resource limits
LimitNOFILE=65536
MemoryLimit=1G

# Environment
Environment=PATH=/usr/local/bin:/usr/bin:/bin

[Install]
WantedBy=multi-user.target
```

#### Create AI Processing Service

Create `/etc/systemd/system/laracity-ai.service`:
```ini
[Unit]
Description=LaraCity AI Processing Workers
After=network.target

[Service]
Type=simple
User=laracity
Group=laracity
WorkingDirectory=/opt/laracity/app
ExecStart=/usr/bin/php artisan queue:work --queue=ai-analysis --sleep=3 --tries=2 --max-time=1800
Restart=always
RestartSec=10

# Resource limits for AI processing
LimitNOFILE=65536
MemoryLimit=2G

# Environment
Environment=PATH=/usr/local/bin:/usr/bin:/bin
Environment=PYTHONPATH=/opt/laracity/app/lacity-ai

[Install]
WantedBy=multi-user.target
```

Enable and start services:
```bash
sudo systemctl daemon-reload
sudo systemctl enable laracity-worker laracity-ai
sudo systemctl start laracity-worker laracity-ai
```

#### Cron Jobs for Maintenance

Add to laracity user's crontab:
```bash
sudo -u laracity crontab -e

# Add these entries:
# Run Laravel scheduler every minute
* * * * * cd /opt/laracity/app && php artisan schedule:run >> /dev/null 2>&1

# Clear expired sessions daily
0 2 * * * cd /opt/laracity/app && php artisan session:gc

# Generate embeddings for new complaints (every 5 minutes)
*/5 * * * * cd /opt/laracity/app && php artisan lacity:generate-embeddings --type=new --batch-size=50

# Health check and restart workers if needed
*/10 * * * * /opt/laracity/scripts/health-check.sh
```

### Step 5: Database Optimization

#### PostgreSQL Configuration

Edit `/etc/postgresql/15/main/postgresql.conf`:
```ini
# Memory settings
shared_buffers = 1GB                    # 25% of total RAM
effective_cache_size = 3GB              # 75% of total RAM
work_mem = 64MB                         # For sorting and hash operations
maintenance_work_mem = 256MB

# Connection settings
max_connections = 100
shared_preload_libraries = 'pg_stat_statements,pgvector'

# WAL settings for performance
wal_buffers = 16MB
checkpoint_completion_target = 0.9
max_wal_size = 2GB
min_wal_size = 1GB

# Query planning
random_page_cost = 1.1                  # For SSD storage
effective_io_concurrency = 200          # For SSD storage
```

#### Database Performance Tuning
```sql
-- Connect as superuser
\c laracity_prod

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS vector;
CREATE EXTENSION IF NOT EXISTS pg_stat_statements;

-- Create optimized indexes
CREATE INDEX CONCURRENTLY idx_complaints_borough_status 
ON complaints(borough, status) 
WHERE status IN ('Open', 'In Progress');

CREATE INDEX CONCURRENTLY idx_complaints_risk_score 
ON complaint_analyses(risk_score DESC, created_at DESC)
WHERE risk_score >= 0.7;

CREATE INDEX CONCURRENTLY idx_embeddings_vector_ops 
ON document_embeddings USING hnsw (vector vector_cosine_ops)
WITH (m = 16, ef_construction = 64);

-- Update table statistics
ANALYZE complaints;
ANALYZE complaint_analyses;
ANALYZE document_embeddings;
```

### Step 6: SSL/TLS Configuration

#### Let's Encrypt Setup
```bash
# Install Certbot
sudo apt install snapd
sudo snap install --classic certbot
sudo ln -s /snap/bin/certbot /usr/bin/certbot

# Obtain SSL certificate
sudo certbot --nginx -d your-domain.com -d www.your-domain.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

#### Custom SSL Certificate
If using custom certificates, ensure:
- Certificate includes full chain
- Private key is properly secured (600 permissions)
- Regular renewal process is in place
- HSTS headers are configured

### Step 7: Monitoring and Logging

#### Application Monitoring

Create `/opt/laracity/scripts/health-check.sh`:
```bash
#!/bin/bash

LARACITY_URL="https://your-domain.com"
LOG_FILE="/var/log/laracity/health-check.log"
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

# Check application health
response=$(curl -s -o /dev/null -w "%{http_code}" "$LARACITY_URL/health")

if [ "$response" != "200" ]; then
    echo "[$TIMESTAMP] ALERT: Application health check failed (HTTP $response)" >> $LOG_FILE
    
    # Restart services if needed
    sudo systemctl restart laracity-worker
    sudo systemctl restart laracity-ai
    
    # Send alert (customize for your notification system)
    curl -X POST "$SLACK_WEBHOOK_URL" \
        -H 'Content-type: application/json' \
        -d "{\"text\":\"🚨 LaraCity health check failed (HTTP $response)\"}"
fi

# Check queue health
queue_failed=$(php /opt/laracity/app/artisan queue:failed --format=json | jq length)

if [ "$queue_failed" -gt 10 ]; then
    echo "[$TIMESTAMP] WARNING: $queue_failed failed jobs in queue" >> $LOG_FILE
fi

# Check disk space
disk_usage=$(df /opt/laracity | awk 'NR==2{print $5}' | sed 's/%//')

if [ "$disk_usage" -gt 80 ]; then
    echo "[$TIMESTAMP] WARNING: Disk usage at $disk_usage%" >> $LOG_FILE
fi
```

#### Log Management

Configure log rotation `/etc/logrotate.d/laracity`:
```
/opt/laracity/app/storage/logs/*.log {
    daily
    missingok
    rotate 30
    compress
    delaycompress
    notifempty
    create 644 laracity laracity
    sharedscripts
    postrotate
        systemctl reload laracity-worker
        systemctl reload laracity-ai
    endscript
}
```

### Step 8: Backup Strategy

#### Database Backup Script

Create `/opt/laracity/scripts/backup-database.sh`:
```bash
#!/bin/bash

BACKUP_DIR="/opt/laracity/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
DB_NAME="laracity_prod"
RETENTION_DAYS=30

# Create backup directory
mkdir -p $BACKUP_DIR

# Perform backup
pg_dump $DB_NAME | gzip > "$BACKUP_DIR/laracity_db_$TIMESTAMP.sql.gz"

# Remove old backups
find $BACKUP_DIR -name "laracity_db_*.sql.gz" -mtime +$RETENTION_DAYS -delete

# Upload to S3 (optional)
# aws s3 cp "$BACKUP_DIR/laracity_db_$TIMESTAMP.sql.gz" s3://your-backup-bucket/
```

#### Application Backup
```bash
#!/bin/bash
# /opt/laracity/scripts/backup-app.sh

BACKUP_DIR="/opt/laracity/backups"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
APP_DIR="/opt/laracity/app"

# Backup application files (excluding vendor and node_modules)
tar --exclude='vendor' --exclude='node_modules' --exclude='storage/logs/*' \
    -czf "$BACKUP_DIR/laracity_app_$TIMESTAMP.tar.gz" -C /opt/laracity app

# Backup environment file
cp "$APP_DIR/.env" "$BACKUP_DIR/env_$TIMESTAMP.backup"
```

Add to crontab:
```bash
# Daily database backup at 2 AM
0 2 * * * /opt/laracity/scripts/backup-database.sh

# Weekly application backup on Sundays at 3 AM  
0 3 * * 0 /opt/laracity/scripts/backup-app.sh
```

## ⚡ Performance Optimization

### Application-Level Optimizations

#### PHP OPcache Configuration
Edit `/etc/php/8.2/fpm/conf.d/10-opcache.ini`:
```ini
opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=64
opcache.max_accelerated_files=32531
opcache.revalidate_freq=0
opcache.validate_timestamps=0
opcache.save_comments=1
opcache.fast_shutdown=1
```

#### Redis Configuration
Edit `/etc/redis/redis.conf`:
```ini
# Memory optimization
maxmemory 2gb
maxmemory-policy allkeys-lru

# Persistence for queue reliability
save 900 1
save 300 10
save 60 10000

# Performance tuning
tcp-keepalive 60
timeout 300

# Security
requirepass your-redis-password
```

#### Queue Optimization
```bash
# Scale queue workers based on load
sudo systemctl edit laracity-worker.service

# Add in the override file:
[Service]
ExecStart=
ExecStart=/usr/bin/php artisan queue:work --sleep=1 --tries=3 --max-jobs=1000 --max-time=3600 --queue=default,ai-analysis,escalation

# For high-load environments, run multiple workers
sudo systemctl enable laracity-worker@{1..4}.service
```

### Database Performance

#### Connection Pooling with PgBouncer
```bash
# Install PgBouncer
sudo apt install pgbouncer

# Configure /etc/pgbouncer/pgbouncer.ini
[databases]
laracity_prod = host=localhost port=5432 dbname=laracity_prod

[pgbouncer]
listen_port = 6432
listen_addr = 127.0.0.1
auth_type = md5
auth_file = /etc/pgbouncer/userlist.txt
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 20
reserve_pool_size = 5
```

#### Vector Index Optimization
```sql
-- Optimize vector search performance
SET maintenance_work_mem = '1GB';

-- Rebuild vector index with optimal parameters
DROP INDEX IF EXISTS idx_embeddings_vector_ops;
CREATE INDEX CONCURRENTLY idx_embeddings_vector_ops 
ON document_embeddings USING hnsw (vector vector_cosine_ops)
WITH (m = 32, ef_construction = 128);

-- Update statistics
ANALYZE document_embeddings;
```

## 🔒 Security Hardening

### Application Security

#### Environment Security
```bash
# Secure environment file
sudo chmod 600 /opt/laracity/app/.env
sudo chown laracity:laracity /opt/laracity/app/.env

# Secure storage directory
sudo chmod -R 755 /opt/laracity/app/storage
sudo chown -R laracity:www-data /opt/laracity/app/storage
```

#### API Security Configuration
Add to `.env`:
```env
# Rate limiting
THROTTLE_API_REQUESTS=60,1
THROTTLE_LOGIN_ATTEMPTS=5,1

# Session security
SESSION_LIFETIME=120
SESSION_EXPIRE_ON_CLOSE=true

# CSRF protection
CSRF_COOKIE_SECURE=true
CSRF_COOKIE_HTTP_ONLY=true

# Sanctum security
SANCTUM_EXPIRATION=null
SANCTUM_TOKEN_PREFIX=laracity_
```

### Server Security

#### Firewall Configuration
```bash
# UFW rules for production
sudo ufw delete allow 22/tcp
sudo ufw allow from YOUR_ADMIN_IP to any port 22

# Database access (internal only)
sudo ufw allow from 10.0.0.0/8 to any port 5432

# Redis access (internal only)  
sudo ufw allow from 10.0.0.0/8 to any port 6379

# Application ports
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
```

#### Intrusion Detection
```bash
# Install AIDE for file integrity monitoring
sudo apt install aide
sudo aide --init
sudo mv /var/lib/aide/aide.db.new /var/lib/aide/aide.db

# Daily integrity check
echo "0 3 * * * /usr/bin/aide --check" | sudo crontab -u root -
```

## 📊 Monitoring and Alerting

### Application Performance Monitoring

#### Laravel Telescope (Development/Staging)
```bash
# Install only in non-production environments
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

#### Custom Metrics Collection
```php
// Add to app/Console/Commands/CollectMetrics.php
use Illuminate\Support\Facades\Redis;

class CollectMetrics extends Command
{
    public function handle()
    {
        // Queue metrics
        $queueSize = Queue::size();
        $failedJobs = DB::table('failed_jobs')->count();
        
        // Database metrics
        $totalComplaints = Complaint::count();
        $todayComplaints = Complaint::whereDate('created_at', today())->count();
        
        // AI processing metrics
        $analyzedToday = ComplaintAnalysis::whereDate('created_at', today())->count();
        $avgRiskScore = ComplaintAnalysis::whereDate('created_at', today())->avg('risk_score');
        
        // Store metrics in Redis
        Redis::setex('metrics:queue_size', 300, $queueSize);
        Redis::setex('metrics:failed_jobs', 300, $failedJobs);
        Redis::setex('metrics:complaints_today', 3600, $todayComplaints);
        Redis::setex('metrics:analyzed_today', 3600, $analyzedToday);
        Redis::setex('metrics:avg_risk_score', 3600, $avgRiskScore);
    }
}
```

### External Monitoring

#### Uptime Monitoring
```bash
# Simple uptime monitoring script
#!/bin/bash
# /opt/laracity/scripts/uptime-monitor.sh

ENDPOINTS=(
    "https://your-domain.com/health"
    "https://your-domain.com/api/health"
)

for endpoint in "${ENDPOINTS[@]}"; do
    status=$(curl -s -o /dev/null -w "%{http_code}" "$endpoint")
    
    if [ "$status" != "200" ]; then
        echo "ALERT: $endpoint returned HTTP $status"
        # Send notification
    fi
done
```

## 🚦 Deployment Automation

### CI/CD Pipeline Example (GitHub Actions)

Create `.github/workflows/deploy.yml`:
```yaml
name: Deploy to Production

on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  test:
    runs-on: ubuntu-latest
    services:
      postgres:
        image: pgvector/pgvector:pg15
        env:
          POSTGRES_PASSWORD: postgres
        options: >-
          --health-cmd pg_isready
          --health-interval 10s
          --health-timeout 5s
          --health-retries 5

    steps:
    - uses: actions/checkout@v3
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.2'
        extensions: pgsql, mbstring, xml, curl, zip, bcmath, intl, gd
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Run tests
      run: php artisan test
      env:
        DB_CONNECTION: pgsql
        DB_HOST: localhost
        DB_PASSWORD: postgres

  deploy:
    needs: test
    runs-on: ubuntu-latest
    if: github.ref == 'refs/heads/main'
    
    steps:
    - name: Deploy to production
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.PRODUCTION_HOST }}
        username: ${{ secrets.PRODUCTION_USER }}
        key: ${{ secrets.PRODUCTION_SSH_KEY }}
        script: |
          cd /opt/laracity/app
          git pull origin main
          composer install --no-dev --optimize-autoloader
          php artisan migrate --force
          php artisan config:cache
          php artisan route:cache
          php artisan view:cache
          sudo systemctl reload laracity-worker
          sudo systemctl reload laracity-ai
```

### Blue-Green Deployment

```bash
#!/bin/bash
# /opt/laracity/scripts/blue-green-deploy.sh

CURRENT_DIR="/opt/laracity/app"
BLUE_DIR="/opt/laracity/blue"
GREEN_DIR="/opt/laracity/green"

# Determine current and target environments
if [ -L "$CURRENT_DIR" ] && [ "$(readlink $CURRENT_DIR)" = "$BLUE_DIR" ]; then
    CURRENT="blue"
    TARGET="green"
    TARGET_DIR="$GREEN_DIR"
else
    CURRENT="green"
    TARGET="blue"
    TARGET_DIR="$BLUE_DIR"
fi

echo "Deploying to $TARGET environment..."

# Clone/update target environment
if [ ! -d "$TARGET_DIR" ]; then
    git clone https://github.com/your-org/LaraCity.git "$TARGET_DIR"
else
    cd "$TARGET_DIR" && git pull origin main
fi

cd "$TARGET_DIR"

# Install dependencies
composer install --no-dev --optimize-autoloader
source venv/bin/activate && pip install -r lacity-ai/requirements.txt

# Copy environment file
cp "$CURRENT_DIR/.env" "$TARGET_DIR/.env"

# Run migrations
php artisan migrate --force

# Cache optimization
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Health check on target
php artisan serve --host=127.0.0.1 --port=8001 &
SERVER_PID=$!
sleep 5

health_check=$(curl -s -o /dev/null -w "%{http_code}" "http://127.0.0.1:8001/health")
kill $SERVER_PID

if [ "$health_check" = "200" ]; then
    echo "Health check passed, switching to $TARGET environment"
    
    # Switch symlink
    ln -sfn "$TARGET_DIR" "$CURRENT_DIR"
    
    # Restart services
    sudo systemctl reload laracity-worker
    sudo systemctl reload laracity-ai
    sudo systemctl reload nginx
    
    echo "Deployment completed successfully"
else
    echo "Health check failed, keeping current environment"
    exit 1
fi
```

## 🔧 Troubleshooting

### Common Issues

#### High Memory Usage
```bash
# Check PHP-FPM memory usage
sudo ps aux | grep php-fpm | awk '{sum+=$6} END {print sum/1024 " MB"}'

# Optimize PHP-FPM pools
sudo nano /etc/php/8.2/fpm/pool.d/laracity.conf
# Adjust pm.max_children based on available memory
```

#### Queue Worker Issues
```bash
# Check worker status
sudo systemctl status laracity-worker

# View worker logs
sudo journalctl -u laracity-worker -f

# Restart workers
sudo systemctl restart laracity-worker laracity-ai

# Clear failed jobs
php artisan queue:flush
```

#### Database Performance
```sql
-- Check slow queries
SELECT query, mean_exec_time, calls, total_exec_time
FROM pg_stat_statements
ORDER BY mean_exec_time DESC
LIMIT 10;

-- Check index usage
SELECT schemaname, tablename, attname, n_distinct, correlation
FROM pg_stats
WHERE tablename = 'complaints'
ORDER BY n_distinct DESC;
```

### Emergency Procedures

#### Rollback Deployment
```bash
# Quick rollback to previous version
cd /opt/laracity
mv app app-failed
ln -sfn app-previous app
sudo systemctl restart laracity-worker laracity-ai nginx
```

#### Database Recovery
```bash
# Restore from backup
sudo systemctl stop laracity-worker laracity-ai
pg_dump laracity_prod > /tmp/current_backup.sql
dropdb laracity_prod
createdb laracity_prod
gunzip -c /opt/laracity/backups/laracity_db_YYYYMMDD_HHMMSS.sql.gz | psql laracity_prod
sudo systemctl start laracity-worker laracity-ai
```

## 📋 Maintenance Checklist

### Daily Tasks
- [ ] Monitor application health endpoints
- [ ] Check queue worker status and failed jobs
- [ ] Review error logs for critical issues
- [ ] Monitor disk space and memory usage
- [ ] Verify backup completion

### Weekly Tasks  
- [ ] Review security logs and failed login attempts
- [ ] Check SSL certificate expiration
- [ ] Analyze database performance metrics
- [ ] Review AI processing metrics and accuracy
- [ ] Update system packages (security updates)

### Monthly Tasks
- [ ] Full security audit and vulnerability scan
- [ ] Database maintenance (VACUUM, REINDEX)
- [ ] Review and rotate log files
- [ ] Performance testing and optimization
- [ ] Update documentation and runbooks

### Quarterly Tasks
- [ ] Disaster recovery testing
- [ ] Security penetration testing
- [ ] Capacity planning and scaling assessment
- [ ] Update SSL certificates and secrets rotation
- [ ] Complete system backup verification

---

This deployment guide provides a solid foundation for running LaraCity in production. Customize the configurations based on your specific infrastructure, security requirements, and scaling needs.