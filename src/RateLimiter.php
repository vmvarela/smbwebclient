<?php

declare(strict_types=1);

namespace SmbWebClient;

/**
 * Simple file-based rate limiter to prevent brute force attacks.
 * 
 * Uses a cache directory to store attempt counts per IP address.
 * No external dependencies required.
 */
class RateLimiter
{
    private const DEFAULT_MAX_ATTEMPTS = 5;
    private const DEFAULT_DECAY_SECONDS = 300; // 5 minutes
    private const DEFAULT_LOCKOUT_SECONDS = 900; // 15 minutes

    public function __construct(
        private readonly string $cacheDir,
        private readonly int $maxAttempts = self::DEFAULT_MAX_ATTEMPTS,
        private readonly int $decaySeconds = self::DEFAULT_DECAY_SECONDS,
        private readonly int $lockoutSeconds = self::DEFAULT_LOCKOUT_SECONDS,
    ) {
        if (!is_dir($this->cacheDir)) {
            @mkdir($this->cacheDir, 0750, true);
        }
    }

    /**
     * Check if the given key (usually IP address) is rate limited.
     *
     * @param string $key Unique identifier (e.g., IP address)
     * @return bool True if rate limited (too many attempts), false if allowed
     */
    public function isLimited(string $key): bool
    {
        $data = $this->getData($key);
        
        if ($data === null) {
            return false;
        }

        // Check if currently locked out
        if (isset($data['locked_until']) && $data['locked_until'] > time()) {
            return true;
        }

        // Check if attempts exceed threshold within decay window
        $recentAttempts = $this->countRecentAttempts($data);
        
        return $recentAttempts >= $this->maxAttempts;
    }

    /**
     * Record a failed attempt for the given key.
     *
     * @param string $key Unique identifier (e.g., IP address)
     */
    public function recordFailedAttempt(string $key): void
    {
        $data = $this->getData($key) ?? ['attempts' => [], 'locked_until' => null];
        
        // Add new attempt timestamp
        $data['attempts'][] = time();
        
        // Clean old attempts outside decay window
        $data['attempts'] = array_filter(
            $data['attempts'],
            fn($timestamp) => $timestamp > (time() - $this->decaySeconds)
        );
        $data['attempts'] = array_values($data['attempts']);
        
        // Check if we should lock out
        if (count($data['attempts']) >= $this->maxAttempts) {
            $data['locked_until'] = time() + $this->lockoutSeconds;
        }
        
        $this->saveData($key, $data);
    }

    /**
     * Record a successful attempt (clears the rate limit for this key).
     *
     * @param string $key Unique identifier (e.g., IP address)
     */
    public function recordSuccess(string $key): void
    {
        $this->clearData($key);
    }

    /**
     * Get remaining attempts before lockout.
     *
     * @param string $key Unique identifier
     * @return int Number of remaining attempts (0 if locked)
     */
    public function getRemainingAttempts(string $key): int
    {
        $data = $this->getData($key);
        
        if ($data === null) {
            return $this->maxAttempts;
        }

        if (isset($data['locked_until']) && $data['locked_until'] > time()) {
            return 0;
        }

        $recentAttempts = $this->countRecentAttempts($data);
        return max(0, $this->maxAttempts - $recentAttempts);
    }

    /**
     * Get seconds until the lockout expires.
     *
     * @param string $key Unique identifier
     * @return int Seconds until unlock (0 if not locked)
     */
    public function getSecondsUntilUnlock(string $key): int
    {
        $data = $this->getData($key);
        
        if ($data === null || !isset($data['locked_until'])) {
            return 0;
        }

        $remaining = $data['locked_until'] - time();
        return max(0, $remaining);
    }

    /**
     * Get the client's IP address, considering proxies.
     *
     * @return string IP address
     */
    public static function getClientIp(): string
    {
        // Check for forwarded IP (behind proxy/load balancer)
        $headers = [
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                // X-Forwarded-For may contain multiple IPs, use the first one
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    /**
     * Generate a safe filename for the cache key.
     */
    private function getCacheFilePath(string $key): string
    {
        // Hash the key to create a safe filename
        $hash = hash('sha256', $key);
        return $this->cacheDir . '/ratelimit_' . $hash . '.json';
    }

    /**
     * Get rate limit data for a key.
     */
    private function getData(string $key): ?array
    {
        $file = $this->getCacheFilePath($key);
        
        if (!file_exists($file)) {
            return null;
        }

        $content = @file_get_contents($file);
        if ($content === false) {
            return null;
        }

        $data = json_decode($content, true);
        if (!is_array($data)) {
            return null;
        }

        return $data;
    }

    /**
     * Save rate limit data for a key.
     */
    private function saveData(string $key, array $data): void
    {
        $file = $this->getCacheFilePath($key);
        @file_put_contents($file, json_encode($data), LOCK_EX);
    }

    /**
     * Clear rate limit data for a key.
     */
    private function clearData(string $key): void
    {
        $file = $this->getCacheFilePath($key);
        if (file_exists($file)) {
            @unlink($file);
        }
    }

    /**
     * Count recent attempts within the decay window.
     */
    private function countRecentAttempts(array $data): int
    {
        if (!isset($data['attempts']) || !is_array($data['attempts'])) {
            return 0;
        }

        $cutoff = time() - $this->decaySeconds;
        return count(array_filter(
            $data['attempts'],
            fn($timestamp) => $timestamp > $cutoff
        ));
    }

    /**
     * Clean up expired rate limit files (can be called periodically).
     */
    public function cleanup(): void
    {
        $files = glob($this->cacheDir . '/ratelimit_*.json');
        if (!is_array($files)) {
            return;
        }

        $now = time();
        foreach ($files as $file) {
            // Delete files older than lockout period + decay period
            $mtime = @filemtime($file);
            if ($mtime && ($now - $mtime) > ($this->lockoutSeconds + $this->decaySeconds)) {
                @unlink($file);
            }
        }
    }
}
