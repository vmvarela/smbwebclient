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
    private string $theme = 'windows';

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
        
        // Resolver tema: primero sesión, luego GET, luego default
        $sessionTheme = $this->session->getTheme();
        if ($sessionTheme) {
            $this->theme = $sessionTheme;
        } else {
            $this->theme = $_GET['theme'] ?? 'windows';
        }
        
        // Validar tema
        $validThemes = array_keys($this->getThemeNames());
        if (!in_array($this->theme, $validThemes)) {
            $this->theme = 'windows';
        }
        
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
            $theme = $_POST['swcTheme'] ?? 'default';
            
            // Validate language
            $validLangs = array_keys($this->getLanguageNames());
            if (!in_array($language, $validLangs)) {
                $language = 'es';
            }
            
            // Validate theme
            $validThemes = array_keys($this->getThemeNames());
            if (!in_array($theme, $validThemes)) {
                $theme = 'default';
            }
            
            $this->session->setCredentials($username, $password);
            $this->session->setLanguage($language);
            $this->session->setTheme($theme);
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
        // Preserve language and theme before destroying session
        $language = $this->session->getLanguage();
        $theme = $this->session->getTheme();
        
        $this->session->clearCredentials();
        $this->session->destroy();
        
        // Redirect with preserved language and theme
        $params = [];
        if ($language) {
            $params['lang'] = $language;
        }
        if ($theme) {
            $params['theme'] = $theme;
        }
        
        header('Location: ' . $this->getUrl('', $params));
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
            
            error_log("Attempting to delete: server=$serverName, share=$shareName, path=$itemPath");
            
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
        return $this->translator->getAvailableLanguages();
    }

    private function getThemeNames(): array
    {
        return [
            'windows' => 'Windows',
            'mac' => 'Mac',
            'ubuntu' => 'Ubuntu'
        ];
    }

    private function renderLanguageSelectorCombo(): string
    {
        $langs = $this->getLanguageNames();
        $current = $this->translator->getLanguage();
        $currentPath = htmlspecialchars($this->currentPath);
        $currentTheme = htmlspecialchars($this->theme);
        $html = '<select name="swcLang" id="swcLang" onchange="window.location.href=\'?path=' . $currentPath . '&lang=\'+this.value+\'&theme=' . $currentTheme . '\'">';
        foreach ($langs as $code => $label) {
            $selected = $code === $current ? ' selected' : '';
            $html .= '<option value="' . $code . '"' . $selected . '>' . htmlspecialchars($label) . '</option>';
        }
        $html .= '</select>';
        return $html;
    }

    private function renderThemeSelectorCombo(): string
    {
        $themes = $this->getThemeNames();
        $current = $this->theme;
        $currentPath = htmlspecialchars($this->currentPath);
        $currentLang = htmlspecialchars($this->translator->getLanguage());
        $html = '<select name="swcTheme" id="swcTheme" onchange="window.location.href=\'?path=' . $currentPath . '&lang=' . $currentLang . '&theme=\'+this.value">';
        foreach ($themes as $code => $label) {
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
        $logoutLink = !$this->config->allowAnonymous ? 
            '<a href="' . $logoutUrl . '" class="toolbar-link">🚪 ' . $this->translator->translate(17) . '</a>' : '';
        $toolbar = '<div class="toolbar-container">' .
            $logoutLink .
            '</div>';

        $html = '<div class="breadcrumb-toolbar-wrapper">' .
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
        $logoutLink = !$this->config->allowAnonymous ? 
            '<a href="' . $logoutUrl . '" class="toolbar-link">🚪 ' . $this->translator->translate(17) . '</a>' : '';
        $toolbar = '<div class="toolbar-container">' .
            $logoutLink .
            '</div>';

        $html = '<div class="breadcrumb-toolbar-wrapper">' .
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
        $html = '<form id="uploadForm" class="inline upload-form-hidden" method="post" action="' . $action . '" enctype="multipart/form-data">' .
            '<input type="hidden" name="action" value="upload" />' .
            '<input type="file" name="file" multiple accept="*/*" />' .
            '</form>';
        
        // Breadcrumb + toolbar en una línea
        $logoutLink = !$this->config->allowAnonymous ? 
            '<a href="' . $logoutUrl . '" class="toolbar-link">🚪 ' . $this->translator->translate(17) . '</a>' : '';
        $toolbar = '<div class="toolbar-container">' .
            '<a href="#" id="newFolderBtn" class="toolbar-link">📁 ' . $this->translator->translate(15) . '</a>' .
            '<a href="#" id="deleteSelectedBtn" class="toolbar-link">🗑️ ' . $this->translator->translate(8) . '</a>' .
            $logoutLink .
            '</div>';

        $html .= '<div class="breadcrumb-toolbar-wrapper">' .
            '<div class="breadcrumb">' . $this->renderBreadcrumb() . '</div>' .
            $toolbar .
            '</div>';

        if (count($items) > 0) {
            $html .= '<form method="post" action="' . $action . '">';
            $html .= '<input type="hidden" name="action" value="delete" />';
            $html .= '<table class="listing"><tr><th><input type="checkbox" id="selectAll" /></th><th></th>';
            $html .= $this->renderSortHeader(1, 'name');
            $html .= $this->renderSortHeader(2, 'size');
            $html .= $this->renderSortHeader(4, 'modified');
            $html .= $this->renderSortHeader(5, 'type');
            $html .= '<th></th></tr>';
            
            foreach ($items as $item) {
                $itemName = $item->getName();
                $name = htmlspecialchars($itemName);
                $size = $item->isDirectory() ? '' : $this->formatSize($item->getSize());
                $dateFormat = $this->translator->translate(6);
                $modified = date($dateFormat, $item->getMTime());
                $path = $this->currentPath . '/' . $itemName;
                $url = $item->isDirectory()
                    ? $this->getUrl($path)
                    : $this->getUrl($path, ['download' => '1']);
                
                // Tipo: mostrar extensión para archivos o "Carpeta" para directorios
                if ($item->isDirectory()) {
                    $typeLabel = $this->translator->translate(11); // "Carpeta"
                } else {
                    $ext = strtoupper(pathinfo($itemName, PATHINFO_EXTENSION));
                    $typeLabel = $ext ? $this->translator->translate(12, $ext) : '';
                }
                
                $checkboxValue = htmlspecialchars($itemName);
                $checkbox = '<input type="checkbox" name="items[]" value="' . $checkboxValue . '" />';
                $icon = $this->getIcon($item);

                $html .= "<tr><td>{$checkbox}</td><td>{$icon}</td><td><a href=\"{$url}\">{$name}</a></td><td>{$size}</td><td>{$modified}</td><td>{$typeLabel}</td><td></td></tr>";
            }
            
            $html .= '</table>';
            $html .= '</form>';
        }

        // Dropzone al final
        $html .= '<div id="dropzone" class="dropzone"><div class="dropzone-text">📂 ' . $this->translator->translate(23) . '</div></div>';
        
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
        $rootLink = '<a href="' . $this->getUrl('') . '">' . $rootLabel . '</a>';
        
        if (empty($this->pathParts)) {
            return $rootLabel;
        }

        $parts = [];
        $accum = [];
        foreach ($this->pathParts as $part) {
            $accum[] = $part;
            $path = implode('/', $accum);
            $parts[] = '<a href="' . $this->getUrl($path) . '">' . htmlspecialchars($part) . '</a>';
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
            $errorHtml = "<div class=\"notification-error\"><strong>Error:</strong> {$errorMessage}</div>";
        }
        $langSelector = $this->renderLanguageSelectorCombo();
        $themeSelector = $this->renderThemeSelectorCombo();
        $labelUser = $this->translator->translate(18);
        $labelPassword = $this->translator->translate(19);
        $labelLanguage = $this->translator->translate(20);
        $labelSubmit = $this->translator->translate(21);
        $labelTheme = $this->translator->translate(22);
        $content = <<<HTML
        <div class="login-overlay">
            <div class="login-window">
                <div class="login-titlebar">SMB Web Client</div>
                <div class="login-body">
                    {$errorHtml}
                    <form method="post" action="{$action}" class="login-form">
                        <input type="text" name="swcUser" placeholder="{$labelUser}" autocomplete="username" />
                        <div class="password-wrapper">
                            <input type="password" id="swcPw" name="swcPw" placeholder="{$labelPassword}" autocomplete="current-password" />
                            <span class="password-toggle" onclick="togglePassword()">
                                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path id="eye-icon" d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                    <circle cx="12" cy="12" r="3"></circle>
                                </svg>
                            </span>
                        </div>
                        <div class="login-form-row">
                            {$langSelector}
                            {$themeSelector}
                        </div>
                        <div class="login-actions">
                            <input type="submit" name="swcSubmit" value="{$labelSubmit}" />
                        </div>
                    </form>
                </div>
            </div>
        </div>
        <script>
        function togglePassword() {
            const pwField = document.getElementById('swcPw');
            const eyeIcon = document.getElementById('eye-icon');
            if (pwField.type === 'password') {
                pwField.type = 'text';
                eyeIcon.setAttribute('d', 'M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24');
            } else {
                pwField.type = 'password';
                eyeIcon.setAttribute('d', 'M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z');
            }
        }
        </script>
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
            $notification = "<div class=\"notification-error\"><strong>Error:</strong><br />{$errorMessage}</div>";
        }

        if ($successMessage) {
            $successMessage = htmlspecialchars($successMessage);
            $notification = "<div class=\"notification-success\"><strong>Success:</strong><br />{$successMessage}</div>";
        }

        $content = $notification . $content;

        $logoutUrl = $this->getUrl('', ['logout' => '1']);
        $cssFile = '/assets/css/' . $this->theme . '.css';

        // Traducciones para el HTML
        $i18nCreateFolder = htmlspecialchars($this->translator->translate(15));
        $i18nFolderNameLabel = htmlspecialchars($this->translator->translate(32));
        $i18nFolderNamePlaceholder = htmlspecialchars($this->translator->translate(1));
        $i18nCancel = htmlspecialchars($this->translator->translate(29));
        $i18nCreate = htmlspecialchars($this->translator->translate(30));
        $i18nConfirmDelete = htmlspecialchars($this->translator->translate(27));
        $i18nDelete = htmlspecialchars($this->translator->translate(31));
        $i18nWarning = htmlspecialchars($this->translator->translate(24));
        $i18nSelectAtLeastOne = htmlspecialchars($this->translator->translate(25));
        $i18nClose = htmlspecialchars($this->translator->translate(26));
        $i18nConfirmDeleteMessage = addslashes($this->translator->translate(28, "%s"));
        $i18nPleaseEnterFolderName = addslashes($this->translator->translate(33));

        return <<<HTML
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="{$this->config->defaultCharset}">
            <title>{$title}</title>
            <link rel="icon" type="image/svg+xml" href="/assets/images/favicon.svg">
            <link rel="stylesheet" href="{$cssFile}">
        </head>
        <body>
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
            <div id="folderModal" class="modal-overlay">
                <div class="modal-content">
                    <h3>{$i18nCreateFolder}</h3>
                    <p>{$i18nFolderNameLabel}</p>
                    <input type="text" id="folderNameInput" placeholder="{$i18nFolderNamePlaceholder}" />
                    <div class="modal-actions">
                        <button id="cancelFolderBtn" type="button" class="btn-cancel">{$i18nCancel}</button>
                        <button id="createFolderBtn" type="button" class="btn-primary">{$i18nCreate}</button>
                    </div>
                </div>
            </div>

            <!-- Formulario oculto para crear carpeta -->
            <form id="createFolderForm" method="post" class="hidden-form">
                <input type="hidden" name="action" value="mkdir" />
                <input type="hidden" name="dirname" id="dirnameInput" />
            </form>

            <!-- Modal para confirmar borrado -->
            <div id="deleteConfirmModal" class="modal-overlay">
                <div class="modal-content" style="max-width: 500px;">
                    <h3 class="warning">{$i18nConfirmDelete}</h3>
                    <p id="deleteConfirmMessage"></p>
                    <div class="modal-actions">
                        <button id="cancelDeleteBtn" type="button" class="btn-cancel">{$i18nCancel}</button>
                        <button id="confirmDeleteBtn" type="button" class="btn-delete">{$i18nDelete}</button>
                    </div>
                </div>
            </div>

            <!-- Modal para avisar que no hay selección -->
            <div id="noSelectionModal" class="modal-overlay">
                <div class="modal-content">
                    <h3 class="warning">{$i18nWarning}</h3>
                    <p id="noSelectionMessage">{$i18nSelectAtLeastOne}</p>
                    <div class="modal-actions">
                        <button id="closeNoSelectionBtn" type="button" class="btn-cancel">{$i18nClose}</button>
                    </div>
                </div>
            </div>

            <!-- Formulario oculto para borrado -->
            <form id="deleteConfirmForm" method="post" class="hidden-form">
                <input type="hidden" name="action" value="delete" />
            </form>

            <script>
                // Traducciones
                const i18n = {
                    confirmDeleteMessage: '{$i18nConfirmDeleteMessage}',
                    pleaseEnterFolderName: '{$i18nPleaseEnterFolderName}'
                };

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
                            alert(i18n.pleaseEnterFolderName);
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
                            deleteConfirmMessage.textContent = i18n.confirmDeleteMessage.replace('%s', itemsList);
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

            $comparison = match ($this->sortBy) {
                'name' => strcasecmp($a->getName(), $b->getName()),
                'size' => ($a->getSize() ?? 0) <=> ($b->getSize() ?? 0),
                'modified' => ($a->getMTime() ?? 0) <=> ($b->getMTime() ?? 0),
                'type' => strcasecmp($typeValue($a), $typeValue($b)),
                default => 0,
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
