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
