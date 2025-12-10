<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use SmbWebClient\RateLimiter;

#[CoversClass(RateLimiter::class)]
class RateLimiterTest extends TestCase
{
    private string $testCacheDir;
    
    protected function setUp(): void
    {
        $this->testCacheDir = sys_get_temp_dir() . '/ratelimiter_test_' . uniqid();
        @mkdir($this->testCacheDir, 0750, true);
    }
    
    protected function tearDown(): void
    {
        // Clean up test cache directory
        $files = glob($this->testCacheDir . '/*');
        if (is_array($files)) {
            foreach ($files as $file) {
                @unlink($file);
            }
        }
        @rmdir($this->testCacheDir);
    }
    
    #[Test]
    public function constructorCreatesCacheDirectory(): void
    {
        $newDir = $this->testCacheDir . '/subdir';
        $this->assertDirectoryDoesNotExist($newDir);
        
        new RateLimiter($newDir);
        
        $this->assertDirectoryExists($newDir);
        @rmdir($newDir);
    }
    
    #[Test]
    public function isLimitedReturnsFalseForNewKey(): void
    {
        $limiter = new RateLimiter($this->testCacheDir);
        
        $this->assertFalse($limiter->isLimited('192.168.1.1'));
    }
    
    #[Test]
    public function getRemainingAttemptsReturnsMaxForNewKey(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 5);
        
        $this->assertSame(5, $limiter->getRemainingAttempts('192.168.1.1'));
    }
    
    #[Test]
    public function recordFailedAttemptDecrementsRemainingAttempts(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 5);
        $key = '192.168.1.2';
        
        $this->assertSame(5, $limiter->getRemainingAttempts($key));
        
        $limiter->recordFailedAttempt($key);
        $this->assertSame(4, $limiter->getRemainingAttempts($key));
        
        $limiter->recordFailedAttempt($key);
        $this->assertSame(3, $limiter->getRemainingAttempts($key));
    }
    
    #[Test]
    public function isLimitedReturnsTrueAfterMaxAttempts(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 3);
        $key = '192.168.1.3';
        
        $limiter->recordFailedAttempt($key);
        $this->assertFalse($limiter->isLimited($key));
        
        $limiter->recordFailedAttempt($key);
        $this->assertFalse($limiter->isLimited($key));
        
        $limiter->recordFailedAttempt($key);
        $this->assertTrue($limiter->isLimited($key));
    }
    
    #[Test]
    public function getRemainingAttemptsReturnsZeroWhenLocked(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 2);
        $key = '192.168.1.4';
        
        $limiter->recordFailedAttempt($key);
        $limiter->recordFailedAttempt($key);
        
        $this->assertSame(0, $limiter->getRemainingAttempts($key));
    }
    
    #[Test]
    public function getSecondsUntilUnlockReturnsZeroForNewKey(): void
    {
        $limiter = new RateLimiter($this->testCacheDir);
        
        $this->assertSame(0, $limiter->getSecondsUntilUnlock('192.168.1.5'));
    }
    
    #[Test]
    public function getSecondsUntilUnlockReturnsPositiveWhenLocked(): void
    {
        $limiter = new RateLimiter(
            $this->testCacheDir,
            maxAttempts: 2,
            lockoutSeconds: 300
        );
        $key = '192.168.1.6';
        
        $limiter->recordFailedAttempt($key);
        $limiter->recordFailedAttempt($key);
        
        $remaining = $limiter->getSecondsUntilUnlock($key);
        $this->assertGreaterThan(0, $remaining);
        $this->assertLessThanOrEqual(300, $remaining);
    }
    
    #[Test]
    public function recordSuccessClearsRateLimit(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 3);
        $key = '192.168.1.7';
        
        // Record some failed attempts
        $limiter->recordFailedAttempt($key);
        $limiter->recordFailedAttempt($key);
        $this->assertSame(1, $limiter->getRemainingAttempts($key));
        
        // Successful login clears the attempts
        $limiter->recordSuccess($key);
        
        $this->assertSame(3, $limiter->getRemainingAttempts($key));
        $this->assertFalse($limiter->isLimited($key));
    }
    
    #[Test]
    public function recordSuccessClearsLockedState(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 2);
        $key = '192.168.1.8';
        
        // Lock the key
        $limiter->recordFailedAttempt($key);
        $limiter->recordFailedAttempt($key);
        $this->assertTrue($limiter->isLimited($key));
        
        // Success should unlock
        $limiter->recordSuccess($key);
        
        $this->assertFalse($limiter->isLimited($key));
        $this->assertSame(0, $limiter->getSecondsUntilUnlock($key));
    }
    
    #[Test]
    public function differentKeysAreTrackedIndependently(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 3);
        $key1 = '192.168.1.10';
        $key2 = '192.168.1.11';
        
        $limiter->recordFailedAttempt($key1);
        $limiter->recordFailedAttempt($key1);
        
        $this->assertSame(1, $limiter->getRemainingAttempts($key1));
        $this->assertSame(3, $limiter->getRemainingAttempts($key2));
    }
    
    #[Test]
    public function cleanupRemovesOldFiles(): void
    {
        $limiter = new RateLimiter(
            $this->testCacheDir,
            decaySeconds: 1,
            lockoutSeconds: 1
        );
        $key = '192.168.1.12';
        
        $limiter->recordFailedAttempt($key);
        
        // Check file exists
        $files = glob($this->testCacheDir . '/ratelimit_*.json');
        $this->assertNotEmpty($files);
        
        // Wait for files to expire
        sleep(3);
        
        // Run cleanup
        $limiter->cleanup();
        
        // Files should be removed
        $files = glob($this->testCacheDir . '/ratelimit_*.json');
        $this->assertEmpty($files);
    }
    
    #[Test]
    public function getClientIpReturnsRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        
        $ip = RateLimiter::getClientIp();
        
        $this->assertSame('10.0.0.1', $ip);
    }
    
    #[Test]
    public function getClientIpPrefersXForwardedFor(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.50, 70.41.3.18, 150.172.238.178';
        
        $ip = RateLimiter::getClientIp();
        
        $this->assertSame('203.0.113.50', $ip);
        
        // Clean up
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
    
    #[Test]
    public function getClientIpPrefersXRealIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_REAL_IP'] = '192.168.5.5';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        
        $ip = RateLimiter::getClientIp();
        
        $this->assertSame('192.168.5.5', $ip);
        
        // Clean up
        unset($_SERVER['HTTP_X_REAL_IP']);
    }
    
    #[Test]
    public function getClientIpIgnoresInvalidIp(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.1';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'invalid-ip';
        
        $ip = RateLimiter::getClientIp();
        
        $this->assertSame('10.0.0.1', $ip);
        
        // Clean up
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }
    
    #[Test]
    public function getClientIpReturnsDefaultWhenNoServerVars(): void
    {
        $originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        unset($_SERVER['REMOTE_ADDR']);
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
        unset($_SERVER['HTTP_X_REAL_IP']);
        unset($_SERVER['HTTP_CLIENT_IP']);
        
        $ip = RateLimiter::getClientIp();
        
        $this->assertSame('0.0.0.0', $ip);
        
        // Restore
        if ($originalRemoteAddr !== null) {
            $_SERVER['REMOTE_ADDR'] = $originalRemoteAddr;
        }
    }
    
    #[Test]
    public function customMaxAttemptsIsRespected(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 10);
        $key = '192.168.1.20';
        
        $this->assertSame(10, $limiter->getRemainingAttempts($key));
        
        for ($i = 0; $i < 9; $i++) {
            $limiter->recordFailedAttempt($key);
        }
        
        $this->assertSame(1, $limiter->getRemainingAttempts($key));
        $this->assertFalse($limiter->isLimited($key));
        
        $limiter->recordFailedAttempt($key);
        $this->assertTrue($limiter->isLimited($key));
    }
    
    #[Test]
    public function handlesCorruptedCacheFile(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 5);
        $key = '192.168.1.30';
        
        // Create a corrupted cache file
        $hash = hash('sha256', $key);
        $file = $this->testCacheDir . '/ratelimit_' . $hash . '.json';
        file_put_contents($file, 'not valid json');
        
        // Should handle gracefully and treat as new key
        $this->assertFalse($limiter->isLimited($key));
        $this->assertSame(5, $limiter->getRemainingAttempts($key));
    }
    
    #[Test]
    public function handlesNonArrayCacheData(): void
    {
        $limiter = new RateLimiter($this->testCacheDir, maxAttempts: 5);
        $key = '192.168.1.31';
        
        // Create a cache file with non-array JSON
        $hash = hash('sha256', $key);
        $file = $this->testCacheDir . '/ratelimit_' . $hash . '.json';
        file_put_contents($file, '"string value"');
        
        // Should handle gracefully
        $this->assertFalse($limiter->isLimited($key));
        $this->assertSame(5, $limiter->getRemainingAttempts($key));
    }
}
