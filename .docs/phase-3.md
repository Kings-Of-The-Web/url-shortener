# Phase 3: API Controllers Implementation

This document outlines the implementation of the API controllers that handle the URL shortening and redirect functionality, completing the MVP.


## Overview

Phase 3 focuses on implementing the API controllers that tie everything together:

1. **ShortenController** - Handles POST /api/shorten requests
2. **RedirectController** - Handles GET /{shortCode} redirect requests
3. **Error Handling** - Comprehensive error management
4. **Input Validation** - Request validation and sanitization
5. **Integration Testing** - End-to-end API testing

### API Endpoints

| Method | Endpoint | Controller | Purpose |
|--------|----------|------------|---------|
| POST | `/api/shorten` | ShortenController | Create short URL |
| GET | `/{shortCode}` | RedirectController | Redirect to original URL |
| GET | `/` | HealthCheck | System status |

## Step 1: ShortenController Implementation

### 1.1 Create ShortenController Class

Create `src/controllers/ShortenController.php`:

```php
<?php

require_once __DIR__ . '/../models/Url.php';
require_once __DIR__ . '/../services/Request.php';
require_once __DIR__ . '/../services/Response.php';

class ShortenController
{
    /**
     * Handle POST /api/shorten request
     */
    public function create(Request $request, Response $response): void
    {
        try {
            // Validate request method
            if (!$request->isMethod('POST')) {
                $response->error('Method not allowed', 405)->send();
                return;
            }

            // Validate content type
            if (!$request->isJson()) {
                $response->error('Content-Type must be application/json', 400)->send();
                return;
            }

            // Get request data
            $data = $request->getBody();
            if (!$data) {
                $response->error('Invalid JSON or empty request body', 400)->send();
                return;
            }

            // Validate required fields
            if (!isset($data['url']) || empty($data['url'])) {
                $response->error('URL is required', 400)->send();
                return;
            }

            $originalUrl = trim($data['url']);

            // Validate URL format
            if (!validateUrl($originalUrl)) {
                $response->error('Invalid URL format. Only HTTP and HTTPS URLs are allowed', 400)->send();
                return;
            }

            // Create short URL
            $urlModel = Url::createShortUrl($originalUrl);

            // Build response data
            $responseData = [
                'success' => true,
                'data' => $urlModel->toArray(),
                'message' => 'URL shortened successfully'
            ];

            $response->success($responseData, 201)->send();

        } catch (Exception $e) {
            // Log error for debugging (in production, use proper logging)
            error_log("ShortenController Error: " . $e->getMessage());
            
            // Return generic error to user
            $response->error('Failed to shorten URL. Please try again', 500)->send();
        }
    }

    /**
     * Validate shorten request data
     */
    private function validateShortenRequest(array $data): array
    {
        $errors = [];

        // Check if URL is provided
        if (!isset($data['url']) || empty($data['url'])) {
            $errors[] = 'URL is required';
        } else {
            $url = trim($data['url']);
            
            // Validate URL format
            if (!validateUrl($url)) {
                $errors[] = 'Invalid URL format. Only HTTP and HTTPS URLs are allowed';
            }
            
            // Check URL length
            if (strlen($url) > 2048) {
                $errors[] = 'URL is too long. Maximum length is 2048 characters';
            }
        }

        return $errors;
    }

    /**
     * Handle validation errors
     */
    private function handleValidationErrors(array $errors, Response $response): void
    {
        $response->json([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $errors
        ], 422)->send();
    }
}
```

## Step 2: RedirectController Implementation

### 2.1 Create RedirectController Class

Create `src/controllers/RedirectController.php`:

```php
<?php

require_once __DIR__ . '/../models/Url.php';
require_once __DIR__ . '/../services/Request.php';
require_once __DIR__ . '/../services/Response.php';

class RedirectController
{
    /**
     * Handle GET /{shortCode} redirect request
     */
    public function redirect(string $shortCode, Request $request, Response $response): void
    {
        try {
            // Validate request method
            if (!$request->isMethod('GET')) {
                $response->error('Method not allowed', 405)->send();
                return;
            }

            // Validate short code format
            if (!validateShortCode($shortCode)) {
                $this->handleNotFound($response);
                return;
            }

            // Find URL by short code
            $urlModel = Url::findByShortCode($shortCode);
            
            if (!$urlModel) {
                $this->handleNotFound($response);
                return;
            }

            // Get original URL
            $originalUrl = $urlModel->getOriginalUrl();
            
            if (!$originalUrl) {
                $this->handleNotFound($response);
                return;
            }

            // Validate the stored URL is still valid
            if (!validateUrl($originalUrl)) {
                error_log("Invalid stored URL: " . $originalUrl);
                $this->handleNotFound($response);
                return;
            }

            // Perform redirect
            $response->redirect($originalUrl, 302)->send();

        } catch (Exception $e) {
            // Log error for debugging
            error_log("RedirectController Error: " . $e->getMessage());
            
            // Return 404 for any error to prevent information leakage
            $this->handleNotFound($response);
        }
    }

    /**
     * Handle not found cases
     */
    private function handleNotFound(Response $response): void
    {
        // For redirects, we want to return a proper 404 page
        // You could customize this to show a branded 404 page
        $response->setStatusCode(404)
                 ->setHeader('Content-Type', 'text/html')
                 ->setBody($this->get404Page())
                 ->send();
    }

    /**
     * Generate 404 page HTML
     */
    private function get404Page(): string
    {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Short URL Not Found</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .container {
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 40px rgba(0,0,0,0.1);
            text-align: center;
            max-width: 500px;
            margin: 2rem;
        }
        h1 {
            color: #333;
            font-size: 3rem;
            margin-bottom: 1rem;
            font-weight: 300;
        }
        p {
            color: #666;
            font-size: 1.1rem;
            line-height: 1.6;
            margin-bottom: 2rem;
        }
        .error-code {
            color: #e74c3c;
            font-size: 1.2rem;
            font-weight: 600;
            margin-bottom: 1rem;
        }
        .back-link {
            display: inline-block;
            background: #667eea;
            color: white;
            padding: 0.75rem 2rem;
            text-decoration: none;
            border-radius: 0.5rem;
            transition: background 0.3s ease;
        }
        .back-link:hover {
            background: #5a67d8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="error-code">404 - Not Found</div>
        <h1>Short URL Not Found</h1>
        <p>
            The short URL you\'re looking for doesn\'t exist or may have been removed.
            Please check the URL and try again.
        </p>
        <a href="/" class="back-link">Go to Homepage</a>
    </div>
</body>
</html>';
    }

    /**
     * Get original URL info (for debugging/admin purposes)
     */
    public function getInfo(string $shortCode, Request $request, Response $response): void
    {
        try {
            // This could be used for admin/debugging purposes
            // Not exposed in main routing for security
            
            if (!validateShortCode($shortCode)) {
                $response->error('Invalid short code format', 400)->send();
                return;
            }

            $urlModel = Url::findByShortCode($shortCode);
            
            if (!$urlModel) {
                $response->error('Short URL not found', 404)->send();
                return;
            }

            $responseData = [
                'success' => true,
                'data' => [
                    'short_code' => $urlModel->getShortCode(),
                    'original_url' => $urlModel->getOriginalUrl(),
                    'created_at' => $urlModel->getCreatedAt(),
                    'short_url' => Response::getBaseUrl() . '/' . $urlModel->getShortCode()
                ]
            ];

            $response->success($responseData)->send();

        } catch (Exception $e) {
            error_log("RedirectController Info Error: " . $e->getMessage());
            $response->error('Failed to retrieve URL information', 500)->send();
        }
    }
}
```

## Step 3: Enhanced Error Handling

### 3.1 Create BaseController Class (Optional Enhancement)

Create `src/controllers/BaseController.php`:

```php
<?php

require_once __DIR__ . '/../services/Request.php';
require_once __DIR__ . '/../services/Response.php';

abstract class BaseController
{
    /**
     * Log error messages
     */
    protected function logError(string $message, Exception $e = null): void
    {
        $logMessage = date('Y-m-d H:i:s') . " - " . $message;
        
        if ($e) {
            $logMessage .= " - " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine();
        }
        
        error_log($logMessage);
    }

    /**
     * Validate request method
     */
    protected function validateMethod(Request $request, string $expectedMethod, Response $response): bool
    {
        if (!$request->isMethod($expectedMethod)) {
            $response->error('Method not allowed', 405)->send();
            return false;
        }
        return true;
    }

    /**
     * Validate JSON content type
     */
    protected function validateJsonContent(Request $request, Response $response): bool
    {
        if (!$request->isJson()) {
            $response->error('Content-Type must be application/json', 400)->send();
            return false;
        }
        return true;
    }

    /**
     * Get and validate request body
     */
    protected function getValidatedBody(Request $request, Response $response): ?array
    {
        $data = $request->getBody();
        
        if (!$data) {
            $response->error('Invalid JSON or empty request body', 400)->send();
            return null;
        }
        
        return $data;
    }

    /**
     * Handle validation errors uniformly
     */
    protected function handleValidationErrors(array $errors, Response $response): void
    {
        $response->json([
            'success' => false,
            'error' => 'Validation failed',
            'errors' => $errors
        ], 422)->send();
    }

    /**
     * Handle generic server errors
     */
    protected function handleServerError(Response $response, string $message = 'Internal server error'): void
    {
        $response->error($message, 500)->send();
    }
}
```

## Step 4: Integration Testing

### 4.1 Create API Test Script

Create `test_api.php` in the project root:

```php
<?php

/**
 * API Integration Tests
 * Run this after Phase 3 implementation to test all endpoints
 */

echo "🧪 URL Shortener API Integration Tests\n";
echo "=====================================\n\n";

$baseUrl = 'http://localhost:8080';
$testResults = [];

/**
 * Make HTTP request
 */
function makeRequest($url, $method = 'GET', $data = null) {
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, false);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // Don't follow redirects for testing
    
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Content-Length: ' . strlen(json_encode($data))
            ]);
        }
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headers = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
    curl_close($ch);
    
    return [
        'status' => $httpCode,
        'headers' => $headers,
        'body' => $body
    ];
}

/**
 * Test case runner
 */
function runTest($name, $callable) {
    global $testResults;
    
    echo "🔍 Testing: $name\n";
    
    try {
        $result = $callable();
        if ($result) {
            echo "✅ PASS: $name\n";
            $testResults['pass']++;
        } else {
            echo "❌ FAIL: $name\n";
            $testResults['fail']++;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: $name - " . $e->getMessage() . "\n";
        $testResults['error']++;
    }
    
    echo "\n";
}

// Initialize test results
$testResults = ['pass' => 0, 'fail' => 0, 'error' => 0];

// Test 1: Health Check
runTest('Health Check Endpoint', function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/');
    
    if ($response['status'] !== 200) {
        throw new Exception("Expected 200, got " . $response['status']);
    }
    
    $data = json_decode($response['body'], true);
    
    return isset($data['message']) && $data['message'] === 'URL Shortener API';
});

// Test 2: Valid URL Shortening
runTest('Shorten Valid URL', function() use ($baseUrl) {
    $testUrl = 'https://www.example.com/test';
    
    $response = makeRequest($baseUrl . '/api/shorten', 'POST', [
        'url' => $testUrl
    ]);
    
    if ($response['status'] !== 201) {
        throw new Exception("Expected 201, got " . $response['status']);
    }
    
    $data = json_decode($response['body'], true);
    
    if (!$data['success'] || !isset($data['data']['short_code'])) {
        throw new Exception("Invalid response structure");
    }
    
    // Store short code for redirect test
    $GLOBALS['test_short_code'] = $data['data']['short_code'];
    $GLOBALS['test_original_url'] = $testUrl;
    
    return true;
});

// Test 3: Invalid URL Shortening
runTest('Reject Invalid URL', function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/api/shorten', 'POST', [
        'url' => 'not-a-valid-url'
    ]);
    
    return $response['status'] === 400;
});

// Test 4: Missing URL Parameter
runTest('Reject Missing URL', function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/api/shorten', 'POST', []);
    
    return $response['status'] === 400;
});

// Test 5: Invalid Content Type
runTest('Reject Invalid Content Type', function() use ($baseUrl) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $baseUrl . '/api/shorten');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, 'url=https://example.com');
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 400;
});

// Test 6: Valid Redirect
runTest('Valid Short URL Redirect', function() use ($baseUrl) {
    if (!isset($GLOBALS['test_short_code'])) {
        throw new Exception("No short code from previous test");
    }
    
    $shortCode = $GLOBALS['test_short_code'];
    $originalUrl = $GLOBALS['test_original_url'];
    
    $response = makeRequest($baseUrl . '/' . $shortCode);
    
    if ($response['status'] !== 302) {
        throw new Exception("Expected 302 redirect, got " . $response['status']);
    }
    
    // Check Location header
    if (strpos($response['headers'], 'Location: ' . $originalUrl) === false) {
        throw new Exception("Redirect location not found in headers");
    }
    
    return true;
});

// Test 7: Invalid Short Code
runTest('Invalid Short Code 404', function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/invalid123');
    
    return $response['status'] === 404;
});

// Test 8: Non-existent Short Code
runTest('Non-existent Short Code 404', function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/aaaaaaaa');
    
    return $response['status'] === 404;
});

// Test 9: Wrong Method
runTest('Wrong Method on Shorten', function() use ($baseUrl) {
    $response = makeRequest($baseUrl . '/api/shorten', 'GET');
    
    return $response['status'] === 405;
});

// Test 10: Multiple URLs Same Domain
runTest('Multiple URLs Same Domain', function() use ($baseUrl) {
    $urls = [
        'https://www.google.com/search?q=test1',
        'https://www.google.com/search?q=test2',
        'https://www.google.com/maps'
    ];
    
    $shortCodes = [];
    
    foreach ($urls as $url) {
        $response = makeRequest($baseUrl . '/api/shorten', 'POST', ['url' => $url]);
        
        if ($response['status'] !== 201) {
            throw new Exception("Failed to shorten: $url");
        }
        
        $data = json_decode($response['body'], true);
        $shortCodes[] = $data['data']['short_code'];
    }
    
    // All short codes should be different
    return count($shortCodes) === count(array_unique($shortCodes));
});

// Print results
echo "📊 Test Results Summary\n";
echo "======================\n";
echo "✅ Passed: " . $testResults['pass'] . "\n";
echo "❌ Failed: " . $testResults['fail'] . "\n";
echo "💥 Errors: " . $testResults['error'] . "\n";

$total = array_sum($testResults);
$passRate = $total > 0 ? round(($testResults['pass'] / $total) * 100, 1) : 0;

echo "📈 Pass Rate: {$passRate}%\n\n";

if ($testResults['fail'] === 0 && $testResults['error'] === 0) {
    echo "🎉 All tests passed! Your API is working correctly.\n";
} else {
    echo "⚠️  Some tests failed. Please check the implementation.\n";
}
```

## Step 5: Manual Testing Guide

### 5.1 Test the API Endpoints

```bash
# Start the containers
docker-compose up -d

# Test health check
curl http://localhost:8080/

# Test URL shortening
curl -X POST http://localhost:8080/api/shorten \
  -H "Content-Type: application/json" \
  -d '{"url": "https://www.example.com"}'

# Test redirect (use the short code from above response)
curl -I http://localhost:8080/abc12345

# Test invalid URL
curl -X POST http://localhost:8080/api/shorten \
  -H "Content-Type: application/json" \
  -d '{"url": "not-a-url"}'

# Test missing URL
curl -X POST http://localhost:8080/api/shorten \
  -H "Content-Type: application/json" \
  -d '{}'

# Test non-existent short code
curl -I http://localhost:8080/notfound
```

### 5.2 Run Integration Tests

```bash
# Run the integration test suite
docker-compose exec web php test_api.php

# Clean up test file
docker-compose exec web rm test_api.php
```

## Step 6: Performance Testing

### 6.1 Simple Load Test

Create `load_test.php` for basic performance testing:

```php
<?php

echo "🚀 URL Shortener Load Test\n";
echo "=========================\n\n";

$baseUrl = 'http://localhost:8080';
$concurrent = 10;
$requests = 100;

echo "Testing $requests requests with $concurrent concurrent users\n\n";

function makeAsyncRequest($url, $data) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    
    return $ch;
}

$startTime = microtime(true);
$handles = [];
$mh = curl_multi_init();

// Create test URLs
for ($i = 0; $i < $requests; $i++) {
    $testUrl = "https://www.example.com/page/" . $i;
    $ch = makeAsyncRequest($baseUrl . '/api/shorten', ['url' => $testUrl]);
    curl_multi_add_handle($mh, $ch);
    $handles[] = $ch;
}

// Execute all requests
$running = null;
do {
    curl_multi_exec($mh, $running);
    curl_multi_select($mh);
} while ($running > 0);

// Collect results
$successCount = 0;
$errorCount = 0;

foreach ($handles as $ch) {
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($httpCode === 201) {
        $successCount++;
    } else {
        $errorCount++;
    }
    curl_multi_remove_handle($mh, $ch);
    curl_close($ch);
}

curl_multi_close($mh);

$endTime = microtime(true);
$duration = $endTime - $startTime;
$rps = $requests / $duration;

echo "Results:\n";
echo "--------\n";
echo "Total Requests: $requests\n";
echo "Successful: $successCount\n";
echo "Errors: $errorCount\n";
echo "Duration: " . round($duration, 2) . " seconds\n";
echo "Requests/sec: " . round($rps, 2) . "\n";
echo "Average response time: " . round(($duration / $requests) * 1000, 2) . " ms\n";
```

## Next Steps

Once Phase 3 is complete, you should have:

✅ **ShortenController** - Complete URL shortening API endpoint
✅ **RedirectController** - URL redirect functionality with custom 404 page
✅ **Error Handling** - Comprehensive error management and logging
✅ **Input Validation** - Request validation and sanitization
✅ **Integration Tests** - Full API test suite
✅ **Performance Testing** - Basic load testing capabilities

**Your MVP is now complete!** 🎉

## Files Created in This Phase

- `src/controllers/ShortenController.php`
- `src/controllers/RedirectController.php`
- `src/controllers/BaseController.php` (optional)
- `test_api.php` (testing)
- `load_test.php` (performance testing)

## Production Considerations

For production deployment, consider:
- **Logging**: Implement proper logging with log rotation
- **Monitoring**: Add health checks and metrics
- **Security**: Rate limiting, input sanitization, HTTPS
- **Performance**: Database indexing, caching, CDN
- **Scalability**: Load balancing, database replication

---

*After completing Phase 3, your URL shortener MVP is ready for deployment and real-world usage!*
