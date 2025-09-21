# Phase 1: Infrastructure Setup

This document outlines all the steps required to set up the infrastructure for the URL shortener MVP, including Docker configuration, database setup, Apache configuration, and basic project structure.

## Prerequisites

- Docker and Docker Compose installed
- Basic knowledge of PHP, PostgreSQL, and Apache
- Text editor or IDE

## Step 1: Project Directory Structure

Create the complete directory structure for the project:

```bash
mkdir -p url-shortener/{src/{models,controllers,services,config,includes},apache,sql}
cd url-shortener
```

**Expected structure after this step:**
```
url-shortener/
├── src/
│   ├── models/
│   ├── controllers/
│   ├── services/
│   ├── config/
│   └── includes/
├── apache/
└── sql/
```

## Step 2: Docker Configuration

### 2.1 Create Dockerfile for PHP/Apache

Create `Dockerfile` in the project root:

```dockerfile
FROM php:8.2-apache

# Install PostgreSQL extension
RUN apt-get update && apt-get install -y \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy Apache configuration
COPY apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# Copy source code
COPY src/ /var/www/html/

# Set proper permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

EXPOSE 80
```

### 2.2 Create Docker Compose Configuration

Create `docker-compose.yml` in the project root:

```yaml
version: '3.8'

services:
  web:
    build: .
    ports:
      - "8080:80"
    depends_on:
      - db
    environment:
      - DB_HOST=db
      - DB_PORT=5432
      - DB_NAME=url_shortener
      - DB_USER=postgres
      - DB_PASSWORD=postgres
    volumes:
      - ./src:/var/www/html
    networks:
      - url-shortener-network

  db:
    image: postgres:15
    environment:
      - POSTGRES_DB=url_shortener
      - POSTGRES_USER=postgres
      - POSTGRES_PASSWORD=postgres
    ports:
      - "5432:5432"
    volumes:
      - postgres_data:/var/lib/postgresql/data
      - ./sql/init.sql:/docker-entrypoint-initdb.d/init.sql
    networks:
      - url-shortener-network

volumes:
  postgres_data:

networks:
  url-shortener-network:
    driver: bridge
```

## Step 3: Apache Configuration

### 3.1 Create Apache Virtual Host Configuration

Create `apache/000-default.conf`:

```apache
<VirtualHost *:80>
    ServerAdmin webmaster@localhost
    DocumentRoot /var/www/html

    # Enable rewrite engine
    RewriteEngine On
    
    # Route all requests to index.php except for existing files
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ /index.php [QSA,L]

    # Set headers for API responses
    Header always set Access-Control-Allow-Origin "*"
    Header always set Access-Control-Allow-Methods "GET, POST, OPTIONS"
    Header always set Access-Control-Allow-Headers "Content-Type, Authorization"

    # Security headers
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"

    ErrorLog ${APACHE_LOG_DIR}/error.log
    CustomLog ${APACHE_LOG_DIR}/access.log combined
</VirtualHost>
```

### 3.2 Create .htaccess Configuration

Create `src/.htaccess` for additional URL rewriting and security:

```apache
# Enable rewrite engine
RewriteEngine On

# Force HTTPS (uncomment if using SSL)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Route API requests
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/api/
RewriteRule ^(.*)$ /index.php [QSA,L]

# Route short URL requests (single path segment, 8 characters)
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_URI} ^/[a-zA-Z0-9]{8}/?$
RewriteRule ^([a-zA-Z0-9]{8})/?$ /index.php?short_code=$1 [QSA,L]

# Block access to sensitive files
<FilesMatch "\.(sql|md|txt|log|ini|conf)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Block access to directories
Options -Indexes

# Security headers (backup to virtual host config)
<IfModule mod_headers.c>
    Header always set X-Content-Type-Options nosniff
    Header always set X-Frame-Options DENY
    Header always set X-XSS-Protection "1; mode=block"
    Header always set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>

# Compression
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
    AddOutputFilterByType DEFLATE application/json
</IfModule>

# Cache control for static assets
<IfModule mod_expires.c>
    ExpiresActive on
    ExpiresByType text/css "access plus 1 month"
    ExpiresByType application/javascript "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType image/jpg "access plus 1 month"
    ExpiresByType image/jpeg "access plus 1 month"
</IfModule>
```

## Step 4: Database Setup

### 4.1 Create Database Schema

Create `sql/init.sql`:

```sql
-- Create the urls table
CREATE TABLE IF NOT EXISTS urls (
    id SERIAL PRIMARY KEY,
    original_url VARCHAR(2048) NOT NULL,
    short_code VARCHAR(8) UNIQUE NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Create index on short_code for faster lookups
CREATE INDEX IF NOT EXISTS idx_urls_short_code ON urls(short_code);

-- Create index on created_at for potential future analytics
CREATE INDEX IF NOT EXISTS idx_urls_created_at ON urls(created_at);

-- Insert sample data for testing (optional)
-- INSERT INTO urls (original_url, short_code) VALUES 
-- ('https://www.example.com', 'test1234'),
-- ('https://www.google.com', 'googl123');
```

## Step 5: Basic PHP Structure

### 5.1 Create Database Configuration

Create `src/config/database.php`:

```php
<?php

return [
    'host' => $_ENV['DB_HOST'] ?? 'localhost',
    'port' => $_ENV['DB_PORT'] ?? '5432',
    'dbname' => $_ENV['DB_NAME'] ?? 'url_shortener',
    'username' => $_ENV['DB_USER'] ?? 'postgres',
    'password' => $_ENV['DB_PASSWORD'] ?? 'postgres',
    'charset' => 'utf8'
];
```

### 5.2 Create Main Entry Point

Create `src/index.php`:

```php
<?php

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Set content type to JSON by default
header('Content-Type: application/json');

// Load database configuration
$dbConfig = require_once __DIR__ . '/config/database.php';

// Test database connectivity
$dbStatus = 'disconnected';
$dbError = null;

try {
    $dsn = "pgsql:host={$dbConfig['host']};port={$dbConfig['port']};dbname={$dbConfig['dbname']};charset={$dbConfig['charset']}";
    $pdo = new PDO($dsn, $dbConfig['username'], $dbConfig['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Test if urls table exists
    $stmt = $pdo->query("SELECT COUNT(*) FROM urls");
    $urlCount = $stmt->fetchColumn();
    
    $dbStatus = 'connected';
    $tableInfo = [
        'urls_table_exists' => true,
        'urls_count' => (int)$urlCount
    ];
    
} catch (PDOException $e) {
    $dbError = $e->getMessage();
    $tableInfo = ['urls_table_exists' => false];
}

// Basic health check response
$response = [
    'message' => 'URL Shortener API',
    'version' => '1.0.0',
    'status' => 'Infrastructure ready',
    'database' => [
        'status' => $dbStatus,
        'host' => $dbConfig['host'],
        'database' => $dbConfig['dbname']
    ]
];

// Add table info if connected
if ($dbStatus === 'connected') {
    $response['database'] = array_merge($response['database'], $tableInfo);
} else {
    $response['database']['error'] = $dbError;
}

// Add routing info for development
$response['endpoints'] = [
    'shorten' => 'POST /api/shorten',
    'redirect' => 'GET /{shortCode}',
    'health' => 'GET /'
];

echo json_encode($response, JSON_PRETTY_PRINT);
```

## Step 6: Verification and Testing

### 6.1 Build and Start Containers

```bash
# Build the Docker images
docker-compose build

# Start the services
docker-compose up -d

# Check if containers are running
docker-compose ps
```

### 6.2 Verify Database Connection

```bash
# Connect to PostgreSQL container
docker-compose exec db psql -U postgres -d url_shortener

# Check if tables exist
\dt

# Exit PostgreSQL
\q
```

### 6.3 Test Web Server

```bash
# Test the web server and database connectivity
curl http://localhost:8080

# Expected response (if database is connected):
# {
#     "message": "URL Shortener API",
#     "version": "1.0.0",
#     "status": "Infrastructure ready",
#     "database": {
#         "status": "connected",
#         "host": "db",
#         "database": "url_shortener",
#         "urls_table_exists": true,
#         "urls_count": 0
#     },
#     "endpoints": {
#         "shorten": "POST /api/shorten",
#         "redirect": "GET /{shortCode}",
#         "health": "GET /"
#     }
# }
```

### 6.4 Check Logs

```bash
# View web server logs
docker-compose logs web

# View database logs
docker-compose logs db

# Follow logs in real-time
docker-compose logs -f
```

## Step 7: Environment Verification

### 7.1 Verify PHP Extensions

Create a temporary file `src/phpinfo.php`:

```php
<?php
phpinfo();
```

Visit `http://localhost:8080/phpinfo.php` and verify:
- PHP version 8.2+
- PDO extension enabled
- pdo_pgsql extension enabled

**Don't forget to delete this file after verification for security!**

### 7.2 Verify Database Tables

```bash
# Connect to database and verify schema
docker-compose exec db psql -U postgres -d url_shortener -c "\d urls"
```

## Troubleshooting

### Common Issues and Solutions

1. **Port 8080 already in use:**
   - Change the port in `docker-compose.yml` from `8080:80` to `8081:80`

2. **Database connection refused:**
   - Ensure PostgreSQL container is running: `docker-compose ps`
   - Check database logs: `docker-compose logs db`

3. **Apache not starting:**
   - Check web server logs: `docker-compose logs web`
   - Verify Apache configuration syntax

4. **File permissions issues:**
   - Rebuild containers: `docker-compose down && docker-compose build --no-cache`

### Useful Commands

```bash
# Stop all services
docker-compose down

# Rebuild containers
docker-compose build --no-cache

# View running containers
docker-compose ps

# Execute commands in containers
docker-compose exec web bash
docker-compose exec db psql -U postgres -d url_shortener

# Remove all containers and volumes (CAUTION: This deletes data!)
docker-compose down -v
```

## Next Steps

Once Phase 1 is complete, you should have:

✅ **Working Docker environment** with PHP/Apache and PostgreSQL containers
✅ **Database schema** created and ready
✅ **Apache configured** with proper URL rewriting
✅ **Basic project structure** in place
✅ **Verified connectivity** between all components

**Ready for Phase 2:** Core Implementation (Database service, URL model, utility functions)

## Files Created in This Phase

- `Dockerfile`
- `docker-compose.yml`
- `apache/000-default.conf`
- `src/.htaccess`
- `sql/init.sql`
- `src/config/database.php`
- `src/index.php`

---
