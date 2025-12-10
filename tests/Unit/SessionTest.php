<?php

declare(strict_types=1);

namespace SmbWebClient\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\Attributes\RunInSeparateProcess;
use SmbWebClient\Config;
use SmbWebClient\Session;

class SessionTest extends TestCase
{
    private Config $config;

    protected function setUp(): void
    {
        $this->config = new Config(
            sessionName: 'TestSession',
            allowAnonymous: false,
        );
    }

    #[Test]
    #[RunInSeparateProcess]
    public function constructorStartsSession(): void
    {
        $session = new Session($this->config);
        
        $this->assertSame(PHP_SESSION_ACTIVE, session_status());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function constructorSetsSessionName(): void
    {
        $session = new Session($this->config);
        
        $this->assertSame('TestSession', session_name());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function setAndGetCredentials(): void
    {
        $session = new Session($this->config);
        
        $session->setCredentials('testuser', 'testpassword');
        $credentials = $session->getCredentials();
        
        $this->assertSame('testuser', $credentials['username']);
        $this->assertSame('testpassword', $credentials['password']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function passwordIsEncryptedInSession(): void
    {
        $session = new Session($this->config);
        
        $session->setCredentials('testuser', 'testpassword');
        
        // The password in $_SESSION should not be plaintext
        $this->assertNotSame('testpassword', $_SESSION['swcPw']);
        
        // But we should be able to retrieve it correctly
        $credentials = $session->getCredentials();
        $this->assertSame('testpassword', $credentials['password']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function clearCredentialsRemovesCredentials(): void
    {
        $session = new Session($this->config);
        
        $session->setCredentials('testuser', 'testpassword');
        $session->clearCredentials();
        
        $this->assertArrayNotHasKey('swcUser', $_SESSION);
        $this->assertArrayNotHasKey('swcPw', $_SESSION);
        
        $credentials = $session->getCredentials();
        $this->assertSame('', $credentials['username']);
        $this->assertSame('', $credentials['password']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function isAuthenticatedReturnsFalseWhenNoCredentials(): void
    {
        $session = new Session($this->config);
        
        $this->assertFalse($session->isAuthenticated());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function isAuthenticatedReturnsTrueWhenCredentialsSet(): void
    {
        $session = new Session($this->config);
        
        $session->setCredentials('user', 'pass');
        
        $this->assertTrue($session->isAuthenticated());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function isAuthenticatedReturnsTrueInAnonymousMode(): void
    {
        $config = new Config(
            sessionName: 'TestSession',
            allowAnonymous: true,
        );
        $session = new Session($config);
        
        // Even without credentials, anonymous mode returns true
        $this->assertTrue($session->isAuthenticated());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function generateCsrfTokenReturnsHexString(): void
    {
        $session = new Session($this->config);
        
        $token = $session->generateCsrfToken();
        
        $this->assertSame(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[a-f0-9]+$/', $token);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function generateCsrfTokenReturnsSameTokenOnMultipleCalls(): void
    {
        $session = new Session($this->config);
        
        $token1 = $session->generateCsrfToken();
        $token2 = $session->generateCsrfToken();
        
        $this->assertSame($token1, $token2);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function validateCsrfTokenReturnsTrueForValidToken(): void
    {
        $session = new Session($this->config);
        
        $token = $session->generateCsrfToken();
        
        $this->assertTrue($session->validateCsrfToken($token));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function validateCsrfTokenReturnsFalseForInvalidToken(): void
    {
        $session = new Session($this->config);
        
        $session->generateCsrfToken();
        
        $this->assertFalse($session->validateCsrfToken('invalid_token'));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function validateCsrfTokenReturnsFalseForNullToken(): void
    {
        $session = new Session($this->config);
        
        $session->generateCsrfToken();
        
        $this->assertFalse($session->validateCsrfToken(null));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function regenerateCsrfTokenChangesToken(): void
    {
        $session = new Session($this->config);
        
        $token1 = $session->generateCsrfToken();
        $session->regenerateCsrfToken();
        $token2 = $session->generateCsrfToken();
        
        $this->assertNotSame($token1, $token2);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function regenerateCreatesNewSessionId(): void
    {
        $session = new Session($this->config);
        
        $originalId = session_id();
        $session->regenerate();
        $newId = session_id();
        
        $this->assertNotSame($originalId, $newId);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function regeneratePreservesCredentials(): void
    {
        $session = new Session($this->config);
        
        $session->setCredentials('user', 'password');
        $session->regenerate();
        
        $credentials = $session->getCredentials();
        $this->assertSame('user', $credentials['username']);
        $this->assertSame('password', $credentials['password']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function setAndGetErrorMessage(): void
    {
        $session = new Session($this->config);
        
        $session->setErrorMessage('Error 1');
        $session->setErrorMessage('Error 2');
        
        $message = $session->getErrorMessage();
        
        $this->assertStringContainsString('Error 1', $message);
        $this->assertStringContainsString('Error 2', $message);
        
        // Message should be cleared after getting
        $this->assertSame('', $session->getErrorMessage());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function setAndGetSuccessMessage(): void
    {
        $session = new Session($this->config);
        
        $session->setSuccessMessage('Success!');
        
        $message = $session->getSuccessMessage();
        
        $this->assertStringContainsString('Success!', $message);
        
        // Message should be cleared after getting
        $this->assertSame('', $session->getSuccessMessage());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function setAndGetLanguage(): void
    {
        $session = new Session($this->config);
        
        $this->assertNull($session->getLanguage());
        
        $session->setLanguage('fr');
        
        $this->assertSame('fr', $session->getLanguage());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function setAndGetTheme(): void
    {
        $session = new Session($this->config);
        
        $this->assertNull($session->getTheme());
        
        $session->setTheme('dark');
        
        $this->assertSame('dark', $session->getTheme());
    }

    #[Test]
    #[RunInSeparateProcess]
    public function setAndGetCachedAuth(): void
    {
        $session = new Session($this->config);
        
        $session->setCachedAuth('server', 'myserver', 'admin', 'secret');
        
        $cached = $session->getCachedAuth('server', 'myserver');
        
        $this->assertNotNull($cached);
        $this->assertSame('admin', $cached['User']);
        $this->assertSame('secret', $cached['Password']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function getCachedAuthReturnsNullForNonexistent(): void
    {
        $session = new Session($this->config);
        
        $cached = $session->getCachedAuth('server', 'unknown');
        
        $this->assertNull($cached);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function cachedAuthPasswordIsEncrypted(): void
    {
        $session = new Session($this->config);
        
        $session->setCachedAuth('server', 'myserver', 'admin', 'secretpassword');
        
        // The password in $_SESSION should be encrypted
        $storedPassword = $_SESSION['swcCachedAuth']['server']['myserver']['Password'];
        $this->assertNotSame('secretpassword', $storedPassword);
        
        // But getCachedAuth should return the decrypted password
        $cached = $session->getCachedAuth('server', 'myserver');
        $this->assertSame('secretpassword', $cached['Password']);
    }

    #[Test]
    #[RunInSeparateProcess]
    public function encryptionKeyIsGeneratedOnConstruction(): void
    {
        $session = new Session($this->config);
        
        $this->assertArrayHasKey('swcEncKey', $_SESSION);
        $this->assertSame(SODIUM_CRYPTO_SECRETBOX_KEYBYTES, strlen($_SESSION['swcEncKey']));
    }

    #[Test]
    #[RunInSeparateProcess]
    public function encryptionKeyIsPreservedAcrossRegeneration(): void
    {
        $session = new Session($this->config);
        
        $originalKey = $_SESSION['swcEncKey'];
        $session->setCredentials('user', 'password');
        
        $session->regenerate();
        
        // Key should be preserved
        $this->assertSame($originalKey, $_SESSION['swcEncKey']);
        
        // Credentials should still be decryptable
        $credentials = $session->getCredentials();
        $this->assertSame('password', $credentials['password']);
    }
}
