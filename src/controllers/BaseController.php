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
