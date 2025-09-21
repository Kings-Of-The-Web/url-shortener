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
