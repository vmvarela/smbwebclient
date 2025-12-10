<?php

declare(strict_types=1);

namespace SmbWebClient;

/**
 * Input validation and sanitization for security
 */
class InputValidator
{
    /**
     * Characters that are never allowed in file/directory names
     */
    private const FORBIDDEN_CHARS = ['/', '\\', "\0", "\n", "\r"];

    /**
     * Maximum length for file/directory names
     */
    private const MAX_NAME_LENGTH = 255;

    /**
     * Maximum length for paths
     */
    private const MAX_PATH_LENGTH = 4096;

    /**
     * Validate and sanitize a path from user input.
     * Prevents path traversal attacks and normalizes the path.
     *
     * @param string $path Raw path from user input
     * @return string Sanitized path
     * @throws \InvalidArgumentException If path contains invalid characters
     */
    public function sanitizePath(string $path): string
    {
        // Check for null bytes (injection attack)
        if (str_contains($path, "\0")) {
            throw new \InvalidArgumentException('Path contains null bytes');
        }

        // Check max length
        if (strlen($path) > self::MAX_PATH_LENGTH) {
            throw new \InvalidArgumentException('Path exceeds maximum length');
        }

        // Normalize slashes to forward slashes
        $path = str_replace('\\', '/', $path);

        // Remove any leading/trailing whitespace
        $path = trim($path);

        // Split into parts and validate each
        $parts = explode('/', $path);
        $sanitizedParts = [];

        foreach ($parts as $part) {
            // Skip empty parts (multiple slashes)
            if ($part === '') {
                continue;
            }

            // Prevent path traversal
            if ($part === '.' || $part === '..') {
                throw new \InvalidArgumentException('Path traversal not allowed');
            }

            // Validate each part as a filename
            $sanitizedParts[] = $this->sanitizeFilename($part);
        }

        return implode('/', $sanitizedParts);
    }

    /**
     * Validate and sanitize a filename (no path separators allowed).
     *
     * @param string $filename Raw filename from user input
     * @return string Sanitized filename
     * @throws \InvalidArgumentException If filename is invalid
     */
    public function sanitizeFilename(string $filename): string
    {
        // Check for null bytes
        if (str_contains($filename, "\0")) {
            throw new \InvalidArgumentException('Filename contains null bytes');
        }

        // Remove leading/trailing whitespace
        $filename = trim($filename);

        // Check if empty after trim
        if ($filename === '') {
            throw new \InvalidArgumentException('Filename cannot be empty');
        }

        // Check max length
        if (strlen($filename) > self::MAX_NAME_LENGTH) {
            throw new \InvalidArgumentException('Filename exceeds maximum length');
        }

        // Check for forbidden characters
        foreach (self::FORBIDDEN_CHARS as $char) {
            if (str_contains($filename, $char)) {
                throw new \InvalidArgumentException('Filename contains forbidden characters');
            }
        }

        // Prevent path traversal
        if ($filename === '.' || $filename === '..') {
            throw new \InvalidArgumentException('Invalid filename');
        }

        // Prevent leading/trailing dots on Windows-style hidden files (optional, configurable)
        // We allow this since it's valid in SMB

        return $filename;
    }

    /**
     * Validate a list of filenames (e.g., for batch delete).
     *
     * @param array $filenames Array of filenames
     * @return array Sanitized filenames
     * @throws \InvalidArgumentException If any filename is invalid
     */
    public function sanitizeFilenameList(array $filenames): array
    {
        $sanitized = [];
        foreach ($filenames as $filename) {
            if (!is_string($filename)) {
                throw new \InvalidArgumentException('Invalid filename type');
            }
            $sanitized[] = $this->sanitizeFilename($filename);
        }
        return $sanitized;
    }

    /**
     * Validate sort field.
     *
     * @param string $sortBy Raw sort field
     * @param array $allowed Allowed sort fields
     * @param string $default Default value if invalid
     * @return string Valid sort field
     */
    public function validateSortField(string $sortBy, array $allowed = ['name', 'size', 'modified', 'type'], string $default = 'name'): string
    {
        return in_array($sortBy, $allowed, true) ? $sortBy : $default;
    }

    /**
     * Validate sort direction.
     *
     * @param string $sortDir Raw sort direction
     * @return string 'asc' or 'desc'
     */
    public function validateSortDirection(string $sortDir): string
    {
        return in_array($sortDir, ['asc', 'desc'], true) ? $sortDir : 'asc';
    }

    /**
     * Validate language code.
     *
     * @param string $language Raw language code
     * @param array $allowed Allowed language codes
     * @param string $default Default language
     * @return string Valid language code
     */
    public function validateLanguage(string $language, array $allowed, string $default = 'en'): string
    {
        // Sanitize: only allow alphanumeric and hyphen
        $language = preg_replace('/[^a-zA-Z0-9\-]/', '', $language);
        return in_array($language, $allowed, true) ? $language : $default;
    }

    /**
     * Validate theme name.
     *
     * @param string $theme Raw theme name
     * @param array $allowed Allowed theme names
     * @param string $default Default theme
     * @return string Valid theme name
     */
    public function validateTheme(string $theme, array $allowed, string $default = 'windows'): string
    {
        // Sanitize: only allow alphanumeric and hyphen
        $theme = preg_replace('/[^a-zA-Z0-9\-_]/', '', $theme);
        return in_array($theme, $allowed, true) ? $theme : $default;
    }

    /**
     * Validate and sanitize username.
     * 
     * @param string $username Raw username
     * @return string Sanitized username
     */
    public function sanitizeUsername(string $username): string
    {
        // Remove null bytes and control characters
        $username = preg_replace('/[\x00-\x1F\x7F]/', '', $username);
        
        // Trim whitespace
        $username = trim($username);
        
        // Limit length
        if (strlen($username) > 256) {
            $username = substr($username, 0, 256);
        }
        
        return $username;
    }

    /**
     * Validate an action name from POST.
     *
     * @param string $action Raw action
     * @param array $allowed Allowed actions
     * @return string|null Valid action or null if invalid
     */
    public function validateAction(string $action, array $allowed): ?string
    {
        // Sanitize: only allow alphanumeric and underscore
        $action = preg_replace('/[^a-zA-Z0-9_]/', '', $action);
        return in_array($action, $allowed, true) ? $action : null;
    }

    /**
     * Check if a path is attempting directory traversal.
     *
     * @param string $path Path to check
     * @return bool True if path is safe, false if traversal detected
     */
    public function isPathSafe(string $path): bool
    {
        // Check for null bytes
        if (str_contains($path, "\0")) {
            return false;
        }

        // Normalize and check for traversal
        $normalized = str_replace('\\', '/', $path);
        $parts = explode('/', $normalized);

        foreach ($parts as $part) {
            if ($part === '..') {
                return false;
            }
        }

        return true;
    }

    /**
     * Sanitize HTML output to prevent XSS.
     *
     * @param string $text Text to sanitize
     * @return string HTML-safe text
     */
    public function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Sanitize for JavaScript string context.
     *
     * @param string $text Text to sanitize
     * @return string JS-safe text
     */
    public function escapeJs(string $text): string
    {
        return addslashes($text);
    }
}
