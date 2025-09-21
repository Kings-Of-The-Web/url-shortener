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
