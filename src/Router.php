<?php

declare(strict_types=1);

namespace SmbWebClient;

/**
 * Handles URL parsing, path resolution, and request routing.
 */
class Router
{
    private string $currentPath = '';
    private array $pathParts = [];
    private string $sortBy = 'name';
    private string $sortDir = 'asc';
    private ?string $action = null;
    private bool $isLogout = false;
    private bool $isDownload = false;

    public function __construct(
        private readonly Config $config,
        private readonly InputValidator $validator,
    ) {
        $this->parseRequest();
    }

    /**
     * Parse the incoming HTTP request.
     */
    private function parseRequest(): void
    {
        // Parse path
        $rawPath = $_GET['path'] ?? null;
        
        if ($rawPath !== null && !$this->validator->isPathSafe($rawPath)) {
            $rawPath = null;
        }
        
        $this->currentPath = $rawPath ?? $this->config->smbRootPath;
        $this->currentPath = trim($this->currentPath, '/');
        $this->pathParts = $this->currentPath ? explode('/', $this->currentPath) : [];

        // Backward compatibility: if only a share was provided via SMB_ROOT_PATH (no server),
        // attach the default server so navigation still works.
        if ($rawPath === null && count($this->pathParts) === 1 && $this->currentPath !== '') {
            $this->pathParts = [$this->config->smbDefaultServer, $this->pathParts[0]];
            $this->currentPath = implode('/', $this->pathParts);
        }
        
        // Parse sort parameters
        $this->sortBy = $this->validator->validateSortField($_GET['sort'] ?? 'name');
        $this->sortDir = $this->validator->validateSortDirection($_GET['dir'] ?? 'asc');
        
        // Parse action
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $rawAction = $_POST['action'] ?? '';
            $allowedActions = ['upload', 'mkdir', 'delete', 'checkdelete', 'rename'];
            $this->action = $this->validator->validateAction($rawAction, $allowedActions);
        }
        
        // Parse special GET parameters
        $this->isLogout = isset($_GET['logout']);
        $this->isDownload = isset($_GET['download']);
    }

    /**
     * Get the current path.
     */
    public function getCurrentPath(): string
    {
        return $this->currentPath;
    }

    /**
     * Get the path parts as an array.
     */
    public function getPathParts(): array
    {
        return $this->pathParts;
    }

    /**
     * Get the number of path parts.
     */
    public function getPathDepth(): int
    {
        return count($this->pathParts);
    }

    /**
     * Get the current sort field.
     */
    public function getSortBy(): string
    {
        return $this->sortBy;
    }

    /**
     * Get the current sort direction.
     */
    public function getSortDir(): string
    {
        return $this->sortDir;
    }

    /**
     * Get the current action (from POST).
     */
    public function getAction(): ?string
    {
        return $this->action;
    }

    /**
     * Check if this is a logout request.
     */
    public function isLogout(): bool
    {
        return $this->isLogout;
    }

    /**
     * Check if this is a download request.
     */
    public function isDownload(): bool
    {
        return $this->isDownload;
    }

    /**
     * Check if this is a POST request.
     */
    public function isPost(): bool
    {
        return $_SERVER['REQUEST_METHOD'] === 'POST';
    }

    /**
     * Check if this is an authentication attempt.
     */
    public function isLoginAttempt(): bool
    {
        return $this->isPost() && isset($_POST['swcSubmit']);
    }

    /**
     * Get server, share, and path from current path parts.
     * 
     * @return array{0: string, 1: string, 2: string} [serverName, shareName, remotePath]
     */
    public function getServerShareAndPath(): array
    {
        if (count($this->pathParts) < 2) {
            return ['', '', '/'];
        }

        $serverName = $this->pathParts[0];
        $shareName = $this->pathParts[1];
        $path = '/' . implode('/', array_slice($this->pathParts, 2));
        if ($path === '//') {
            $path = '/';
        }
        
        return [$serverName, $shareName, $path];
    }

    /**
     * Build a URL with the given path and parameters.
     */
    public function buildUrl(string $path = '', array $params = []): string
    {
        $params['path'] = $path;
        $query = http_build_query(array_filter($params));
        
        if ($this->config->modRewrite) {
            return $this->config->baseUrl . $path . ($query ? '?' . $query : '');
        }
        
        return ($_SERVER['PHP_SELF'] ?? '/index.php') . ($query ? '?' . $query : '');
    }

    /**
     * Redirect to a URL.
     */
    public function redirect(string $url): never
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Redirect to a path within the application.
     */
    public function redirectToPath(string $path = '', array $params = []): never
    {
        $this->redirect($this->buildUrl($path, $params));
    }

    /**
     * Get POST data with optional key.
     */
    public function getPostData(?string $key = null): mixed
    {
        if ($key === null) {
            return $_POST;
        }
        return $_POST[$key] ?? null;
    }

    /**
     * Get GET data with optional key.
     */
    public function getQueryData(?string $key = null): mixed
    {
        if ($key === null) {
            return $_GET;
        }
        return $_GET[$key] ?? null;
    }

    /**
     * Get uploaded file data.
     */
    public function getUploadedFile(string $key = 'file'): ?array
    {
        if (!isset($_FILES[$key]) || $_FILES[$key]['error'] === UPLOAD_ERR_NO_FILE) {
            return null;
        }
        return $_FILES[$key];
    }

    /**
     * Determine the route type based on path depth.
     */
    public function getRouteType(): string
    {
        return match ($this->getPathDepth()) {
            0 => 'servers',
            1 => 'shares',
            default => 'directory',
        };
    }
}
