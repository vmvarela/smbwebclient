<?php

declare(strict_types=1);

namespace SmbWebClient;

class Application
{
    private SmbClient $smbClient;
    private Session $session;
    private Translator $translator;
    private string $currentPath = '';
    private array $pathParts = [];
    private string $sortBy = 'name';
    private string $sortDir = 'asc';

    public function __construct(
        private readonly Config $config,
    ) {
        $this->session = new Session($config);
        
        // Resolver idioma: primero sesión, luego GET, luego detección de navegador
        $defaultLanguage = $config->defaultLanguage;
        $sessionLanguage = $this->session->getLanguage();
        
        if ($sessionLanguage) {
            $language = $sessionLanguage;
        } else {
            $languageDetector = new Translator($defaultLanguage);
            $language = $_GET['lang'] ?? $languageDetector->detectLanguage($defaultLanguage);
        }
        
        $this->translator = new Translator($language);
        
        $credentials = $this->session->getCredentials();
        $this->smbClient = new SmbClient(
            $config,
            $credentials['username'] ?: null,
            $credentials['password'] ?: null,
        );

        $this->parsePath();
    }

    private function parsePath(): void
    {
        $rawPath = $_GET['path'] ?? null;
        $this->currentPath = $rawPath ?? $this->config->smbRootPath;
        $this->currentPath = trim($this->currentPath, '/');
        $this->pathParts = $this->currentPath ? explode('/', $this->currentPath) : [];

        // Backward compatibility: if only a share was provided via SMB_ROOT_PATH (no server),
        // attach the default server so navigation still works.
        if ($rawPath === null && count($this->pathParts) === 1 && $this->currentPath !== '') {
            $this->pathParts = [$this->config->smbDefaultServer, $this->pathParts[0]];
            $this->currentPath = implode('/', $this->pathParts);
        }
        
        $this->sortBy = $_GET['sort'] ?? 'name';
        if (!in_array($this->sortBy, ['name', 'size', 'modified', 'type'])) {
            $this->sortBy = 'name';
        }
        
        $this->sortDir = $_GET['dir'] ?? 'asc';
        if (!in_array($this->sortDir, ['asc', 'desc'])) {
            $this->sortDir = 'asc';
        }
    }

    public function run(): void
    {
        // Handle authentication
        if (!$this->session->isAuthenticated()) {
            $this->handleAuthentication();
            return;
        }

        // Handle logout
        if (isset($_GET['logout'])) {
            $this->handleLogout();
            return;
        }

        // Handle POST actions
        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            $this->handleAction();
        }

        // Handle file download
        if (isset($_GET['download'])) {
            $this->handleDownload();
            return;
        }

        // Display content
        $this->displayContent();
    }

    private function handleAuthentication(): void
    {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['swcSubmit'])) {
            $username = $_POST['swcUser'] ?? '';
            $password = $_POST['swcPw'] ?? '';
            $language = $_POST['swcLang'] ?? 'es';
            
            // Validate language
            $validLangs = array_keys($this->getLanguageNames());
            if (!in_array($language, $validLangs)) {
                $language = 'es';
            }
            
            $this->session->setCredentials($username, $password);
            $this->session->setLanguage($language);
            $this->smbClient->setCredentials($username, $password);
            
            // Test credentials by trying to list shares on default server
            try {
                $this->smbClient->listShares($this->config->smbDefaultServer);
                header('Location: ' . $this->getUrl());
                exit;
            } catch (\Exception $e) {
                // Authentication failed - clear credentials and show error
                $this->session->clearCredentials();
                $this->session->setErrorMessage('Credenciales incorrectas. Por favor, inténtalo de nuevo.');
            }
        }

        $this->displayLoginForm();
    }

    private function handleLogout(): void
    {
        $this->session->clearCredentials();
        $this->session->destroy();
        header('Location: ' . $this->getUrl());
        exit;
    }

    private function handleAction(): void
    {
        $action = $_POST['action'] ?? '';

        match ($action) {
            'upload' => $this->handleUpload(),
            'mkdir' => $this->handleCreateDirectory(),
            'delete' => $this->handleDelete(),
            'check_delete' => $this->handleCheckDelete(),
            'rename' => $this->handleRename(),
            default => null,
        };

        // Don't redirect for check_delete action
        if ($action === 'check_delete') {
            return;
        }

        // Redirect back to current path
        header('Location: ' . $this->getUrl($this->currentPath, ['sort' => $this->sortBy, 'dir' => $this->sortDir]));
        exit;
    }

    private function handleUpload(): void
    {
        if (!isset($_FILES['file'])) {
            return;
        }

        // Handle both single file and multiple files
        if (!isset($_FILES['file'])) {
            return;
        }

        $rawFiles = $_FILES['file'];
        [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        
        // Normalize to array format
        $fileArray = [];
        if (is_array($rawFiles['name'])) {
            // Multiple files uploaded
            for ($i = 0; $i < count($rawFiles['name']); $i++) {
                $fileArray[] = [
                    'name' => $rawFiles['name'][$i],
                    'tmp_name' => $rawFiles['tmp_name'][$i],
                    'error' => $rawFiles['error'][$i],
                ];
            }
        } else {
            // Single file uploaded
            $fileArray[] = $rawFiles;
        }
        
        $successCount = 0;
        $errorCount = 0;
        $errors = [];

        foreach ($fileArray as $file) {
            if ($file['error'] !== UPLOAD_ERR_OK) {
                $errorCount++;
                $errors[] = 'Error: ' . basename($file['name']);
                continue;
            }

            // Build the full remote path
            $fullRemotePath = rtrim($remotePath, '/') . '/' . basename($file['name']);
            
            // Extract directory path and create it if needed
            $fileDir = dirname($fullRemotePath);
            if ($fileDir !== '/' && $fileDir !== $remotePath) {
                try {
                    $this->smbClient->createDirectory($serverName, $shareName, $fileDir);
                } catch (\Exception $e) {
                    // Directory might already exist, continue
                }
            }

            try {
                $this->smbClient->uploadFile($serverName, $shareName, $file['tmp_name'], $fullRemotePath);
                $successCount++;
            } catch (\Exception $e) {
                $errorCount++;
                $errors[] = htmlspecialchars(basename($file['name'])) . ': ' . $e->getMessage();
            }
        }

        if ($successCount > 0) {
            $this->session->setSuccessMessage("Uploaded $successCount file(s)");
        }
        if ($errorCount > 0) {
            $this->session->setErrorMessage(implode(', ', $errors));
        }
    }

    private function handleCreateDirectory(): void
    {
        $dirName = $_POST['dirname'] ?? '';
        if (empty($dirName)) {
            return;
        }

        [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        $remotePath = rtrim($remotePath, '/') . '/' . $dirName;

        try {
            $this->smbClient->createDirectory($serverName, $shareName, $remotePath);
        } catch (\Exception $e) {
            $this->session->setErrorMessage('Error creating directory: ' . $e->getMessage());
        }
    }

    private function handleDelete(): void
    {
        $items = isset($_POST['items']) ? (array)$_POST['items'] : [];
        $items = array_filter(array_map('trim', $items));
        
        if (empty($items)) {
            $this->session->setErrorMessage('No items selected for deletion');
            return;
        }

        [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        $successCount = 0;
        $errorCount = 0;

        foreach ($items as $item) {
            // Build proper path: if remotePath is '/', use just the filename
            if ($remotePath === '/') {
                $itemPath = '/' . $item;
            } else {
                $itemPath = rtrim($remotePath, '/') . '/' . $item;
            }
            
            $deleted = false;
            
            // Try as file first
            try {
                $this->smbClient->deleteFile($serverName, $shareName, $itemPath);
                $deleted = true;
            } catch (\Exception $fileError) {
                // Try as empty directory
                try {
                    $this->smbClient->deleteDirectory($serverName, $shareName, $itemPath);
                    $deleted = true;
                } catch (\Exception $dirError) {
                    $errorCount++;
                    $this->session->setErrorMessage('Error deleting ' . htmlspecialchars($item) . ': ' . $dirError->getMessage());
                }
            }
            
            if ($deleted) {
                $successCount++;
            }
        }
        
        if ($successCount > 0) {
            $this->session->setSuccessMessage($successCount . ' item(s) deleted successfully');
        }
    }

    private function handleCheckDelete(): void
    {
        header('Content-Type: application/json');
        
        try {
            $items = isset($_POST['items']) ? json_decode($_POST['items'], true) : [];
            $items = array_filter(array_map('trim', $items));
            
            if (empty($items)) {
                echo json_encode(['nonEmptyDirs' => [], 'error' => null]);
                exit;
            }

            try {
                [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();
                if ($serverName === '' || $shareName === '') {
                    echo json_encode(['nonEmptyDirs' => [], 'error' => 'Ruta inválida']);
                    exit;
                }
            } catch (\Exception $e) {
                echo json_encode(['nonEmptyDirs' => [], 'error' => 'Connection error']);
                exit;
            }

            $nonEmptyDirs = [];

            foreach ($items as $item) {
                // Build proper path: if remotePath is '/', use just the filename
                if ($remotePath === '/') {
                    $itemPath = '/' . $item;
                } else {
                    $itemPath = rtrim($remotePath, '/') . '/' . $item;
                }
                
                // Try to delete. If it's a non-empty directory, it will fail with a specific error
                try {
                    // First, check if it's a directory by trying to rmdir
                    // If rmdir fails, it's either a file or a non-empty directory
                    $this->smbClient->deleteDirectory($serverName, $shareName, $itemPath);
                    // If we reach here, it was an empty directory and we deleted it (oops!)
                    // But that's ok for this check - we just need to know if it was empty
                } catch (\Exception $e) {
                    // Check if the error indicates the directory is not empty
                    $message = $e->getMessage();
                    if (stripos($message, 'not empty') !== false || 
                        stripos($message, 'notempty') !== false ||
                        stripos($message, 'NT_STATUS_DIRECTORY_NOT_EMPTY') !== false) {
                        // It's a non-empty directory
                        $nonEmptyDirs[] = htmlspecialchars($item);
                    }
                    // If it's a file (error would be different), we don't add it to nonEmptyDirs
                }
            }

            echo json_encode(['nonEmptyDirs' => $nonEmptyDirs, 'error' => null]);
            exit;
        } catch (\Exception $e) {
            header('HTTP/1.1 500 Internal Server Error');
            echo json_encode(['nonEmptyDirs' => [], 'error' => $e->getMessage()]);
            exit;
        }
    }

    private function handleRename(): void
    {
        $oldName = $_POST['oldname'] ?? '';
        $newName = $_POST['newname'] ?? '';
        
        if (empty($oldName) || empty($newName)) {
            return;
        }

        [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();
        if ($serverName === '' || $shareName === '') {
            $this->session->setErrorMessage('Ruta inválida: falta servidor o recurso');
            return;
        }
        $oldPath = rtrim($remotePath, '/') . '/' . $oldName;
        $newPath = rtrim($remotePath, '/') . '/' . $newName;

        try {
            $this->smbClient->rename($serverName, $shareName, $oldPath, $newPath);
        } catch (\Exception $e) {
            $this->session->setErrorMessage('Error renaming: ' . $e->getMessage());
        }
    }

    private function handleDownload(): void
    {
        [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();

        if ($serverName === '' || $shareName === '' || $remotePath === '/' || str_ends_with($remotePath, '/')) {
            $this->session->setErrorMessage('Ruta de descarga inválida');
            header('Location: ' . $this->getUrl($this->currentPath));
            exit;
        }

        $tempFile = tempnam(sys_get_temp_dir(), 'swc');

        try {
            $this->smbClient->downloadFile($serverName, $shareName, $remotePath, $tempFile);
            $fileName = basename($remotePath);
            header('Content-Type: application/octet-stream');
            header('Content-Disposition: attachment; filename="' . $fileName . '"');
            header('Content-Length: ' . filesize($tempFile));
            readfile($tempFile);
        } catch (\Exception $e) {
            $this->session->setErrorMessage('Error al descargar: ' . $e->getMessage());
            header('Location: ' . $this->getUrl($this->currentPath));
        } finally {
            if (is_file($tempFile)) {
                @unlink($tempFile);
            }
        }

        exit;
    }

    private function getServerShareAndPath(): array
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

    private function displayContent(): void
    {
        $content = match (count($this->pathParts)) {
            0 => $this->displayServers(),
            1 => $this->displayShares($this->pathParts[0]),
            default => $this->displayDirectory(),
        };

        echo $this->renderPage('SMB Web Client', $content);
    }

    private function getLanguageNames(): array
    {
        return [
            'es' => 'Español',
            'en' => 'English',
            'fr' => 'Français'
        ];
    }

    private function renderLanguageSelectorCombo(): string
    {
        $langs = $this->getLanguageNames();
        $current = $this->translator->getLanguage();
        $currentPath = htmlspecialchars($this->currentPath);
        $html = '<select name="swcLang" id="swcLang" onchange="window.location.href=\'?path=' . $currentPath . '&lang=\'+this.value" style="width: 100%; padding: 8px; border: 1px solid #c5d1ea; border-radius: 3px; font-size: 0.85rem; box-sizing: border-box; background: #fff;">';
        foreach ($langs as $code => $label) {
            $selected = $code === $current ? ' selected' : '';
            $html .= '<option value="' . $code . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function displayServers(): string
    {
        $servers = $this->smbClient->discoverServers();
        $logoutUrl = $this->getUrl('', ['logout' => '1']);
        // Breadcrumb + toolbar en una línea
        $toolbar = '<div style="display: flex; align-items: center; gap: 10px;">' .
            '<a href="' . $logoutUrl . '" class="toolbar-link" style="color: #666; text-decoration: none; font-size: 0.85rem;">🚪 ' . $this->translator->translate(17) . '</a>' .
            '</div>';
        
        $html = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';
        
        $html .= '<table class="listing"><tr><th>' . $this->translator->translate(1) . '</th><th>' . $this->translator->translate(5) . '</th></tr>';

        foreach ($servers as $server) {
            $safeName = htmlspecialchars($server);
            $url = $this->getUrl($server);
            $html .= "<tr><td><a href=\"{$url}\">{$safeName}</a></td><td>Servidor</td></tr>";
        }

        $html .= '</table>';
        return $html;
    }

    private function renderShareList(string $serverName, array $shares): string
    {
        $logoutUrl = $this->getUrl('', ['logout' => '1']);
        // Breadcrumb + toolbar en una línea
        $toolbar = '<div style="display: flex; align-items: center; gap: 10px;">' .
            '<a href="' . $logoutUrl . '" class="toolbar-link" style="color: #666; text-decoration: none; font-size: 0.85rem.">🚪 ' . $this->translator->translate(17) . '</a>' .
            '</div>';
        
        $html = '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';
        
        $html .= '<table class="listing"><tr><th>' . $this->translator->translate(1) . '</th><th>' . $this->translator->translate(5) . '</th></tr>';
        
        foreach ($shares as $share) {
            $name = htmlspecialchars($share->getName());
            $url = $this->getUrl($serverName . '/' . $name);
            $html .= "<tr><td><a href=\"{$url}\">{$name}</a></td><td>Share</td></tr>";
        }
        
        $html .= '</table>';
        return $html;
    }

    private function displayDirectoryList(array $items): string
    {
        $action = $this->getUrl($this->currentPath);
        $actionEscaped = htmlspecialchars($action);
        $logoutUrl = $this->getUrl('', ['logout' => '1']);
        
        // Form oculto para upload
        $html = '<form id="uploadForm" class="inline" method="post" action="' . $action . '" enctype="multipart/form-data" style="display: none;">' .
            '<input type="hidden" name="action" value="upload" />' .
            '<input type="file" name="file" multiple accept="*/*" />' .
            '</form>';
        
        // Breadcrumb + toolbar en una línea
        $toolbar = '<div style="display: flex; align-items: center; gap: 10px;">' .
            '<a href="#" id="newFolderBtn" class="toolbar-link" style="color: #666; text-decoration: none; font-size: 0.85rem;">📁 ' . $this->translator->translate(15) . '</a>' .
            '<a href="#" id="deleteSelectedBtn" class="toolbar-link" style="color: #666; text-decoration: none; font-size: 0.85rem;">🗑️ ' . $this->translator->translate(8) . '</a>' .
            '<a href="' . $logoutUrl . '" class="toolbar-link" style="color: #666; text-decoration: none; font-size: 0.85rem;">🚪 ' . $this->translator->translate(17) . '</a>' .
            '</div>';
        
        $html .= '<div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px;">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';

        if (count($items) > 0) {
            $html .= '<form method="post" action="' . $action . '">';
            $html .= '<input type="hidden" name="action" value="delete" />';
            $html .= '<table class="listing"><tr><th><input type="checkbox" id="selectAll" style="margin: 0; vertical-align: middle;" /></th><th></th>';
            $html .= $this->renderSortHeader(1, 'name');
            $html .= $this->renderSortHeader(2, 'size');
            $html .= $this->renderSortHeader(4, 'modified');
            $html .= $this->renderSortHeader(5, 'type');
            $html .= '<th></th></tr>';
            
            foreach ($items as $item) {
                $name = htmlspecialchars($item->getName());
                $size = $item->isDirectory() ? '' : $this->formatSize($item->getSize());
                $dateFormat = $this->translator->translate(6);
                $modified = date($dateFormat, $item->getMTime());
                $path = $this->currentPath . '/' . $item->getName();
                $url = $item->isDirectory()
                    ? $this->getUrl($path)
                    : $this->getUrl($path, ['download' => '1']);
                
                // Tipo: mostrar extensión para archivos o "Carpeta" para directorios
                if ($item->isDirectory()) {
                    $typeLabel = $this->translator->translate(11); // "Carpeta"
                } else {
                    $ext = strtoupper(pathinfo($name, PATHINFO_EXTENSION));
                    $typeLabel = $ext ? $this->translator->translate(12, $ext) : '';
                }
                
                $checkbox = '<input type="checkbox" name="items[]" value="' . $name . '" />';
                $icon = $this->getIcon($item);

                $html .= "<tr><td>{$checkbox}</td><td>{$icon}</td><td><a href=\"{$url}\">{$name}</a></td><td>{$size}</td><td>{$modified}</td><td>{$typeLabel}</td><td></td></tr>";
            }
            
            $html .= '</table>';
            $html .= '</form>';
        }

        // Dropzone al final
        $html .= '<div id="dropzone" class="dropzone"><div class="dropzone-text">📂 Arrastra archivos aquí para subir o haz clic</div></div>';
        
        // Agregar atributo data con la URL de acción para el JavaScript
        $html .= '<script>window.createFolderAction = "' . $actionEscaped . '";</script>';
        
        return $html;
    }

    private function formatSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function getIcon($item): string
    {
        if ($item->isDirectory()) {
            return '📁';
        }

        $name = strtolower($item->getName());
        $ext = pathinfo($name, PATHINFO_EXTENSION);

        return match ($ext) {
            'txt', 'md', 'log' => '📄',
            'pdf' => '📕',
            'doc', 'docx', 'rtf' => '📄',
            'xls', 'xlsx', 'csv' => '📊',
            'ppt', 'pptx' => '📊',
            'jpg', 'jpeg', 'png', 'gif', 'bmp', 'webp' => '🖼️',
            'mp3', 'wav', 'ogg', 'flac' => '🎵',
            'mp4', 'mkv', 'mov', 'avi', 'wmv' => '🎞️',
            'zip', 'gz', 'bz2', 'xz', 'rar', '7z', 'tar', 'tgz' => '🗜️',
            'exe' => '⚙️',
            'dll' => '🧩',
            default => '📃',
        };
    }

    private function renderBreadcrumb(): string
    {
        $rootLabel = $this->translator->translate(0);
        $linkStyle = 'color: inherit; text-decoration: none;';
        $rootLink = '<a href="' . $this->getUrl('') . '" style="' . $linkStyle . '">' . $rootLabel . '</a>';
        
        if (empty($this->pathParts)) {
            return $rootLabel;
        }

        $parts = [];
        $accum = [];
        foreach ($this->pathParts as $part) {
            $accum[] = $part;
            $path = implode('/', $accum);
            $parts[] = '<a href="' . $this->getUrl($path) . '" style="' . $linkStyle . '">' . htmlspecialchars($part) . '</a>';
        }

        return $rootLink . ' > ' . implode(' > ', $parts);
    }

    private function displayLoginForm(): void
    {
        $action = $this->getUrl($this->currentPath);
        $errorMessage = $this->session->getErrorMessage();
        $errorHtml = '';
        if ($errorMessage) {
            $errorMessage = htmlspecialchars($errorMessage);
            $errorHtml = "<div style=\"background: #ffe0e0; border: 1px solid #ff6b6b; color: #cc0000; padding: 8px; margin-bottom: 10px; border-radius: 4px; font-size: 0.85rem;\"><strong>Error:</strong> {$errorMessage}</div>";
        }
        $langSelector = $this->renderLanguageSelectorCombo();
        $labelUser = $this->translator->translate(18);
        $labelPassword = $this->translator->translate(19);
        $labelLanguage = $this->translator->translate(20);
        $labelSubmit = $this->translator->translate(21);
        $content = <<<HTML
        <div class="login-overlay">
            <div class="login-window">
                <div class="login-titlebar">SMB Web Client</div>
                <div class="login-body">
                    {$errorHtml}
                    <form method="post" action="{$action}" class="login-form">
                        <label>{$labelUser}</label>
                        <input type="text" name="swcUser" autocomplete="username" />
                        <label>{$labelPassword}</label>
                        <input type="password" name="swcPw" autocomplete="current-password" />
                        <label>{$labelLanguage}</label>
                        {$langSelector}
                        <div class="login-actions">
                            <input type="submit" name="swcSubmit" value="{$labelSubmit}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
        HTML;

        echo $this->renderPage('Login - SMB Web Client', $content);
    }

    private function renderPage(string $title, string $content): string
    {
        $errorMessage = $this->session->getErrorMessage();
        $successMessage = $this->session->getSuccessMessage();
        $notification = '';

        if ($errorMessage) {
            $errorMessage = htmlspecialchars($errorMessage);
            $notification = "<div style=\"background: #ffe0e0; border: 1px solid #ff6b6b; color: #cc0000; padding: 8px; margin-bottom: 10px; border-radius: 4px;\"><strong>Error:</strong><br />{$errorMessage}</div>";
        }

        if ($successMessage) {
            $successMessage = htmlspecialchars($successMessage);
            $notification = "<div style=\"background: #e0ffe0; border: 1px solid #6bff6b; color: #00cc00; padding: 8px; margin-bottom: 10px; border-radius: 4px;\"><strong>Success:</strong><br />{$successMessage}</div>";
        }

        $content = $notification . $content;

        $logoutUrl = $this->getUrl('', ['logout' => '1']);

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="{$this->config->defaultCharset}">
            <title>{$title}</title>
            <style>
                body { font-family: Tahoma, Arial, sans-serif; font-size: 100%; margin: 0; padding: 0; background: #f6f6f6; }
                .container { padding: 14px; font-size: 0.85rem; }
                /* Login modal style */
                .login-overlay { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #e6ebf5, #f7f9fc); }
                .login-window { width: 320px; background: #f4f7fb; border: 1px solid #c5d1ea; border-radius: 6px; box-shadow: 0 8px 18px rgba(0,0,0,0.15); overflow: hidden; }
                .login-titlebar { background: #4a6ea9; color: #fff; padding: 10px; font-weight: bold; font-size: 0.9rem; letter-spacing: 0.3px; }
                .login-body { padding: 14px 16px 18px; color: #333; }
                .login-instruction { margin: 0 0 12px; font-size: 0.85rem; color: #555; }
                .login-form { display: flex; flex-direction: column; gap: 8px; }
                .login-form label { font-weight: bold; font-size: 0.85rem; color: #2f4f8a; }
                .login-form input[type="text"], .login-form input[type="password"] { width: 100%; padding: 8px; border: 1px solid #c5d1ea; border-radius: 3px; font-size: 0.85rem; box-sizing: border-box; background: #fff; }
                .login-form input[type="text"]:focus, .login-form input[type="password"]:focus { outline: none; border-color: #4a6ea9; box-shadow: 0 0 0 2px rgba(74,110,169,0.15); }
                .login-actions { display: flex; justify-content: flex-end; margin-top: 6px; }
                .login-actions input[type="submit"] { border: 1px solid #3b5c9a; background: #4a6ea9; color: #fff; padding: 6px 14px; font-size: 0.85rem; cursor: pointer; border-radius: 3px; }
                .login-actions input[type="submit"]:hover { background: #3b5c9a; }
                .toolbar { margin-bottom: 10px; display: flex; gap: 10px; align-items: center; }
                .toolbar form.inline { display: inline-flex; align-items: center; gap: 6px; background: #e9eef7; border: 1px solid #c5d1ea; padding: 6px 8px; border-radius: 4px; }
                .toolbar button { border: 1px solid #3b5c9a; background: #4a6ea9; color: #fff; padding: 4px 8px; font-size: 10px; cursor: pointer; border-radius: 3px; }
                .toolbar input[type="text"], .toolbar input[type="file"] { font-size: 10px; }
                .breadcrumb { margin: 6px 0 10px; font-size: 10px; color: #555; }
                .dropzone { border: 2px dashed #c5d1ea; border-radius: 4px; padding: 20px; background: #f8f9fc; margin-bottom: 10px; text-align: center; cursor: pointer; transition: all 0.3s; }
                .dropzone.drag-over { border-color: #4a6ea9; background: #e3e9f5; box-shadow: 0 0 6px rgba(74, 110, 169, 0.3); }
                .dropzone-text { color: #666; font-size: 0.85rem; }
                table.listing { border-collapse: collapse; width: 100%; background: #fff; border: 1px solid #c5d1ea; font-size: 0.85rem; }
                table.listing th, table.listing td { padding: 4px 4px; text-align: left; border-bottom: 1px solid #e1e6f0; font-size: inherit; }
                table.listing td:first-child { padding-right: 0px; width: 20px; }
                table.listing td:nth-child(2) { padding-left: 0px; padding-right: 2px; width: 20px; }
                table.listing td:nth-child(3) { padding-left: 2px; }
                table.listing input[type="checkbox"] { margin: 0; vertical-align: middle; }
                table.listing th { background: #e3e9f5; color: #2f4f8a; }
                table.listing tr:nth-child(even) { background: #f8f9fc; }
                a { color: #1a4f9c; text-decoration: none; }
                a:hover { color: #fff; background-color: #1a4f9c; }
                a.sort-link { display: inline-block; width: 100%; }
                .toolbar a { color: #666; }
                .toolbar a:hover { color: #666; background-color: transparent; text-decoration: underline; }
                .toolbar-link { color: #666 !important; text-decoration: none !important; }
                .toolbar-link:hover { color: #666 !important; background-color: transparent !important; text-decoration: none !important; }
                .breadcrumb a { color: inherit !important; text-decoration: none !important; }
                .breadcrumb a:hover { color: inherit !important; background-color: transparent !important; text-decoration: none !important; }
                .lang-selector a { color: #fff !important; font-weight: normal; text-decoration: underline; }
                .lang-selector b { color: #ffd700 !important; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="container">
                {$content}
            </div>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    const dropzone = document.getElementById('dropzone');
                    if (!dropzone) return;

                    const uploadForm = document.querySelector('form[enctype="multipart/form-data"]');
                    const fileInput = uploadForm ? uploadForm.querySelector('input[type="file"]') : null;

                    if (!fileInput) return;

                    function uploadFilesOneByOne(files) {
                        if (files.length === 0) {
                            location.reload();
                            return;
                        }

                        // Get the form action attribute directly (includes path parameter)
                        const actionUrl = uploadForm.getAttribute('action');
                        console.log('Form action URL:', actionUrl);

                        // Process files one by one using fetch
                        let completed = 0;
                        const total = files.length;

                        Array.from(files).forEach((file, index) => {
                            const formData = new FormData();
                            formData.append('action', 'upload');
                            formData.append('file', file);

                            console.log('Uploading file', index + 1, 'of', total, 'to:', actionUrl);

                            fetch(actionUrl, {
                                method: 'POST',
                                body: formData
                            }).then(() => {
                                completed++;
                                if (completed === total) {
                                    location.reload();
                                }
                            }).catch(err => {
                                console.error('Upload error:', err);
                                completed++;
                                if (completed === total) {
                                    location.reload();
                                }
                            });
                        });
                    }

                    // Visual feedback for drag and drop
                    dropzone.addEventListener('dragover', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.add('drag-over');
                    });

                    dropzone.addEventListener('dragleave', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('drag-over');
                    });

                    dropzone.addEventListener('drop', (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        dropzone.classList.remove('drag-over');

                        const files = e.dataTransfer.files;
                        if (files.length > 0) {
                            uploadFilesOneByOne(files);
                        }
                    });

                    // Allow clicking dropzone to open file picker
                    dropzone.addEventListener('click', () => {
                        fileInput.click();
                    });

                    // Auto-upload when files are selected
                    fileInput.addEventListener('change', () => {
                        if (fileInput.files.length > 0) {
                            uploadFilesOneByOne(fileInput.files);
                        }
                    });
                });
            </script>

            <!-- Modal para crear carpeta -->
            <div id="folderModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; display: none; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); width: 90%; max-width: 400px;">
                    <h3 style="margin-top: 0;">Nueva carpeta</h3>
                    <p style="margin: 10px 0 5px 0; font-size: 0.85rem;">Nombre de la carpeta:</p>
                    <input type="text" id="folderNameInput" placeholder="Nombre" style="width: 100%; padding: 8px; border: 1px solid #ccc; border-radius: 3px; box-sizing: border-box; font-size: 0.85rem;" />
                    <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button id="cancelFolderBtn" type="button" style="padding: 6px 12px; border: 1px solid #ccc; background: #f0f0f0; cursor: pointer; border-radius: 3px; font-size: 0.85rem;">Cancelar</button>
                        <button id="createFolderBtn" type="button" style="padding: 6px 12px; border: 1px solid #3b5c9a; background: #4a6ea9; color: #fff; cursor: pointer; border-radius: 3px; font-size: 0.85rem;">Crear</button>
                    </div>
                </div>
            </div>

            <!-- Formulario oculto para crear carpeta -->
            <form id="createFolderForm" method="post" style="display: none;">
                <input type="hidden" name="action" value="mkdir" />
                <input type="hidden" name="dirname" id="dirnameInput" />
            </form>

            <!-- Modal para confirmar borrado -->
            <div id="deleteConfirmModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); width: 90%; max-width: 500px;">
                    <h3 style="margin-top: 0; color: #cc0000;">Confirmar borrado</h3>
                    <p id="deleteConfirmMessage" style="margin: 10px 0; font-size: 0.85rem;"></p>
                    <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button id="cancelDeleteBtn" type="button" style="padding: 6px 12px; border: 1px solid #ccc; background: #f0f0f0; cursor: pointer; border-radius: 3px; font-size: 0.85rem;">Cancelar</button>
                        <button id="confirmDeleteBtn" type="button" style="padding: 6px 12px; border: 1px solid #cc0000; background: #ff6b6b; color: #fff; cursor: pointer; border-radius: 3px; font-size: 0.85rem;">Borrar</button>
                    </div>
                </div>
            </div>

            <!-- Modal para avisar que no hay selección -->
            <div id="noSelectionModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0, 0, 0, 0.5); z-index: 1000; justify-content: center; align-items: center;">
                <div style="background: #fff; padding: 20px; border-radius: 4px; box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3); width: 90%; max-width: 400px;">
                    <h3 style="margin-top: 0; color: #cc0000;">Aviso</h3>
                    <p id="noSelectionMessage" style="margin: 10px 0; font-size: 0.85rem;">Por favor, selecciona al menos un elemento.</p>
                    <div style="margin-top: 15px; display: flex; gap: 10px; justify-content: flex-end;">
                        <button id="closeNoSelectionBtn" type="button" style="padding: 6px 12px; border: 1px solid #ccc; background: #f0f0f0; cursor: pointer; border-radius: 3px; font-size: 0.85rem;">Cerrar</button>
                    </div>
                </div>
            </div>

            <!-- Formulario oculto para borrado -->
            <form id="deleteConfirmForm" method="post" style="display: none;">
                <input type="hidden" name="action" value="delete" />
            </form>

            <script>
                // Modal para crear carpeta
                const newFolderBtn = document.getElementById('newFolderBtn');
                const folderModal = document.getElementById('folderModal');
                const folderNameInput = document.getElementById('folderNameInput');
                const cancelFolderBtn = document.getElementById('cancelFolderBtn');
                const createFolderBtn = document.getElementById('createFolderBtn');
                const createFolderForm = document.getElementById('createFolderForm');
                const dirnameInput = document.getElementById('dirnameInput');

                // Checkbox para seleccionar todos
                const selectAllCheckbox = document.getElementById('selectAll');
                if (selectAllCheckbox) {
                    selectAllCheckbox.addEventListener('change', function() {
                        const checkboxes = document.querySelectorAll('input[name="items[]"]');
                        checkboxes.forEach(checkbox => {
                            checkbox.checked = this.checked;
                        });
                    });
                }

                if (newFolderBtn) {
                    // Set the form action from the global variable
                    if (window.createFolderAction) {
                        createFolderForm.setAttribute('action', window.createFolderAction);
                    }

                    newFolderBtn.addEventListener('click', (e) => {
                        e.preventDefault();
                        folderModal.style.display = 'flex';
                        folderNameInput.focus();
                    });

                    cancelFolderBtn.addEventListener('click', () => {
                        folderModal.style.display = 'none';
                        folderNameInput.value = '';
                    });

                    createFolderBtn.addEventListener('click', () => {
                        const folderName = folderNameInput.value.trim();
                        if (!folderName) {
                            alert('Por favor ingresa un nombre para la carpeta');
                            return;
                        }
                        
                        dirnameInput.value = folderName;
                        createFolderForm.submit();
                    });

                    folderNameInput.addEventListener('keypress', (e) => {
                        if (e.key === 'Enter') {
                            createFolderBtn.click();
                        }
                    });

                    // Cerrar modal al hacer clic fuera
                    folderModal.addEventListener('click', (e) => {
                        if (e.target === folderModal) {
                            folderModal.style.display = 'none';
                            folderNameInput.value = '';
                        }
                    });
                }

                // Manejo del modal de confirmación de borrado
                const deleteConfirmModal = document.getElementById('deleteConfirmModal');
                const deleteConfirmForm = document.getElementById('deleteConfirmForm');
                const deleteConfirmMessage = document.getElementById('deleteConfirmMessage');
                const cancelDeleteBtn = document.getElementById('cancelDeleteBtn');
                const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
                const deleteSelectedBtn = document.getElementById('deleteSelectedBtn');
                const noSelectionModal = document.getElementById('noSelectionModal');
                const closeNoSelectionBtn = document.getElementById('closeNoSelectionBtn');

                // Encontrar el formulario de borrado
                const deleteForm = document.querySelector('form input[name="action"][value="delete"]')?.closest('form');
                
                if (deleteSelectedBtn && deleteForm) {
                    deleteSelectedBtn.addEventListener('click', (e) => {
                        // Get the items to delete
                        const items = Array.from(deleteForm.querySelectorAll('input[name="items[]"]:checked'))
                            .map(input => input.value);
                        
                        if (items.length > 0) {
                            // Show confirmation modal for delete
                            e.preventDefault();
                            e.stopPropagation();
                            
                            const itemsList = items.join(', ');
                            deleteConfirmMessage.textContent = '¿Está seguro de que desea eliminar: ' + itemsList + '? Solo se pueden eliminar archivos y carpetas vacías.';
                            deleteConfirmModal.style.display = 'flex';
                            
                            // Clear previous items from the form
                            deleteConfirmForm.querySelectorAll('input[name="items[]"]').forEach(el => el.remove());
                            
                            // Add current items to the hidden form
                            items.forEach(item => {
                                const input = document.createElement('input');
                                input.type = 'hidden';
                                input.name = 'items[]';
                                input.value = item;
                                deleteConfirmForm.appendChild(input);
                            });
                            
                            // Set action URL
                            deleteConfirmForm.setAttribute('action', deleteForm.getAttribute('action'));
                        } else {
                            e.preventDefault();
                            if (noSelectionModal) {
                                noSelectionModal.style.display = 'flex';
                            }
                        }
                    });
                }

                cancelDeleteBtn.addEventListener('click', () => {
                    deleteConfirmModal.style.display = 'none';
                });

                confirmDeleteBtn.addEventListener('click', () => {
                    deleteConfirmForm.submit();
                });

                // Aviso de sin selección
                if (closeNoSelectionBtn && noSelectionModal) {
                    closeNoSelectionBtn.addEventListener('click', () => {
                        noSelectionModal.style.display = 'none';
                    });

                    noSelectionModal.addEventListener('click', (e) => {
                        if (e.target === noSelectionModal) {
                            noSelectionModal.style.display = 'none';
                        }
                    });
                }

                // Cerrar modal al hacer clic fuera
                deleteConfirmModal.addEventListener('click', (e) => {
                    if (e.target === deleteConfirmModal) {
                        deleteConfirmModal.style.display = 'none';
                    }
                });
            </script>
        </body>
        </html>
        HTML;
    }

    private function getUrl(string $path = '', array $params = []): string
    {
        $params['path'] = $path;
        $query = http_build_query(array_filter($params));
        
        if ($this->config->modRewrite) {
            return $this->config->baseUrl . $path . ($query ? '?' . $query : '');
        }
        
        return $_SERVER['PHP_SELF'] . ($query ? '?' . $query : '');
    }

    private function sortItems(array $items): array
    {
        usort($items, function($a, $b) {
            $aDir = $a->isDirectory() ? 0 : 1;
            $bDir = $b->isDirectory() ? 0 : 1;
            
            if ($aDir !== $bDir) {
                return $aDir <=> $bDir;
            }

            $typeValue = function($item) {
                if ($item->isDirectory()) {
                    return $this->translator->translate(11);
                }

                $ext = strtoupper(pathinfo($item->getName(), PATHINFO_EXTENSION));
                return $ext ? $this->translator->translate(12, $ext) : '';
            };

            $comparison = 0;
            match ($this->sortBy) {
                'name' => $comparison = strcasecmp($a->getName(), $b->getName()),
                'size' => $comparison = ($a->getSize() ?? 0) <=> ($b->getSize() ?? 0),
                'modified' => $comparison = ($a->getMTime() ?? 0) <=> ($b->getMTime() ?? 0),
                'type' => $comparison = strcasecmp($typeValue($a), $typeValue($b)),
            };

            if ($comparison === 0 && $this->sortBy !== 'name') {
                $comparison = strcasecmp($a->getName(), $b->getName());
            }

            return $this->sortDir === 'desc' ? -$comparison : $comparison;
        });

        return $items;
    }

    private function renderSortHeader(int $labelIndex, string $sortField): string
    {
        $nextDir = ($this->sortBy === $sortField && $this->sortDir === 'asc') ? 'desc' : 'asc';
        $sortIcon = '';

        if ($this->sortBy === $sortField) {
            $sortIcon = $this->sortDir === 'asc' ? ' ▲' : ' ▼';
        }

        $url = $this->getUrl($this->currentPath, ['sort' => $sortField, 'dir' => $nextDir]);
        $label = $this->translator->translate($labelIndex);

        return "<th><a href=\"{$url}\" class=\"sort-link\">{$label}{$sortIcon}</a></th>";
    }

    private function displayShares(string $serverName): string
    {
        try {
            $shares = $this->smbClient->listShares($serverName);
            return $this->renderShareList($serverName, $shares);
        } catch (\Exception $e) {
            return '<p>Error listing shares: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }

    private function displayDirectory(): string
    {
        [$serverName, $shareName, $remotePath] = $this->getServerShareAndPath();
        try {
            $items = $this->smbClient->listDirectory($serverName, $shareName, $remotePath);
            $items = $this->sortItems($items);
            return $this->displayDirectoryList($items);
        } catch (\Exception $e) {
            return '<p>Error listing directory: ' . htmlspecialchars($e->getMessage()) . '</p>';
        }
    }
}
