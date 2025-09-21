# Phase 2: Core Implementation

This document outlines all the steps required to implement the core functionality of the URL shortener MVP, including the Database service, URL model, utility functions, and main router.


## Overview

Phase 2 focuses on building the foundational classes and services that will power the URL shortener:

1. **Database Service** - Centralized database connection and query handling
2. **Request/Response Classes** - HTTP request and response handling
3. **URL Model** - Data model for URL operations (CRUD)
4. **Utility Functions** - URL validation, sanitization, and hash-based short code generation
5. **Main Router** - Request routing and dispatch logic

### Short Code Generation Strategy

This implementation uses a **hash-based approach with increment collision resolution**:

- **Base Hash**: Generate 7-character base code using CRC32 hash of original URL
- **Increment Suffix**: Add 1-character increment (a-z, A-Z, 0-9) for duplicates
- **Performance**: O(1) for most cases, O(k) where k = number of existing codes per URL
- **Benefits**: 
  - Fast generation (no random collision loops)
  - Allows multiple short codes per URL
  - Predictable collision resolution
  - Related URLs have similar base codes

**Example**: `https://example.com` → `abc1234a`, `abc1234b`, `abc1234c`...

## Step 1: Database Service Implementation

### 1.1 Create Database Service Class

Create `src/services/Database.php`:

```php
<?php

class Database
{
    private static $instance = null;
    private $pdo;
    private $config;

    private function __construct()
    {
        $this->config = require __DIR__ . '/../config/database.php';
        $this->connect();
    }

    /**
     * Singleton pattern to ensure single database connection
     */
    public static function getInstance(): Database
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Establish database connection
     */
    private function connect(): void
    {
        try {
            $dsn = "pgsql:host={$this->config['host']};port={$this->config['port']};dbname={$this->config['dbname']};charset={$this->config['charset']}";
            
            $this->pdo = new PDO($dsn, $this->config['username'], $this->config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            throw new Exception("Database connection failed: " . $e->getMessage());
        }
    }

    /**
     * Get PDO instance
     */
    public function getConnection(): PDO
    {
        return $this->pdo;
    }

    /**
     * Execute a SELECT query
     */
    public function select(string $query, array $params = []): array
    {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return $stmt->fetchAll();
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    /**
     * Execute a SELECT query and return single row
     */
    public function selectOne(string $query, array $params = []): ?array
    {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            $result = $stmt->fetch();
            return $result ?: null;
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    /**
     * Execute an INSERT, UPDATE, or DELETE query
     */
    public function execute(string $query, array $params = []): bool
    {
        try {
            $stmt = $this->pdo->prepare($query);
            return $stmt->execute($params);
        } catch (PDOException $e) {
            throw new Exception("Query execution failed: " . $e->getMessage());
        }
    }

    /**
     * Execute INSERT query and return last inserted ID
     */
    public function insert(string $query, array $params = []): int
    {
        try {
            $stmt = $this->pdo->prepare($query);
            $stmt->execute($params);
            return (int) $this->pdo->lastInsertId();
        } catch (PDOException $e) {
            throw new Exception("Insert query failed: " . $e->getMessage());
        }
    }

    /**
     * Begin database transaction
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * Rollback database transaction
     */
    public function rollback(): bool
    {
        return $this->pdo->rollback();
    }

    /**
     * Check if a transaction is active
     */
    public function inTransaction(): bool
    {
        return $this->pdo->inTransaction();
    }

    /**
     * Prevent cloning of the instance
     */
    private function __clone() {}

    /**
     * Prevent unserialization of the instance
     */
    public function __wakeup()
    {
        throw new Exception("Cannot unserialize singleton");
    }
}
```

## Step 2: Utility Functions Implementation

### 2.1 Create Utility Functions

Create `src/includes/utils.php`:

```php
<?php

/**
 * Validate URL format and protocol
 */
function validateUrl(string $url): bool
{
    // Check if URL is valid format
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return false;
    }

    // Parse URL to check protocol
    $parsed = parse_url($url);
    
    // Must have http or https scheme
    if (!isset($parsed['scheme']) || !in_array($parsed['scheme'], ['http', 'https'])) {
        return false;
    }

    // Must have host
    if (!isset($parsed['host']) || empty($parsed['host'])) {
        return false;
    }

    // Check URL length (max 2048 characters)
    if (strlen($url) > 2048) {
        return false;
    }

    // Additional security: Check for suspicious patterns
    $suspiciousPatterns = [
        '/javascript:/i',
        '/data:/i',
        '/vbscript:/i',
        '/file:/i',
        '/ftp:/i'
    ];
    
    foreach ($suspiciousPatterns as $pattern) {
        if (preg_match($pattern, $url)) {
            return false;
        }
    }

    return true;
}

/**
 * Generate base hash code from URL
 */
function generateBaseHashCode(string $url, int $length = 7): string
{
    $hash = crc32($url);
    if ($hash < 0) {
        $hash += 4294967296;
    }
    
    return base62Encode($hash, $length);
}


/**
 * Convert number to base62 string
 */
function base62Encode(int $number, int $minLength = 1): string
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $base = strlen($chars);
    $result = '';
    
    if ($number === 0) {
        $result = $chars[0];
    } else {
        while ($number > 0) {
            $result = $chars[$number % $base] . $result;
            $number = intval($number / $base);
        }
    }
    
    return str_pad($result, $minLength, $chars[0], STR_PAD_LEFT);
}

/**
 * Convert base62 string to number
 */
function base62Decode(string $string): int
{
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
    $base = strlen($chars);
    $number = 0;
    $length = strlen($string);
    
    for ($i = 0; $i < $length; $i++) {
        $position = strpos($chars, $string[$i]);
        if ($position === false) {
            throw new Exception("Invalid character in base62 string: " . $string[$i]);
        }
        $number = $number * $base + $position;
    }
    
    return $number;
}

/**
 * Sanitize URL for storage
 */
function sanitizeUrl(string $url): string
{
    // Trim whitespace
    $url = trim($url);
    
    // Remove any null bytes or control characters
    $url = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $url);
    
    // Add protocol if missing
    if (!preg_match('/^https?:\/\//', $url)) {
        $url = 'http://' . $url;
    }
    
    // Additional sanitization: encode any remaining suspicious characters
    $url = filter_var($url, FILTER_SANITIZE_URL);
    
    return $url;
}

/**
 * Validate short code format
 */
function validateShortCode(string $shortCode): bool
{
    // Must be exactly 8 characters
    if (strlen($shortCode) !== 8) {
        return false;
    }
    
    // Must contain only alphanumeric characters
    if (!preg_match('/^[a-zA-Z0-9]+$/', $shortCode)) {
        return false;
    }
    
    return true;
}

```

## Step 3: Request and Response Handler Classes

### 3.1 Create Request Handler Class

Create `src/services/Request.php`:

```php
<?php

class Request
{
    private $method;
    private $path;
    private $query;
    private $body;
    private $headers;

    public function __construct()
    {
        $this->method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
        $this->path = $this->parsePath();
        $this->query = $_GET ?? [];
        $this->body = $this->parseBody();
        $this->headers = $this->parseHeaders();
    }

    /**
     * Get HTTP method
     */
    public function getMethod(): string
    {
        return strtoupper($this->method);
    }

    /**
     * Get request path without query string
     */
    public function getPath(): string
    {
        return $this->path;
    }

    /**
     * Get query parameters
     */
    public function getQuery(): array
    {
        return $this->query;
    }

    /**
     * Get specific query parameter
     */
    public function getQueryParam(string $key, $default = null)
    {
        return $this->query[$key] ?? $default;
    }

    /**
     * Get request body
     */
    public function getBody(): ?array
    {
        return $this->body;
    }

    /**
     * Get request headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Get specific header
     */
    public function getHeader(string $name, $default = null): ?string
    {
        $name = strtolower($name);
        return $this->headers[$name] ?? $default;
    }

    /**
     * Check if request is JSON
     */
    public function isJson(): bool
    {
        $contentType = $this->getHeader('content-type', '');
        return strpos($contentType, 'application/json') !== false;
    }

    /**
     * Check if request method matches
     */
    public function isMethod(string $method): bool
    {
        return $this->getMethod() === strtoupper($method);
    }

    /**
     * Check if path matches pattern
     */
    public function matchesPath(string $pattern): bool
    {
        return preg_match($pattern, $this->path);
    }

    /**
     * Extract path segments
     */
    public function getPathSegments(): array
    {
        return array_filter(explode('/', trim($this->path, '/')));
    }

    /**
     * Get path segment by index
     */
    public function getPathSegment(int $index, $default = null): ?string
    {
        $segments = $this->getPathSegments();
        return $segments[$index] ?? $default;
    }

    /**
     * Parse request path
     */
    private function parsePath(): string
    {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        $path = parse_url($uri, PHP_URL_PATH);
        return rtrim($path, '/') ?: '/';
    }

    /**
     * Parse request body
     */
    private function parseBody(): ?array
    {
        if (!$this->isJson()) {
            return null;
        }

        $input = file_get_contents('php://input');
        
        if (empty($input)) {
            return null;
        }
        
        $data = json_decode($input, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            return null;
        }
        
        return $data;
    }

    /**
     * Parse request headers
     */
    private function parseHeaders(): array
    {
        $headers = [];
        
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        
        // Add content-type if available
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        
        return $headers;
    }
}
```

### 3.2 Create Response Handler Class

Create `src/services/Response.php`:

```php
<?php

class Response
{
    private $statusCode = 200;
    private $headers = [];
    private $body = '';

    /**
     * Set HTTP status code
     */
    public function setStatusCode(int $code): self
    {
        $this->statusCode = $code;
        return $this;
    }

    /**
     * Get HTTP status code
     */
    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    /**
     * Set response header
     */
    public function setHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /**
     * Set multiple headers
     */
    public function setHeaders(array $headers): self
    {
        foreach ($headers as $name => $value) {
            $this->setHeader($name, $value);
        }
        return $this;
    }

    /**
     * Get response headers
     */
    public function getHeaders(): array
    {
        return $this->headers;
    }

    /**
     * Set response body
     */
    public function setBody(string $body): self
    {
        $this->body = $body;
        return $this;
    }

    /**
     * Get response body
     */
    public function getBody(): string
    {
        return $this->body;
    }

    /**
     * Send JSON response
     */
    public function json(array $data, int $statusCode = 200): self
    {
        $this->setStatusCode($statusCode)
             ->setHeader('Content-Type', 'application/json')
             ->setBody(json_encode($data, JSON_PRETTY_PRINT));
        
        return $this;
    }

    /**
     * Send error response
     */
    public function error(string $message, int $statusCode = 404): self
    {
        return $this->json(['error' => $message], $statusCode);
    }

    /**
     * Send success response
     */
    public function success(array $data, int $statusCode = 200): self
    {
        return $this->json($data, $statusCode);
    }

    /**
     * Send redirect response
     */
    public function redirect(string $url, int $statusCode = 302): self
    {
        $this->setStatusCode($statusCode)
             ->setHeader('Location', $url);
        
        return $this;
    }

    /**
     * Set CORS headers
     */
    public function setCorsHeaders(): self
    {
        return $this->setHeaders([
            'Access-Control-Allow-Origin' => '*',
            'Access-Control-Allow-Methods' => 'GET, POST, OPTIONS',
            'Access-Control-Allow-Headers' => 'Content-Type, Authorization'
        ]);
    }

    /**
     * Set security headers
     */
    public function setSecurityHeaders(): self
    {
        return $this->setHeaders([
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'DENY',
            'X-XSS-Protection' => '1; mode=block',
            'Referrer-Policy' => 'strict-origin-when-cross-origin'
        ]);
    }

    /**
     * Send the response
     */
    public function send(): void
    {
        // Set status code
        http_response_code($this->statusCode);
        
        // Set headers
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }
        
        // Send body
        echo $this->body;
        
        // End execution
        exit;
    }

    /**
     * Send and exit immediately
     */
    public function sendAndExit(): void
    {
        $this->send();
    }

    /**
     * Get base URL for generating full URLs
     */
    public static function getBaseUrl(): string
    {
        $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        return "{$protocol}://{$host}";
    }
}
```

## Step 4: URL Model Implementation

### 3.1 Create URL Model Class

Create `src/models/Url.php`:

```php
<?php

require_once __DIR__ . '/../services/Database.php';
require_once __DIR__ . '/../services/Response.php';
require_once __DIR__ . '/../includes/utils.php';

class Url
{
    private $id;
    private $originalUrl;
    private $shortCode;
    private $createdAt;

    public function __construct(?array $data = null)
    {
        if ($data) {
            $this->id = $data['id'] ?? null;
            $this->originalUrl = $data['original_url'] ?? null;
            $this->shortCode = $data['short_code'] ?? null;
            $this->createdAt = $data['created_at'] ?? null;
        }
    }

    // Getters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalUrl(): ?string
    {
        return $this->originalUrl;
    }

    public function getShortCode(): ?string
    {
        return $this->shortCode;
    }

    public function getCreatedAt(): ?string
    {
        return $this->createdAt;
    }

    // Setters
    public function setOriginalUrl(string $originalUrl): void
    {
        $this->originalUrl = $originalUrl;
    }

    public function setShortCode(string $shortCode): void
    {
        $this->shortCode = $shortCode;
    }

    /**
     * Save URL to database (insert)
     */
    public function save(): bool
    {
        if (!$this->originalUrl || !$this->shortCode) {
            throw new Exception("Original URL and short code are required");
        }

        $db = Database::getInstance();
        
        $query = "INSERT INTO urls (original_url, short_code) VALUES (?, ?) RETURNING id, created_at";
        
        try {
            $stmt = $db->getConnection()->prepare($query);
            $stmt->execute([$this->originalUrl, $this->shortCode]);
            
            $result = $stmt->fetch();
            if ($result) {
                $this->id = $result['id'];
                $this->createdAt = $result['created_at'];
                return true;
            }
            
            return false;
        } catch (Exception $e) {
            throw new Exception("Failed to save URL: " . $e->getMessage());
        }
    }

    /**
     * Find URL by short code
     */
    public static function findByShortCode(string $shortCode): ?self
    {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM urls WHERE short_code = ?";
        $result = $db->selectOne($query, [$shortCode]);
        
        return $result ? new self($result) : null;
    }

    /**
     * Find URL by ID
     */
    public static function findById(int $id): ?self
    {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM urls WHERE id = ?";
        $result = $db->selectOne($query, [$id]);
        
        return $result ? new self($result) : null;
    }

    /**
     * Check if short code exists
     */
    public static function existsByShortCode(string $shortCode): bool
    {
        $db = Database::getInstance();
        
        $query = "SELECT COUNT(*) as count FROM urls WHERE short_code = ?";
        $result = $db->selectOne($query, [$shortCode]);
        
        return ($result['count'] ?? 0) > 0;
    }

    /**
     * Check if original URL exists
     */
    public static function findByOriginalUrl(string $originalUrl): ?self
    {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM urls WHERE original_url = ?";
        $result = $db->selectOne($query, [$originalUrl]);
        
        return $result ? new self($result) : null;
    }

    /**
     * Get total URL count
     */
    public static function getTotalCount(): int
    {
        $db = Database::getInstance();
        
        $query = "SELECT COUNT(*) as count FROM urls";
        $result = $db->selectOne($query);
        
        return (int) ($result['count'] ?? 0);
    }

    /**
     * Get recent URLs (for debugging/admin purposes)
     */
    public static function getRecent(int $limit = 10): array
    {
        $db = Database::getInstance();
        
        $query = "SELECT * FROM urls ORDER BY created_at DESC LIMIT ?";
        $results = $db->select($query, [$limit]);
        
        return array_map(function($row) {
            return new self($row);
        }, $results);
    }

    /**
     * Get short codes that start with given prefix (for collision detection)
     */
    public static function getShortCodesStartingWith(string $prefix): array
    {
        $db = Database::getInstance();
        
        $query = "SELECT short_code FROM urls WHERE short_code LIKE ?";
        $results = $db->select($query, [$prefix . '%']);
        
        return array_column($results, 'short_code');
    }

    /**
     * Generate unique short code with collision handling
     */
    private static function generateUniqueShortCode(string $url, int $length = 8): string
    {
        // Step 1: Generate base hash code
        $baseLength = $length - 1; // 7 characters for hash
        $baseCode = generateBaseHashCode($url, $baseLength);
        
        // Step 2: Check for collisions and increment
        $increment = 0;
        $shortCode = $baseCode . base62Encode($increment, 1); // Start with base + 'a'
        
        while (self::existsByShortCode($shortCode)) {
            $increment++;
            if ($increment >= 62) {
                // If we've exhausted single character increments, 
                // use 2 characters for increment (reduce base to 6 chars)
                $baseCode = generateBaseHashCode($url, $length - 2);
                $shortCode = $baseCode . base62Encode($increment, 2);
            } else {
                $shortCode = $baseCode . base62Encode($increment, 1);
            }
            
            // Safety valve to prevent infinite loops
            if ($increment > 3844) { // 62^2 = 3844
                throw new Exception("Too many collisions for URL hash");
            }
        }
        
        return $shortCode;
    }

    /**
     * Create new shortened URL using hash-based generation
     */
    public static function createShortUrl(string $originalUrl): self
    {
        // Validate URL
        if (!validateUrl($originalUrl)) {
            throw new Exception("Invalid URL format");
        }

        // Sanitize URL
        $originalUrl = sanitizeUrl($originalUrl);

        // Generate hash-based short code with increment for duplicates
        $shortCode = self::generateUniqueShortCode($originalUrl);

        // Create new URL object
        $url = new self();
        $url->setOriginalUrl($originalUrl);
        $url->setShortCode($shortCode);

        // Save to database
        if (!$url->save()) {
            throw new Exception("Failed to save URL to database");
        }

        return $url;
    }

    /**
     * Convert to array for JSON response
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'original_url' => $this->originalUrl,
            'short_code' => $this->shortCode,
            'short_url' => Response::getBaseUrl() . '/' . $this->shortCode,
            'created_at' => $this->createdAt
        ];
    }
}
```

## Step 5: Main Router Implementation

### 5.1 Update Main Entry Point

Replace the content in `src/index.php`:

```php
<?php

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Include required files
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/services/Request.php';
require_once __DIR__ . '/services/Response.php';
require_once __DIR__ . '/models/Url.php';

// Create request and response objects
$request = new Request();
$response = new Response();

// Set CORS and security headers
$response->setCorsHeaders()->setSecurityHeaders();

// Handle preflight OPTIONS request
if ($request->isMethod('OPTIONS')) {
    $response->setStatusCode(200)->send();
}

try {
    // Route requests based on path and method
    $path = $request->getPath();
    $method = $request->getMethod();
    
    if ($path === '/') {
        // Health check endpoint
        handleHealthCheck($request, $response);
    } elseif ($path === '/api/shorten' && $request->isMethod('POST')) {
        // Shorten URL endpoint
        require_once __DIR__ . '/controllers/ShortenController.php';
        $controller = new ShortenController();
        $controller->create($request, $response);
    } elseif (preg_match('/^\/([a-zA-Z0-9]{8})$/', $path, $matches)) {
        // Redirect short URL
        require_once __DIR__ . '/controllers/RedirectController.php';
        $controller = new RedirectController();
        $controller->redirect($matches[1], $request, $response);
    } else {
        // 404 - Not found
        $response->error('Endpoint not found', 404)->send();
    }
} catch (Exception $e) {
    // Handle any uncaught exceptions
    $response->error('Internal server error', 500)->send();
}

/**
 * Handle health check endpoint
 */
function handleHealthCheck(Request $request, Response $response): void
{
    // Test database connectivity
    $dbStatus = 'disconnected';
    $dbError = null;
    $tableInfo = ['urls_table_exists' => false];

    try {
        $db = Database::getInstance();
        $urlCount = Url::getTotalCount();
        
        $dbStatus = 'connected';
        $tableInfo = [
            'urls_table_exists' => true,
            'urls_count' => $urlCount
        ];
    } catch (Exception $e) {
        $dbError = $e->getMessage();
    }

    // Build response data
    $data = [
        'message' => 'URL Shortener API',
        'version' => '1.0.0',
        'status' => 'ready',
        'database' => [
            'status' => $dbStatus,
        ]
    ];

    // Add database details
    if ($dbStatus === 'connected') {
        $data['database'] = array_merge($data['database'], $tableInfo);
    } else {
        $data['database']['error'] = $dbError;
    }

    // Add endpoint information
    $data['endpoints'] = [
        'health' => 'GET /',
        'shorten' => 'POST /api/shorten',
        'redirect' => 'GET /{shortCode}'
    ];

    $response->success($data)->send();
}
```

## Step 5: Verification and Testing

### 5.1 Test Database Service

Create a temporary test file `test_db.php` in the project root:

```php
<?php

require_once 'src/services/Database.php';

try {
    $db = Database::getInstance();
    echo "✅ Database connection successful\n";
    
    $result = $db->selectOne("SELECT COUNT(*) as count FROM urls");
    echo "✅ Query execution successful. URL count: " . $result['count'] . "\n";
    
} catch (Exception $e) {
    echo "❌ Database test failed: " . $e->getMessage() . "\n";
}
```

### 5.2 Test URL Model

Create a temporary test file `test_model.php` in the project root:

```php
<?php

require_once 'src/models/Url.php';

try {
    // Test URL creation
    $url = Url::createShortUrl('https://www.example.com');
    echo "✅ URL created successfully\n";
    echo "   Short code: " . $url->getShortCode() . "\n";
    echo "   Original URL: " . $url->getOriginalUrl() . "\n";
    
    // Test URL retrieval
    $foundUrl = Url::findByShortCode($url->getShortCode());
    if ($foundUrl && $foundUrl->getOriginalUrl() === 'https://www.example.com') {
        echo "✅ URL retrieval successful\n";
    } else {
        echo "❌ URL retrieval failed\n";
    }
    
    // Test total count
    $count = Url::getTotalCount();
    echo "✅ Total URLs in database: " . $count . "\n";
    
} catch (Exception $e) {
    echo "❌ Model test failed: " . $e->getMessage() . "\n";
}
```

### 5.3 Test Utility Functions

Create a temporary test file `test_utils.php` in the project root:

```php
<?php

require_once 'src/includes/utils.php';

// Test URL validation
$testUrls = [
    'https://www.example.com' => true,
    'http://test.com' => true,
    'ftp://test.com' => false,
    'invalid-url' => false,
    'https://' => false
];

echo "Testing URL validation:\n";
foreach ($testUrls as $url => $expected) {
    $result = validateUrl($url);
    $status = ($result === $expected) ? '✅' : '❌';
    echo "  {$status} {$url}: " . ($result ? 'valid' : 'invalid') . "\n";
}

// Test hash-based short code generation
echo "\nTesting hash-based short code generation:\n";
$testUrls = [
    'https://www.example.com',
    'https://www.google.com',
    'https://www.github.com'
];

foreach ($testUrls as $url) {
    $baseCode = generateBaseHashCode($url, 7);
    $valid = (strlen($baseCode) === 7 && preg_match('/^[a-zA-Z0-9]+$/', $baseCode));
    $status = $valid ? '✅' : '❌';
    echo "  {$status} {$url} -> {$baseCode}[increment]\n";
}

// Test hash consistency
echo "\nTesting hash consistency:\n";
$testUrls = [
    'http://example.com',
    'HTTP://EXAMPLE.COM',
    'http://example.com/',
    'https://example.com'
];

foreach ($testUrls as $url) {
    $baseCode = generateBaseHashCode($url, 7);
    echo "  ✅ {$url} -> {$baseCode}[increment]\n";
}

// Test base62 encoding/decoding
echo "\nTesting base62 encoding/decoding:\n";
$testNumbers = [0, 1, 61, 62, 3843, 238328];
foreach ($testNumbers as $num) {
    $encoded = base62Encode($num, 8);
    $decoded = base62Decode($encoded);
    $status = ($decoded === $num) ? '✅' : '❌';
    echo "  {$status} {$num} -> {$encoded} -> {$decoded}\n";
}
```

### 5.4 Test Request and Response Classes

Create a temporary test file `test_request_response.php` in the project root:

```php
<?php

require_once 'src/services/Request.php';
require_once 'src/services/Response.php';

echo "Testing Request and Response Classes:\n";

try {
    // Simulate a request environment
    $_SERVER['REQUEST_METHOD'] = 'POST';
    $_SERVER['REQUEST_URI'] = '/api/shorten?test=1';
    $_SERVER['HTTP_CONTENT_TYPE'] = 'application/json';
    $_SERVER['HTTP_USER_AGENT'] = 'Test Agent';
    $_GET['test'] = '1';
    
    // Test Request class
    $request = new Request();
    
    echo "✅ Request class instantiated\n";
    echo "  Method: " . $request->getMethod() . "\n";
    echo "  Path: " . $request->getPath() . "\n";
    echo "  Query params: " . json_encode($request->getQuery()) . "\n";
    echo "  Is JSON: " . ($request->isJson() ? 'true' : 'false') . "\n";
    echo "  Is POST: " . ($request->isMethod('POST') ? 'true' : 'false') . "\n";
    
    // Test path segments
    $segments = $request->getPathSegments();
    echo "  Path segments: " . json_encode($segments) . "\n";
    
    // Test Response class
    $response = new Response();
    echo "✅ Response class instantiated\n";
    
    // Test fluent interface
    $response->setStatusCode(200)
             ->setHeader('X-Test', 'success')
             ->setCorsHeaders()
             ->setSecurityHeaders();
    
    echo "  Status code: " . $response->getStatusCode() . "\n";
    echo "  Headers count: " . count($response->getHeaders()) . "\n";
    
    // Test JSON response (don't send, just prepare)
    $testData = ['message' => 'test', 'status' => 'ok'];
    $response->json($testData, 201);
    
    echo "  JSON body set: " . (strlen($response->getBody()) > 0 ? 'true' : 'false') . "\n";
    echo "  JSON status: " . $response->getStatusCode() . "\n";
    
    // Test static methods
    $baseUrl = Response::getBaseUrl();
    echo "  Base URL: " . $baseUrl . "\n";
    
    echo "✅ All Request/Response tests passed\n";
    
} catch (Exception $e) {
    echo "❌ Request/Response test failed: " . $e->getMessage() . "\n";
}
```

### 5.5 Run Tests

```bash
# Test database service
docker-compose exec web php test_db.php

# Test URL model
docker-compose exec web php test_model.php

# Test utility functions
docker-compose exec web php test_utils.php

# Test request/response classes
docker-compose exec web php test_request_response.php

# Clean up test files
docker-compose exec web rm test_db.php test_model.php test_utils.php test_request_response.php
```

### 5.5 Test Health Check Endpoint

```bash
# Test the updated health check
curl http://localhost:8080/

# Should return detailed status with database info
```

## Step 6: Troubleshooting

### Common Issues and Solutions

1. **Database connection errors:**
   - Verify containers are running: `docker-compose ps`
   - Check database logs: `docker-compose logs db`
   - Ensure database configuration is correct

2. **Class not found errors:**
   - Verify file paths in require_once statements
   - Check file permissions: `docker-compose exec web ls -la src/`

3. **PDO extension not available:**
   - Rebuild containers: `docker-compose build --no-cache`
   - Verify Dockerfile includes pdo_pgsql extension

4. **Permission denied errors:**
   - Fix file permissions: `docker-compose exec web chown -R www-data:www-data /var/www/html`

## Next Steps

Once Phase 2 is complete, you should have:

✅ **Database Service** - Centralized database operations with connection pooling
✅ **Request/Response Classes** - Professional HTTP request and response handling
✅ **URL Model** - Complete CRUD operations for URL management
✅ **Utility Functions** - URL validation, sanitization, and hash-based short code generation
✅ **Main Router** - Request routing and basic endpoint structure
✅ **Health Check** - Comprehensive system status reporting

**Ready for Phase 3:** API Implementation (Controllers for shorten and redirect endpoints)

## Files Created in This Phase

- `src/services/Database.php`
- `src/services/Request.php`
- `src/services/Response.php`
- `src/models/Url.php`
- `src/includes/utils.php`
- Updated `src/index.php`

---