<?php

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Cache raw input before any processing to avoid php://input consumption issues
$GLOBALS['raw_input'] = file_get_contents('php://input');

// Include required files
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/services/Request.php';
require_once __DIR__ . '/services/Response.php';
require_once __DIR__ . '/services/Database.php';
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