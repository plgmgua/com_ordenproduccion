# Production Orders Management System - Deployment Guide

**Version:** 1.0.0  
**Author:** Grimpsa  
**Date:** January 2025  

---

## Table of Contents

1. [Deployment Overview](#deployment-overview)
2. [Pre-Deployment Checklist](#pre-deployment-checklist)
3. [Production Deployment](#production-deployment)
4. [Staging Deployment](#staging-deployment)
5. [Development Deployment](#development-deployment)
6. [Database Migration](#database-migration)
7. [Configuration Management](#configuration-management)
8. [Monitoring and Logging](#monitoring-and-logging)
9. [Backup and Recovery](#backup-and-recovery)
10. [Rollback Procedures](#rollback-procedures)

---

## Deployment Overview

This guide covers the deployment of the Production Orders Management System (`com_ordenproduccion`) across different environments. The component is designed to work seamlessly in production, staging, and development environments.

### Deployment Environments

- **Production**: Live system serving real users
- **Staging**: Pre-production testing environment
- **Development**: Development and testing environment

### Deployment Methods

- **Manual Deployment**: Direct file upload and configuration
- **Automated Deployment**: CI/CD pipeline deployment
- **Container Deployment**: Docker-based deployment

---

## Pre-Deployment Checklist

### 1. System Requirements Verification

#### Production Environment
- [ ] Joomla 5.0+ installed and configured
- [ ] PHP 8.1+ with required extensions
- [ ] MySQL 5.7+ or MariaDB 10.3+
- [ ] SSL certificate installed
- [ ] Backup system configured
- [ ] Monitoring system in place

#### Staging Environment
- [ ] Mirror of production environment
- [ ] Test data available
- [ ] Webhook endpoints configured
- [ ] Debug mode enabled

#### Development Environment
- [ ] Local development server
- [ ] Version control system
- [ ] Development tools installed
- [ ] Debug mode enabled

### 2. Security Checklist

- [ ] SSL/TLS encryption enabled
- [ ] Firewall configured
- [ ] Access controls in place
- [ ] Regular security updates scheduled
- [ ] Backup encryption enabled
- [ ] Log monitoring configured

### 3. Performance Checklist

- [ ] Server resources adequate
- [ ] Database optimized
- [ ] Caching configured
- [ ] CDN configured (if applicable)
- [ ] Load balancing configured (if applicable)

---

## Production Deployment

### Method 1: Manual Deployment

#### Step 1: Prepare Production Environment

```bash
# Create deployment directory
mkdir -p /tmp/deployment
cd /tmp/deployment

# Download component package
wget https://releases.grimpsa.com/com_ordenproduccion-1.0.0.zip

# Verify package integrity
sha256sum com_ordenproduccion-1.0.0.zip
```

#### Step 2: Backup Current System

```bash
# Backup Joomla files
tar -czf /backups/joomla-$(date +%Y%m%d-%H%M%S).tar.gz /var/www/html/

# Backup database
mysqldump -u username -p database_name > /backups/database-$(date +%Y%m%d-%H%M%S).sql

# Backup configuration
cp /var/www/html/configuration.php /backups/configuration-$(date +%Y%m%d-%H%M%S).php
```

#### Step 3: Deploy Component

```bash
# Extract component
unzip com_ordenproduccion-1.0.0.zip -d /tmp/com_ordenproduccion/

# Copy files to Joomla
cp -r /tmp/com_ordenproduccion/admin/* /var/www/html/administrator/components/com_ordenproduccion/
cp -r /tmp/com_ordenproduccion/site/* /var/www/html/components/com_ordenproduccion/
cp -r /tmp/com_ordenproduccion/media/* /var/www/html/media/com_ordenproduccion/

# Set proper permissions
chown -R www-data:www-data /var/www/html/administrator/components/com_ordenproduccion/
chown -R www-data:www-data /var/www/html/components/com_ordenproduccion/
chown -R www-data:www-data /var/www/html/media/com_ordenproduccion/

chmod -R 755 /var/www/html/administrator/components/com_ordenproduccion/
chmod -R 755 /var/www/html/components/com_ordenproduccion/
chmod -R 755 /var/www/html/media/com_ordenproduccion/
```

#### Step 4: Install via Joomla Admin

1. Log in to Joomla administrator
2. Navigate to **Extensions > Manage > Install**
3. Upload the component package
4. Complete installation

#### Step 5: Configure Component

1. Go to **Components > Production Orders > Configuration**
2. Set production settings:
   - Disable debug mode
   - Set appropriate log levels
   - Configure webhook endpoints
   - Set up monitoring

#### Step 6: Verify Deployment

```bash
# Check component files
ls -la /var/www/html/administrator/components/com_ordenproduccion/
ls -la /var/www/html/components/com_ordenproduccion/
ls -la /var/www/html/media/com_ordenproduccion/

# Check database tables
mysql -u username -p database_name -e "SHOW TABLES LIKE '%ordenproduccion%';"

# Test webhook endpoint
curl -X GET https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.health
```

### Method 2: Automated Deployment

#### CI/CD Pipeline Configuration

```yaml
# .github/workflows/deploy.yml
name: Deploy to Production

on:
  push:
    branches: [main]
    tags: ['v*']

jobs:
  deploy:
    runs-on: ubuntu-latest
    
    steps:
    - uses: actions/checkout@v2
    
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '8.1'
        
    - name: Install dependencies
      run: composer install --no-dev --optimize-autoloader
      
    - name: Run tests
      run: php tests/run-tests.php
      
    - name: Build package
      run: |
        zip -r com_ordenproduccion-1.0.0.zip admin/ site/ media/ com_ordenproduccion.xml
        
    - name: Deploy to production
      uses: appleboy/ssh-action@v0.1.5
      with:
        host: ${{ secrets.PROD_HOST }}
        username: ${{ secrets.PROD_USER }}
        key: ${{ secrets.PROD_SSH_KEY }}
        script: |
          cd /var/www/html
          wget https://github.com/grimpsa/com_ordenproduccion/releases/download/v1.0.0/com_ordenproduccion-1.0.0.zip
          unzip -o com_ordenproduccion-1.0.0.zip
          chown -R www-data:www-data administrator/components/com_ordenproduccion/
          chown -R www-data:www-data components/com_ordenproduccion/
          chown -R www-data:www-data media/com_ordenproduccion/
          systemctl reload apache2
```

#### Deployment Script

```bash
#!/bin/bash
# deploy.sh - Automated deployment script

set -e

# Configuration
COMPONENT_NAME="com_ordenproduccion"
VERSION="1.0.0"
JOOMLA_ROOT="/var/www/html"
BACKUP_DIR="/backups"
LOG_FILE="/var/log/deployment.log"

# Logging function
log() {
    echo "$(date '+%Y-%m-%d %H:%M:%S') - $1" | tee -a $LOG_FILE
}

# Backup function
backup() {
    log "Creating backup..."
    
    # Backup files
    tar -czf $BACKUP_DIR/joomla-$(date +%Y%m%d-%H%M%S).tar.gz $JOOMLA_ROOT/
    
    # Backup database
    mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/database-$(date +%Y%m%d-%H%M%S).sql
    
    log "Backup completed"
}

# Deploy function
deploy() {
    log "Starting deployment..."
    
    # Download package
    wget -O /tmp/$COMPONENT_NAME-$VERSION.zip https://releases.grimpsa.com/$COMPONENT_NAME-$VERSION.zip
    
    # Extract package
    unzip -o /tmp/$COMPONENT_NAME-$VERSION.zip -d /tmp/$COMPONENT_NAME/
    
    # Copy files
    cp -r /tmp/$COMPONENT_NAME/admin/* $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
    cp -r /tmp/$COMPONENT_NAME/site/* $JOOMLA_ROOT/components/$COMPONENT_NAME/
    cp -r /tmp/$COMPONENT_NAME/media/* $JOOMLA_ROOT/media/$COMPONENT_NAME/
    
    # Set permissions
    chown -R www-data:www-data $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
    chown -R www-data:www-data $JOOMLA_ROOT/components/$COMPONENT_NAME/
    chown -R www-data:www-data $JOOMLA_ROOT/media/$COMPONENT_NAME/
    
    chmod -R 755 $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
    chmod -R 755 $JOOMLA_ROOT/components/$COMPONENT_NAME/
    chmod -R 755 $JOOMLA_ROOT/media/$COMPONENT_NAME/
    
    # Cleanup
    rm -rf /tmp/$COMPONENT_NAME-$VERSION.zip /tmp/$COMPONENT_NAME/
    
    log "Deployment completed"
}

# Verify function
verify() {
    log "Verifying deployment..."
    
    # Check files
    if [ ! -d "$JOOMLA_ROOT/administrator/components/$COMPONENT_NAME" ]; then
        log "ERROR: Admin component directory not found"
        exit 1
    fi
    
    if [ ! -d "$JOOMLA_ROOT/components/$COMPONENT_NAME" ]; then
        log "ERROR: Site component directory not found"
        exit 1
    fi
    
    if [ ! -d "$JOOMLA_ROOT/media/$COMPONENT_NAME" ]; then
        log "ERROR: Media directory not found"
        exit 1
    fi
    
    # Test webhook
    if ! curl -f -s https://yoursite.com/index.php?option=$COMPONENT_NAME&task=webhook.health > /dev/null; then
        log "ERROR: Webhook health check failed"
        exit 1
    fi
    
    log "Deployment verification completed"
}

# Main execution
main() {
    log "Starting deployment process..."
    
    backup
    deploy
    verify
    
    log "Deployment process completed successfully"
}

# Run main function
main "$@"
```

---

## Staging Deployment

### Staging Environment Setup

```bash
# Create staging environment
mkdir -p /var/www/staging
cd /var/www/staging

# Clone Joomla
git clone https://github.com/joomla/joomla-cms.git .

# Configure staging
cp configuration.php.staging configuration.php
```

### Staging Configuration

```php
// configuration.php.staging
<?php
class JConfig {
    public $host = 'localhost';
    public $user = 'staging_user';
    public $password = 'staging_password';
    public $db = 'staging_database';
    public $dbprefix = 'jos_';
    public $live_site = 'https://staging.yoursite.com';
    public $secret = 'staging_secret_key';
    public $gzip = '0';
    public $error_reporting = 'maximum';
    public $helpurl = 'https://help.joomla.org/proxy?keyref=Help{major}{minor}:{keyref}&lang={langcode}';
    public $ftp_host = '';
    public $ftp_port = '';
    public $ftp_user = '';
    public $ftp_pass = '';
    public $ftp_root = '';
    public $ftp_enable = '0';
    public $offset = 'UTC';
    public $mailonline = '1';
    public $mailer = 'mail';
    public $mailfrom = 'admin@staging.yoursite.com';
    public $fromname = 'Staging Site';
    public $sendmail = '/usr/sbin/sendmail';
    public $smtpauth = '0';
    public $smtpuser = '';
    public $smtppass = '';
    public $smtphost = 'localhost';
    public $smtpsecure = 'none';
    public $smtpport = '25';
    public $caching = '0';
    public $cache_handler = 'file';
    public $cachetime = '15';
    public $cache_platformprefix = '0';
    public $MetaDesc = 'Staging Site';
    public $MetaKeys = '';
    public $MetaTitle = '1';
    public $MetaAuthor = '1';
    public $MetaVersion = '0';
    public $robots = '';
    public $sef = '1';
    public $sef_rewrite = '0';
    public $sef_suffix = '0';
    public $unicodeslugs = '0';
    public $feed_limit = '10';
    public $feed_email = 'none';
    public $log_path = '/var/www/staging/logs';
    public $tmp_path = '/var/www/staging/tmp';
    public $lifetime = '15';
    public $session_handler = 'database';
    public $shared_session = '0';
}
?>
```

### Staging Deployment Process

1. **Deploy to Staging**
   ```bash
   # Use same deployment script with staging configuration
   ./deploy.sh --environment=staging
   ```

2. **Configure Staging Settings**
   - Enable debug mode
   - Set staging webhook URLs
   - Configure test data
   - Set up monitoring

3. **Run Staging Tests**
   ```bash
   # Run automated tests
   php tests/run-tests.php --environment=staging
   
   # Run integration tests
   php tests/integration-tests.php
   ```

---

## Development Deployment

### Local Development Setup

```bash
# Create development environment
mkdir -p ~/dev/joomla
cd ~/dev/joomla

# Clone Joomla
git clone https://github.com/joomla/joomla-cms.git .

# Install dependencies
composer install

# Configure development
cp configuration.php.dev configuration.php
```

### Development Configuration

```php
// configuration.php.dev
<?php
class JConfig {
    public $host = 'localhost';
    public $user = 'dev_user';
    public $password = 'dev_password';
    public $db = 'dev_database';
    public $dbprefix = 'jos_';
    public $live_site = 'http://localhost:8080';
    public $secret = 'dev_secret_key';
    public $gzip = '0';
    public $error_reporting = 'maximum';
    public $debug = '1';
    public $debug_lang = '1';
    public $log_path = '/var/log/joomla';
    public $tmp_path = '/tmp/joomla';
    public $lifetime = '15';
    public $session_handler = 'database';
}
?>
```

### Development Tools

```bash
# Install development tools
npm install -g gulp
composer install --dev

# Setup development environment
gulp setup
```

---

## Database Migration

### Migration Script

```php
<?php
// migrate.php - Database migration script

class DatabaseMigration {
    private $db;
    private $version;
    
    public function __construct($db, $version) {
        $this->db = $db;
        $this->version = $version;
    }
    
    public function migrate() {
        $currentVersion = $this->getCurrentVersion();
        
        if (version_compare($currentVersion, $this->version, '<')) {
            $this->runMigrations($currentVersion, $this->version);
            $this->updateVersion($this->version);
        }
    }
    
    private function getCurrentVersion() {
        $query = $this->db->getQuery(true)
            ->select('version')
            ->from('#__ordenproduccion_config')
            ->where('setting_key = ' . $this->db->quote('component_version'));
        
        $this->db->setQuery($query);
        return $this->db->loadResult() ?: '0.0.0';
    }
    
    private function runMigrations($from, $to) {
        $migrations = $this->getMigrations($from, $to);
        
        foreach ($migrations as $migration) {
            $this->executeMigration($migration);
        }
    }
    
    private function executeMigration($migration) {
        $sql = file_get_contents(__DIR__ . '/sql/migrations/' . $migration . '.sql');
        
        $statements = explode(';', $sql);
        
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $this->db->setQuery($statement);
                $this->db->execute();
            }
        }
    }
    
    private function updateVersion($version) {
        $query = $this->db->getQuery(true)
            ->update('#__ordenproduccion_config')
            ->set('setting_value = ' . $this->db->quote($version))
            ->where('setting_key = ' . $this->db->quote('component_version'));
        
        $this->db->setQuery($query);
        $this->db->execute();
    }
}

// Usage
$db = JFactory::getDbo();
$migration = new DatabaseMigration($db, '1.0.0');
$migration->migrate();
?>
```

---

## Configuration Management

### Environment-Specific Configuration

```php
<?php
// config/environment.php

class EnvironmentConfig {
    public static function getConfig($environment) {
        $configs = [
            'development' => [
                'debug' => true,
                'log_level' => 'DEBUG',
                'webhook_url' => 'http://localhost:8080/index.php?option=com_ordenproduccion&task=webhook.process',
                'database' => [
                    'host' => 'localhost',
                    'user' => 'dev_user',
                    'password' => 'dev_password',
                    'name' => 'dev_database'
                ]
            ],
            'staging' => [
                'debug' => true,
                'log_level' => 'INFO',
                'webhook_url' => 'https://staging.yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process',
                'database' => [
                    'host' => 'staging-db.example.com',
                    'user' => 'staging_user',
                    'password' => 'staging_password',
                    'name' => 'staging_database'
                ]
            ],
            'production' => [
                'debug' => false,
                'log_level' => 'ERROR',
                'webhook_url' => 'https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.process',
                'database' => [
                    'host' => 'prod-db.example.com',
                    'user' => 'prod_user',
                    'password' => 'prod_password',
                    'name' => 'prod_database'
                ]
            ]
        ];
        
        return $configs[$environment] ?? $configs['production'];
    }
}
?>
```

---

## Monitoring and Logging

### Application Monitoring

```bash
# Install monitoring tools
apt-get install htop iotop nethogs

# Configure log monitoring
echo "*/5 * * * * /usr/local/bin/monitor-logs.sh" | crontab -
```

### Log Monitoring Script

```bash
#!/bin/bash
# monitor-logs.sh - Log monitoring script

LOG_DIR="/var/log/joomla"
ALERT_EMAIL="admin@yoursite.com"
THRESHOLD=1000

# Check error logs
ERROR_COUNT=$(grep -c "ERROR" $LOG_DIR/error.log)
if [ $ERROR_COUNT -gt $THRESHOLD ]; then
    echo "High error count: $ERROR_COUNT" | mail -s "Alert: High Error Count" $ALERT_EMAIL
fi

# Check webhook logs
WEBHOOK_ERRORS=$(grep -c "webhook.*error" $LOG_DIR/webhook.log)
if [ $WEBHOOK_ERRORS -gt 100 ]; then
    echo "Webhook errors detected: $WEBHOOK_ERRORS" | mail -s "Alert: Webhook Errors" $ALERT_EMAIL
fi

# Check disk space
DISK_USAGE=$(df /var/log | tail -1 | awk '{print $5}' | sed 's/%//')
if [ $DISK_USAGE -gt 80 ]; then
    echo "Disk usage high: $DISK_USAGE%" | mail -s "Alert: High Disk Usage" $ALERT_EMAIL
fi
```

### Performance Monitoring

```php
<?php
// monitoring/performance.php

class PerformanceMonitor {
    private $startTime;
    private $memoryStart;
    
    public function __construct() {
        $this->startTime = microtime(true);
        $this->memoryStart = memory_get_usage();
    }
    
    public function getMetrics() {
        return [
            'execution_time' => microtime(true) - $this->startTime,
            'memory_usage' => memory_get_usage() - $this->memoryStart,
            'peak_memory' => memory_get_peak_usage(),
            'database_queries' => $this->getQueryCount(),
            'cache_hits' => $this->getCacheHits()
        ];
    }
    
    public function logMetrics() {
        $metrics = $this->getMetrics();
        
        if ($metrics['execution_time'] > 1.0) {
            error_log("Slow request: " . json_encode($metrics));
        }
        
        if ($metrics['memory_usage'] > 50 * 1024 * 1024) {
            error_log("High memory usage: " . json_encode($metrics));
        }
    }
}
?>
```

---

## Backup and Recovery

### Automated Backup Script

```bash
#!/bin/bash
# backup.sh - Automated backup script

BACKUP_DIR="/backups"
JOOMLA_ROOT="/var/www/html"
DB_NAME="joomla_db"
DB_USER="backup_user"
DB_PASS="backup_password"
RETENTION_DAYS=30

# Create backup directory
mkdir -p $BACKUP_DIR/$(date +%Y%m%d)

# Backup files
tar -czf $BACKUP_DIR/$(date +%Y%m%d)/joomla-$(date +%H%M%S).tar.gz $JOOMLA_ROOT/

# Backup database
mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/$(date +%Y%m%d)/database-$(date +%H%M%S).sql

# Compress database backup
gzip $BACKUP_DIR/$(date +%Y%m%d)/database-$(date +%H%M%S).sql

# Clean old backups
find $BACKUP_DIR -type d -mtime +$RETENTION_DAYS -exec rm -rf {} \;

# Upload to cloud storage (optional)
# aws s3 sync $BACKUP_DIR/$(date +%Y%m%d) s3://your-backup-bucket/$(date +%Y%m%d)/
```

### Recovery Script

```bash
#!/bin/bash
# restore.sh - Recovery script

BACKUP_DIR="/backups"
JOOMLA_ROOT="/var/www/html"
DB_NAME="joomla_db"
DB_USER="restore_user"
DB_PASS="restore_password"
BACKUP_DATE=$1

if [ -z "$BACKUP_DATE" ]; then
    echo "Usage: $0 YYYYMMDD"
    exit 1
fi

# Stop web server
systemctl stop apache2

# Restore files
tar -xzf $BACKUP_DIR/$BACKUP_DATE/joomla-*.tar.gz -C /

# Restore database
gunzip -c $BACKUP_DIR/$BACKUP_DATE/database-*.sql.gz | mysql -u $DB_USER -p$DB_PASS $DB_NAME

# Set permissions
chown -R www-data:www-data $JOOMLA_ROOT/
chmod -R 755 $JOOMLA_ROOT/

# Start web server
systemctl start apache2

echo "Recovery completed for date: $BACKUP_DATE"
```

---

## Rollback Procedures

### Rollback Script

```bash
#!/bin/bash
# rollback.sh - Rollback script

COMPONENT_NAME="com_ordenproduccion"
JOOMLA_ROOT="/var/www/html"
BACKUP_DIR="/backups"
VERSION=$1

if [ -z "$VERSION" ]; then
    echo "Usage: $0 VERSION"
    exit 1
fi

# Stop web server
systemctl stop apache2

# Remove current component
rm -rf $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
rm -rf $JOOMLA_ROOT/components/$COMPONENT_NAME/
rm -rf $JOOMLA_ROOT/media/$COMPONENT_NAME/

# Restore from backup
tar -xzf $BACKUP_DIR/$COMPONENT_NAME-$VERSION-backup.tar.gz -C $JOOMLA_ROOT/

# Set permissions
chown -R www-data:www-data $JOOMLA_ROOT/administrator/components/$COMPONENT_NAME/
chown -R www-data:www-data $JOOMLA_ROOT/components/$COMPONENT_NAME/
chown -R www-data:www-data $JOOMLA_ROOT/media/$COMPONENT_NAME/

# Start web server
systemctl start apache2

echo "Rollback completed to version: $VERSION"
```

### Emergency Rollback

```bash
#!/bin/bash
# emergency-rollback.sh - Emergency rollback script

# Quick rollback to last known good state
./rollback.sh $(ls -t /backups/ | head -1)

# Verify system is working
curl -f https://yoursite.com/index.php?option=com_ordenproduccion&task=webhook.health

if [ $? -eq 0 ]; then
    echo "Emergency rollback successful"
else
    echo "Emergency rollback failed - manual intervention required"
    # Send alert to administrators
    echo "Emergency rollback failed" | mail -s "URGENT: System Down" admin@yoursite.com
fi
```

---

## Conclusion

This deployment guide provides comprehensive instructions for deploying the Production Orders Management System across different environments. Follow the procedures carefully and always test in staging before deploying to production.

For additional support or questions, please contact the Grimpsa support team.

---

**© 2025 Grimpsa. All rights reserved.**
