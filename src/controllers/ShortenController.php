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
