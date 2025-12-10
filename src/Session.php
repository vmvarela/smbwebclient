<?php

declare(strict_types=1);

namespace SmbWebClient;

class Session
{
    private const ENCRYPTION_KEY_SESSION_VAR = 'swcEncKey';

    public function __construct(
        private readonly Config $config,
    ) {
        if ($this->config->sessionName !== '') {
            session_name($this->config->sessionName);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Ensure we have an encryption key for this session
        if (!isset($_SESSION[self::ENCRYPTION_KEY_SESSION_VAR])) {
            $_SESSION[self::ENCRYPTION_KEY_SESSION_VAR] = sodium_crypto_secretbox_keygen();
        }
    }

    /**
     * Encrypt sensitive data using sodium
     */
    private function encrypt(string $data): string
    {
        $key = $_SESSION[self::ENCRYPTION_KEY_SESSION_VAR];
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = sodium_crypto_secretbox($data, $nonce, $key);
        return base64_encode($nonce . $ciphertext);
    }

    /**
     * Decrypt sensitive data using sodium
     */
    private function decrypt(string $encrypted): string
    {
        $key = $_SESSION[self::ENCRYPTION_KEY_SESSION_VAR] ?? null;
        if ($key === null) {
            return '';
        }

        $decoded = base64_decode($encrypted, true);
        if ($decoded === false) {
            return '';
        }

        $nonce = substr($decoded, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $ciphertext = substr($decoded, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);

        $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        if ($plaintext === false) {
            return '';
        }

        return $plaintext;
    }

    /**
     * Regenerate session ID to prevent session fixation attacks
     */
    public function regenerate(): void
    {
        // Preserve encryption key across regeneration
        $encKey = $_SESSION[self::ENCRYPTION_KEY_SESSION_VAR] ?? null;
        
        session_regenerate_id(true);
        
        if ($encKey !== null) {
            $_SESSION[self::ENCRYPTION_KEY_SESSION_VAR] = $encKey;
        }
    }

    public function getCredentials(): array
    {
        $username = $_SESSION['swcUser'] ?? '';
        $encryptedPassword = $_SESSION['swcPw'] ?? '';
        
        return [
            'username' => $username,
            'password' => $encryptedPassword ? $this->decrypt($encryptedPassword) : '',
        ];
    }

    public function setCredentials(string $username, string $password): void
    {
        $_SESSION['swcUser'] = $username;
        $_SESSION['swcPw'] = $this->encrypt($password);
    }

    public function clearCredentials(): void
    {
        unset($_SESSION['swcUser'], $_SESSION['swcPw']);
    }

    public function isAuthenticated(): bool
    {
        if ($this->config->allowAnonymous) {
            return true;
        }

        return !empty($_SESSION['swcUser']);
    }

    /**
     * Generate a CSRF token for forms
     */
    public function generateCsrfToken(): string
    {
        if (!isset($_SESSION['swcCsrfToken'])) {
            $_SESSION['swcCsrfToken'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['swcCsrfToken'];
    }

    /**
     * Validate a CSRF token from form submission
     */
    public function validateCsrfToken(?string $token): bool
    {
        if ($token === null || !isset($_SESSION['swcCsrfToken'])) {
            return false;
        }
        return hash_equals($_SESSION['swcCsrfToken'], $token);
    }

    /**
     * Regenerate CSRF token (call after successful validation)
     */
    public function regenerateCsrfToken(): void
    {
        $_SESSION['swcCsrfToken'] = bin2hex(random_bytes(32));
    }

    public function getCachedAuth(string $type, string $name): ?array
    {
        $cached = $_SESSION['swcCachedAuth'][$type][$name] ?? null;
        if ($cached === null) {
            return null;
        }
        
        return [
            'User' => $cached['User'],
            'Password' => $this->decrypt($cached['Password']),
        ];
    }

    public function setCachedAuth(string $type, string $name, string $username, string $password): void
    {
        $_SESSION['swcCachedAuth'][$type][$name] = [
            'User' => $username,
            'Password' => $this->encrypt($password),
        ];
    }

    public function setErrorMessage(string $message): void
    {
        $_SESSION['swcErrorMessage'] = ($_SESSION['swcErrorMessage'] ?? '') . $message . "\n";
    }

    public function getErrorMessage(): string
    {
        $message = $_SESSION['swcErrorMessage'] ?? '';
        $_SESSION['swcErrorMessage'] = '';
        return $message;
    }

    public function setSuccessMessage(string $message): void
    {
        $_SESSION['swcSuccessMessage'] = ($_SESSION['swcSuccessMessage'] ?? '') . $message . "\n";
    }

    public function getSuccessMessage(): string
    {
        $message = $_SESSION['swcSuccessMessage'] ?? '';
        $_SESSION['swcSuccessMessage'] = '';
        return $message;
    }

    public function setLanguage(string $language): void
    {
        $_SESSION['swcLanguage'] = $language;
    }

    public function getLanguage(): ?string
    {
        return $_SESSION['swcLanguage'] ?? null;
    }

    public function setTheme(string $theme): void
    {
        $_SESSION['swcTheme'] = $theme;
    }

    public function getTheme(): ?string
    {
        return $_SESSION['swcTheme'] ?? null;
    }

    public function destroy(): void
    {
        session_destroy();
    }
}
