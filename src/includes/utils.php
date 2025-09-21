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
