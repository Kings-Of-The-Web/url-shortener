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
        $this->headers = $this->parseHeaders();
        $this->body = $this->parseBody();
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

        // Use cached raw input from global variable to avoid php://input consumption issues
        $input = $GLOBALS['raw_input'] ?? '';
        
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
