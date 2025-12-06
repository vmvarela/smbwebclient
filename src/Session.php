<?php

declare(strict_types=1);

namespace SmbWebClient;

class Session
{
    private const AUTH_BASIC = 'BasicAuth';
    private const AUTH_FORM = 'FormAuth';

    public function __construct(
        private readonly Config $config,
    ) {
        if ($this->config->sessionName !== '') {
            session_name($this->config->sessionName);
        }
        
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    public function getCredentials(): array
    {
        return [
            'username' => $_SESSION['swcUser'] ?? '',
            'password' => $_SESSION['swcPw'] ?? '',
        ];
    }

    public function setCredentials(string $username, string $password): void
    {
        $_SESSION['swcUser'] = $username;
        $_SESSION['swcPw'] = $password;
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

    public function getCachedAuth(string $type, string $name): ?array
    {
        return $_SESSION['swcCachedAuth'][$type][$name] ?? null;
    }

    public function setCachedAuth(string $type, string $name, string $username, string $password): void
    {
        $_SESSION['swcCachedAuth'][$type][$name] = [
            'User' => $username,
            'Password' => $password,
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
